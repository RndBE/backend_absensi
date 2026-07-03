<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aktor Tessa (token per-user).
 *
 * Endpoint data & aksi Tessa dilindungi `auth:sanctum` lalu middleware ini. Token
 * diterbitkan lewat POST /tessa/session (login karyawan). Identitas TIDAK bisa
 * dipalsukan: aktor = pemilik token, dan kapabilitasnya mengikuti role HRIS orang itu
 * (dicek di controller lewat AdminPermission).
 *
 * Payroll/slip gaji tetap diblokir total, apa pun role-nya.
 */
class TessaActor
{
    public function handle(Request $request, Closure $next): Response
    {
        // Tessa selalu klien API: balas JSON, bukan redirect HTML.
        $request->headers->set('Accept', 'application/json');

        $user = $request->user();

        if (! $user instanceof Employee) {
            return response()->json([
                'success' => false,
                'message' => 'Token Tessa tidak valid. Login dulu di /tessa/session.',
            ], 401);
        }

        // Token harus untuk Tessa (ability "tessa"; token ber-scope "*" juga lolos).
        if (method_exists($user, 'tokenCan') && ! $user->tokenCan('tessa')) {
            return response()->json([
                'success' => false,
                'message' => 'Token ini bukan untuk Tessa.',
            ], 403);
        }

        if (! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Akun karyawan tidak aktif.',
            ], 403);
        }

        // Pertahanan berlapis: blokir apa pun yang berbau payroll/gaji.
        $path = strtolower($request->path());
        foreach (['payroll', 'payslip', 'gaji', 'salary'] as $forbidden) {
            if (str_contains($path, $forbidden)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses payroll tidak diizinkan untuk Tessa.',
                ], 403);
            }
        }

        return $next($request);
    }
}
