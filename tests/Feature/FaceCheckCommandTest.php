<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ReflectionProperty;
use Tests\TestCase;

/**
 * `php artisan face:check` menjawab "kenapa verifikasi wajah tidak menolak?" dari kondisi server:
 * pengaturan, layanan wajah (mati/versi lama), karyawan tanpa foto, dan kualitas foto terdaftar.
 */
class FaceCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new ReflectionProperty(Setting::class, 'punyaKolomCompany'))->setValue(null, null);
        Storage::fake('public');

        foreach (['settings', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('face_photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        config(['services.face_verification.when_unavailable' => 'block']);

        parent::tearDown();
    }

    public function test_reports_a_dead_service_and_that_attendance_is_refused_by_default(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $output = $this->runCheck();

        $this->assertStringContainsString('TIDAK BISA DIHUBUNGI', $output);
        $this->assertStringContainsString('clock in/out DITOLAK (kebijakan block)', $output);
        $this->assertMatchesRegularExpression('/Bila layanan mati: block \(presensi ditolak\)/', $output);
    }

    public function test_reports_that_review_policy_lets_attendance_in_without_face_matching(): void
    {
        config(['services.face_verification.when_unavailable' => 'review']);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $output = $this->runCheck();

        $this->assertStringContainsString('tetap masuk tetapi tanpa pencocokan wajah (kebijakan review)', $output);
        $this->assertMatchesRegularExpression('/Bila layanan mati: review \(presensi dicatat dan ditandai untuk HR\)/', $output);
    }

    public function test_warns_when_the_policy_lets_attendance_through_silently(): void
    {
        config(['services.face_verification.when_unavailable' => 'allow']);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->assertStringContainsString('DILOLOSKAN tanpa tanda', $this->runCheck());
    }

    public function test_reports_that_face_verification_is_switched_off(): void
    {
        DB::table('settings')->insert(['key' => 'face_verification_enabled', 'value' => '0', 'created_at' => now(), 'updated_at' => now()]);
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->assertStringContainsString('MATI', $this->runCheck());
    }

    public function test_detects_an_old_service_that_was_not_restarted_after_deploy(): void
    {
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'models_loaded' => true])]);

        $output = $this->runCheck();

        $this->assertStringContainsString('Layanan berversi lama', $output);
        $this->assertStringContainsString('systemctl restart face-service', $output);
        $this->assertStringContainsString('Versi            : 1', $output);
    }

    public function test_reports_a_healthy_current_service_with_its_limits(): void
    {
        $this->healthyService();

        $output = $this->runCheck();

        $this->assertStringContainsString('Terhubung        : ya', $output);
        $this->assertStringContainsString('Model siap       : ya', $output);
        $this->assertStringNotContainsString('berversi lama', $output);
        $this->assertStringContainsString('jarak < 0.5', $output);
        $this->assertStringContainsString('rata-rata >= 45', $output);
    }

    public function test_reports_a_service_whose_models_are_not_loaded_yet(): void
    {
        Http::fake(['*/health' => Http::response(['status' => 'ok', 'models_loaded' => false, 'version' => 2, 'limits' => []])]);

        $this->assertStringContainsString('Model siap       : BELUM', $this->runCheck());
    }

    public function test_counts_employees_without_a_face_photo_and_lists_missing_files(): void
    {
        $this->healthyService();
        $this->employee(1, 'Ada Foto', 'employees/face-photos/a.jpg', file: true);
        $this->employee(2, 'Foto Hilang', 'employees/face-photos/hilang.jpg', file: false);
        $this->employee(3, 'Belum Daftar', null);
        $this->employee(4, 'Sudah Keluar', null, active: false);

        $output = $this->runCheck();

        $this->assertMatchesRegularExpression('/Karyawan aktif   : 3/', $output);
        $this->assertMatchesRegularExpression('/Sudah terdaftar  : 2/', $output);
        $this->assertMatchesRegularExpression('/Belum terdaftar  : 1 /', $output);
        $this->assertMatchesRegularExpression('/File foto hilang : 1/', $output);
        $this->assertStringContainsString('Belum Daftar', $output);
        $this->assertStringNotContainsString('Sudah Keluar', $output);
        $this->assertStringContainsString('employees/face-photos/hilang.jpg', $output);
    }

    public function test_audit_lists_registered_photos_that_are_too_poor_to_trust(): void
    {
        $this->healthyService();
        Http::fake([
            '*/health' => Http::response(['status' => 'ok', 'models_loaded' => true, 'version' => 2, 'threshold' => 0.5, 'limits' => []]),
            '*/analyze' => function (HttpRequest $request) {
                return match (base64_decode($request['photo_base64'])) {
                    'foto-gelap' => Http::response(['ok' => false, 'reason' => 'too_dark', 'message' => 'gelap']),
                    'foto-ramai' => Http::response(['ok' => false, 'reason' => 'multiple_faces', 'message' => 'ramai']),
                    default => Http::response(['ok' => true, 'reason' => 'ok', 'message' => 'bagus']),
                };
            },
        ]);
        $this->employee(1, 'Foto Bagus', 'employees/face-photos/1.jpg', file: true, content: 'foto-bagus');
        $this->employee(2, 'Foto Gelap', 'employees/face-photos/2.jpg', file: true, content: 'foto-gelap');
        $this->employee(3, 'Foto Ramai', 'employees/face-photos/3.jpg', file: true, content: 'foto-ramai');

        $output = $this->runCheck(['--audit' => true]);

        $this->assertMatchesRegularExpression('/Diperiksa : 3 foto/', $output);
        $this->assertMatchesRegularExpression('/Bermasalah: 2/', $output);
        $this->assertMatchesRegularExpression('/Foto Gelap\s+\|\s+terlalu gelap/', $output);
        $this->assertMatchesRegularExpression('/Foto Ramai\s+\|\s+lebih dari satu wajah/', $output);
        $this->assertDoesNotMatchRegularExpression('/Foto Bagus\s+\|\s+(terlalu|lebih|wajah)/', $output);
    }

    public function test_audit_respects_the_limit(): void
    {
        $this->healthyService();
        Http::fake(['*/analyze' => Http::response(['ok' => true, 'reason' => 'ok', 'message' => 'bagus'])] + $this->healthFake());
        foreach (range(1, 5) as $id) {
            $this->employee($id, "Karyawan {$id}", "employees/face-photos/{$id}.jpg", file: true);
        }

        $output = $this->runCheck(['--audit' => true, '--limit' => 2]);

        $this->assertMatchesRegularExpression('/Diperiksa : 2 foto/', $output);
    }

    public function test_audit_cannot_run_when_the_service_is_down(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        $this->employee(1, 'Ada Foto', 'employees/face-photos/a.jpg', file: true);

        $this->assertStringContainsString('audit tidak bisa dijalankan', $this->runCheck(['--audit' => true]));
    }

    public function test_search_shows_one_employee_with_photo_state_and_quality(): void
    {
        Http::fake([
            '*/analyze' => Http::response(['ok' => false, 'reason' => 'too_dark', 'message' => 'gelap']),
        ] + $this->healthFake());
        $this->employee(1, 'Avissa Putri', 'employees/face-photos/1.jpg', file: true);
        $this->employee(2, 'Budi', 'employees/face-photos/2.jpg', file: true);

        $output = $this->runCheck(['--cari' => 'avissa']);

        $this->assertMatchesRegularExpression('/Avissa Putri\s+\|\s+ya\s+\|\s+ada\s+\|\s+terlalu gelap/', $output);
        $this->assertStringNotContainsString('Budi  ', $output);
    }

    public function test_search_with_no_match_says_so(): void
    {
        $this->healthyService();

        $this->assertStringContainsString('Tidak ada karyawan dengan nama itu', $this->runCheck(['--cari' => 'tidak-ada']));
    }

    public function test_the_check_only_reads_and_never_changes_data(): void
    {
        $this->healthyService();
        $this->employee(1, 'Ada Foto', 'employees/face-photos/a.jpg', file: true);
        $before = [DB::table('employees')->get()->toArray(), DB::table('settings')->get()->toArray()];

        $this->runCheck(['--audit' => true, '--cari' => 'ada']);

        $this->assertEquals($before, [DB::table('employees')->get()->toArray(), DB::table('settings')->get()->toArray()]);
        Storage::disk('public')->assertExists('employees/face-photos/a.jpg');
    }

    private function runCheck(array $options = []): string
    {
        Artisan::call('face:check', $options);

        return Artisan::output();
    }

    private function healthFake(): array
    {
        return ['*/health' => Http::response([
            'status' => 'ok', 'models_loaded' => true, 'version' => 2, 'threshold' => 0.5,
            'limits' => ['minBrightness' => 45, 'minHighlight' => 70],
        ])];
    }

    private function healthyService(): void
    {
        Http::fake($this->healthFake());
    }

    private function employee(int $id, string $name, ?string $photo, bool $file = false, bool $active = true, string $content = 'foto'): void
    {
        DB::table('employees')->insert([
            'id' => $id,
            'employee_code' => 'EMP'.$id,
            'full_name' => $name,
            'email' => "emp{$id}@example.test",
            'password' => 'password',
            'face_photo' => $photo,
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($photo && $file) {
            Storage::disk('public')->put($photo, $content);
        }
    }
}
