<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeePermissionOverride;
use App\Models\RolePermission;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminPermission
{
    public function groupedPermissions(): array
    {
        return config('admin_permissions.groups', []);
    }

    public function allPermissions(): array
    {
        return Arr::flatten(array_map(fn ($items) => array_keys($items), $this->groupedPermissions()));
    }

    public function can(Employee $employee, string $permission): bool
    {
        if ($employee->role === 'superadmin') {
            return true;
        }

        $override = $this->employeeOverride($employee->id, $permission);
        if ($override !== null) {
            return $override;
        }

        $roleValue = $this->rolePermission($employee->role, $permission);
        if ($roleValue !== null) {
            return $roleValue;
        }

        return in_array($permission, config("admin_permissions.defaults.{$employee->role}", []), true)
            || in_array('*', config("admin_permissions.defaults.{$employee->role}", []), true);
    }

    public function canAny(Employee $employee, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->can($employee, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function permissionForRoute(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        foreach (config('admin_permissions.route_permissions', []) as $pattern => $permission) {
            if (Str::is($pattern, $routeName)) {
                return $permission;
            }
        }

        return null;
    }

    public function roleState(string $role): array
    {
        $permissions = array_fill_keys($this->allPermissions(), false);
        foreach (config("admin_permissions.defaults.{$role}", []) as $permission) {
            if ($permission === '*') {
                return array_fill_keys($this->allPermissions(), true);
            }
            $permissions[$permission] = true;
        }

        if (Schema::hasTable('role_permissions')) {
            RolePermission::where('role', $role)->get()->each(function (RolePermission $item) use (&$permissions) {
                $permissions[$item->permission] = $item->allowed;
            });
        }

        return $permissions;
    }

    public function updateRole(string $role, array $allowedPermissions): void
    {
        $allowed = array_flip($allowedPermissions);

        foreach ($this->allPermissions() as $permission) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission' => $permission],
                ['allowed' => array_key_exists($permission, $allowed)]
            );
        }
    }

    public function overridesForEmployee(Employee $employee): array
    {
        if (!Schema::hasTable('employee_permission_overrides')) {
            return [];
        }

        return EmployeePermissionOverride::where('employee_id', $employee->id)
            ->pluck('allowed', 'permission')
            ->map(fn ($allowed) => $allowed ? 'allow' : 'deny')
            ->all();
    }

    public function updateEmployeeOverrides(Employee $employee, array $states): void
    {
        foreach ($this->allPermissions() as $permission) {
            $state = $states[$permission] ?? 'inherit';
            if ($state === 'inherit') {
                EmployeePermissionOverride::where('employee_id', $employee->id)
                    ->where('permission', $permission)
                    ->delete();
                continue;
            }

            EmployeePermissionOverride::updateOrCreate(
                ['employee_id' => $employee->id, 'permission' => $permission],
                ['allowed' => $state === 'allow']
            );
        }
    }

    private function employeeOverride(int $employeeId, string $permission): ?bool
    {
        if (!Schema::hasTable('employee_permission_overrides')) {
            return null;
        }

        $override = EmployeePermissionOverride::where('employee_id', $employeeId)
            ->where('permission', $permission)
            ->first();

        return $override?->allowed;
    }

    private function rolePermission(string $role, string $permission): ?bool
    {
        if (!Schema::hasTable('role_permissions')) {
            return null;
        }

        $rolePermission = RolePermission::where('role', $role)
            ->where('permission', $permission)
            ->first();

        return $rolePermission?->allowed;
    }
}
