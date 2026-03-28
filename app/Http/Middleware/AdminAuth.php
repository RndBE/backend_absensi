<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('admin_id')) {
            return redirect()->route('admin.login');
        }

        $admin = \App\Models\Employee::find(session('admin_id'));
        if (!$admin) {
            session()->forget('admin_id');
            return redirect()->route('admin.login')->with('error', 'Akses ditolak.');
        }

        view()->share('currentAdmin', $admin);
        return $next($request);
    }
}
