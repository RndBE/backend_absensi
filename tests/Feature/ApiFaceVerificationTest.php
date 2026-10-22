<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Employee\FacePhotoController;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\FaceVerificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Keamanan clock in/out: selfie yang gelap, tanpa wajah, berisi banyak wajah, atau bukan pemilik
 * akun harus ditolak, dan layanan wajah yang mati tidak boleh meloloskan siapa pun diam-diam.
 */
class ApiFaceVerificationTest extends TestCase
{
    /** PNG 1x1 yang sah, agar lolos pengecekan "file ini gambar". */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-26 14:34:00');
        (new ReflectionProperty(Setting::class, 'punyaKolomCompany'))->setValue(null, null);
        Storage::fake('public');

        foreach ([
            'leave_requests', 'leave_types', 'schedule_assignments', 'schedule_template_days', 'schedule_templates',
            'holidays', 'notifications', 'attendances', 'overtime_requests', 'shifts', 'settings', 'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('face_photo')->nullable();
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
        config(['services.face_verification.when_unavailable' => 'block']);

        parent::tearDown();
    }

    // ───────────────────────────────────────────── clock in

    public function test_clock_in_passes_when_face_matches(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => true, 'similarity' => 0.81, 'distance' => 0.19, 'reason' => 'ok', 'quality' => ['brightness' => 118.4]]);

        $response = $this->clockIn(photo: true);

