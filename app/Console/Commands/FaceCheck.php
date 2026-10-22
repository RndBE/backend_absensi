<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Setting;
use App\Services\FaceVerificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Menjawab "kenapa verifikasi wajah clock in/out tidak menolak?" dari kondisi server.
 * Hanya membaca; tidak mengubah data.
 *
 *   php artisan face:check
 *   php artisan face:check --cari=avissa
 *   php artisan face:check --audit          (cek kualitas foto terdaftar semua karyawan aktif)
 */
class FaceCheck extends Command
{
    /** Versi minimal face-service yang sudah punya cek cahaya, wajah ganda, dan jarak. */
    private const MIN_SERVICE_VERSION = 2;

    protected $signature = 'face:check
        {--cari= : Nama karyawan yang ingin diperiksa}
        {--audit : Periksa kualitas foto wajah terdaftar semua karyawan aktif lewat layanan wajah}
        {--limit=300 : Batas jumlah foto yang diperiksa pada --audit}';

    protected $description = 'Periksa pengaturan, layanan, dan foto terdaftar verifikasi wajah. Hanya membaca.';

    public function handle(FaceVerificationService $service): int
    {
        $health = $this->section1($service);
        $this->section2($health);
        $this->section3();

        if ($search = trim((string) $this->option('cari'))) {
            $this->search($service, $search, $health !== null);
        }

        if ($this->option('audit')) {
            $this->audit($service, $health !== null);
        }

        return self::SUCCESS;
    }

    /** @return array|null jawaban /health, null bila layanan mati */
    private function section1(FaceVerificationService $service): ?array
    {
        $this->info('1. Pengaturan');
        $enabled = FaceVerificationService::enabled();
        $this->line('   Verifikasi wajah : '.($enabled ? 'AKTIF' : 'MATI (Pengaturan Presensi > Verifikasi Wajah) - tidak ada pencocokan sama sekali'));
        $this->line('   Wajib foto absen : '.(Setting::getValue('require_photo', '1') === '1' ? 'ya' : 'tidak'));
        $this->line('   Alamat layanan   : '.$service->baseUrl());
        $this->line('   Bila layanan mati: '.$this->policyLabel(FaceVerificationService::whenUnavailable()));
        $this->newLine();

        return $service->health();
    }

    private function section2(?array $health): void
    {
        $this->info('2. Layanan wajah (face-service)');

        if ($health === null) {
            $this->error('   TIDAK BISA DIHUBUNGI. '.match (FaceVerificationService::whenUnavailable()) {
                FaceVerificationService::WHEN_UNAVAILABLE_BLOCK => 'Selama ini mati, clock in/out DITOLAK (kebijakan block).',
                FaceVerificationService::WHEN_UNAVAILABLE_REVIEW => 'Selama ini mati, clock in/out tetap masuk tetapi tanpa pencocokan wajah (kebijakan review).',
                default => 'Selama ini mati, clock in/out DILOLOSKAN tanpa pencocokan wajah (kebijakan allow).',
            });
            $this->line('   Cek: systemctl status face-service  |  journalctl -u face-service -n 50');
            $this->newLine();

            return;
        }

        $loaded = ($health['models_loaded'] ?? false) === true;
        $version = (int) ($health['version'] ?? 1);
        $this->line('   Terhubung        : ya');
        $this->line('   Model siap       : '.($loaded ? 'ya' : 'BELUM (masih memuat, atau gagal memuat)'));
        $this->line('   Versi            : '.$version);

        if ($version < self::MIN_SERVICE_VERSION) {
            $this->warn('   Layanan berversi lama: belum menolak foto gelap, wajah ganda, atau wajah terlalu jauh.');
            $this->line('   Deploy folder face-service lalu: systemctl restart face-service');
        } else {
            $limits = (array) ($health['limits'] ?? []);
            $this->line('   Batas pencocokan : jarak < '.($health['threshold'] ?? '?'));
            $this->line('   Batas cahaya     : rata-rata >= '.($limits['minBrightness'] ?? '?').', bagian terang >= '.($limits['minHighlight'] ?? '?'));
        }

        $this->newLine();
    }

