<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jaring pengaman untuk API app: karyawan yang sudah tidak aktif (dinonaktifkan, resign, atau
 * diubah lewat jalur lain) ditolak walau token lamanya masih ada. Tokennya dicabut dan dibalas 401,
 * jadi app menghapus sesi dan kembali ke halaman login.
 */
class EnsureEmployeeActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Hanya status yang tegas nonaktif; kolom yang tidak terisi tidak membuat orang terlempar keluar.
        if ($user instanceof Employee && $user->is_active === false) {
            $user->revokeAppAccess();

            return response()->json([
                'success' => false,
                'message' => 'Akun anda tidak aktif.',
            ], 401);
        }

        return $next($request);
    }
}
