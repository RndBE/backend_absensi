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

        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('schedule_template_days');
        Schema::dropIfExists('schedule_templates');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('overtime_requests');
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
            $table->string('position')->nullable();
            $table->string('fcm_token')->nullable();
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
            $table->boolean('is_overnight')->default(false);
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
            $table->string('review_status')->nullable();
            $table->text('suspicious_reason')->nullable();
            $table->json('security_flags')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->boolean('is_late')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->text('remote_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('overtime_type')->default('workday');
            $table->integer('total_duration')->default(0);
            $table->integer('break_duration')->default(0);
            $table->integer('approved_duration')->nullable();
            $table->integer('approved_break')->nullable();
            $table->integer('actual_duration')->nullable();
            $table->time('actual_clock_in')->nullable();
            $table->time('actual_clock_out')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->default('info');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
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
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('delegate_to')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_step')->default(1);
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

    public function test_clock_in_on_national_holiday_is_not_marked_late(): void
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

        DB::table('holidays')->insert([
            'company_id' => 1,
            'date' => '2026-05-26',
            'name' => 'Libur Nasional Test',
            'is_national' => true,
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
            'is_late' => false,
        ]);
    }

    public function test_clock_in_with_mock_location_is_saved_for_hr_review(): void
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

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['needs_review']);
        $this->assertSame('Clock in berhasil', $response->getData(true)['message']);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => 1,
            'clock_in' => '14:34:00',
            'status' => 'present',
            'review_status' => 'pending',
            'clock_in_is_mocked' => true,
        ]);
        $this->assertStringContainsString(
            'Fake GPS',
            DB::table('attendances')->where('employee_id', 1)->value('suspicious_reason')
        );
    }

    public function test_clock_in_with_mock_location_notifies_hr_attendance_admins(): void
    {
        $this->seedLocationSettings();
        $this->seedEmployee();
        $this->seedAdminEmployee(2, 'HR Admin', 'hr_admin', 1);
        $this->seedAdminEmployee(3, 'Finance Admin', 'finance_admin', 1);
        $this->seedAdminEmployee(4, 'Other Company HR', 'hr_admin', 2);
        $this->seedAdminEmployee(5, 'Super Admin', 'superadmin', 1);
        $this->seedAdminEmployee(6, 'General Admin', 'admin', 1);

        $request = Request::create('/api/attendance/clock-in', 'POST', [
            'latitude' => '1.0456',
            'longitude' => '104.0305',
            'location_accuracy' => 10,
            'location_timestamp' => '2026-05-26T07:34:00Z',
            'is_mock_location' => true,
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockIn($request);

        $this->assertSame(200, $response->getStatusCode());
        $attendanceId = DB::table('attendances')->where('employee_id', 1)->value('id');

        $this->assertDatabaseHas('notifications', [
            'employee_id' => 2,
            'title' => 'Presensi Mencurigakan',
            'type' => 'attendance_security_review',
            'reference_type' => \App\Models\Attendance::class,
            'reference_id' => $attendanceId,
            'is_read' => false,
        ]);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => 5,
            'title' => 'Presensi Mencurigakan',
            'type' => 'attendance_security_review',
            'reference_type' => \App\Models\Attendance::class,
            'reference_id' => $attendanceId,
            'is_read' => false,
        ]);
        $this->assertStringContainsString(
            'Employee One terdeteksi Fake GPS saat clock in',
            DB::table('notifications')
                ->where('employee_id', 2)
                ->where('type', 'attendance_security_review')
                ->value('message')
        );
        $this->assertDatabaseMissing('notifications', ['employee_id' => 1]);
        $this->assertDatabaseMissing('notifications', ['employee_id' => 3]);
        $this->assertDatabaseMissing('notifications', ['employee_id' => 4]);
        $this->assertDatabaseMissing('notifications', ['employee_id' => 6]);
    }

    public function test_clock_in_notifies_active_hr_admins_in_same_company(): void
    {
        DB::table('settings')->insert([
            ['key' => 'require_photo', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'require_gps', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'face_verification_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seedEmployee();
        $this->seedAdminEmployee(2, 'HR Admin', 'hr_admin', 1);
        $this->seedAdminEmployee(3, 'Other Company HR', 'hr_admin', 2);
        $this->seedAdminEmployee(4, 'Finance Admin', 'finance_admin', 1);
        $this->seedAdminEmployee(5, 'Super Admin', 'superadmin', 1);
        $this->seedAdminEmployee(6, 'Inactive HR', 'hr_admin', 1, null, false);

        $request = Request::create('/api/attendance/clock-in', 'POST');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockIn($request);

        $this->assertSame(200, $response->getStatusCode());
        $attendanceId = DB::table('attendances')->where('employee_id', 1)->value('id');

        $this->assertDatabaseHas('notifications', [
            'employee_id' => 2,
            'title' => 'Clock-In Karyawan',
            'type' => 'attendance_clock_in',
            'reference_type' => \App\Models\Attendance::class,
            'reference_id' => $attendanceId,
            'is_read' => false,
        ]);
        $this->assertStringContainsString(
            'Employee One sudah clock-in pukul 14:34 pada 26/05/2026.',
            DB::table('notifications')->where('employee_id', 2)->value('message')
        );
        $this->assertDatabaseMissing('notifications', ['employee_id' => 1, 'type' => 'attendance_clock_in']);
        $this->assertDatabaseMissing('notifications', ['employee_id' => 3, 'type' => 'attendance_clock_in']);
        $this->assertDatabaseMissing('notifications', ['employee_id' => 4, 'type' => 'attendance_clock_in']);
        $this->assertDatabaseMissing('notifications', ['employee_id' => 5, 'type' => 'attendance_clock_in']);
        $this->assertDatabaseMissing('notifications', ['employee_id' => 6, 'type' => 'attendance_clock_in']);
    }

    public function test_repeated_clock_in_does_not_duplicate_hr_admin_notification(): void
    {
        DB::table('settings')->insert([
            ['key' => 'require_photo', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'require_gps', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'face_verification_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seedEmployee();
        $this->seedAdminEmployee(2, 'HR Admin', 'hr_admin', 1);

        $request = Request::create('/api/attendance/clock-in', 'POST');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        (new AttendanceController())->clockIn($request);
        (new AttendanceController())->clockIn($request);

        $this->assertSame(1, DB::table('notifications')->where('type', 'attendance_clock_in')->count());
    }

    public function test_employee_with_regular_shift_can_clock_in_today_when_yesterday_was_not_clocked_out(): void
    {
        DB::table('settings')->insert([
            ['key' => 'require_photo', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'require_gps', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'face_verification_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seedEmployee();

        DB::table('shifts')->insert([
            'id' => 10,
            'company_id' => 1,
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
            'is_overnight' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('schedule_assignments')->insert([
            'employee_id' => 1,
            'shift_id' => 10,
            'date' => '2026-05-25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-05-25',
            'clock_in' => '08:05:00',
            'clock_out' => null,
            'status' => 'present',
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/attendance/clock-in', 'POST');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockIn($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(
            DB::table('attendances')
                ->where('employee_id', 1)
                ->whereDate('date', '2026-05-26')
                ->where('clock_in', '14:34:00')
                ->exists()
        );
    }

    public function test_employee_with_overnight_shift_must_clock_out_before_clocking_in_next_day(): void
    {
        DB::table('settings')->insert([
            ['key' => 'require_photo', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'require_gps', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'face_verification_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->seedEmployee();

        DB::table('shifts')->insert([
            'id' => 11,
            'company_id' => 1,
            'name' => 'Security Malam',
            'start_time' => '19:00:00',
            'end_time' => '07:00:00',
            'is_off' => false,
            'is_overnight' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('schedule_assignments')->insert([
            'employee_id' => 1,
            'shift_id' => 11,
            'date' => '2026-05-25',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-05-25',
            'clock_in' => '19:05:00',
            'clock_out' => null,
            'status' => 'present',
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/attendance/clock-in', 'POST');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockIn($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('shift malam', $response->getData(true)['message']);
        $this->assertFalse(
            DB::table('attendances')
                ->where('employee_id', 1)
                ->whereDate('date', '2026-05-26')
                ->exists()
        );
    }

    public function test_existing_security_review_notification_still_sends_push_on_clock_out_without_duplicate_inbox_item(): void
    {
        $this->seedLocationSettings();
        $this->seedEmployee();
        $this->seedAdminEmployee(2, 'HR Admin', 'hr_admin', 1, 'hr-fcm-token');

        DB::table('attendances')->insert([
            'id' => 55,
            'employee_id' => 1,
            'date' => '2026-05-26',
            'clock_in' => '08:00:00',
            'status' => 'present',
            'review_status' => 'pending',
            'suspicious_reason' => 'Fake GPS terdeteksi saat clock in',
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'employee_id' => 2,
            'title' => 'Presensi Mencurigakan',
            'message' => 'Pesan lama',
            'type' => 'attendance_security_review',
            'reference_type' => \App\Models\Attendance::class,
            'reference_id' => 55,
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/attendance/clock-out', 'POST', [
            'latitude' => '1.0456',
            'longitude' => '104.0305',
            'location_accuracy' => 8,
            'location_timestamp' => '2026-05-26T10:34:00Z',
            'is_mock_location' => true,
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $controller = new AttendanceControllerPushSpy();
        $response = $controller->clockOut($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $controller->sentPushes);
        $this->assertSame(2, $controller->sentPushes[0]['employee_id']);
        $this->assertSame('Presensi Mencurigakan', $controller->sentPushes[0]['title']);
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_clock_in_with_poor_location_accuracy_is_saved_for_hr_review(): void
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

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['needs_review']);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => 1,
            'clock_in' => '14:34:00',
            'review_status' => 'pending',
            'clock_in_accuracy_meters' => 125,
        ]);
        $this->assertStringContainsString(
            'Akurasi GPS rendah',
            DB::table('attendances')->where('employee_id', 1)->value('suspicious_reason')
        );
    }

    public function test_clock_out_with_mock_location_marks_attendance_for_hr_review(): void
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
            'latitude' => '1.0456',
            'longitude' => '104.0305',
            'location_accuracy' => 8,
            'location_timestamp' => '2026-05-26T10:34:00Z',
            'is_mock_location' => true,
        ]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        $response = (new AttendanceController())->clockOut($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['needs_review']);
        $this->assertSame('Clock out berhasil', $response->getData(true)['message']);
        $this->assertDatabaseHas('attendances', [
            'employee_id' => 1,
            'clock_out' => '14:34:00',
            'review_status' => 'pending',
            'clock_out_is_mocked' => true,
        ]);
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

    private function seedAdminEmployee(int $id, string $name, string $role, int $companyId, ?string $fcmToken = null, bool $isActive = true): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'work_schedule_id' => null,
            'schedule_template_id' => null,
            'employee_code' => 'ADM'.str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'full_name' => $name,
            'email' => "admin{$id}@example.test",
            'password' => 'password',
            'role' => $role,
            'position' => $name,
            'fcm_token' => $fcmToken,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

class AttendanceControllerPushSpy extends AttendanceController
{
    public array $sentPushes = [];

    protected function sendAttendanceSecurityPush(\App\Models\Employee $recipient, \App\Models\Notification $notification): void
    {
        $this->sentPushes[] = [
            'employee_id' => $recipient->id,
            'title' => $notification->title,
            'message' => $notification->message,
        ];
    }
}
