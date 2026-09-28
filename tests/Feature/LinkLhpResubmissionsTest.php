<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LinkLhpResubmissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['lpjs', 'approval_logs', 'travel_reports', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('budget_request_id')->nullable();
            $table->unsignedBigInteger('resubmission_of_id')->nullable()->unique();
            $table->string('destination_city');
            $table->date('departure_date');
            $table->date('return_date');
            $table->text('purpose');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('action');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('lpjs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('travel_report_id')->nullable();
            $table->timestamps();
        });

        DB::table('employees')->insert([
            ['id' => 1, 'full_name' => 'Karyawan A'],
            ['id' => 2, 'full_name' => 'Karyawan B'],
        ]);
    }

    private function report(int $id, int $employeeId, string $city, string $status, string $createdAt, ?int $budgetId = null): void
    {
        DB::table('travel_reports')->insert([
            'id' => $id,
            'employee_id' => $employeeId,
            'budget_request_id' => $budgetId,
            'destination_city' => $city,
            'departure_date' => '2026-09-01',
            'return_date' => '2026-09-01',
            'purpose' => 'Tugas',
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function rejectAt(int $id, string $at): void
    {
        DB::table('approval_logs')->insert([
            'approvable_type' => \App\Models\TravelReport::class,
            'approvable_id' => $id,
            'approver_id' => 2,
            'action' => 'rejected',
            'notes' => 'Kurang lengkap',
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    private function link(int $replacementId): ?int
    {
        return DB::table('travel_reports')->where('id', $replacementId)->value('resubmission_of_id');
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->report(1, 1, 'Jogjakarta', 'rejected', '2026-09-01 15:34:00');
        $this->rejectAt(1, '2026-09-01 15:36:00');
        $this->report(2, 1, 'Jogjakarta', 'approved', '2026-09-01 15:37:00');

        $this->artisan('lhp:link-resubmissions')->assertSuccessful();

        $this->assertNull($this->link(2));
    }

    public function test_links_first_same_city_report_created_after_rejection(): void
    {
        $this->report(1, 1, 'Magelang - Semarang', 'rejected', '2026-09-01 08:00:00');
        $this->rejectAt(1, '2026-09-01 10:00:00');
        // Dibuat sebelum penolakan: bukan pengganti.
        $this->report(2, 1, 'Magelang - Semarang', 'approved', '2026-09-01 09:00:00');
        // Kota lain dan karyawan lain: bukan pengganti.
        $this->report(3, 1, 'Bojonegoro', 'approved', '2026-09-01 11:00:00');
        $this->report(4, 2, 'Magelang - Semarang', 'approved', '2026-09-01 11:00:00');
        $this->report(5, 1, 'magelang-semarang', 'pending', '2026-09-01 12:00:00');
        $this->report(6, 1, 'Magelang - Semarang', 'pending', '2026-09-01 13:00:00');
        DB::table('lpjs')->insert(['id' => 1, 'travel_report_id' => 1]);

        $this->artisan('lhp:link-resubmissions', ['--apply' => true])->assertSuccessful();

        $this->assertSame(1, $this->link(5));
        $this->assertNull($this->link(6));
        $this->assertSame(5, DB::table('lpjs')->where('id', 1)->value('travel_report_id'));
        // updated_at LHP pengganti tidak disentuh.
        $this->assertSame('2026-09-01 12:00:00', DB::table('travel_reports')->where('id', 5)->value('updated_at'));
    }

    public function test_respects_day_window_skip_and_existing_links(): void
    {
        $this->report(1, 1, 'DIY', 'rejected', '2026-07-01 08:00:00');
        $this->rejectAt(1, '2026-07-01 09:00:00');
        $this->report(2, 1, 'DIY', 'approved', '2026-08-01 08:00:00');

        $this->report(3, 1, 'Purworejo', 'rejected', '2026-07-02 08:00:00');
        $this->rejectAt(3, '2026-07-02 09:00:00');
        $this->report(4, 1, 'Purworejo', 'approved', '2026-07-03 08:00:00');

        $this->artisan('lhp:link-resubmissions', ['--apply' => true, '--skip' => ['3']])->assertSuccessful();

        $this->assertNull($this->link(2));
        $this->assertNull($this->link(4));

        // Dijalankan ulang tanpa --skip: #3 tertaut, sisanya tetap.
        $this->artisan('lhp:link-resubmissions', ['--apply' => true])->assertSuccessful();
        $this->assertSame(3, $this->link(4));
        $this->artisan('lhp:link-resubmissions', ['--apply' => true])->assertSuccessful();
        $this->assertSame(3, $this->link(4));
    }

    public function test_manual_pair_overrides_guess_and_is_validated(): void
    {
        $this->report(1, 1, 'Bojonegoro', 'rejected', '2026-07-01 08:00:00');
        $this->rejectAt(1, '2026-07-01 09:00:00');
        $this->report(2, 1, 'Magelang', 'approved', '2026-07-05 08:00:00');
        $this->report(3, 2, 'Bojonegoro', 'approved', '2026-07-05 08:00:00');

        $this->artisan('lhp:link-resubmissions', ['--apply' => true, '--pair' => ['1:3']])->assertSuccessful();
        $this->assertNull($this->link(3));

        $this->artisan('lhp:link-resubmissions', ['--apply' => true, '--pair' => ['1:2']])->assertSuccessful();
        $this->assertSame(1, $this->link(2));

        $this->artisan('lhp:link-resubmissions', ['--pair' => ['salah']])->assertFailed();
    }

    public function test_same_budget_wins_over_city(): void
    {
        $this->report(1, 1, 'Semarang', 'rejected', '2026-09-01 08:00:00', 7);
        $this->rejectAt(1, '2026-09-01 09:00:00');
        $this->report(2, 1, 'Semarang', 'approved', '2026-09-01 10:00:00', 8);
        $this->report(3, 1, 'Kota Semarang', 'approved', '2026-09-01 11:00:00', 7);

        $this->artisan('lhp:link-resubmissions', ['--apply' => true])->assertSuccessful();

        $this->assertNull($this->link(2));
        $this->assertSame(1, $this->link(3));
    }
}
