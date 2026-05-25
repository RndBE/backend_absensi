<?php

namespace App\Services;

use App\Models\AdminPermission;
use App\Models\AdminRolePermission;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminPermissionService
{
    public static function syncDefaults(bool $seedAdminDefaults = false): void
    {
        $now = now();

        foreach (config('admin_permissions.permissions', []) as $permission) {
            AdminPermission::updateOrCreate(
                ['key' => $permission['key']],
                [
                    'group' => $permission['group'],
                    'name' => $permission['name'],
                    'description' => $permission['description'] ?? null,
                ]
            );
        }

        if (!$seedAdminDefaults && AdminRolePermission::where('role', 'admin')->exists()) {
            return;
        }

        $keys = collect(config('admin_permissions.admin_default_permissions', []));
        $permissionIds = AdminPermission::whereIn('key', $keys)->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('admin_role_permissions')->updateOrInsert(
                ['role' => 'admin', 'admin_permission_id' => $permissionId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public static function groupedPermissions(): Collection
    {
        return AdminPermission::orderBy('group')
            ->orderBy('key')
            ->get()
            ->groupBy('group');
    }

    public static function rolePermissionKeys(string $role): Collection
    {
        return AdminRolePermission::query()
            ->where('role', $role)
            ->join('admin_permissions', 'admin_permissions.id', '=', 'admin_role_permissions.admin_permission_id')
            ->pluck('admin_permissions.key');
    }

    public static function employeeHas(Employee $employee, ?string $permission): bool
    {
        if (!$permission || $employee->role === 'superadmin') {
            return true;
        }

        if (!in_array($employee->role, ['admin', 'superadmin'], true)) {
            return false;
        }

        $keys = self::rolePermissionKeys($employee->role);
        if ($keys->contains($permission)) {
            return true;
        }

        if (Str::endsWith($permission, '.view')) {
            return $keys->contains(Str::beforeLast($permission, '.') . '.manage');
        }

        return false;
    }

    public static function permissionForRoute(?string $routeName, string $method): ?string
    {
        if (!$routeName || in_array($routeName, ['admin.dashboard', 'admin.logout'], true)) {
            return null;
        }

        if (Str::is('admin.role-permissions.*', $routeName)) {
            return 'permissions.manage';
        }

        if (Str::is('admin.audit-logs.*', $routeName)) {
            return 'audit.view';
        }

        $module = self::moduleForRoute($routeName);
        if (!$module) {
            return null;
        }

        if ($module === 'reports') {
            return 'reports.view';
        }

        if ($module === 'settings') {
            return 'settings.manage';
        }

        return in_array(strtoupper($method), ['GET', 'HEAD'], true)
            ? "{$module}.view"
            : "{$module}.manage";
    }

    private static function moduleForRoute(string $routeName): ?string
    {
        $modules = [
            'employees' => ['admin.employees.*', 'admin.departments.*'],
            'attendance' => ['admin.attendance.*', 'admin.attendance-recap.*'],
            'leave' => ['admin.leaves.*', 'admin.leave-policies.*', 'admin.leave-balances.*'],
            'budget' => ['admin.budget-requests.*', 'admin.budget-payments.*', 'admin.travel-reports.*', 'admin.policies.*', 'admin.travel-zones.*'],
            'schedule' => ['admin.schedules.*', 'admin.shifts.*', 'admin.schedule-templates.*', 'admin.holidays.*'],
            'payroll' => ['admin.payroll-components.*', 'admin.employee-payrolls.*', 'admin.payroll-runs.*', 'admin.payslips.*', 'admin.payroll-adjustments.*'],
            'tax' => ['admin.tax.*'],
            'approval' => ['admin.approvals.*', 'admin.approval-rules.*'],
            'reports' => ['admin.reports.*'],
            'settings' => ['admin.company.*', 'admin.attendance-settings.*'],
        ];

        foreach ($modules as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $module;
                }
            }
        }

        return null;
    }
}
