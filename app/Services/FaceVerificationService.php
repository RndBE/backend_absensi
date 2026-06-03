<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceVerificationService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.face_verification.url', 'http://127.0.0.1:5001');
        $this->timeout = (int) config('services.face_verification.timeout', 15);
    }

    /**
     * Bandingkan selfie absen dengan foto wajah terdaftar.
     *
     * @return array{match: bool, similarity: float, message: string}
     */
    public function verify(string $selfiePath, string $referencePath): array
    {
        try {
            $selfieB64    = base64_encode(Storage::disk('public')->get($selfiePath));
            $referenceB64 = base64_encode(Storage::disk('public')->get($referencePath));

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/verify", [
                    'selfie_base64'    => $selfieB64,
                    'reference_base64' => $referenceB64,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('[FaceVerification] Service error: ' . $response->status() . ' ' . $response->body());
            return $this->failOpen();

        } catch (\Throwable $e) {
            Log::warning('[FaceVerification] Service unavailable: ' . $e->getMessage());
            return $this->failOpen();
        }
    }

    /**
     * Cek apakah face service aktif.
     */
    public function isHealthy(): bool
    {
        try {
            $response = Http::timeout(3)->get("{$this->baseUrl}/health");
            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Jika service tidak bisa dihubungi, izinkan clock-in (fail-open)
     * agar absensi tidak lumpuh saat service down.
     */
    private function failOpen(): array
    {
        return [
            'match'      => true,
            'similarity' => 1.0,
            'message'    => 'Face service tidak tersedia, absensi diizinkan.',
        ];
    }
}