    private function section3(): void
    {
        $this->info('3. Foto wajah terdaftar (karyawan aktif)');

        $active = Employee::query()->where('is_active', true)->orderBy('id')->get(['id', 'full_name', 'face_photo']);
        $withPhoto = $active->filter(fn (Employee $e) => $e->face_photo);
        $without = $active->reject(fn (Employee $e) => $e->face_photo);
        $missing = $withPhoto->reject(fn (Employee $e) => Storage::disk('public')->exists($e->face_photo));

        $this->line('   Karyawan aktif   : '.$active->count());
        $this->line('   Sudah terdaftar  : '.$withPhoto->count());
        $this->line('   Belum terdaftar  : '.$without->count().' (tidak bisa clock in selama verifikasi aktif)');
        $this->line('   File foto hilang : '.$missing->count());

        if ($without->isNotEmpty()) {
            $this->line('   Belum terdaftar  : '.$without->take(15)->pluck('full_name')->implode(', ').($without->count() > 15 ? ', ...' : ''));
        }

        if ($missing->isNotEmpty()) {
            $this->table(['ID', 'Nama', 'Path foto'], $missing->map(fn (Employee $e) => [$e->id, $e->full_name, $e->face_photo])->all());
        }

        $this->newLine();
    }

    private function search(FaceVerificationService $service, string $search, bool $serviceUp): void
    {
        $this->info("4. Pencarian \"{$search}\"");
        $found = Employee::query()->where('full_name', 'like', "%{$search}%")->orderBy('id')->get();

        if ($found->isEmpty()) {
            $this->warn('   Tidak ada karyawan dengan nama itu.');
            $this->newLine();

            return;
        }

        $this->table(
            ['ID', 'Nama', 'Aktif', 'Foto terdaftar', 'Kualitas foto'],
            $found->map(fn (Employee $e) => [
                $e->id,
                $e->full_name,
                $e->is_active ? 'ya' : 'tidak',
                $e->face_photo ? (Storage::disk('public')->exists($e->face_photo) ? 'ada' : 'FILE HILANG') : 'belum',
                $this->quality($service, $e, $serviceUp),
            ])->all(),
        );
        $this->newLine();
    }

    private function audit(FaceVerificationService $service, bool $serviceUp): void
    {
        $this->info('5. Audit kualitas foto terdaftar');

        if (!$serviceUp) {
            $this->warn('   Layanan wajah mati, audit tidak bisa dijalankan.');

            return;
        }

        $limit = max(1, (int) $this->option('limit'));
        $employees = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('face_photo')
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'full_name', 'face_photo']);

        $bad = [];
        $bar = $this->output->createProgressBar($employees->count());
        foreach ($employees as $employee) {
            $result = $this->quality($service, $employee, true);
            if ($result !== 'baik') {
                $bad[] = [$employee->id, $employee->full_name, $result];
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->line('   Diperiksa : '.$employees->count().' foto');
        $this->line('   Bermasalah: '.count($bad));

        if ($bad) {
            $this->table(['ID', 'Nama', 'Masalah'], $bad);
            $this->line('   Minta mereka mendaftarkan ulang di menu Akun > Verifikasi Wajah. Foto seperti ini membuat pencocokan tidak andal.');
        }
    }

    /** "baik" atau alasan foto terdaftar tidak layak; "-" bila tidak bisa diperiksa. */
    private function quality(FaceVerificationService $service, Employee $employee, bool $serviceUp): string
    {
        if (!$employee->face_photo) {
            return '-';
        }

        if (!Storage::disk('public')->exists($employee->face_photo)) {
            return 'file hilang';
        }

        if (!$serviceUp) {
            return '- (layanan mati)';
        }

        $result = $service->analyze(Storage::disk('public')->get($employee->face_photo));

        if (!$result['available']) {
            return '- (layanan tidak menjawab)';
        }

        if ($result['ok']) {
            return 'baik';
        }

        return match ($result['reason']) {
            'too_dark' => 'terlalu gelap',
            'too_bright' => 'terlalu silau',
            'no_face' => 'wajah tidak terdeteksi',
            'multiple_faces' => 'lebih dari satu wajah',
            'face_small' => 'wajah terlalu kecil',
            'invalid_image' => 'file bukan gambar',
            default => $result['reason'],
        };
    }

    private function policyLabel(string $policy): string
    {
        return match ($policy) {
            FaceVerificationService::WHEN_UNAVAILABLE_REVIEW => 'review (presensi dicatat dan ditandai untuk HR)',
            FaceVerificationService::WHEN_UNAVAILABLE_ALLOW => 'allow (DILOLOSKAN tanpa tanda - tidak aman)',
            default => 'block (presensi ditolak)',
        };
    }
}
