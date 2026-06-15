<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeePortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Carbon::setTestNow('2026-06-15 08:15:00');

        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('schedule_template_days');
        Schema::dropIfExists('schedule_templates');
        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('employees');

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
            $table->string('face_photo')->nullable();
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
            $table->string('color')->nullable();
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

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->unsignedTinyInteger('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->default(12);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->integer('year');
            $table->integer('total_days')->default(12);
            $table->integer('used_days')->default(0);
            $table->integer('remaining_days')->default(12);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason');
            $table->unsignedBigInteger('delegate_to')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
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
            $table->time('planned_start')->nullable();
            $table->time('planned_end')->nullable();
            $table->integer('total_duration')->default(0);
            $table->integer('break_duration')->default(0);
            $table->integer('pre_shift_duration')->default(0);
            $table->integer('pre_shift_break')->default(0);
            $table->integer('post_shift_duration')->default(0);
            $table->integer('post_shift_break')->default(0);
            $table->integer('approved_duration')->nullable();
            $table->integer('approved_break')->nullable();
            $table->integer('actual_duration')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->time('actual_clock_in')->nullable();
            $table->time('actual_clock_out')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedTinyInteger('current_step')->default(1);
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

        foreach ([
            'require_photo' => '0',
            'require_gps' => '0',
            'face_verification_enabled' => '0',
            'office_latitude' => '1.0456',
            'office_longitude' => '104.0305',
            'office_radius_meters' => '100',
            'allow_remote_clockin' => '0',
        ] as $key => $value) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_active_employee_can_login_and_view_employee_dashboard(): void
    {
        $this->seedEmployee();

        $login = $this->post('/employee/login', [
            'email' => 'employee@example.test',
            'password' => 'password',
        ]);

        $login->assertRedirect(route('employee.dashboard'));
        $this->assertSame(1, session('employee_id'));

        $this->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('HRIS Beacon')
            ->assertSee('Senin, 15 Juni 2026')
            ->assertSee('Employee One')
            ->assertSee('Clock In Sekarang')
            ->assertSee('Pengajuan Cuti')
            ->assertSee('Pengajuan Lembur')
            ->assertSee('Verifikasi Wajah')
            ->assertSee('/employee/face-photo', false);
    }

    public function test_inactive_employee_cannot_login_to_employee_portal(): void
    {
        $this->seedEmployee(isActive: false);

        $this->post('/employee/login', [
            'email' => 'employee@example.test',
            'password' => 'password',
        ])->assertSessionHas('error', 'Akun anda tidak aktif.');

        $this->assertNull(session('employee_id'));
    }

    public function test_employee_clock_in_uses_logged_in_employee(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->postJson('/employee/attendance/clock-in', [
                'latitude' => '1.0456',
                'longitude' => '104.0305',
                'location_accuracy' => 12,
                'location_timestamp' => '2026-06-15T01:15:00Z',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Clock in berhasil',
            ]);

        $this->assertDatabaseHas('attendances', [
            'employee_id' => 1,
            'date' => '2026-06-15 00:00:00',
            'clock_in' => '08:15:00',
        ]);
    }

    public function test_attendance_page_has_retry_location_control(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/attendance/clock-in')
            ->assertOk()
            ->assertSee('Coba Ambil Lokasi Lagi')
            ->assertSee('retryLocationBtn');
    }

    public function test_attendance_photo_capture_unmirrors_front_camera_photo(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/attendance/clock-in')
            ->assertOk()
            ->assertSee("context.translate(width, 0)", false)
            ->assertSee("context.scale(-1, 1)", false);
    }

    public function test_attendance_camera_preview_is_unmirrored(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/attendance/clock-in')
            ->assertOk()
            ->assertSee('id="cameraPreview"', false)
            ->assertSee('transform: scaleX(-1)', false);
    }

    public function test_attendance_camera_overlay_is_clipped_to_camera_area(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/attendance/clock-in')
            ->assertOk()
            ->assertSee('relative bg-slate-950 overflow-hidden', false);
    }

    public function test_employee_can_open_face_photo_registration_page(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/face-photo')
            ->assertOk()
            ->assertSee('Daftarkan Wajah')
            ->assertSee('cameraPreview')
            ->assertSee('/employee/face-photo', false)
            ->assertSee('relative bg-slate-950 overflow-hidden', false);
    }

    public function test_employee_can_save_face_photo_from_web_portal(): void
    {
        Storage::fake('public');
        $this->seedEmployee();

        $response = $this->withSession(['employee_id' => 1])
            ->postJson('/employee/face-photo', [
                'photo_base64' => base64_encode('fake-reference-photo'),
            ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Foto verifikasi wajah berhasil disimpan.',
            ]);

        $path = DB::table('employees')->where('id', 1)->value('face_photo');

        $this->assertNotNull($path);
        $this->assertStringStartsWith('employees/face-photos/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_employee_can_open_leave_request_page(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/leaves')
            ->assertOk()
            ->assertSee('Pengajuan Cuti')
            ->assertSee('Cuti Tahunan')
            ->assertSee('/employee/leaves/create', false);
    }

    public function test_employee_can_submit_leave_request_from_web_portal(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/leaves', [
                'leave_type_id' => 1,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-21',
                'total_days' => '2',
                'reason' => 'Keperluan keluarga',
            ])
            ->assertRedirect(route('employee.leaves.index'));

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => 1,
            'leave_type_id' => 1,
            'status' => 'pending',
            'current_step' => 1,
        ]);
    }

    public function test_employee_can_open_overtime_request_page(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/overtimes')
            ->assertOk()
            ->assertSee('Pengajuan Lembur')
            ->assertSee('/employee/overtimes/create', false);
    }

    public function test_employee_can_submit_overtime_request_from_web_portal(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/overtimes', [
                'date' => '2026-06-20',
                'overtime_type' => 'workday',
                'post_shift_duration' => 120,
                'post_shift_break' => 15,
                'reason' => 'Closing laporan',
            ])
            ->assertRedirect(route('employee.overtimes.index'));

        $this->assertDatabaseHas('overtime_requests', [
            'employee_id' => 1,
            'date' => '2026-06-20 00:00:00',
            'overtime_type' => 'workday',
            'total_duration' => 120,
            'break_duration' => 15,
            'status' => 'pending',
            'current_step' => 1,
        ]);
    }

    private function seedEmployee(bool $isActive = true): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'employee_code' => 'EMP001',
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'position' => 'Staff',
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedLeaveTypeAndBalance(): void
    {
        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Tahunan',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_balances')->insert([
            'employee_id' => 1,
            'leave_type_id' => 1,
            'year' => 2026,
            'total_days' => 12,
            'used_days' => 0,
            'remaining_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