        $this->assertSame(200, $response->getStatusCode());
        $attendance = DB::table('attendances')->where('employee_id', 1)->first();
        $this->assertNull($attendance->review_status);
        $face = json_decode($attendance->security_flags, true)['clock_in']['face'];
        $this->assertSame('verified', $face['status']);
        $this->assertSame(0.81, $face['similarity']);
        $this->assertSame(118.4, $face['brightness']);
    }

    public function test_clock_in_is_rejected_when_face_is_someone_else(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays([
            'match' => false, 'similarity' => 0.31, 'distance' => 0.69, 'reason' => 'mismatch',
            'message' => 'Verifikasi wajah gagal. Wajah tidak sesuai dengan foto yang terdaftar.',
        ]);

        $response = $this->clockIn(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $body = $response->getData(true);
        $this->assertSame('mismatch', $body['reason']);
        $this->assertStringContainsString('tidak sesuai', $body['message']);
        $this->assertEquals(31, $body['similarity']);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('attendance/clock-in'), 'selfie yang ditolak tidak boleh disimpan');
    }

    #[DataProvider('rejectedSelfies')]
    public function test_clock_in_is_rejected_for_poor_selfie(string $reason, string $message): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => false, 'similarity' => 0, 'distance' => 1, 'reason' => $reason, 'message' => $message]);

        $response = $this->clockIn(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($reason, $response->getData(true)['reason']);
        $this->assertSame($message, $response->getData(true)['message']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public static function rejectedSelfies(): array
    {
        return [
            'terlalu gelap' => ['too_dark', 'Pencahayaan kurang terang. Pindah ke tempat yang lebih terang lalu coba lagi.'],
            'tanpa wajah' => ['no_face', 'Wajah tidak terdeteksi pada foto selfie.'],
            'banyak wajah' => ['multiple_faces', 'Terdeteksi lebih dari satu wajah.'],
            'terlalu jauh' => ['face_small', 'Wajah terlalu jauh dari kamera.'],
            'terlalu silau' => ['too_bright', 'Foto terlalu silau.'],
        ];
    }

    public function test_clock_in_when_service_is_down_is_saved_but_flagged_for_hr_under_review_policy(): void
    {
        config(['services.face_verification.when_unavailable' => 'review']);
        $this->setting();
        $this->employee(withFace: true);
        $this->adminEmployee(2, 'HR Admin', 'hr_admin');
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockIn(photo: true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['needs_review']);
        $attendance = DB::table('attendances')->where('employee_id', 1)->first();
        $this->assertSame('pending', $attendance->review_status);
        $this->assertStringContainsString('Wajah belum terverifikasi saat clock in', $attendance->suspicious_reason);
        $this->assertSame('unavailable', json_decode($attendance->security_flags, true)['clock_in']['face']['status']);
        $this->assertStringContainsString(
            'Employee One terdeteksi wajah belum terverifikasi saat clock in',
            DB::table('notifications')->where('employee_id', 2)->where('type', 'attendance_security_review')->value('message')
        );
    }

    #[DataProvider('brokenServiceReplies')]
    public function test_service_errors_are_not_treated_as_a_face_mismatch_under_review_policy(int $status, array $body): void
    {
        config(['services.face_verification.when_unavailable' => 'review']);
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(['*/verify' => Http::response($body, $status)]);

        $response = $this->clockIn(photo: true);

        // Dulu: 5xx dengan match=false menolak dengan pesan "kesalahan internal" yang menyesatkan.
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('pending', DB::table('attendances')->value('review_status'));
    }

    public static function brokenServiceReplies(): array
    {
        return [
            'error internal' => [500, ['match' => false, 'message' => 'Terjadi kesalahan internal.']],
            'model belum siap' => [503, ['match' => false, 'message' => 'Service belum siap, coba lagi.']],
            'permintaan salah' => [400, ['error' => 'selfie_base64 dan reference_base64 wajib diisi.']],
            'balasan bukan json' => [200, []],
        ];
    }

    public function test_clock_in_is_refused_by_default_when_the_face_service_is_down(): void
    {
        // Tanpa mengatur kebijakan apa pun: layanan mati berarti presensi ditolak, bukan lolos.
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockIn(photo: true);

        $this->assertSame('block', FaceVerificationService::whenUnavailable());
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData(true)['reason']);
        $this->assertSame(FaceVerificationService::UNAVAILABLE_MESSAGE, $response->getData(true)['message']);
        $this->assertDatabaseCount('attendances', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('attendance/clock-in'), 'selfie tidak disimpan');
    }

    #[DataProvider('brokenServiceReplies')]
    public function test_service_errors_refuse_attendance_by_default(int $status, array $body): void
    {
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(['*/verify' => Http::response($body, $status)]);

        $response = $this->clockIn(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData(true)['reason']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_the_refusal_message_says_what_happened_and_what_to_do(): void
    {
        $message = FaceVerificationService::UNAVAILABLE_MESSAGE;

        $this->assertStringContainsString('layanan wajah sedang tidak tersedia', $message);
        $this->assertStringContainsString('presensi belum dicatat', $message);
        $this->assertStringContainsString('Coba lagi', $message);
        $this->assertStringContainsString('hubungi HRD', $message);
    }

    public function test_the_default_policy_comes_from_config_and_is_block(): void
    {
        $this->assertSame('block', config('services.face_verification.when_unavailable'));
    }

    public function test_clock_in_is_blocked_when_service_is_down_and_policy_is_block(): void
    {
        config(['services.face_verification.when_unavailable' => 'block']);
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockIn(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData(true)['reason']);
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_clock_in_passes_unflagged_only_when_policy_is_explicitly_allow(): void
    {
        config(['services.face_verification.when_unavailable' => 'allow']);
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockIn(photo: true);

        $this->assertSame(200, $response->getStatusCode());
        $attendance = DB::table('attendances')->first();
        $this->assertNull($attendance->review_status);
        $this->assertSame('skipped', json_decode($attendance->security_flags, true)['clock_in']['face']['status']);
    }

    public function test_unknown_policy_value_fails_closed_like_block(): void
    {
        config(['services.face_verification.when_unavailable' => 'abaikan-saja']);
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockIn(photo: true);

        $this->assertSame('block', FaceVerificationService::whenUnavailable());
        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_clock_in_requires_a_selfie_even_when_photo_setting_is_off(): void
    {
        $this->setting(['require_photo' => '0']);
        $this->employee(withFace: true);
        Http::fake();

        $response = $this->clockIn(photo: false);

        // Dulu: tanpa foto, pencocokan wajah dilewati begitu saja.
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('no_photo', $response->getData(true)['reason']);
        $this->assertDatabaseCount('attendances', 0);
        Http::assertNothingSent();
    }

    public function test_clock_in_requires_a_registered_face(): void
    {
        $this->setting();
        $this->employee(withFace: false);
        Http::fake();

        $response = $this->clockIn(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('not_registered', $response->getData(true)['reason']);
        Http::assertNothingSent();
    }

    public function test_clock_in_with_a_registered_photo_whose_file_is_gone_asks_to_register_again(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        Storage::disk('public')->delete('employees/face-photos/ref.jpg');
        Http::fake();

        $response = $this->clockIn(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('reference_invalid', $response->getData(true)['reason']);
        Http::assertNothingSent();
    }

    public function test_nothing_is_checked_when_face_verification_is_switched_off(): void
    {
        $this->setting(['face_verification_enabled' => '0']);
        $this->employee(withFace: false);
        Http::fake();

        $response = $this->clockIn(photo: true);

        $this->assertSame(200, $response->getStatusCode());
        Http::assertNothingSent();
    }

    public function test_face_and_gps_problems_are_both_reported_to_hr(): void
    {
        config(['services.face_verification.when_unavailable' => 'review']);
        $this->setting(['require_gps' => '1', 'office_latitude' => '1.0456', 'office_longitude' => '104.0305']);
        $this->employee(withFace: true);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->clockIn(photo: true, extra: [
            'latitude' => '1.0456', 'longitude' => '104.0305', 'location_accuracy' => 10, 'is_mock_location' => true,
        ]);

        $reason = DB::table('attendances')->value('suspicious_reason');
        $this->assertStringContainsString('Fake GPS', $reason);
        $this->assertStringContainsString('Wajah belum terverifikasi', $reason);
    }

    public function test_match_reply_from_an_older_service_without_reason_or_quality_still_works(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => true, 'similarity' => 0.7, 'message' => 'Verifikasi wajah berhasil.']);

        $this->assertSame(200, $this->clockIn(photo: true)->getStatusCode());
    }

    public function test_mismatch_reply_from_an_older_service_is_still_rejected(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => false, 'similarity' => 0.2, 'message' => 'Verifikasi wajah gagal.']);

        $rejected = $this->clockIn(photo: true);

        $this->assertSame(422, $rejected->getStatusCode());
        $this->assertSame('mismatch', $rejected->getData(true)['reason']);
    }

    public function test_poor_reference_photo_is_recorded_so_hr_can_ask_to_register_again(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays([
            'match' => true, 'similarity' => 0.7, 'distance' => 0.3, 'reason' => 'ok',
            'reference_quality' => ['reason' => 'too_dark', 'brightness' => 30],
        ]);

        $this->clockIn(photo: true);

        $face = json_decode(DB::table('attendances')->value('security_flags'), true)['clock_in']['face'];
        $this->assertSame('too_dark', $face['reference_quality']);
    }

    public function test_selfie_and_reference_are_sent_to_the_face_service(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => true, 'similarity' => 0.9, 'reason' => 'ok']);

        $this->clockIn(photo: true);

        Http::assertSent(function (HttpRequest $request) {
            return str_ends_with($request->url(), '/verify')
                && $request['selfie_base64'] === base64_encode('selfie-jpeg')
                && $request['reference_base64'] === base64_encode('reference-jpeg');
        });
    }

    // ───────────────────────────────────────────── clock out

    public function test_clock_out_passes_when_face_matches(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->openAttendance();
        $this->serviceSays(['match' => true, 'similarity' => 0.8, 'reason' => 'ok']);

        $response = $this->clockOut(photo: true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('14:34:00', DB::table('attendances')->value('clock_out'));
    }

    public function test_clock_out_is_rejected_when_face_is_someone_else(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->openAttendance();
        $this->serviceSays(['match' => false, 'similarity' => 0.2, 'reason' => 'mismatch', 'message' => 'Verifikasi wajah gagal.']);

        $response = $this->clockOut(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('mismatch', $response->getData(true)['reason']);
        $this->assertNull(DB::table('attendances')->value('clock_out'));
        $this->assertSame([], Storage::disk('public')->allFiles('attendance/clock-out'));
    }

    public function test_clock_out_is_rejected_for_a_dark_selfie(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->openAttendance();
        $this->serviceSays(['match' => false, 'similarity' => 0, 'reason' => 'too_dark', 'message' => 'Pencahayaan kurang terang.']);

        $response = $this->clockOut(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('too_dark', $response->getData(true)['reason']);
        $this->assertNull(DB::table('attendances')->value('clock_out'));
    }

    public function test_clock_out_without_registered_face_is_no_longer_skipped(): void
    {
        $this->setting();
        $this->employee(withFace: false);
        $this->openAttendance();
        Http::fake();

        $response = $this->clockOut(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('not_registered', $response->getData(true)['reason']);
        $this->assertNull(DB::table('attendances')->value('clock_out'));
    }

    public function test_clock_out_requires_a_selfie_even_when_photo_setting_is_off(): void
    {
        $this->setting(['require_photo' => '0']);
        $this->employee(withFace: true);
        $this->openAttendance();
        Http::fake();

        $response = $this->clockOut(photo: false);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('no_photo', $response->getData(true)['reason']);
        Http::assertNothingSent();
    }

    public function test_clock_out_when_service_is_down_under_review_policy_is_saved_and_flagged_without_losing_clock_in_flags(): void
    {
        config(['services.face_verification.when_unavailable' => 'review']);
        $this->setting();
        $this->employee(withFace: true);
        $this->openAttendance(['clock_in' => ['face' => ['status' => 'verified']]]);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockOut(photo: true);

        $this->assertSame(200, $response->getStatusCode());
        $attendance = DB::table('attendances')->first();
        $this->assertSame('14:34:00', $attendance->clock_out);
        $this->assertSame('pending', $attendance->review_status);
        $this->assertStringContainsString('Wajah belum terverifikasi saat clock out', $attendance->suspicious_reason);
        $flags = json_decode($attendance->security_flags, true);
        $this->assertSame('unavailable', $flags['clock_out']['face']['status']);
        $this->assertSame('verified', $flags['clock_in']['face']['status']);
    }

    public function test_clock_out_is_refused_by_default_when_the_face_service_is_down(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->openAttendance();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockOut(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData(true)['reason']);
        $this->assertSame(FaceVerificationService::UNAVAILABLE_MESSAGE, $response->getData(true)['message']);
        $this->assertNull(DB::table('attendances')->value('clock_out'));
        $this->assertSame([], Storage::disk('public')->allFiles('attendance/clock-out'));
    }

    public function test_clock_out_blocked_by_policy_when_service_is_down(): void
    {
        config(['services.face_verification.when_unavailable' => 'block']);
        $this->setting();
        $this->employee(withFace: true);
        $this->openAttendance();
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->clockOut(photo: true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull(DB::table('attendances')->value('clock_out'));
    }

    // ───────────────────────────────────────────── pendaftaran foto wajah pertama

    public function test_first_registration_saves_a_good_photo_after_a_quality_check(): void
    {
        $this->setting();
        $this->employee(withFace: false);
        $this->analysisSays(['ok' => true, 'reason' => 'ok', 'message' => 'Foto selfie memenuhi syarat.']);

        $response = $this->register(self::PNG);

        $this->assertSame(200, $response->getStatusCode());
        $path = DB::table('employees')->where('id', 1)->value('face_photo');
        Storage::disk('public')->assertExists($path);
        // Belum ada foto lama, jadi tidak ada yang dicocokkan.
        Http::assertNotSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/verify'));
    }

    #[DataProvider('rejectedRegistrations')]
    public function test_first_registration_rejects_a_poor_photo(string $reason, string $message): void
    {
        $this->setting();
        $this->employee(withFace: false);
        $this->analysisSays(['ok' => false, 'reason' => $reason, 'message' => $message]);

        $response = $this->register(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($message, $response->getData(true)['message']);
        $this->assertSame($reason, $response->getData(true)['reason']);
        $this->assertNull(DB::table('employees')->where('id', 1)->value('face_photo'));
        $this->assertSame([], Storage::disk('public')->allFiles('employees/face-photos'));
    }

    public static function rejectedRegistrations(): array
    {
        return [
            'terlalu gelap' => ['too_dark', 'Pencahayaan kurang terang.'],
            'tanpa wajah' => ['no_face', 'Wajah tidak terdeteksi pada foto selfie.'],
            'banyak wajah' => ['multiple_faces', 'Terdeteksi lebih dari satu wajah.'],
            'terlalu jauh' => ['face_small', 'Wajah terlalu jauh dari kamera.'],
        ];
    }

    public function test_registration_of_something_that_is_not_an_image_is_rejected_before_asking_the_service(): void
    {
        $this->setting();
        $this->employee(withFace: false);
        Http::fake();

        $response = $this->register(base64_encode('ini bukan gambar'));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('invalid_image', $response->getData(true)['reason']);
        $this->assertNull(DB::table('employees')->where('id', 1)->value('face_photo'));
        Http::assertNothingSent();
    }

    public function test_first_registration_still_works_when_service_is_down_under_review_policy(): void
    {
        config(['services.face_verification.when_unavailable' => 'review']);
        $this->setting();
        $this->employee(withFace: false);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->register(self::PNG);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull(DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_first_registration_is_refused_when_service_is_down_under_block_policy(): void
    {
        config(['services.face_verification.when_unavailable' => 'block']);
        $this->setting();
        $this->employee(withFace: false);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->register(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData(true)['reason']);
        $this->assertNull(DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_first_registration_is_refused_by_default_when_the_face_service_is_down(): void
    {
        $this->setting();
        $this->employee(withFace: false);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->register(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData(true)['reason']);
        $this->assertSame(FaceVerificationService::UNAVAILABLE_REGISTRATION_MESSAGE, $response->getData(true)['message']);
        $this->assertStringContainsString('foto wajah belum bisa disimpan', $response->getData(true)['message']);
        $this->assertNull(DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_registration_is_not_analysed_when_face_verification_is_off(): void
    {
        $this->setting(['face_verification_enabled' => '0']);
        $this->employee(withFace: false);
        Http::fake();

        $response = $this->register(base64_encode('apa saja'));

        $this->assertSame(200, $response->getStatusCode());
        Http::assertNothingSent();
    }

    public function test_web_portal_first_registration_rejects_a_poor_photo(): void
    {
        $this->setting();
        $this->employee(withFace: false);
        $this->analysisSays(['ok' => false, 'reason' => 'too_dark', 'message' => 'Pencahayaan kurang terang.']);

        $response = $this->registerFromPortal(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Pencahayaan kurang terang.', $response->getData(true)['message']);
        $this->assertNull(DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_web_portal_first_registration_saves_a_good_photo(): void
    {
        $this->setting();
        $this->employee(withFace: false);
        $this->analysisSays(['ok' => true, 'reason' => 'ok', 'message' => 'Foto selfie memenuhi syarat.']);

        $response = $this->registerFromPortal('data:image/png;base64,'.self::PNG);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull(DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    // ───────────────────────────────────────────── mengganti foto: harus cocok dengan foto lama

    public function test_replacing_the_photo_with_the_same_face_is_accepted(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => true, 'similarity' => 0.88, 'distance' => 0.12, 'reason' => 'ok']);

        $response = $this->register(self::PNG);

        $this->assertSame(200, $response->getStatusCode());
        $path = DB::table('employees')->where('id', 1)->value('face_photo');
        $this->assertNotSame('employees/face-photos/ref.jpg', $path);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertMissing('employees/face-photos/ref.jpg');
    }

    public function test_the_new_photo_is_compared_against_the_registered_one(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => true, 'similarity' => 0.9, 'reason' => 'ok']);

        $this->register(self::PNG);

        Http::assertSent(function (HttpRequest $request) {
            return str_ends_with($request->url(), '/verify')
                && $request['selfie_base64'] === self::PNG
                && $request['reference_base64'] === base64_encode('reference-jpeg');
        });
    }

    public function test_replacing_with_someone_elses_face_is_refused_and_the_old_photo_stays(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays([
            'match' => false, 'similarity' => 0.3, 'distance' => 0.7, 'reason' => 'mismatch',
            'message' => 'Verifikasi wajah gagal. Wajah tidak sesuai dengan foto yang terdaftar.',
        ]);

        $response = $this->register(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('replace_mismatch', $response->getData(true)['reason']);
        $this->assertStringContainsString('minta HRD mereset', $response->getData(true)['message']);
        $this->assertSame('employees/face-photos/ref.jpg', DB::table('employees')->where('id', 1)->value('face_photo'));
        Storage::disk('public')->assertExists('employees/face-photos/ref.jpg');
        $this->assertCount(1, Storage::disk('public')->allFiles('employees/face-photos'));
    }

    #[DataProvider('rejectedRegistrations')]
    public function test_a_poor_replacement_photo_is_reported_for_what_it_is_not_as_a_mismatch(string $reason, string $message): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => false, 'similarity' => 0, 'reason' => $reason, 'message' => $message]);

        $response = $this->register(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($reason, $response->getData(true)['reason']);
        $this->assertSame($message, $response->getData(true)['message']);
        $this->assertSame('employees/face-photos/ref.jpg', DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_a_poor_registered_photo_does_not_lock_the_employee_out(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        // Foto lama gelap: ketidakcocokan tidak bisa dipercaya. Foto baru sudah lolos kualitas
        // (pencocokan baru jalan setelah cek kualitas), jadi boleh menggantikan.
        $this->serviceSays([
            'match' => false, 'similarity' => 0.4, 'reason' => 'mismatch',
            'reference_quality' => ['reason' => 'too_dark', 'brightness' => 30],
        ]);

        $response = $this->register(self::PNG);

        $this->assertSame(200, $response->getStatusCode());
        Storage::disk('public')->assertMissing('employees/face-photos/ref.jpg');
    }

    public function test_an_unusable_registered_photo_falls_back_to_a_quality_check_only(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        Http::fake([
            '*/verify' => Http::response(['match' => false, 'reason' => 'reference_invalid', 'message' => 'Foto wajah yang terdaftar tidak bisa dipakai.']),
            '*/analyze' => Http::response(['ok' => true, 'reason' => 'ok', 'message' => 'Foto selfie memenuhi syarat.']),
        ]);

        $this->assertSame(200, $this->register(self::PNG)->getStatusCode());
    }

    public function test_an_unusable_registered_photo_still_requires_a_good_new_photo(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        Http::fake([
            '*/verify' => Http::response(['match' => false, 'reason' => 'reference_invalid', 'message' => 'x']),
            '*/analyze' => Http::response(['ok' => false, 'reason' => 'too_dark', 'message' => 'Pencahayaan kurang terang.']),
        ]);

        $response = $this->register(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('too_dark', $response->getData(true)['reason']);
        $this->assertSame('employees/face-photos/ref.jpg', DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_a_registered_photo_whose_file_is_gone_does_not_lock_the_employee_out(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        Storage::disk('public')->delete('employees/face-photos/ref.jpg');
        $this->analysisSays(['ok' => true, 'reason' => 'ok', 'message' => 'Foto selfie memenuhi syarat.']);

        $this->assertSame(200, $this->register(self::PNG)->getStatusCode());
        Http::assertNotSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/verify'));
    }

    public function test_replacing_the_photo_is_refused_while_the_service_is_down(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $response = $this->register(self::PNG);

        // Tanpa layanan, kecocokan dengan foto lama tidak bisa dipastikan.
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('unavailable', $response->getData(true)['reason']);
        $this->assertSame('employees/face-photos/ref.jpg', DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_replacing_the_photo_while_the_service_is_down_is_allowed_only_under_allow_policy(): void
    {
        config(['services.face_verification.when_unavailable' => 'allow']);
        $this->setting();
        $this->employee(withFace: true);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->assertSame(200, $this->register(self::PNG)->getStatusCode());
    }

    public function test_replacing_is_not_checked_when_face_verification_is_off(): void
    {
        $this->setting(['face_verification_enabled' => '0']);
        $this->employee(withFace: true);
        Http::fake();

        $this->assertSame(200, $this->register(base64_encode('apa saja'))->getStatusCode());
        Http::assertNothingSent();
    }

    public function test_web_portal_replacement_applies_the_same_rule(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => false, 'similarity' => 0.3, 'reason' => 'mismatch']);

        $response = $this->registerFromPortal(self::PNG);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('replace_mismatch', $response->getData(true)['reason']);
        $this->assertSame('employees/face-photos/ref.jpg', DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_web_portal_replacement_with_the_same_face_is_saved(): void
    {
        $this->setting();
        $this->employee(withFace: true);
        $this->serviceSays(['match' => true, 'similarity' => 0.9, 'reason' => 'ok']);

        $this->assertSame(200, $this->registerFromPortal(self::PNG)->getStatusCode());
        Storage::disk('public')->assertMissing('employees/face-photos/ref.jpg');
    }

    // ───────────────────────────────────────────── menghapus foto tidak boleh jadi jalan pintas

    public function test_employee_cannot_delete_the_registered_photo_while_verification_is_on(): void
    {
        $this->setting();
        $this->employee(withFace: true);

        $response = $this->deletePhoto();

        // Hapus lalu daftar ulang melewati syarat "harus cocok dengan foto lama".
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('locked', $response->getData(true)['reason']);
        $this->assertStringContainsString('HRD', $response->getData(true)['message']);
        $this->assertSame('employees/face-photos/ref.jpg', DB::table('employees')->where('id', 1)->value('face_photo'));
        Storage::disk('public')->assertExists('employees/face-photos/ref.jpg');
    }

    public function test_employee_cannot_delete_from_the_web_portal_either(): void
    {
        $this->setting();
        $this->employee(withFace: true);

        $request = Request::create('/employee/face-photo', 'DELETE');
        $request->attributes->set('employee', Employee::findOrFail(1));
        $response = (new FacePhotoController())->destroy($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('employees/face-photos/ref.jpg', DB::table('employees')->where('id', 1)->value('face_photo'));
    }

    public function test_employee_can_delete_the_photo_when_verification_is_off(): void
    {
        $this->setting(['face_verification_enabled' => '0']);
        $this->employee(withFace: true);

        $response = $this->deletePhoto();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNull(DB::table('employees')->where('id', 1)->value('face_photo'));
        Storage::disk('public')->assertMissing('employees/face-photos/ref.jpg');
    }

    public function test_deleting_when_there_is_no_photo_is_harmless(): void
    {
        $this->setting();
        $this->employee(withFace: false);

        $this->assertSame(200, $this->deletePhoto()->getStatusCode());
    }

    // ───────────────────────────────────────────── helpers

    private function setting(array $overrides = []): void
    {
        $settings = array_merge([
            'require_photo' => '1',
            'require_gps' => '0',
            'face_verification_enabled' => '1',
            'office_latitude' => '1.0456',
            'office_longitude' => '104.0305',
            'office_radius_meters' => '100',
            'allow_remote_clockin' => '0',
            'max_gps_accuracy_meters' => '100',
        ], $overrides);

        foreach ($settings as $key => $value) {
            DB::table('settings')->insert(['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function employee(bool $withFace): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'employee_code' => 'EMP001',
            'full_name' => 'Employee One',
            'email' => 'employee@example.test',
            'password' => 'password',
            'role' => 'employee',
            'face_photo' => $withFace ? 'employees/face-photos/ref.jpg' : null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($withFace) {
            Storage::disk('public')->put('employees/face-photos/ref.jpg', 'reference-jpeg');
        }
    }

    private function adminEmployee(int $id, string $name, string $role): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'company_id' => 1,
            'employee_code' => 'ADM'.$id,
            'full_name' => $name,
            'email' => "admin{$id}@example.test",
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function openAttendance(array $flags = []): void
    {
        DB::table('attendances')->insert([
            'employee_id' => 1,
            'date' => '2026-05-26',
            'clock_in' => '08:00:00',
            'status' => 'present',
            'security_flags' => $flags ? json_encode($flags) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Layanan wajah menjawab /verify dengan putusan ini. */
    private function serviceSays(array $body): void
    {
        Http::fake(['*/verify' => Http::response($body)]);
    }

    private function clockIn(bool $photo, array $extra = [])
    {
        return $this->send('clockIn', '/api/attendance/clock-in', $photo, $extra);
    }

    private function clockOut(bool $photo, array $extra = [])
    {
        return $this->send('clockOut', '/api/attendance/clock-out', $photo, $extra);
    }

    private function send(string $action, string $uri, bool $photo, array $extra)
    {
        $request = Request::create($uri, 'POST', $extra + ($photo ? ['photo_base64' => base64_encode('selfie-jpeg')] : []));
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        return (new AttendanceController())->{$action}($request);
    }

    private function register(string $base64)
    {
        $request = Request::create('/api/profile/face-photo', 'POST', ['photo_base64' => $base64]);
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        return (new ProfileController())->uploadFacePhoto($request);
    }

    private function registerFromPortal(string $base64)
    {
        $request = Request::create('/employee/face-photo', 'POST', ['photo_base64' => $base64]);
        $request->attributes->set('employee', Employee::findOrFail(1));

        return (new FacePhotoController())->store($request);
    }

    private function deletePhoto()
    {
        $request = Request::create('/api/profile/face-photo', 'DELETE');
        $request->setUserResolver(fn () => Employee::findOrFail(1));

        return (new ProfileController())->deleteFacePhoto($request);
    }

    /** Layanan wajah menjawab /analyze (cek kualitas satu foto) dengan hasil ini. */
    private function analysisSays(array $body): void
    {
        Http::fake(['*/analyze' => Http::response($body)]);
    }
}
