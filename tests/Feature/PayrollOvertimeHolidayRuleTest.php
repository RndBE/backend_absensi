<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PayrollRunController;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\ScheduleTemplate;
use App\Models\ScheduleTemplateDay;
use App\Models\Shift;
use App\Models\WorkSchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

class PayrollOvertimeHolidayRuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createPayrollOvertimeSchema();
    }

    public function test_six_day_official_holiday_on_shortest_workday_uses_short_workday_rule(): void
    {
        $employee = $this->createSixDayEmployeeWithShortSaturday();

        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-13',
            'overtime_type' => 'holiday',
            'total_duration' => 540,
            'break_duration' => 0,
            'reason' => 'Official holiday overtime',
            'status' => 'approved',
        ]);

        $result = $this->invokePrivate(new PayrollRunController, 'calculateOvertime', [
            $employee->id,
            '2026-06-01',
            '2026-06-30',
            ['2026-06-13'],
            1730000,
            1,
        ]);

        $this->assertSame(250000.0, $result['total_amount']);
        $this->assertStringContainsString('Hari libur hari kerja terpendek', $result['detail']);
    }

    public function test_six_day_official_holiday_on_regular_workday_keeps_regular_six_day_rule(): void
    {
        $employee = $this->createSixDayEmployeeWithShortSaturday();

        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-08',
            'overtime_type' => 'holiday',
            'total_duration' => 540,
            'break_duration' => 0,
            'reason' => 'Official holiday overtime',
            'status' => 'approved',
        ]);

        $result = $this->invokePrivate(new PayrollRunController, 'calculateOvertime', [
            $employee->id,
            '2026-06-01',
            '2026-06-30',
            ['2026-06-08'],
            1730000,
            1,
        ]);

        $this->assertSame(210000.0, $result['total_amount']);
        $this->assertStringContainsString('Hari libur:', $result['detail']);
    }

    public function test_overtime_on_holiday_uses_workday_rate_when_employee_has_working_shift(): void
    {
        $company = Company::create(['name' => 'PT Security']);
        $shift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Jaga',
            'start_time' => '08:00:00',
            'end_time' => '20:00:00',
            'color' => '#111827',
            'is_off' => false,
        ]);
        $employee = Employee::create([
            'employee_code' => 'SEC-1',
            'company_id' => $company->id,
            'full_name' => 'Security',
            'email' => 'sec@test.id',
            'password' => 'secret',
            'is_active' => true,
        ]);

        // Security DIJADWALKAN MASUK (ada shift kerja) di tanggal merah 16 Juni.
        \App\Models\ScheduleAssignment::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-16',
            'shift_id' => $shift->id,
        ]);

        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-16',
            'overtime_type' => 'holiday', // disubmit sebagai holiday, tapi ada shift kerja
            'total_duration' => 240, // 4 jam
            'break_duration' => 0,
            'reason' => 'Lembur jaga',
            'status' => 'approved',
        ]);

        $result = $this->invokePrivate(new PayrollRunController, 'calculateOvertime', [
            $employee->id,
            '2026-06-01',
            '2026-06-30',
            ['2026-06-16'], // 16 Juni = libur resmi
            1730000,        // gaji pokok → tarif/jam = 1.730.000/173 = 10.000
            1,
        ]);

        // Ada shift kerja di tanggal merah → tarif HARI KERJA BIASA:
        // 1j×1,5 + 3j×2 = 7,5 × 10.000 = 75.000 (bukan 8×10.000 = 80.000 tarif libur).
        $this->assertSame(75000.0, $result['total_amount']);
        $this->assertStringContainsString('Hari kerja:', $result['detail']);
    }

    private function createSixDayEmployeeWithShortSaturday(): Employee
    {
        $company = Company::create(['name' => 'PT Test']);

        $workSchedule = WorkSchedule::create([
            'company_id' => $company->id,
            'name' => '6 Hari Kerja',
            'work_days' => 6,
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
        ]);

        $regularShift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '16:00:00',
            'color' => '#3B82F6',
            'is_off' => false,
        ]);

        $shortShift = Shift::create([
            'company_id' => $company->id,
            'name' => 'Short Saturday',
            'start_time' => '08:00:00',
            'end_time' => '13:00:00',
            'color' => '#10B981',
            'is_off' => false,
        ]);

        $template = ScheduleTemplate::create([
            'company_id' => $company->id,
            'name' => '6 Hari dengan Sabtu Pendek',
        ]);

        foreach ([1, 2, 3, 4, 5] as $dayOfWeek) {
            ScheduleTemplateDay::create([
                'template_id' => $template->id,
                'day_of_week' => $dayOfWeek,
                'shift_id' => $regularShift->id,
            ]);
        }

        ScheduleTemplateDay::create([
            'template_id' => $template->id,
            'day_of_week' => 6,
            'shift_id' => $shortShift->id,
        ]);

        return Employee::create([
            'employee_code' => 'EMP-OT-'.uniqid(),
            'company_id' => $company->id,
            'work_schedule_id' => $workSchedule->id,
            'schedule_template_id' => $template->id,
            'full_name' => 'Overtime Tester',
            'email' => uniqid('overtime.').'@example.test',
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);
    }

    private function createPayrollOvertimeSchema(): void
    {
        foreach ([
            'schedule_assignments',
            'overtime_requests',
            'employees',
            'schedule_template_days',
            'schedule_templates',
            'shifts',
            'work_schedules',
            'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
            $table->string('name');
            $table->integer('work_days')->default(5);
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
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
            $table->foreignId('company_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->foreignId('shift_id');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->foreignId('company_id');
            $table->foreignId('work_schedule_id')->nullable();
            $table->foreignId('schedule_template_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->date('date');
            $table->string('overtime_type')->default('workday');
            $table->time('planned_start')->nullable();
            $table->time('planned_end')->nullable();
            $table->integer('pre_shift_duration')->default(0);
            $table->integer('pre_shift_break')->default(0);
            $table->integer('post_shift_duration')->default(0);
            $table->integer('post_shift_break')->default(0);
            $table->integer('break_duration')->default(0);
            $table->integer('total_duration')->default(0);
            $table->integer('approved_duration')->nullable();
            $table->integer('approved_break')->nullable();
            $table->integer('actual_duration')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->time('actual_clock_in')->nullable();
            $table->time('actual_clock_out')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id');
            $table->date('date');
            $table->foreignId('shift_id');
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
