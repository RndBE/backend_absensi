<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Employee;
use App\Support\AdminPermission;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('admin_id')) {
            return redirect()->route('admin.login');
        }

        $admin = Employee::find(session('admin_id'));
        if (!$admin || !app(AdminPermission::class)->isAdminUser($admin)) {
            session()->forget('admin_id');
            return redirect()->route('admin.login')->with('error', 'Akses ditolak.');
        }

        view()->share('currentAdmin', $admin);
        return $next($request);
    }
}
