<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceController;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ApiAttendanceClockInTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-26 14:34:00');

        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('schedule_template_days');
        Schema::dropIfExists('schedule_templates');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off')->default(false);
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
            $table->decimal('clock_in_accuracy_meters', 8, 2)->nullable();
            $table->decimal('clock_out_accuracy_meters', 8, 2)->nullable();
            $table->boolean('clock_in_is_mocked')->default(false);
            $table->boolean('clock_out_is_mocked')->default(false);
            $table->timestamp('clock_in_location_recorded_at')->nullable();
            $table->timestamp('clock_out_location_recorded_at')->nullable();
            $table->string('clock_in_photo')->nullable();
            $table->string('clock_out_photo')->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->text('remote_notes')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_clock_in_marks_late_using_schedule_template_shift_start_time(): void
    {
        DB::table('settings')->insert([
            ['key' => 'require_photo', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'require_gps', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'face_verification_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('shifts')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Pagi',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedule_templates')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => '6 Hari Kerja (Pagi)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedule_template_days')->insert([
            'template_id' => 1,
            'day_of_week' => 2,
            'shift_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'work_schedule_id' => null,
            'schedule_template_id' => 1,
            'employee_code' => 'EMP001',
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => 'password',
            'role' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/attendance/clock-in', 'POST');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        (new AttendanceController())->clockIn($request);

        $this->assertDatabaseHas('attendances', [
            'employee_id' => 1,
            'clock_in' => '14:34:00',
            'is_late' => true,
        ]);
    }

    public function test_clock_in_rejects_mock_location(): void
    {
        $this->seedLocationSettings();
        $this->seedEmployee();

        $request = Request::create('/api/attendance/clock-in', 'POST', [
            'latitude' => '1.0456',
            'longitude' => '104.0305',
            'location_accuracy' => 10,
            'location_timestamp' => '2026-05-26T07:34:00Z',
            'is_mock_location' => true,
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockIn($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('lokasi palsu', $response->getData(true)['message']);
        $this->assertDatabaseMissing('attendances', ['employee_id' => 1]);
    }

    public function test_clock_in_rejects_poor_location_accuracy(): void
    {
        $this->seedLocationSettings(['max_gps_accuracy_meters' => '50']);
        $this->seedEmployee();

        $request = Request::create('/api/attendance/clock-in', 'POST', [
            'latitude' => '1.0456',
            'longitude' => '104.0305',
            'location_accuracy' => 125,
            'location_timestamp' => '2026-05-26T07:34:00Z',
            'is_mock_location' => false,
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockIn($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('Akurasi lokasi', $response->getData(true)['message']);
        $this->assertDatabaseMissing('attendances', ['employee_id' => 1]);
    }

    public function test_clock_out_rejects_location_outside_office_radius(): void
    {
        $this->seedLocationSettings();
        $this->seedEmployee();

        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-05-26',
            'clock_in' => '08:00:00',
            'clock_out' => null,
            'status' => 'present',
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/attendance/clock-out', 'POST', [
            'latitude' => '1.1000',
            'longitude' => '104.1000',
            'location_accuracy' => 10,
            'location_timestamp' => '2026-05-26T10:34:00Z',
            'is_mock_location' => false,
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockOut($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('luar radius kantor', $response->getData(true)['message']);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => 1,
            'clock_out' => null,
        ]);
    }

    private function seedLocationSettings(array $overrides = []): void
    {
        $settings = array_merge([
            'require_photo' => '0',
            'require_gps' => '1',
            'face_verification_enabled' => '0',
            'office_latitude' => '1.0456',
            'office_longitude' => '104.0305',
            'office_radius_meters' => '100',
            'allow_remote_clockin' => '0',
            'max_gps_accuracy_meters' => '100',
        ], $overrides);

        foreach ($settings as $key => $value) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedEmployee(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'work_schedule_id' => null,
            'schedule_template_id' => null,
            'employee_code' => 'EMP001',
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => 'password',
            'role' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
