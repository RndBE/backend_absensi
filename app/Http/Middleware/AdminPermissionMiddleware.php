<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Support\AdminPermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $admin = Employee::find(session('admin_id'));
        if (!$admin) {
            return response('Akses ditolak.', 403);
        }

        if (empty($permissions)) {
            $permission = app(AdminPermission::class)->permissionForRoute($request->route()?->getName());
            $permissions = $permission ? [$permission] : [];
        }

        if ($permissions && !app(AdminPermission::class)->canAny($admin, $permissions)) {
            // Banyak tautan (logo, tombol kembali, halaman 403) menuju dashboard. Admin tanpa izin
            // dashboard dialihkan ke halaman pertama yang boleh ia buka, bukan diberi 403.
            if ($request->route()?->getName() === 'admin.dashboard' && $request->isMethod('GET')) {
                $home = app(AdminPermission::class)->homeRoute($admin);
                if ($home && $home !== 'admin.dashboard') {
                    return redirect()->route($home);
                }
            }

            // Halaman biasa → tampilkan halaman 403 bergaya admin; API/AJAX → JSON.
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki permission untuk mengakses fitur ini.'], 403);
            }

            return response()->view('admin.errors.403', [], 403);
        }

        return $next($request);
    }
}
