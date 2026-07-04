<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Models\Attendance;
use App\Models\Employee;
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
                    ['email' => 'staff@example.test', 'late_days' => 2],
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

        $this->assertSame(2, $counts['staff@example.test']);
        $this->assertSame(0, $counts['other@example.test']);
        Http::assertSent(fn ($request) => $request->url() === 'http://daily.test/api/internal/payroll/daily-report-late?start=2026-06-01&end=2026-06-30&emails%5B0%5D=staff%40example.test&emails%5B1%5D=other%40example.test'
            && $request->header('X-Internal-Secret')[0] === 'bridge-secret');
    }

    public function test_payroll_builds_potongan_kedisiplinan_component(): void
    {
        $component = $this->invokePrivate(
            new PayrollRunController,
            'buildDisciplinePenaltyComponent',
            [3, 50000]
        );

        $this->assertSame('Potongan Sanksi Laporan', $component['name']);
        $this->assertSame('deduction', $component['type']);
        $this->assertSame(150000.0, $component['amount']);
        $this->assertSame('3 hari × Rp 50.000', $component['detail']);
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
            'attendances',
            'employees',
            'shifts',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
