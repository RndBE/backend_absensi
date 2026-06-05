<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\WorkSchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiDailyReportPrefillTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('work_schedules');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->json('work_days')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off')->default(false);
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
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
    }

    public function test_prefill_uses_clock_out_for_work_finished_and_counted_duration_for_post_shift_overtime(): void
    {
        $employee = $this->makeEmployee();

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-04',
            'clock_in' => '08:00:00',
            'clock_out' => '19:30:00',
        ]);
        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-04',
            'overtime_type' => 'workday',
            'post_shift_duration' => 90,
            'total_duration' => 90,
            'reason' => 'Deploy',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/daily/prefill?date=2026-06-04')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.work_finished_at', '19:30')
            ->assertJsonPath('data.overtime_status', true)
            ->assertJsonPath('data.overtime_source', 'post_shift')
            ->assertJsonPath('data.overtime_start', '17:00')
            ->assertJsonPath('data.overtime_end', '18:30');
    }

    public function test_prefill_uses_pre_shift_overtime_from_clock_in_to_shift_start(): void
    {
        $employee = $this->makeEmployee();

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-04',
            'clock_in' => '06:00:00',
            'clock_out' => '17:00:00',
        ]);
        OvertimeRequest::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-04',
            'overtime_type' => 'workday',
            'pre_shift_duration' => 90,
            'total_duration' => 90,
            'reason' => 'Setup',
            'status' => 'approved',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/daily/prefill?date=2026-06-04')
            ->assertOk()
            ->assertJsonPath('data.work_finished_at', '17:00')
            ->assertJsonPath('data.overtime_status', true)
            ->assertJsonPath('data.overtime_source', 'pre_shift')
            ->assertJsonPath('data.overtime_start', '06:30')
            ->assertJsonPath('data.overtime_end', '08:00');
    }

    private function makeEmployee(): Employee
    {
        $company = Company::create(['name' => 'PT Arta']);
        $schedule = WorkSchedule::create([
            'company_id' => $company->id,
            'name' => 'Office',
            'work_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        return Employee::create([
            'employee_code' => 'EMP001',
            'company_id' => $company->id,
            'work_schedule_id' => $schedule->id,
            'full_name' => 'Mobile Staff',
            'email' => 'staff@example.test',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
    }
}
