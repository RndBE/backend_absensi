<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LeaveController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Riwayat cuti di app: bulan yang dipilih menampilkan pengajuan yang dibuat di bulan itu atau cuti yang
 * tanggalnya jatuh di bulan itu; tanpa bulan, semua riwayat (seperti portal employee).
 */
class ApiLeaveHistoryTest extends TestCase
{
    private const ME = 1;

    private const OTHER = 2;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['request_attachments', 'leave_requests', 'leave_types', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->default(12);
            $table->timestamps();
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason');
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });

        foreach ([self::ME => 'Saya', self::OTHER => 'Rekan'] as $id => $name) {
            DB::table('employees')->insert([
                'id' => $id, 'full_name' => $name, 'email' => "e{$id}@example.test", 'password' => 'x',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('leave_types')->insert(['id' => 1, 'name' => 'Cuti Tahunan', 'max_days' => 12, 'created_at' => now(), 'updated_at' => now()]);
    }

    // ── dengan bulan ─────────────────────────────────────────────────────────

    public function test_a_request_created_in_the_month_shows_even_when_the_leave_dates_are_elsewhere(): void
    {
        $id = $this->leave('2026-10-05 09:00:00', '2026-12-01', '2026-12-03');

        $this->assertSame([$id], $this->ids('2026-10'));
    }

    public function test_a_leave_taken_in_the_month_shows_even_when_it_was_requested_in_another_month(): void
    {
        $id = $this->leave('2026-09-28 09:00:00', '2026-10-02', '2026-10-03');

        $this->assertSame([$id], $this->ids('2026-10'), 'bulan cutinya');
        $this->assertSame([$id], $this->ids('2026-09'), 'bulan pengajuannya');
        $this->assertSame([], $this->ids('2026-11'));
        $this->assertSame([], $this->ids('2026-08'));
    }

    public function test_a_leave_spanning_two_months_shows_in_both_but_not_after(): void
    {
        $id = $this->leave('2026-10-20 09:00:00', '2026-10-30', '2026-11-03');

        $this->assertSame([$id], $this->ids('2026-10'));
        $this->assertSame([$id], $this->ids('2026-11'));
        $this->assertSame([], $this->ids('2026-12'));
        $this->assertSame([], $this->ids('2026-09'));
    }

    public function test_a_leave_spanning_a_whole_month_shows_in_the_months_in_between(): void
    {
        $id = $this->leave('2026-09-01 09:00:00', '2026-09-20', '2026-11-10');

        $this->assertSame([$id], $this->ids('2026-10'), 'cuti panjang yang melewati seluruh bulan');
    }

    public function test_a_request_never_appears_twice_when_both_rules_match(): void
    {
        $id = $this->leave('2026-10-05 09:00:00', '2026-10-06', '2026-10-07');

        $this->assertSame([$id], $this->ids('2026-10'));
    }

    public function test_the_edges_of_the_month_are_inclusive_by_creation_time_and_by_leave_date(): void
    {
        $lastSecond = $this->leave('2026-10-31 23:59:59', '2027-03-01', '2027-03-02');
        $firstSecond = $this->leave('2026-10-01 00:00:00', '2027-03-01', '2027-03-02');
        $nextMonth = $this->leave('2026-11-01 00:00:00', '2027-03-01', '2027-03-02');
        $endsOnFirst = $this->leave('2026-08-01 09:00:00', '2026-09-29', '2026-10-01');
        $endsBefore = $this->leave('2026-08-02 09:00:00', '2026-09-28', '2026-09-30');
        $startsOnLast = $this->leave('2026-08-03 09:00:00', '2026-10-31', '2026-11-02');
        $startsAfter = $this->leave('2026-08-04 09:00:00', '2026-11-01', '2026-11-02');

        $october = $this->ids('2026-10');

        $this->assertContains($lastSecond, $october, 'dibuat 31 Okt 23:59:59');
        $this->assertContains($firstSecond, $october, 'dibuat 1 Okt 00:00:00');
        $this->assertNotContains($nextMonth, $october, 'dibuat 1 Nov 00:00:00');
        $this->assertContains($endsOnFirst, $october, 'cuti berakhir 1 Okt');
        $this->assertNotContains($endsBefore, $october, 'cuti berakhir 30 Sep');
        $this->assertContains($startsOnLast, $october, 'cuti mulai 31 Okt');
        $this->assertNotContains($startsAfter, $october, 'cuti mulai 1 Nov');
    }

    public function test_the_month_view_lists_newest_request_first(): void
    {
        $old = $this->leave('2026-10-02 09:00:00', '2026-10-05', '2026-10-05');
        $new = $this->leave('2026-10-20 09:00:00', '2026-10-21', '2026-10-21');
        $middle = $this->leave('2026-10-10 09:00:00', '2026-10-11', '2026-10-11');

        $this->assertSame([$new, $middle, $old], $this->ids('2026-10'));
    }

    public function test_only_my_own_requests_are_listed(): void
    {
        $mine = $this->leave('2026-10-05 09:00:00', '2026-10-06', '2026-10-06');
        $this->leave('2026-10-05 09:00:00', '2026-10-06', '2026-10-06', employee: self::OTHER);

        $this->assertSame([$mine], $this->ids('2026-10'));
        $this->assertSame([$mine], $this->ids(null));
    }

    // ── tanpa bulan: semua riwayat ───────────────────────────────────────────

    public function test_without_a_month_every_request_is_listed_newest_first(): void
    {
        $a = $this->leave('2025-03-01 09:00:00', '2025-03-05', '2025-03-06');
        $b = $this->leave('2026-10-05 09:00:00', '2026-12-01', '2026-12-02');
        $c = $this->leave('2026-01-15 09:00:00', '2026-01-20', '2026-01-21');

        $this->assertSame([$b, $c, $a], $this->ids(null));
    }

    public function test_an_empty_period_means_all_history_too(): void
    {
        $a = $this->leave('2025-03-01 09:00:00', '2025-03-05', '2025-03-06');

        $this->assertSame([$a], $this->ids(''));
    }

    public function test_no_request_means_an_empty_list(): void
    {
        $this->assertSame([], $this->ids(null));
        $this->assertSame([], $this->ids('2026-10'));
    }

    public function test_the_history_is_capped_to_the_most_recent_requests(): void
    {
        $this->assertSame(500, LeaveController::HISTORY_LIMIT);

        for ($i = 0; $i < LeaveController::HISTORY_LIMIT + 3; $i++) {
            $this->leave('2024-01-01 09:00:00', '2024-01-02', '2024-01-02');
        }
        $newest = $this->leave('2026-10-05 09:00:00', '2026-10-06', '2026-10-06');

        $ids = $this->ids(null);

        $this->assertCount(LeaveController::HISTORY_LIMIT, $ids);
        $this->assertSame($newest, $ids[0]);
    }

    // ── bentuk data ──────────────────────────────────────────────────────────

    public function test_each_request_carries_its_leave_type_and_attachments(): void
    {
        $id = $this->leave('2026-10-05 09:00:00', '2026-10-06', '2026-10-06');
        DB::table('request_attachments')->insert([
            'attachable_type' => \App\Models\LeaveRequest::class, 'attachable_id' => $id,
            'file_path' => 'attachments/leave-requests/a.pdf', 'file_name' => 'a.pdf', 'file_size' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = $this->rows('2026-10')[0];

        $this->assertSame('Cuti Tahunan', $row['leave_type']['name']);
        $this->assertSame('a.pdf', $row['attachments'][0]['file_name']);
        $this->assertSame('2026-10-06', $row['start_date']);
    }

    public function test_a_malformed_month_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->rows('Oktober 2026');
    }

    public function test_the_route_still_sits_behind_sanctum(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->match(Request::create('/api/leave/requests', 'GET'));

        $this->assertSame(LeaveController::class.'@index', $route->getActionName());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }

    // ── bantuan ──────────────────────────────────────────────────────────────

    private function leave(string $createdAt, string $start, string $end, int $employee = self::ME): int
    {
        return DB::table('leave_requests')->insertGetId([
            'employee_id' => $employee, 'leave_type_id' => 1, 'start_date' => $start, 'end_date' => $end,
            'total_days' => 1, 'reason' => 'Keperluan keluarga', 'status' => 'pending',
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function rows(?string $period): array
    {
        $request = Request::create('/api/leave/requests', 'GET', $period === null ? [] : ['period' => $period]);
        $request->setUserResolver(fn () => Employee::findOrFail(self::ME));

        return (new LeaveController())->index($request)->getData(true)['data'];
    }

    /** @return list<int> */
    private function ids(?string $period): array
    {
        return array_map('intval', array_column($this->rows($period), 'id'));
    }
}
