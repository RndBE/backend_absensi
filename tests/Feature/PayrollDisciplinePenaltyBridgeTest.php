<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateDay;
use App\Models\Shift;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class PayrollDisciplinePenaltyBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPayrollDisciplineSchema();
    }

    public function test_payroll_fetches_daily_report_late_counts_from_daily_app(): void
    {
        config([
            'services.daily.url' => 'http://daily.test',
            'services.daily.internal_secret' => 'bridge-secret',
        ]);

        Http::fake([
            'http://daily.test/api/internal/payroll/daily-report-late*' => Http::response([
                'success' => true,
                'data' => [
                    [
                        'email' => 'staff@example.test',
                        'late_days' => 2,
                        'late_dates' => ['2026-06-05', '2026-06-04'],
                    ],
                    ['email' => 'other@example.test', 'late_days' => 0],
                ],
            ]),
        ]);

        $counts = $this->invokePrivate(
            new PayrollRunController,
            'fetchDailyReportLateCounts',
            [
                collect(['staff@example.test', 'other@example.test']),
                Carbon::parse('2026-06-01'),
                Carbon::parse('2026-06-30'),
            ]
        );

        $this->assertSame(2, $counts['staff@example.test']['days']);
        $this->assertSame(['2026-06-04', '2026-06-05'], $counts['staff@example.test']['dates']);
        $this->assertSame(0, $counts['other@example.test']['days']);
        $this->assertSame([], $counts['other@example.test']['dates']);
        Http::assertSent(fn ($request) => $request->url() === 'http://daily.test/api/internal/payroll/daily-report-late?start=2026-06-01&end=2026-06-30&emails%5B0%5D=staff%40example.test&emails%5B1%5D=other%40example.test'
            && $request->header('X-Internal-Secret')[0] === 'bridge-secret');
    }

    public function test_payroll_builds_potongan_kedisiplinan_component(): void
    {
        $component = $this->invokePrivate(
            new PayrollRunController,
            'buildDisciplinePenaltyComponent',
            [3, 50000, ['2026-06-07', '2026-06-05', '2026-06-06']]
        );

        $this->assertSame('Potongan Sanksi Laporan', $component['name']);
        $this->assertSame('deduction', $component['type']);
        $this->assertSame(150000.0, $component['amount']);
        $this->assertSame('3 hari × Rp 50.000', $component['detail']);
        $this->assertSame('Data keterlambatan laporan harian', $component['penalty']['source']);
        $this->assertSame(3, $component['penalty']['count']);
        $this->assertSame(50000.0, $component['penalty']['unit_amount']);
        $this->assertCount(3, $component['lines']);
        $this->assertSame('2026-06-05', $component['lines'][0]['date']);
        $this->assertSame('Laporan harian terlambat', $component['lines'][0]['description']);
    }

    public function test_existing_payroll_can_attach_daily_report_dates_without_regenerate(): void
    {
        $detail = new PayrollRunDetail([
            'employee_id' => 77,
            'basic_salary' => 6000000,
            'components' => [[
                'name' => 'Potongan Sanksi Laporan',
                'type' => 'deduction',
                'amount' => 100000,
                'detail' => '2 hari × Rp 50.000',
            ]],
        ]);

        $this->invokePrivate(
            new PayrollRunController,
            'attachDailyReportPenaltyLinesForDetail',
            [$detail, ['days' => 2, 'dates' => ['2026-06-04', '2026-06-05']]]
        );

        $component = $detail->components[0];
        $this->assertCount(2, $component['lines']);
        $this->assertSame('2026-06-04', $component['lines'][0]['date']);
        $this->assertSame('2026-06-05', $component['lines'][1]['date']);
        $this->assertSame('Data keterlambatan laporan harian', $component['penalty']['source']);
    }

    public function test_existing_lhp_alias_can_attach_daily_report_dates_without_regenerate(): void
    {
        $detail = new PayrollRunDetail([
            'employee_id' => 77,
            'basic_salary' => 6000000,
            'components' => [[
                'name' => 'Denda LHP',
                'type' => 'deduction',
                'amount' => 50000,
                'detail' => '1 hari Ã— Rp 50.000',
            ]],
        ]);

        $this->invokePrivate(
            new PayrollRunController,
            'attachDailyReportPenaltyLinesForDetail',
            [$detail, ['days' => 1, 'dates' => ['2026-06-04']]]
        );

        $component = $detail->components[0];
        $this->assertCount(1, $component['lines']);
        $this->assertSame('2026-06-04', $component['lines'][0]['date']);
        $this->assertSame('Data keterlambatan laporan harian', $component['penalty']['source']);
    }

    public function test_late_penalty_includes_daily_detail_lines(): void
    {
        Attendance::create([
            'employee_id' => 77,
            'date' => '2026-06-03',
            'clock_in' => '08:25:00',
            'status' => 'present',
            'is_late' => true,
        ]);

        $penalty = $this->invokePrivate(
            new PayrollRunController,
            'calculateLatePenalty',
            [77, '2026-06-01', '2026-06-30', [], 50000]
        );

        $this->assertSame(1, $penalty['days']);
        $this->assertSame(50000.0, $penalty['amount']);
        $this->assertCount(1, $penalty['lines']);
        $this->assertSame('2026-06-03', $penalty['lines'][0]['date']);
        $this->assertSame('Clock-in 08:25', $penalty['lines'][0]['evidence']);
        $this->assertSame(50000.0, $penalty['lines'][0]['amount']);
    }

    public function test_existing_payroll_can_show_late_and_alpha_dates_without_regenerate(): void
    {
        Attendance::create([
            'employee_id' => 77,
            'date' => '2026-06-09',
            'clock_in' => '08:35:00',
            'status' => 'present',
            'is_late' => true,
        ]);
        Attendance::create([
            'employee_id' => 77,
            'date' => '2026-06-10',
            'status' => 'absent',
        ]);

        $detail = new PayrollRunDetail([
            'employee_id' => 77,
            'basic_salary' => 6000000,
            'components' => [
                [
                    'name' => 'Potongan Keterlambatan',
                    'type' => 'deduction',
                    'amount' => 50000,
                    'detail' => '1 hari × Rp 50.000',
                ],
                [
                    'name' => 'Potongan Alpha',
                    'type' => 'deduction',
                    'amount' => 100000,
                    'detail' => '1 hari × Rp 100.000',
                ],
            ],
        ]);
        $run = new PayrollRun(['period' => '2026-06']);

        $this->invokePrivate(
            new PayrollRunController,
            'attachPenaltyLinesForDetail',
            [$detail, $run]
        );

        $component = $detail->components[0];
        $this->assertCount(1, $component['lines']);
        $this->assertSame('2026-06-09', $component['lines'][0]['date']);
        $this->assertSame('Clock-in 08:35', $component['lines'][0]['evidence']);
        $this->assertSame('Data presensi', $component['penalty']['source']);

        $alphaComponent = $detail->components[1];
        $this->assertCount(1, $alphaComponent['lines']);
        $this->assertSame('2026-06-10', $alphaComponent['lines'][0]['date']);
        $this->assertSame('Status presensi tercatat Alpha', $alphaComponent['lines'][0]['evidence']);
        $this->assertSame('Data presensi dan jadwal kerja', $alphaComponent['penalty']['source']);
    }

    public function test_alpha_penalty_is_fixed_one_hundred_thousand_per_absent_day(): void
    {
        Attendance::create([
            'employee_id' => 77,
            'date' => '2026-06-03',
            'status' => 'absent',
        ]);
        Attendance::create([
            'employee_id' => 77,
            'date' => '2026-06-04',
            'status' => 'absent',
        ]);

        $penalty = $this->invokePrivate(
            new PayrollRunController,
            'calculateAlphaPenalty',
            [77, '2026-06-01', '2026-06-30', [], 6000000]
        );

        $this->assertSame(2, $penalty['days']);
        $this->assertSame(100000, $penalty['per_day']);
        $this->assertSame(200000, $penalty['amount']);
        $this->assertCount(2, $penalty['lines']);
        $this->assertSame('2026-06-03', $penalty['lines'][0]['date']);
        $this->assertSame('Status presensi tercatat Alpha', $penalty['lines'][0]['evidence']);
    }

    public function test_alpha_penalty_counts_past_scheduled_workdays_without_attendance(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');
        $employee = $this->createEmployeeWithWeekdaySchedule();

        $penalty = $this->invokePrivate(
            new PayrollRunController,
            'calculateAlphaPenalty',
            [$employee->id, '2026-06-01', '2026-06-02', [], 6000000]
        );

        $this->assertSame(2, $penalty['days']);
        $this->assertSame(200000, $penalty['amount']);

        Carbon::setTestNow();
    }

    public function test_report_late_excludes_approved_full_day_leave_dates(): void
    {
        $this->approveLeave(77, 'Cuti Tahunan', '2026-06-04', '2026-06-04');

        $filtered = $this->invokePrivate(
            new PayrollRunController,
            'excludeFullDayLeaveFromReportLate',
            [
                ['days' => 2, 'dates' => ['2026-06-04', '2026-06-05']],
                77,
                Carbon::parse('2026-06-01'),
                Carbon::parse('2026-06-30'),
            ]
        );

        $this->assertSame(1, $filtered['days']);
        $this->assertSame(['2026-06-05'], $filtered['dates']);
    }

    public function test_report_late_excludes_approved_sick_leave_dates(): void
    {
        $this->approveLeave(77, 'Izin Sakit', '2026-06-08', '2026-06-10');

        $filtered = $this->invokePrivate(
            new PayrollRunController,
            'excludeFullDayLeaveFromReportLate',
            [
                ['days' => 4, 'dates' => ['2026-06-08', '2026-06-09', '2026-06-10', '2026-06-11']],
                77,
                Carbon::parse('2026-06-01'),
                Carbon::parse('2026-06-30'),
            ]
        );

        $this->assertSame(1, $filtered['days']);
        $this->assertSame(['2026-06-11'], $filtered['dates']);
    }

    /**
     * WFH dan izin parsial tetap hari kerja, jadi laporan hariannya tetap wajib dan
     * potongannya tetap berlaku. Kalau ini bocor, orang yang cuma izin terlambat
     * sejam ikut bebas dari sanksi laporan.
     */
    public function test_report_late_keeps_wfh_and_partial_leave_dates(): void
    {
        $this->approveLeave(77, 'Work From Home', '2026-06-04', '2026-06-04');
        $this->approveLeave(77, 'Izin Datang Terlambat', '2026-06-05', '2026-06-05');
        $this->approveLeave(77, 'Izin Pulang Cepat', '2026-06-06', '2026-06-06');

        $filtered = $this->invokePrivate(
            new PayrollRunController,
            'excludeFullDayLeaveFromReportLate',
            [
                ['days' => 3, 'dates' => ['2026-06-04', '2026-06-05', '2026-06-06']],
                77,
                Carbon::parse('2026-06-01'),
                Carbon::parse('2026-06-30'),
            ]
        );

        $this->assertSame(3, $filtered['days']);
        $this->assertSame(['2026-06-04', '2026-06-05', '2026-06-06'], $filtered['dates']);
    }

    public function test_report_late_ignores_leave_that_is_not_approved(): void
    {
        $this->approveLeave(77, 'Cuti Tahunan', '2026-06-04', '2026-06-04', 'pending');

        $filtered = $this->invokePrivate(
            new PayrollRunController,
            'excludeFullDayLeaveFromReportLate',
            [
                ['days' => 1, 'dates' => ['2026-06-04']],
                77,
                Carbon::parse('2026-06-01'),
                Carbon::parse('2026-06-30'),
            ]
        );

        $this->assertSame(1, $filtered['days']);
        $this->assertSame(['2026-06-04'], $filtered['dates']);
    }

    /**
     * Tanpa rincian tanggal tidak ada yang bisa disaring. Angkanya dibiarkan apa
     * adanya, bukan dianggap nol — menganggap nol berarti menghapus potongan sah.
     */
    public function test_report_late_without_dates_keeps_day_count(): void
    {
        $this->approveLeave(77, 'Cuti Tahunan', '2026-06-01', '2026-06-30');

        $filtered = $this->invokePrivate(
            new PayrollRunController,
            'excludeFullDayLeaveFromReportLate',
            [
                ['days' => 3, 'dates' => []],
                77,
                Carbon::parse('2026-06-01'),
                Carbon::parse('2026-06-30'),
            ]
        );

        $this->assertSame(3, $filtered['days']);
        $this->assertSame([], $filtered['dates']);
    }

    private function approveLeave(
        int $employeeId,
        string $leaveTypeName,
        string $startDate,
        string $endDate,
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

    private function createEmployeeWithWeekdaySchedule(): Employee
    {
        $shift = Shift::create([
            'company_id' => 1,
            'name' => 'Pagi',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'is_off' => false,
        ]);

        $template = ScheduleTemplate::create([
            'company_id' => 1,
            'name' => 'Weekday',
        ]);

        foreach ([1, 2] as $dayOfWeek) {
            ScheduleTemplateDay::create([
                'template_id' => $template->id,
                'day_of_week' => $dayOfWeek,
                'shift_id' => $shift->id,
            ]);
        }

        return Employee::create([
            'employee_code' => 'EMP-ALPHA-'.uniqid(),
            'company_id' => 1,
            'schedule_template_id' => $template->id,
            'full_name' => 'Alpha Tester',
            'email' => uniqid('alpha.').'@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);
    }

    private function createPayrollDisciplineSchema(): void
    {
        foreach ([
            'schedule_assignments',
            'schedule_template_days',
            'schedule_templates',
            'leave_requests',
            'leave_types',
            'attendances',
            'employees',
            'shifts',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->decimal('clock_in_lat', 10, 7)->nullable();
            $table->decimal('clock_in_lng', 10, 7)->nullable();
            $table->decimal('clock_out_lat', 10, 7)->nullable();
            $table->decimal('clock_out_lng', 10, 7)->nullable();
            $table->string('clock_in_photo')->nullable();
            $table->string('clock_out_photo')->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->string('review_status')->nullable();
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

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('color', 7)->default('#3B82F6');
            $table->boolean('is_off')->default(false);
            $table->integer('sort_order')->default(0);
            $table->unsignedTinyInteger('work_hours')->nullable();
            $table->boolean('auto_overtime')->default(false);
            $table->timestamps();
        });

        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedBigInteger('shift_id');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    private function invokePrivate(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}
