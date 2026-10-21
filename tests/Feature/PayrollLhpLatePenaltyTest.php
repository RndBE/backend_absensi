<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Models\TravelReport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * Denda LHP yang dikumpulkan lewat batas: nominalnya sama dengan denda terlambat clock in dan
 * tetap per LHP, berapa pun lamanya telat (semenit atau seminggu).
 */
class PayrollLhpLatePenaltyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-06-23 08:00:00');

        foreach (['travel_reports', 'budget_requests', 'holidays', 'employees', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->date('date');
            $table->string('name')->nullable();
            $table->boolean('is_national')->default(false);
            $table->timestamps();
        });

        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->unsignedTinyInteger('lhp_deadline_days')->nullable();
            $table->timestamps();
        });

        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('resubmission_of_id')->nullable();
            $table->string('destination_city')->default('Solo');
            $table->date('submission_deadline')->nullable();
            $table->boolean('is_late')->default(false);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function report(array $attributes = []): TravelReport
    {
        $report = TravelReport::create($attributes + [
            'employee_id' => 7,
            'destination_city' => 'Solo',
            'submission_deadline' => '2026-06-10',
            'is_late' => true,
        ]);

        // created_at = tanggal LHP dikumpulkan; dipaksa lewat query karena Eloquent menimpanya saat create.
        DB::table('travel_reports')->where('id', $report->id)->update([
            'created_at' => $attributes['created_at'] ?? '2026-06-15 09:00:00',
        ]);

        return $report;
    }

    private function penalty(int $employeeId = 7, float $rate = 20000): array
    {
        $controller = new PayrollRunController;
        $method = (new ReflectionClass($controller))->getMethod('calculateLhpLatePenalty');
        $method->setAccessible(true);

        return $method->invoke($controller, $employeeId, '2026-06-01', '2026-06-30', $rate);
    }

    public function test_each_late_lhp_costs_the_same_amount_however_late_it_was(): void
    {
        // Telat 1 hari vs telat 3 minggu: nominalnya sama.
        $this->report(['submission_deadline' => '2026-06-14', 'created_at' => '2026-06-15 00:01:00']);
        $this->report(['submission_deadline' => '2026-05-25', 'created_at' => '2026-06-15 09:00:00']);

        $result = $this->penalty();

        $this->assertSame(2, $result['days']);
        $this->assertSame(40000.0, $result['amount']);
        $this->assertSame([20000.0, 20000.0], array_column($result['lines'], 'amount'));
        $this->assertSame('2026-06-15', $result['lines'][0]['date']);
    }

    public function test_only_late_lhp_submitted_in_the_period_by_that_employee_count(): void
    {
        $this->report(['is_late' => false]);
        $this->report(['created_at' => '2026-05-29 10:00:00']);
        $this->report(['created_at' => '2026-07-01 10:00:00']);
        $this->report(['employee_id' => 8]);

        $this->assertSame(0, $this->penalty()['days']);

        $this->report(['created_at' => '2026-06-30 23:30:00']);

        $this->assertSame(1, $this->penalty()['days']);
    }

    public function test_resubmission_that_inherits_late_flag_is_not_fined_again(): void
    {
        $original = $this->report(['status' => 'rejected']);
        $this->report(['resubmission_of_id' => $original->id, 'created_at' => '2026-06-20 09:00:00']);

        $result = $this->penalty();

        $this->assertSame(1, $result['days']);
        $this->assertSame(20000.0, $result['amount']);
    }

    public function test_submission_deadline_flags_lhp_late_only_after_the_deadline_day(): void
    {
        $budget = DB::table('budget_requests')->insertGetId([
            'employee_id' => DB::table('employees')->insertGetId([
                'full_name' => 'Budi', 'email' => 'budi@test.id', 'company_id' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'title' => 'Perjalanan Solo',
            'status' => 'approved',
            'departure_date' => '2026-06-15',
            'return_date' => '2026-06-15',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Pulang 2026-06-15 (Senin) + 5 hari kerja = batas 2026-06-22.
        Carbon::setTestNow('2026-06-22 23:59:00');
        [$deadline, $isLate] = TravelReport::submissionDeadlineFor($budget);
        $this->assertSame('2026-06-22', $deadline->toDateString());
        $this->assertFalse($isLate);

        Carbon::setTestNow('2026-06-23 00:01:00');
        [, $isLate] = TravelReport::submissionDeadlineFor($budget);
        $this->assertTrue($isLate);

        $this->assertSame([null, false], TravelReport::submissionDeadlineFor(null));
    }
}
