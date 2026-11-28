<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /api/overtime/check-shift ikut mengirim jam clock-in/out hari itu supaya aplikasi
 * bisa mengisi jam mulai/selesai lembur hari libur dari presensi.
 */
class ApiOvertimeCheckShiftTest extends TestCase
{
    private const DATE = '2026-11-29';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['attendances', 'holidays', 'schedule_assignments', 'shifts', 'employees', 'work_schedules', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('work_schedules', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('name');
            $t->json('work_days')->nullable();
            $t->time('start_time');
            $t->time('end_time');
            $t->timestamps();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('employee_code')->unique();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->unsignedBigInteger('work_schedule_id')->nullable();
            $t->unsignedBigInteger('schedule_template_id')->nullable();
            $t->string('full_name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role')->default('employee');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('shifts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->string('name');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->boolean('is_off')->default(false);
            $t->timestamps();
        });
        Schema::create('schedule_assignments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('shift_id');
            $t->date('date');
            $t->timestamps();
        });
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->date('date');
            $t->timestamps();
        });
        Schema::create('attendances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->date('date');
            $t->time('clock_in')->nullable();
            $t->time('clock_out')->nullable();
            $t->string('status')->default('present');
            $t->timestamps();
        });
    }

    private function employeeOffOnDate(): Employee
    {
        $employee = Employee::create([
            'employee_code' => 'EMP-1',
            'company_id' => Company::create(['name' => 'PT X'])->id,
            'full_name' => 'Karyawan',
            'email' => 'karyawan@t.test',
            'password' => Hash::make('secret'),
        ]);
        $shiftId = DB::table('shifts')->insertGetId(['company_id' => $employee->company_id, 'name' => 'Libur', 'is_off' => true]);
        DB::table('schedule_assignments')->insert(['employee_id' => $employee->id, 'shift_id' => $shiftId, 'date' => self::DATE]);

        return $employee;
    }

    public function test_holiday_returns_actual_clock_in_and_clock_out(): void
    {
        $employee = $this->employeeOffOnDate();
        Attendance::create(['employee_id' => $employee->id, 'date' => self::DATE, 'clock_in' => '09:12:40', 'clock_out' => '15:47:05']);

        Sanctum::actingAs($employee);
        $data = $this->getJson('/api/overtime/check-shift?date='.self::DATE)->assertOk()->json('data');

        $this->assertSame('holiday', $data['overtime_type']);
        $this->assertSame('09:12', $data['clock_in']);
        $this->assertSame('15:47', $data['clock_out']);
    }

    public function test_clock_times_are_null_without_attendance_or_clock_out(): void
    {
        $employee = $this->employeeOffOnDate();

        Sanctum::actingAs($employee);
        $data = $this->getJson('/api/overtime/check-shift?date='.self::DATE)->assertOk()->json('data');
        $this->assertNull($data['clock_in']);
        $this->assertNull($data['clock_out']);

        Attendance::create(['employee_id' => $employee->id, 'date' => self::DATE, 'clock_in' => '08:05:00']);
        $data = $this->getJson('/api/overtime/check-shift?date='.self::DATE)->assertOk()->json('data');
        $this->assertSame('08:05', $data['clock_in']);
        $this->assertNull($data['clock_out']);
    }
}
