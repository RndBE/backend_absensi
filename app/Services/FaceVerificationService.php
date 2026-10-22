<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceVerificationService
{
    /** Kebijakan bila layanan wajah tidak bisa dihubungi. */
    public const WHEN_UNAVAILABLE_REVIEW = 'review';
    public const WHEN_UNAVAILABLE_BLOCK = 'block';
    public const WHEN_UNAVAILABLE_ALLOW = 'allow';

    public const ACCEPT = 'accept';
    public const REJECT = 'reject';
    public const REVIEW = 'review';

    /** Presensi ditolak karena wajah belum bisa diverifikasi. Ditampilkan apa adanya di aplikasi. */
    public const UNAVAILABLE_MESSAGE = 'Verifikasi wajah belum bisa dijalankan karena layanan wajah sedang tidak tersedia, '
        .'jadi presensi belum dicatat. Coba lagi beberapa saat lagi, atau hubungi HRD bila terus terjadi.';

    /** Foto wajah belum bisa dicek/disimpan karena layanan wajah tidak tersedia. */
    public const UNAVAILABLE_REGISTRATION_MESSAGE = 'Layanan verifikasi wajah sedang tidak tersedia, '
        .'jadi foto wajah belum bisa disimpan. Coba lagi beberapa saat lagi, atau hubungi HRD bila terus terjadi.';

    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.face_verification.url', 'http://127.0.0.1:5001');
        $this->timeout = (int) config('services.face_verification.timeout', 15);
    }

    /** Verifikasi wajah dinyalakan di Pengaturan Presensi. */
    public static function enabled(): bool
    {
        return Setting::getValue('face_verification_enabled', '1') === '1';
    }

    /**
     * Apa yang dilakukan bila layanan wajah mati. Bawaannya "block": presensi ditolak, karena
     * kalau tidak siapa pun bisa clock in atas nama orang lain selama layanan mati. "review"
     * mencatat presensi tetapi menandainya untuk HR; "allow" meloloskan tanpa tanda (tidak aman).
     * Nilai yang tidak dikenal diperlakukan sebagai "block" (gagal tertutup).
     */
    public static function whenUnavailable(): string
    {
        $policy = strtolower((string) config('services.face_verification.when_unavailable', self::WHEN_UNAVAILABLE_BLOCK));

        return in_array($policy, [self::WHEN_UNAVAILABLE_REVIEW, self::WHEN_UNAVAILABLE_ALLOW], true)
            ? $policy
            : self::WHEN_UNAVAILABLE_BLOCK;
    }

    /**
     * Bandingkan selfie absen dengan foto wajah terdaftar. Selfie yang gelap, tanpa wajah,
     * berisi lebih dari satu wajah, atau terlalu jauh ditolak sebelum dibandingkan.
     *
     * @return array{match: bool, similarity: float, distance: ?float, message: string, reason: string, available: bool, quality: ?array, reference_quality: ?array}
     */
    public function verify(string $selfiePath, string $referencePath): array
    {
        $disk = Storage::disk('public');

        if (!$disk->exists($referencePath)) {
            return $this->missingReference();
        }

        return $this->compare($disk->get($selfiePath), $disk->get($referencePath));
    }

    /**
     * Sama seperti [verify], tetapi dari isi foto (belum disimpan ke disk).
     *
     * @return array{match: bool, similarity: float, distance: ?float, message: string, reason: string, available: bool, quality: ?array, reference_quality: ?array}
     */
    public function compare(string $selfieData, string $referenceData): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/verify", [
                    'selfie_base64' => base64_encode($selfieData),
                    'reference_base64' => base64_encode($referenceData),
                ]);

            $body = $response->json();

            // Hanya jawaban 200 yang berisi putusan. 4xx/5xx (model belum siap, error internal)
            // berarti pemeriksaan tidak berjalan, bukan "wajah tidak cocok".
            if ($response->successful() && is_array($body) && array_key_exists('match', $body)) {
                $this->warnIfLegacy($body);

                return $this->verdict($body);
            }

            Log::warning('[FaceVerification] Service error: '.$response->status().' '.$response->body());
        } catch (\Throwable $e) {
            Log::warning('[FaceVerification] Service unavailable: '.$e->getMessage());
        }

        return $this->unavailable();
    }

    /**
     * Periksa kualitas satu foto (satu wajah, cukup terang, tidak terlalu jauh).
     * Dipakai saat mendaftarkan foto wajah.
     *
     * @return array{ok: bool, available: bool, reason: string, message: string, quality: ?array}
     */
    public function analyze(string $imageData): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/analyze", ['photo_base64' => base64_encode($imageData)]);

            $body = $response->json();

            if ($response->successful() && is_array($body) && array_key_exists('ok', $body)) {
                return [
                    'ok' => (bool) $body['ok'],
                    'available' => true,
                    'reason' => (string) ($body['reason'] ?? ($body['ok'] ? 'ok' : 'rejected')),
                    'message' => (string) ($body['message'] ?? ''),
                    'quality' => is_array($body['quality'] ?? null) ? $body['quality'] : null,
                ];
            }

            Log::warning('[FaceVerification] Analyze error: '.$response->status().' '.$response->body());
        } catch (\Throwable $e) {
            Log::warning('[FaceVerification] Analyze unavailable: '.$e->getMessage());
        }

        return [
            'ok' => false,
            'available' => false,
            'reason' => 'unavailable',
            'message' => self::UNAVAILABLE_REGISTRATION_MESSAGE,
            'quality' => null,
        ];
    }

    /**
     * Masalah pada foto yang mau didaftarkan sebagai wajah acuan, atau null bila boleh disimpan.
     * Foto acuan yang gelap, tanpa wajah, atau berisi banyak orang membuat verifikasi tidak
     * berarti, jadi ditolak di depan. Tanpa pengecekan bila verifikasi wajah dimatikan.
     *
     * @return array{reason: string, message: string}|null
     */
    public function registrationProblem(string $imageData): ?array
    {
        if (!self::enabled()) {
            return null;
        }

        if ($imageData === '' || @getimagesizefromstring($imageData) === false) {
            return ['reason' => 'invalid_image', 'message' => 'Foto wajah tidak valid.'];
        }

        $result = $this->analyze($imageData);

        if (!$result['available']) {
            // Pendaftaran tidak bisa "ditandai untuk diperiksa", jadi hanya kebijakan block yang menolak.
            return self::whenUnavailable() === self::WHEN_UNAVAILABLE_BLOCK
                ? ['reason' => 'unavailable', 'message' => self::UNAVAILABLE_REGISTRATION_MESSAGE]
                : null;
        }

        return $result['ok']
            ? null
            : ['reason' => $result['reason'], 'message' => $result['message']];
    }

    /**
     * Masalah pada foto yang mau MENGGANTIKAN foto wajah terdaftar, atau null bila boleh.
     *
     * Tanpa ini siapa pun bisa mendaftarkan wajah orang lain di akunnya lalu temannya clock in
     * atas namanya. Foto pengganti harus lolos kualitas dan cocok dengan foto yang sekarang
     * terdaftar.
     *
     * Dua keadaan tetap boleh mengganti tanpa mencocokkan, supaya tidak ada yang terkunci:
     * foto lama tidak bisa dipakai (hilang, rusak, tanpa wajah) dan foto lama kualitasnya buruk
     * (data lama yang gelap dsb.), sehingga pencocokannya sendiri tidak andal.
     *
     * @return array{reason: string, message: string}|null
     */
    public function replacementProblem(string $imageData, ?string $currentPath): ?array
    {
        if (!self::enabled()) {
            return null;
        }

        if ($imageData === '' || @getimagesizefromstring($imageData) === false) {
            return ['reason' => 'invalid_image', 'message' => 'Foto wajah tidak valid.'];
        }

        $disk = Storage::disk('public');

        // Pendaftaran pertama (atau file lama hilang): hanya kualitas foto yang diperiksa.
        if (!$currentPath || !$disk->exists($currentPath)) {
            return $this->registrationProblem($imageData);
        }

        $result = $this->compare($imageData, $disk->get($currentPath));

        if (!$result['available']) {
            return self::whenUnavailable() === self::WHEN_UNAVAILABLE_ALLOW
                ? null
                : ['reason' => 'unavailable', 'message' => self::UNAVAILABLE_REGISTRATION_MESSAGE];
        }

        if ($result['match']) {
            return null;
        }

        // Foto lama tidak bisa dipakai pembanding: periksa kualitas foto baru saja.
        if ($result['reason'] === 'reference_invalid') {
            return $this->registrationProblem($imageData);
        }

        // Foto baru sendiri bermasalah (gelap, tanpa wajah, banyak wajah, terlalu jauh, ...).
        if ($result['reason'] !== 'mismatch') {
            return ['reason' => $result['reason'], 'message' => $result['message']];
        }

        // Foto lama buruk: ketidakcocokan tidak bisa dipercaya, jangan mengunci karyawan.
        // Foto baru sudah lolos kualitas karena pencocokan baru dijalankan sesudahnya.
        if ($result['reference_quality'] !== null) {
            return null;
        }

        return [
            'reason' => 'replace_mismatch',
            'message' => 'Wajah pada foto baru tidak sama dengan foto yang terdaftar. '
                .'Bila wajah Anda memang berubah atau foto lama salah, minta HRD mereset foto wajah Anda.',
        ];
    }

    /** Terima, tolak, atau terima-dengan-pemeriksaan-HR untuk hasil verify(). */
    public function outcome(array $result): string
    {
        if ($result['available']) {
            return $result['match'] ? self::ACCEPT : self::REJECT;
        }

        return match (self::whenUnavailable()) {
            self::WHEN_UNAVAILABLE_BLOCK => self::REJECT,
            self::WHEN_UNAVAILABLE_ALLOW => self::ACCEPT,
            default => self::REVIEW,
        };
    }

    /** Jawaban /health layanan, atau null bila tidak bisa dihubungi. */
    public function health(): ?array
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");

            return $response->successful() && is_array($response->json()) ? $response->json() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Cek apakah face service aktif.
     */
    public function isHealthy(): bool
    {
        return $this->health() !== null;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    private function verdict(array $body): array
    {
        $match = (bool) ($body['match'] ?? false);

        return [
            'match' => $match,
            'similarity' => (float) ($body['similarity'] ?? 0),
            'distance' => isset($body['distance']) ? (float) $body['distance'] : null,
            'message' => (string) ($body['message'] ?? ''),
            'reason' => (string) ($body['reason'] ?? ($match ? 'ok' : 'mismatch')),
            'available' => true,
            'quality' => is_array($body['quality'] ?? null) ? $body['quality'] : null,
            'reference_quality' => is_array($body['reference_quality'] ?? null) ? $body['reference_quality'] : null,
        ];
    }

    private function missingReference(): array
    {
        return $this->verdict([
            'match' => false,
            'reason' => 'reference_invalid',
            'message' => 'Foto wajah yang terdaftar tidak ditemukan. Daftarkan ulang di menu Akun, lalu Verifikasi Wajah.',
        ]);
    }

    private function unavailable(): array
    {
        return [
            'match' => false,
            'similarity' => 0.0,
            'distance' => null,
            'message' => self::UNAVAILABLE_MESSAGE,
            'reason' => 'unavailable',
            'available' => false,
            'quality' => null,
            'reference_quality' => null,
        ];
    }

    /** Layanan lama (belum di-restart setelah deploy) tidak mengirim `quality`. */
    private function warnIfLegacy(array $body): void
    {
        if (!array_key_exists('quality', $body)) {
            Log::warning('[FaceVerification] Layanan wajah versi lama: tidak ada pengecekan cahaya. Restart face-service.');
        }
    }
}
