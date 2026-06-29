<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi untuk AI kantor "Tessa".
 *
 * Memvalidasi API key statis (Bearer / X-Api-Key) terhadap config services.tessa.api_key,
 * lalu menyetel scope perusahaan ke request. Sebagai pertahanan berlapis, middleware ini
 * juga MENOLAK akses ke segala hal yang berbau payroll/slip gaji — meskipun route Tessa
 * memang tidak pernah mendaftarkannya.
 */
class TessaApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.tessa.api_key');

        if (empty($configured)) {
            return response()->json([
                'success' => false,
                'message' => 'Integrasi Tessa belum dikonfigurasi (TESSA_API_KEY kosong).',
            ], 503);
        }

        $provided = $request->bearerToken() ?: $request->header('X-Api-Key');

        if (empty($provided) || ! hash_equals((string) $configured, (string) $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'API key tidak valid.',
            ], 401);
        }

        // Pertahanan berlapis: blokir apa pun yang berkaitan dengan payroll/gaji.
        $path = strtolower($request->path());
        foreach (['payroll', 'payslip', 'gaji', 'salary'] as $forbidden) {
            if (str_contains($path, $forbidden)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses payroll tidak diizinkan untuk Tessa.',
                ], 403);
            }
        }

        // Scope perusahaan (opsional). Null = semua perusahaan.
        $companyId = config('services.tessa.company_id');
        $request->attributes->set('tessa_company_id', $companyId ? (int) $companyId : null);

        return $next($request);
    }
}
