<?php

namespace Tests\Feature;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\LeaveDayCategory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeaveDayCategoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createLeaveSchema();
    }

    public static function categoryProvider(): array
    {
        return [
            'cuti tahunan' => ['Cuti Tahunan', LeaveDayCategory::CUTI, true],
            'cuti melahirkan' => ['Cuti Melahirkan', LeaveDayCategory::CUTI, true],
            'izin sakit' => ['Izin Sakit', LeaveDayCategory::SAKIT, true],
            'sick leave' => ['Sick Leave', LeaveDayCategory::SAKIT, true],
            'medical leave' => ['Medical Leave', LeaveDayCategory::SAKIT, true],
            'wfh' => ['Work From Home', LeaveDayCategory::WFH, false],
            'wfh singkat' => ['WFH', LeaveDayCategory::WFH, false],
            'datang terlambat' => ['Izin Datang Terlambat', LeaveDayCategory::IZIN_PARSIAL, false],
            'pulang cepat' => ['Izin Pulang Cepat', LeaveDayCategory::IZIN_PARSIAL, false],
            'pulang awal' => ['Izin Pulang Awal', LeaveDayCategory::IZIN_PARSIAL, false],
        ];
    }

    #[DataProvider('categoryProvider')]
    public function test_it_maps_leave_type_name_to_category(
        string $leaveTypeName,
        string $expectedCategory,
        bool $expectedFullDayAway
    ): void {
        $leave = $this->makeLeave($leaveTypeName, '2026-06-04', '2026-06-04');

        $this->assertSame($expectedCategory, LeaveDayCategory::for($leave));
        $this->assertSame($expectedFullDayAway, LeaveDayCategory::isFullDayAway($leave));
    }

    public function test_it_returns_null_category_without_leave(): void
    {
        $this->assertNull(LeaveDayCategory::for(null));
        $this->assertFalse(LeaveDayCategory::isFullDayAway(null));
    }

    public function test_unknown_leave_type_name_falls_back_to_cuti(): void
    {
        $leave = $this->makeLeave('Izin Menikah', '2026-06-04', '2026-06-04');

        $this->assertSame(LeaveDayCategory::CUTI, LeaveDayCategory::for($leave));
        $this->assertTrue(LeaveDayCategory::isFullDayAway($leave));
    }

    public function test_full_day_away_dates_expands_range_and_clips_to_period(): void
    {
        $this->makeLeave('Cuti Tahunan', '2026-05-29', '2026-06-02', 77);

        $dates = LeaveDayCategory::fullDayAwayDates(
            77,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30')
        );

        $this->assertSame(['2026-06-01', '2026-06-02'], $dates);
    }

    public function test_full_day_away_dates_omits_wfh_and_partial_leave(): void
    {
        $this->makeLeave('Cuti Tahunan', '2026-06-03', '2026-06-03', 77);
        $this->makeLeave('Work From Home', '2026-06-04', '2026-06-04', 77);
        $this->makeLeave('Izin Datang Terlambat', '2026-06-05', '2026-06-05', 77);

        $dates = LeaveDayCategory::fullDayAwayDates(
            77,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30')
        );

        $this->assertSame(['2026-06-03'], $dates);
    }

    public function test_full_day_away_dates_only_counts_approved_leave(): void
    {
        $this->makeLeave('Cuti Tahunan', '2026-06-03', '2026-06-03', 77, 'pending');
        $this->makeLeave('Cuti Tahunan', '2026-06-04', '2026-06-04', 77, 'rejected');
        $this->makeLeave('Cuti Tahunan', '2026-06-05', '2026-06-05', 77);

        $dates = LeaveDayCategory::fullDayAwayDates(
            77,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30')
        );

        $this->assertSame(['2026-06-05'], $dates);
    }

    public function test_full_day_away_dates_deduplicates_overlapping_leave(): void
    {
        $this->makeLeave('Cuti Tahunan', '2026-06-03', '2026-06-05', 77);
        $this->makeLeave('Izin Sakit', '2026-06-04', '2026-06-06', 77);

        $dates = LeaveDayCategory::fullDayAwayDates(
            77,
            Carbon::parse('2026-06-01'),
            Carbon::parse('2026-06-30')
        );

        $this->assertSame(['2026-06-03', '2026-06-04', '2026-06-05', '2026-06-06'], $dates);
    }

    /**
     * Instance periode milik pemanggil dipakai ulang untuk seluruh karyawan dalam satu
     * payroll run. Kalau tergeser di sini, karyawan berikutnya dihitung dengan periode
     * yang salah — dan gejalanya baru muncul di angka gaji orang lain.
     */
    public function test_full_day_away_dates_does_not_mutate_the_period_arguments(): void
    {
        $this->makeLeave('Cuti Tahunan', '2026-05-20', '2026-07-10', 77);

        $periodStart = Carbon::parse('2026-06-01');
        $periodEnd = Carbon::parse('2026-06-30');

        LeaveDayCategory::fullDayAwayDates(77, $periodStart, $periodEnd);

        $this->assertSame('2026-06-01', $periodStart->toDateString());
        $this->assertSame('2026-06-30', $periodEnd->toDateString());
    }

    public function test_full_day_away_dates_returns_empty_for_inverted_period(): void
    {
        $this->makeLeave('Cuti Tahunan', '2026-06-01', '2026-06-30', 77);

        $dates = LeaveDayCategory::fullDayAwayDates(
            77,
            Carbon::parse('2026-06-30'),
            Carbon::parse('2026-06-01')
        );

        $this->assertSame([], $dates);
    }

    private function makeLeave(
        string $leaveTypeName,
        string $startDate,
        string $endDate,
        int $employeeId = 1,
        string $status = 'approved'
    ): LeaveRequest {
        $type = LeaveType::firstOrCreate(['name' => $leaveTypeName]);

        return LeaveRequest::create([
            'employee_id' => $employeeId,
            'leave_type_id' => $type->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => 1,
            'reason' => 'test',
            'status' => $status,
        ]);
    }

    private function createLeaveSchema(): void
    {
        foreach (['leave_requests', 'leave_types'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('delegate_to')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });
    }
}
