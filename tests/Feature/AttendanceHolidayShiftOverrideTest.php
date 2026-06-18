<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\AttendanceRecapController;
use App\Http\Controllers\Api\AttendanceController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceHolidayShiftOverrideTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-18 09:00:00');

        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('schedule_template_days');
        Schema::dropIfExists('schedule_templates');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('employees');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('color')->default('#2563eb');
            $table->boolean('is_off')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->date('date');
            $table->string('name');
            $table->boolean('is_national')->default(true);
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present');
            $table->string('review_status')->nullable();
            $table->boolean('is_late')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->text('remote_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedBigInteger('shift_id');
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

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $this->seedHolidayOverrideSchedule();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_daily_recap_uses_manual_shift_override_on_national_holiday(): void
    {
        session(['admin_id' => 99]);

        $request = Request::create('/admin/attendance-recap', 'GET', [
            'date' => '2026-12-25',
        ]);

        $view = (new AttendanceRecapController())->index($request);
        $rows = $view->getData()['rows'];

        $employeeRow = collect($rows)->firstWhere('employee.id', 1);

        $this->assertSame('Piket Natal', $employeeRow['shift']->name);
        $this->assertSame('scheduled', $employeeRow['status']);
        $this->assertSame('Terjadwal', $employeeRow['status_label']);
    }

    public function test_admin_employee_monthly_detail_keeps_manual_shift_override_on_national_holiday(): void
    {
        session(['admin_id' => 99]);

        $request = Request::create('/admin/attendance-recap/employee/1', 'GET', [
            'period' => '2026-12',
        ]);

        $view = (new AttendanceRecapController())->employeeDetail($request, 1);
        $rows = $view->getData()['rows'];

        $christmasRow = collect($rows)->first(fn ($row) => $row['date']->format('Y-m-d') === '2026-12-25');

        $this->assertSame('Piket Natal', $christmasRow['shift']->name);
        $this->assertSame('scheduled', $christmasRow['status']);
        $this->assertSame('Terjadwal', $christmasRow['status_label']);
        $this->assertSame('Natal Nasional', $christmasRow['holiday']->name);
    }

    public function test_api_schedule_returns_manual_shift_override_on_national_holiday(): void
    {
        $request = Request::create('/api/attendance/schedule', 'GET', [
            'period' => '2026-12',
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->schedule($request);
        $days = $response->getData(true)['data']['days'];

        $christmasDay = collect($days)->firstWhere('date', '2026-12-25');

        $this->assertSame('Natal Nasional', $christmasDay['holiday']);
        $this->assertSame('Piket Natal', $christmasDay['shift']['name']);
        $this->assertSame('08:00', $christmasDay['shift']['start_time']);
    }

    private function seedHolidayOverrideSchedule(): void
    {
        DB::table('departments')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Security',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => 'EMP001',
                'full_name' => 'Employee One',
                'email' => 'employee@example.test',
                'password' => 'password',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 99,
                'company_id' => 1,
                'department_id' => 1,
                'employee_code' => 'ADM099',
                'full_name' => 'HR Admin',
                'email' => 'admin@example.test',
                'password' => 'password',
                'role' => 'hr_admin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('shifts')->insert([
            'id' => 7,
            'company_id' => 1,
            'name' => 'Piket Natal',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'color' => '#dc2626',
            'is_off' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('holidays')->insert([
            'id' => 1,
            'company_id' => 1,
            'date' => '2026-12-25',
            'name' => 'Natal Nasional',
            'is_national' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedule_assignments')->insert([
            'employee_id' => 1,
            'shift_id' => 7,
            'date' => '2026-12-25',
            'notes' => 'Piket libur nasional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
