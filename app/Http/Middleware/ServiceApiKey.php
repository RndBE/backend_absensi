<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi service-to-service untuk sistem lain yang membaca data HRIS
 * (saat ini: inventory). Bukan untuk aplikasi karyawan — itu pakai auth:sanctum.
 *
 * Kunci dibaca lewat config(), BUKAN env(). env() mengembalikan null begitu
 * `php artisan config:cache` dijalankan, dan pembanding yang naif akan
 * meloloskan request tanpa header sama sekali. Karena itu kunci kosong di sini
 * dijawab 503 — gagal tertutup, bukan terbuka.
 */
class ServiceApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.hris_api.key');

        if (empty($configured)) {
            return response()->json([
                'success' => false,
                'message' => 'API service HRIS belum dikonfigurasi (HRIS_API_KEY kosong).',
            ], 503);
        }

        $provided = $request->header('X-API-KEY') ?: $request->bearerToken();

        if (empty($provided) || ! hash_equals((string) $configured, (string) $provided)) {
            // Pesannya sengaja spesifik. Route yang tidak ada membalas 404 dari
            // Laravel, jadi 401 di sini pasti soal kunci — tidak perlu menebak
            // seperti waktu men-debug sisi inventory.
            return response()->json([
                'success' => false,
                'message' => 'X-API-KEY tidak valid.',
            ], 401);
        }

        // Pemanggil selalu klien API: pastikan error dibalas JSON, bukan redirect HTML.
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
