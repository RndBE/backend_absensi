<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Services\AdminPermissionService;
use Closure;
use Illuminate\Http\Request;

class AdminPermission
{
    public function handle(Request $request, Closure $next)
    {
        $admin = Employee::find(session('admin_id'));
        $required = AdminPermissionService::permissionForRoute(
            $request->route()?->getName(),
            $request->method()
        );

        if ($admin && !$admin->hasAdminPermission($required)) {
            abort(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}
