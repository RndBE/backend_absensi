<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
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
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('employee_approvers');
        Schema::dropIfExists('data_change_requests');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_requests');
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

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->date('date');
            $table->string('name');
            $table->boolean('is_national')->default(true);
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

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id');
            $table->string('action');
            $table->unsignedTinyInteger('step_order');
            $table->unsignedBigInteger('approval_rule_id')->nullable();
            $table->text('notes')->nullable();
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

        Schema::create('attendance_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
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

        Schema::create('data_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value');
            $table->string('status')->default('pending');
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
            ->assertSee('images/logo_be2.png', false)
            ->assertSee('Senin, 15 Juni 2026')
            ->assertSee('Employee One')
            ->assertSee('Clock In Sekarang')
            ->assertSee('Cuti')
            ->assertSee('Lembur')
            ->assertSee('Verifikasi Wajah')
            ->assertSee('/employee/face-photo', false)
            ->assertSee('/employee/profile', false)
            ->assertDontSee('Tambahkan ke Home Screen')
            ->assertDontSee('/employee/help/attendance', false);
    }

    public function test_employee_login_page_uses_hris_beacon_logo(): void
    {
        $this->get('/employee/login')
            ->assertOk()
            ->assertSee('HRIS Beacon')
            ->assertSee('images/logo_be2.png', false);
    }

    public function test_employee_portal_layout_does_not_include_pwa_or_help_shortcuts(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertDontSee('manifest.webmanifest', false)
            ->assertDontSee('apple-mobile-web-app-capable', false)
            ->assertDontSee('employee-sw.js', false)
            ->assertDontSee('Bantuan Presensi');
    }

    public function test_employee_dashboard_shows_clock_times_on_national_holiday_attendance(): void
    {
        Carbon::setTestNow('2026-12-25 13:00:00');
        $this->seedEmployee();

        DB::table('holidays')->insert([
            'company_id' => 1,
            'date' => '2026-12-25',
            'name' => 'Natal Nasional',
            'is_national' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-12-25',
            'clock_in' => '09:00:00',
            'clock_out' => '12:30:00',
            'status' => 'present',
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Natal Nasional')
            ->assertSee('09:00')
            ->assertSee('12:30')
            ->assertSee('Presensi Selesai');
    }

    public function test_employee_dashboard_shows_approved_late_arrival_permission_instead_of_late_status(): void
    {
        $this->seedEmployee();

        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-06-15',
            'clock_in' => '09:30:00',
            'clock_out' => null,
            'status' => 'present',
            'review_status' => null,
            'is_late' => true,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_types')->insert([
            'id' => 3,
            'name' => 'Izin Datang Terlambat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'employee_id' => 1,
            'leave_type_id' => 3,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'total_days' => 1,
            'reason' => 'Macet',
            'status' => 'approved',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Izin Terlambat')
            ->assertDontSee('>Terlambat<', false);
    }

    public function test_employee_dashboard_shows_approved_early_departure_permission(): void
    {
        $this->seedEmployee();

        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-06-15',
            'clock_in' => '08:00:00',
            'clock_out' => '14:00:00',
            'status' => 'present',
            'review_status' => null,
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_types')->insert([
            'id' => 4,
            'name' => 'Izin Pulang Cepat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leave_requests')->insert([
            'employee_id' => 1,
            'leave_type_id' => 4,
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-15',
            'total_days' => 1,
            'reason' => 'Keperluan mendadak',
            'status' => 'approved',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Izin Pulang Cepat');
    }

    public function test_employee_dashboard_shows_manual_partial_day_permission_statuses(): void
    {
        $this->seedEmployee();

        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-06-15',
            'clock_in' => '09:30:00',
            'clock_out' => '14:00:00',
            'status' => 'late_excuse',
            'review_status' => null,
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-06-14',
            'clock_in' => '08:30:00',
            'clock_out' => '14:00:00',
            'status' => 'early_departure',
            'review_status' => null,
            'is_late' => false,
            'is_remote' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Izin Terlambat')
            ->assertSee('Izin Pulang Cepat');
    }

    public function test_employee_can_open_profile_page(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/profile')
            ->assertOk()
            ->assertSee('Profil Saya')
            ->assertSee('EMP001')
            ->assertSee('Info Personal')
            ->assertSee('Info Pekerjaan')
            ->assertSee('Slip Gaji')
            ->assertSee('Verifikasi Wajah')
            ->assertSee('Ubah Kata Sandi')
            ->assertSee('/employee/profile/personal', false)
            ->assertSee('/employee/payslips', false);
    }

    public function test_employee_can_open_personal_info_page(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/profile/personal')
            ->assertOk()
            ->assertSee('Info Personal')
            ->assertSee('employee@example.test')
            ->assertSee('Ajukan Perubahan');
    }

    public function test_employee_can_open_employment_info_page(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/profile/employment')
            ->assertOk()
            ->assertSee('Info Pekerjaan')
            ->assertSee('EMP001');
    }

    public function test_employee_can_open_change_password_page(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/profile/password')
            ->assertOk()
            ->assertSee('Ubah Kata Sandi');
    }

    public function test_payslip_requires_password_verification_first(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/payslips')
            ->assertOk()
            ->assertSee('Verifikasi Kata Sandi');
    }

    public function test_payslip_unlock_with_correct_password_grants_access(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/payslips/unlock', ['password' => 'password'])
            ->assertRedirect(route('employee.payslips.index'))
            ->assertSessionHas('payslip_unlock');
    }

    public function test_payslip_unlock_rejects_wrong_password(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/payslips/unlock', ['password' => 'salah'])
            ->assertSessionHasErrors('password')
            ->assertSessionMissing('payslip_unlock');
    }

    public function test_payslip_unlock_is_revoked_after_leaving_payslip_area(): void
    {
        $this->seedEmployee();

        $this->withSession([
            'employee_id' => 1,
            'payslip_unlock' => ['id' => 1, 'until' => now()->addMinutes(10)->timestamp],
        ])
            ->get('/employee/profile')
            ->assertOk()
            ->assertSessionMissing('payslip_unlock');
    }

    public function test_employee_can_change_password(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->put('/employee/profile/password', [
                'current_password' => 'password',
                'new_password' => 'newsecret123',
                'new_password_confirmation' => 'newsecret123',
            ])
            ->assertRedirect();

        $hash = DB::table('employees')->where('id', 1)->value('password');
        $this->assertTrue(Hash::check('newsecret123', $hash));
    }

    public function test_employee_change_password_rejects_wrong_current_password(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->put('/employee/profile/password', [
                'current_password' => 'salah',
                'new_password' => 'newsecret123',
                'new_password_confirmation' => 'newsecret123',
            ])
            ->assertSessionHasErrors('current_password');

        $hash = DB::table('employees')->where('id', 1)->value('password');
        $this->assertTrue(Hash::check('password', $hash));
    }

    public function test_employee_can_submit_data_change_request(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/profile/data-change', [
                'field_name' => 'phone',
                'new_value' => '08123456789',
            ])
            ->assertRedirect(route('employee.profile.data-change'));

        $this->assertDatabaseHas('data_change_requests', [
            'employee_id' => 1,
            'field_name' => 'phone',
            'new_value' => '08123456789',
            'status' => 'pending',
        ]);
    }

    public function test_employee_data_change_rejects_disallowed_field(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/profile/data-change', [
                'field_name' => 'job_level',
                'new_value' => 'Manager',
            ])
            ->assertSessionHasErrors('field_name');
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

    public function test_employee_clock_in_within_start_minute_is_not_late(): void
    {
        Carbon::setTestNow('2026-06-15 09:00:30');
        $this->seedEmployee(['schedule_template_id' => 1]);

        DB::table('shifts')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Pagi',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_off' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('schedule_templates')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Office',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('schedule_template_days')->insert([
            'template_id' => 1,
            'day_of_week' => 1,
            'shift_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->postJson('/employee/attendance/clock-in', [
                'latitude' => '1.0456',
                'longitude' => '104.0305',
            ])
            ->assertOk();

        $this->assertDatabaseHas('attendances', [
            'employee_id' => 1,
            'date' => '2026-06-15 00:00:00',
            'clock_in' => '09:00:30',
            'is_late' => false,
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

    public function test_attendance_success_redirect_shows_employee_portal_alert(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/attendance/clock-in')
            ->assertOk()
            ->assertSee('employeePortalFlash', false)
            ->assertSee('sessionStorage.setItem', false)
            ->assertSee('employee-attendance-alert', false);
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
        DB::table('leave_requests')->insert([
            'employee_id' => 1,
            'leave_type_id' => 1,
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-20',
            'total_days' => 1,
            'reason' => 'Keperluan keluarga',
            'status' => 'approved',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/leaves')
            ->assertOk()
            ->assertSee('Pengajuan Cuti')
            ->assertSee('Cuti Tahunan')
            ->assertSee('>1<', false)
            ->assertDontSee('>1.0<', false)
            ->assertSee('/employee/leaves/create', false);
    }

    public function test_employee_leave_create_only_lists_leave_types_with_balance(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();
        $this->seedMaternityLeaveType();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/leaves')
            ->assertOk()
            ->assertSee('Cuti Tahunan')
            ->assertDontSee('Cuti Melahirkan');

        $this->withSession(['employee_id' => 1])
            ->get('/employee/leaves/create')
            ->assertOk()
            ->assertSee('Cuti Tahunan - sisa 12 hari')
            ->assertDontSee('Cuti Melahirkan');
    }

    public function test_employee_leave_create_lists_non_annual_leave_type_when_employee_has_balance(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();
        $this->seedMaternityLeaveTypeAndBalance();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/leaves/create')
            ->assertOk()
            ->assertSee('Cuti Tahunan - sisa 12 hari')
            ->assertSee('Cuti Melahirkan')
            ->assertDontSee('Cuti Melahirkan - sisa');
    }

    public function test_employee_leave_create_auto_calculates_total_days_from_selected_dates(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/leaves/create')
            ->assertOk()
            ->assertSee('id="leaveStartDate"', false)
            ->assertSee('id="leaveEndDate"', false)
            ->assertSee('id="leaveTotalDays"', false)
            ->assertSee('calculateLeaveTotalDays', false)
            ->assertSee('readonly', false);
    }

    public function test_employee_leave_store_recalculates_total_days_from_dates(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/leaves', [
                'leave_type_id' => 1,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-22',
                'total_days' => '1',
                'reason' => 'Keperluan keluarga',
            ])
            ->assertRedirect(route('employee.leaves.index'));

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => 1,
            'leave_type_id' => 1,
            'start_date' => '2026-06-20',
            'end_date' => '2026-06-22',
            'total_days' => 3,
            'status' => 'pending',
        ]);
    }

    public function test_employee_can_submit_non_annual_leave_without_balance_from_web_portal(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();
        $this->seedMaternityLeaveType(); // tanpa saldo

        $this->withSession(['employee_id' => 1])
            ->post('/employee/leaves', [
                'leave_type_id' => 2,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-21',
                'total_days' => '2',
                'reason' => 'Cuti melahirkan',
            ])
            ->assertRedirect(route('employee.leaves.index'));

        // Izin/cuti non-tahunan tidak berkuota → tetap bisa diajukan walau tanpa saldo.
        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => 1,
            'leave_type_id' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_employee_cannot_submit_annual_leave_without_balance_from_web_portal(): void
    {
        $this->seedEmployee();
        // Cuti Tahunan TANPA saldo
        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Tahunan',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->post('/employee/leaves', [
                'leave_type_id' => 1,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-21',
                'total_days' => '2',
                'reason' => 'Keperluan keluarga',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Saldo cuti belum tersedia.');

        $this->assertDatabaseMissing('leave_requests', [
            'employee_id' => 1,
            'leave_type_id' => 1,
        ]);
    }

    public function test_employee_can_submit_non_annual_leave_from_web_portal(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();
        $this->seedMaternityLeaveTypeAndBalance();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/leaves', [
                'leave_type_id' => 2,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-21',
                'total_days' => '2',
                'reason' => 'Cuti melahirkan',
            ])
            ->assertRedirect(route('employee.leaves.index'));

        $this->assertDatabaseHas('leave_requests', [
            'employee_id' => 1,
            'leave_type_id' => 2,
            'status' => 'pending',
            'current_step' => 1,
        ]);
    }

    public function test_employee_can_attach_file_to_leave_request(): void
    {
        Storage::fake('public');
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/leaves', [
                'leave_type_id' => 1,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-20',
                'total_days' => '1',
                'reason' => 'Sakit, terlampir surat dokter',
                'attachment' => UploadedFile::fake()->create('surat-dokter.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('employee.leaves.index'));

        $this->assertDatabaseHas('request_attachments', [
            'attachable_type' => \App\Models\LeaveRequest::class,
            'file_name' => 'surat-dokter.pdf',
        ]);

        $path = DB::table('request_attachments')->value('file_path');
        Storage::disk('public')->assertExists($path);
    }

    public function test_employee_can_edit_pending_leave_request(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();
        DB::table('leave_requests')->insert([
            'id' => 1, 'employee_id' => 1, 'leave_type_id' => 1,
            'start_date' => '2026-06-20', 'end_date' => '2026-06-20', 'total_days' => 1,
            'reason' => 'Alasan awal', 'status' => 'pending', 'current_step' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->put('/employee/leaves/1', [
                'leave_type_id' => 1,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-22',
                'total_days' => '1',
                'reason' => 'Alasan diperbarui',
            ])
            ->assertRedirect(route('employee.leaves.index'));

        $this->assertDatabaseHas('leave_requests', [
            'id' => 1,
            'reason' => 'Alasan diperbarui',
            'end_date' => '2026-06-22',
            'total_days' => 3,
        ]);
    }

    public function test_employee_cannot_edit_processed_leave_request(): void
    {
        $this->seedEmployee();
        $this->seedLeaveTypeAndBalance();
        DB::table('leave_requests')->insert([
            'id' => 1, 'employee_id' => 1, 'leave_type_id' => 1,
            'start_date' => '2026-06-20', 'end_date' => '2026-06-20', 'total_days' => 1,
            'reason' => 'Alasan awal', 'status' => 'approved', 'current_step' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->put('/employee/leaves/1', [
                'leave_type_id' => 1,
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-25',
                'total_days' => '1',
                'reason' => 'Coba ubah',
            ])
            ->assertRedirect(route('employee.leaves.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('leave_requests', [
            'id' => 1,
            'reason' => 'Alasan awal',
        ]);
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

    public function test_employee_can_open_attendance_request_page(): void
    {
        $this->seedEmployee();
        DB::table('attendance_requests')->insert([
            'employee_id' => 1,
            'date' => '2026-06-14',
            'clock_in' => '09:05',
            'clock_out' => '17:10',
            'reason' => 'Koreksi presensi kemarin',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 1])
            ->get('/employee/attendance-requests')
            ->assertOk()
            ->assertSee('Pengajuan Absensi')
            ->assertSee('Koreksi presensi kemarin')
            ->assertSee('/employee/attendance-requests/create', false);
    }

    public function test_employee_can_submit_attendance_request_from_web_portal(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/attendance-requests', [
                'date' => '2026-06-14',
                'clock_in' => '09:05',
                'clock_out' => '17:10',
                'reason' => 'Lupa clock in dan clock out',
            ])
            ->assertRedirect(route('employee.attendance-requests.index'));

        $this->assertDatabaseHas('attendance_requests', [
            'employee_id' => 1,
            'date' => '2026-06-14',
            'clock_in' => '09:05',
            'clock_out' => '17:10',
            'reason' => 'Lupa clock in dan clock out',
            'status' => 'pending',
            'current_step' => 1,
        ]);
    }

    public function test_employee_dashboard_links_to_attendance_requests(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Absensi')
            ->assertSee('/employee/attendance-requests', false);
    }

    public function test_employee_dashboard_shortcuts_use_compact_mobile_grid(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('employee-dashboard-shortcuts', false)
            ->assertSee('grid-cols-2', false)
            ->assertSee('Persetujuan Tim')
            ->assertDontSee('chevron_right');
    }

    public function test_employee_dashboard_shows_team_approval_alert_for_current_approver(): void
    {
        $this->seedEmployee(['id' => 1, 'employee_code' => 'EMP001', 'email' => 'shandy@example.test', 'full_name' => 'Shandy Bagus']);
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'fadel@example.test', 'full_name' => 'Fadel Approver']);
        $this->seedOvertimeApprovalForEmployee(1, [2]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/dashboard')
            ->assertOk()
            ->assertSee('Ada 1 pengajuan menunggu approval Anda')
            ->assertSee('/employee/approvals', false)
            ->assertSee('Lihat Persetujuan Tim');
    }

    public function test_employee_approver_can_open_my_approvals_page(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'fadel@example.test', 'full_name' => 'Fadel Approver']);
        $this->seedOvertimeApprovalForEmployee(1, [2]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/approvals')
            ->assertOk()
            ->assertSee('Persetujuan Tim')
            ->assertSee('Fadel Approver')
            ->assertSee('Employee One')
            ->assertSee('Pengajuan Lembur');
    }

    public function test_employee_approval_actions_use_compact_mobile_layout(): void
    {
        $this->seedEmployee();
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'fadel@example.test', 'full_name' => 'Fadel Approver']);
        $this->seedOvertimeApprovalForEmployee(1, [2]);

        $this->withSession(['employee_id' => 2])
            ->get('/employee/approvals')
            ->assertOk()
            ->assertSee('approval-action-bar', false)
            ->assertSee('Tambah catatan setuju')
            ->assertSee('Tambah catatan tolak')
            ->assertDontSee('rounded-lg border border-emerald-200 bg-emerald-50 p-3 space-y-3', false);
    }

    public function test_employee_approver_can_approve_overtime_step_then_next_approver_final_approves(): void
    {
        $this->seedEmployee(['id' => 1, 'employee_code' => 'EMP001', 'email' => 'shandy@example.test', 'full_name' => 'Shandy Bagus']);
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'fadel@example.test', 'full_name' => 'Fadel Approver']);
        $this->seedEmployee(['id' => 3, 'employee_code' => 'EMP003', 'email' => 'nofiyanto@example.test', 'full_name' => 'Nofiyanto Manager', 'role' => 'manager']);
        $this->seedOvertimeApprovalForEmployee(1, [2, 3]);

        $this->withSession(['employee_id' => 2])
            ->post('/employee/approvals/overtime/1/approve', [
                'notes' => 'Oke dari Fadel',
            ])
            ->assertRedirect(route('employee.approvals.index'));

        $this->assertDatabaseHas('overtime_requests', [
            'id' => 1,
            'status' => 'in_review',
            'current_step' => 2,
        ]);
        $this->assertDatabaseHas('approval_logs', [
            'approvable_type' => \App\Models\OvertimeRequest::class,
            'approvable_id' => 1,
            'approver_id' => 2,
            'action' => 'approved',
            'step_order' => 1,
        ]);

        $this->withSession(['employee_id' => 3])
            ->get('/employee/approvals')
            ->assertOk()
            ->assertSee('Shandy Bagus')
            ->assertSee('Step 2');

        $this->withSession(['employee_id' => 3])
            ->post('/employee/approvals/overtime/1/approve', [
                'notes' => 'Final approve',
            ])
            ->assertRedirect(route('employee.approvals.index'));

        $this->assertDatabaseHas('overtime_requests', [
            'id' => 1,
            'status' => 'approved',
            'current_step' => 2,
        ]);
        $this->assertDatabaseHas('approval_logs', [
            'approvable_type' => \App\Models\OvertimeRequest::class,
            'approvable_id' => 1,
            'approver_id' => 3,
            'action' => 'approved',
            'step_order' => 2,
        ]);
    }

    public function test_employee_cannot_approve_when_not_current_approver(): void
    {
        $this->seedEmployee(['id' => 1, 'employee_code' => 'EMP001', 'email' => 'shandy@example.test', 'full_name' => 'Shandy Bagus']);
        $this->seedEmployee(['id' => 2, 'employee_code' => 'EMP002', 'email' => 'fadel@example.test', 'full_name' => 'Fadel Approver']);
        $this->seedEmployee(['id' => 4, 'employee_code' => 'EMP004', 'email' => 'other@example.test', 'full_name' => 'Other Employee']);
        $this->seedOvertimeApprovalForEmployee(1, [2]);

        $this->withSession(['employee_id' => 4])
            ->post('/employee/approvals/overtime/1/approve')
            ->assertRedirect(route('employee.approvals.index'))
            ->assertSessionHas('error', 'Anda bukan approver untuk step ini.');

        $this->assertDatabaseHas('overtime_requests', [
            'id' => 1,
            'status' => 'pending',
            'current_step' => 1,
        ]);
        $this->assertDatabaseCount('approval_logs', 0);
    }

    public function test_employee_can_open_overtime_request_page(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/overtimes')
            ->assertOk()
            ->assertSee('Pengajuan Lembur')
            ->assertSee('employee-mobile-page-header', false)
            ->assertSee('employee-mobile-action', false)
            ->assertSee('/employee/overtimes/create', false);
    }

    public function test_employee_overtime_form_uses_mobile_style_duration_sections(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->get('/employee/overtimes/create')
            ->assertOk()
            ->assertSee('Durasi Lembur Hari Kerja')
            ->assertSee('Lembur Pre-Shift')
            ->assertSee('Lembur Post-Shift')
            ->assertSee('0j 0m')
            ->assertSee('type="hidden" name="pre_shift_duration" value="0"', false)
            ->assertSee('type="hidden" name="pre_shift_break" value="0"', false)
            ->assertSee('type="number"', false)
            ->assertSee('data-duration-control', false)
            ->assertSee('data-step-action', false)
            ->assertSee('overtime-stepper-row', false)
            ->assertSee('overtime-stepper-button', false)
            ->assertSee('overtime-mobile-native-field', false)
            ->assertSee('overtime-mobile-select-field', false)
            ->assertSee('overtime-select-wrapper', false)
            ->assertDontSee('type="time" name="pre_shift_duration"', false)
            ->assertSee('data-overtime-section="before-shift"', false)
            ->assertSee('data-overtime-section="after-shift"', false);
    }

    public function test_employee_can_submit_overtime_request_from_web_portal(): void
    {
        $this->seedEmployee();

        $this->withSession(['employee_id' => 1])
            ->post('/employee/overtimes', [
                'date' => '2026-06-20',
                'overtime_type' => 'workday',
                'pre_shift_duration' => 90,
                'pre_shift_break' => 15,
                'post_shift_duration' => 120,
                'post_shift_break' => 30,
                'reason' => 'Closing laporan',
            ])
            ->assertRedirect(route('employee.overtimes.index'));

        $this->assertDatabaseHas('overtime_requests', [
            'employee_id' => 1,
            'date' => '2026-06-20 00:00:00',
            'overtime_type' => 'workday',
            'total_duration' => 210,
            'break_duration' => 45,
            'status' => 'pending',
            'current_step' => 1,
        ]);
    }

    private function seedEmployee(bool|array $attributes = true, ?bool $isActive = null): void
    {
        $attributes = is_array($attributes) ? $attributes : ['is_active' => $attributes];
        if (! is_null($isActive)) {
            $attributes['is_active'] = $isActive;
        }

        DB::table('employees')->insert(array_merge([
            'id' => 1,
            'company_id' => 1,
            'employee_code' => 'EMP001',
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'position' => 'Staff',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
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

    private function seedMaternityLeaveTypeAndBalance(): void
    {
        $this->seedMaternityLeaveType();

        DB::table('leave_balances')->insert([
            'employee_id' => 1,
            'leave_type_id' => 2,
            'year' => 2026,
            'total_days' => 90,
            'used_days' => 0,
            'remaining_days' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedMaternityLeaveType(): void
    {
        DB::table('leave_types')->insert([
            'id' => 2,
            'name' => 'Cuti Melahirkan',
            'max_days' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedOvertimeApprovalForEmployee(int $employeeId, array $approverIds): void
    {
        DB::table('overtime_requests')->insert([
            'id' => 1,
            'employee_id' => $employeeId,
            'date' => '2026-06-20',
            'overtime_type' => 'workday',
            'pre_shift_duration' => 60,
            'pre_shift_break' => 0,
            'post_shift_duration' => 90,
            'post_shift_break' => 0,
            'break_duration' => 0,
            'total_duration' => 150,
            'reason' => 'Lembur closing laporan',
            'status' => 'pending',
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($approverIds as $index => $approverId) {
            DB::table('employee_approvers')->insert([
                'employee_id' => $employeeId,
                'request_type' => 'overtime',
                'step_order' => $index + 1,
                'approver_id' => $approverId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
