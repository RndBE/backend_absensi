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
    /**
     * Cache seumur-request. Satu panggilan can() dulu menembak ~10 query (cek keberadaan
     * tabel/kolom lewat information_schema, roles, role_permissions, overrides). Halaman admin
     * memanggilnya puluhan kali — sidebar saja sudah belasan — sehingga satu halaman bisa
     * menghabiskan ratusan query untuk pertanyaan yang jawabannya sama.
     *
     * Container dibangun ulang tiap request (dan tiap test), jadi cache ini tidak pernah basi
     * antar request. Penulis (updateRole/updateEmployeeOverrides) membuang cache-nya sendiri.
     */
    private array $tableExists = [];

    private array $columnExists = [];

    /** @var array<int, array<int, string>> employee_id => slug role */
    private array $roleSlugsCache = [];

    /** @var array<int, array<string, bool>> employee_id => permission => allowed */
    private array $overridesCache = [];

    /** @var array<string, array<string, bool>> role slug => permission => allowed */
    private array $rolePermissionCache = [];

    /** @var array<string, int|null>|null slug role => id */
    private ?array $roleIdBySlug = null;

    public function groupedPermissions(): array
    {
        return config('admin_permissions.groups', []);
    }

    private function hasTable(string $table): bool
    {
        return $this->tableExists[$table] ??= Schema::hasTable($table);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return $this->columnExists["{$table}.{$column}"] ??= Schema::hasColumn($table, $column);
    }

    private function roleId(string $slug): ?int
    {
        if ($this->roleIdBySlug === null) {
            $this->roleIdBySlug = $this->hasTable('roles')
                ? Role::pluck('id', 'slug')->all()
                : [];
        }

        return $this->roleIdBySlug[$slug] ?? null;
    }

    /** Buang cache setelah permission ditulis ulang. */
    private function flushCache(): void
    {
        $this->roleSlugsCache = [];
        $this->overridesCache = [];
        $this->rolePermissionCache = [];
        $this->roleIdBySlug = null;
    }

    public function allPermissions(): array
    {
        return Arr::flatten(array_map(fn ($items) => array_keys($items), $this->groupedPermissions()));
    }

    public function roles(): array
    {
        if ($this->hasTable('roles')) {
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
        if (isset($this->roleSlugsCache[$employee->id])) {
            return $this->roleSlugsCache[$employee->id];
        }

        $slugs = [];

        if ($this->hasTable('roles') && $this->hasTable('employee_roles')) {
            $slugs = $employee->roles()
                ->pluck('roles.slug')
                ->filter()
                ->values()
                ->all();
        }

        if (!$slugs && $employee->role) {
            $slugs[] = $this->normalizeLegacyRole($employee->role);
        }

        return $this->roleSlugsCache[$employee->id] = array_values(array_unique($slugs));
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

        if ($this->hasTable('role_permissions')) {
            $query = RolePermission::query();

            if ($this->hasColumn('role_permissions', 'role_id') && $this->hasTable('roles')) {
                $roleId = $this->roleId($role);
                $query->where('role_id', $roleId);
            } elseif ($this->hasColumn('role_permissions', 'role')) {
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

        if ($this->hasTable('roles') && $this->hasColumn('role_permissions', 'role_id')) {
            $roleId = Role::firstOrCreate(
                ['slug' => $role],
                ['name' => $this->roles()[$role] ?? Str::headline(str_replace('_', ' ', $role)), 'is_system' => true]
            )->id;
        }

        foreach ($this->allPermissions() as $permission) {
            if ($roleId) {
                $values = ['allowed' => array_key_exists($permission, $allowed)];
                if ($this->hasColumn('role_permissions', 'role')) {
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

        $this->flushCache();
    }

    public function overridesForEmployee(Employee $employee): array
    {
        if (!$this->hasTable('employee_permission_overrides')) {
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

        $this->flushCache();
    }

    /** Seluruh override karyawan dimuat sekali, bukan satu query per permission. */
    private function employeeOverride(int $employeeId, string $permission): ?bool
    {
        if (!$this->hasTable('employee_permission_overrides')) {
            return null;
        }

        $this->overridesCache[$employeeId] ??= EmployeePermissionOverride::where('employee_id', $employeeId)
            ->pluck('allowed', 'permission')
            ->map(fn ($allowed) => (bool) $allowed)
            ->all();

        return $this->overridesCache[$employeeId][$permission] ?? null;
    }

    /** Seluruh permission sebuah role dimuat sekali, bukan satu query per permission. */
    private function rolePermission(string $role, string $permission): ?bool
    {
        if (!$this->hasTable('role_permissions')) {
            return null;
        }

        if (! isset($this->rolePermissionCache[$role])) {
            $query = RolePermission::query();

            if ($this->hasColumn('role_permissions', 'role_id') && $this->hasTable('roles')) {
                $roleId = $this->roleId($role);
                if (!$roleId) {
                    $this->rolePermissionCache[$role] = [];

                    return null;
                }
                $query->where('role_id', $roleId);
            } elseif ($this->hasColumn('role_permissions', 'role')) {
                $query->where('role', $role);
            } else {
                return null;
            }

            $this->rolePermissionCache[$role] = $query->pluck('allowed', 'permission')
                ->map(fn ($allowed) => (bool) $allowed)
                ->all();
        }

        return $this->rolePermissionCache[$role][$permission] ?? null;
    }

    private function normalizeLegacyRole(string $role): string
    {
        return $role;
    }
}
