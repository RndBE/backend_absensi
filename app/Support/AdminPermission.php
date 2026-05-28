<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeePermissionOverride;
use App\Models\Role;
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

    public function roles(): array
    {
        if (Schema::hasTable('roles')) {
            $roles = Role::query()
                ->orderByRaw("case slug when 'superadmin' then 1 when 'hr_admin' then 2 when 'payroll_admin' then 3 when 'finance_admin' then 4 when 'manager' then 5 when 'employee' then 6 else 99 end")
                ->pluck('name', 'slug')
                ->all();

            if ($roles) {
                return $roles;
            }
        }

        return config('admin_permissions.roles', [
            'superadmin' => 'Superadmin',
            'hr_admin' => 'HR Admin',
            'payroll_admin' => 'Payroll Admin',
            'finance_admin' => 'Finance Admin',
            'manager' => 'Manager',
            'employee' => 'Employee',
        ]);
    }

    public function editableRoles(): array
    {
        return array_values(array_diff(array_keys($this->roles()), ['superadmin']));
    }

    public function roleSlugs(Employee $employee): array
    {
        $slugs = [];

        if (Schema::hasTable('roles') && Schema::hasTable('employee_roles')) {
            $slugs = $employee->roles()
                ->pluck('roles.slug')
                ->filter()
                ->values()
                ->all();
        }

        if (!$slugs && $employee->role) {
            $slugs[] = $this->normalizeLegacyRole($employee->role);
        }

        return array_values(array_unique($slugs));
    }

    public function isAdminUser(Employee $employee): bool
    {
        if (in_array('superadmin', $this->roleSlugs($employee), true)) {
            return true;
        }

        return $this->can($employee, 'dashboard.view')
            && count(array_diff($this->roleSlugs($employee), ['employee'])) > 0;
    }

    public function can(Employee $employee, string $permission): bool
    {
        $roles = $this->roleSlugs($employee);

        if (in_array('superadmin', $roles, true)) {
            return true;
        }

        $override = $this->employeeOverride($employee->id, $permission);
        if ($override !== null) {
            return $override;
        }

        foreach ($roles as $role) {
            $roleValue = $this->rolePermission($role, $permission);
            if ($roleValue === true) {
                return true;
            }
            if ($roleValue === false) {
                continue;
            }

            $defaults = config("admin_permissions.defaults.{$role}", []);
            if (in_array($permission, $defaults, true) || in_array('*', $defaults, true)) {
                return true;
            }
        }

        return false;
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
            $query = RolePermission::query();

            if (Schema::hasColumn('role_permissions', 'role_id') && Schema::hasTable('roles')) {
                $roleId = Role::where('slug', $role)->value('id');
                $query->where('role_id', $roleId);
            } elseif (Schema::hasColumn('role_permissions', 'role')) {
                $query->where('role', $role);
            } else {
                return $permissions;
            }

            $query->get()->each(function (RolePermission $item) use (&$permissions) {
                $permissions[$item->permission] = $item->allowed;
            });
        }

        return $permissions;
    }

    public function updateRole(string $role, array $allowedPermissions): void
    {
        $allowed = array_flip($allowedPermissions);
        $roleId = null;

        if (Schema::hasTable('roles') && Schema::hasColumn('role_permissions', 'role_id')) {
            $roleId = Role::firstOrCreate(
                ['slug' => $role],
                ['name' => $this->roles()[$role] ?? Str::headline(str_replace('_', ' ', $role)), 'is_system' => true]
            )->id;
        }

        foreach ($this->allPermissions() as $permission) {
            if ($roleId) {
                $values = ['allowed' => array_key_exists($permission, $allowed)];
                if (Schema::hasColumn('role_permissions', 'role')) {
                    $values['role'] = $role;
                }

                RolePermission::updateOrCreate(
                    ['role_id' => $roleId, 'permission' => $permission],
                    $values
                );
            } else {
                RolePermission::updateOrCreate(
                    ['role' => $role, 'permission' => $permission],
                    ['allowed' => array_key_exists($permission, $allowed)]
                );
            }
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

        $query = RolePermission::where('permission', $permission);

        if (Schema::hasColumn('role_permissions', 'role_id') && Schema::hasTable('roles')) {
            $roleId = Role::where('slug', $role)->value('id');
            if (!$roleId) {
                return null;
            }
            $query->where('role_id', $roleId);
        } elseif (Schema::hasColumn('role_permissions', 'role')) {
            $query->where('role', $role);
        } else {
            return null;
        }

        $rolePermission = $query->first();

        return $rolePermission?->allowed;
    }

    private function normalizeLegacyRole(string $role): string
    {
        return $role;
    }
}
