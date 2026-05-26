<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    private const ROLES = ['superadmin', 'admin', 'manager'];
    private const EDITABLE_ROLES = ['admin', 'manager'];

    public function index(Request $request, AdminPermission $permissions)
    {
        $admin = Employee::find(session('admin_id'));
        $selectedEmployee = null;

        $admins = Employee::query()
            ->when($admin->role !== 'superadmin', fn ($q) => $q->where('company_id', $admin->company_id))
            ->whereIn('role', self::ROLES)
            ->with('department:id,name')
            ->orderBy('role')
            ->orderBy('full_name')
            ->get();

        if ($request->employee_id) {
            $selectedEmployee = $admins->firstWhere('id', (int) $request->employee_id);
        }

        $roleStates = [];
        foreach (self::ROLES as $role) {
            $roleStates[$role] = $permissions->roleState($role);
        }

        $selectedOverrides = $selectedEmployee
            ? $permissions->overridesForEmployee($selectedEmployee)
            : [];

        return view('admin.role-permissions.index', [
            'groups' => $permissions->groupedPermissions(),
            'roles' => self::ROLES,
            'editableRoles' => self::EDITABLE_ROLES,
            'roleStates' => $roleStates,
            'admins' => $admins,
            'selectedEmployee' => $selectedEmployee,
            'selectedOverrides' => $selectedOverrides,
        ]);
    }

    public function updateRole(Request $request, string $role, AdminPermission $permissions)
    {
        abort_unless(in_array($role, self::EDITABLE_ROLES, true), 403);

        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'string',
        ]);

        $permissions->updateRole($role, $request->input('permissions', []));

        return redirect()->route('admin.role-permissions.index')
            ->with('success', 'Permission role berhasil diperbarui.');
    }

    public function updateEmployee(Request $request, Employee $employee, AdminPermission $permissions)
    {
        $admin = Employee::find(session('admin_id'));
        abort_unless($admin->role === 'superadmin' || $employee->company_id === $admin->company_id, 403);

        if ($employee->role === 'superadmin') {
            return redirect()->route('admin.role-permissions.index', ['employee_id' => $employee->id])
                ->with('error', 'Super Admin selalu memiliki semua akses dan tidak memakai override.');
        }

        $request->validate([
            'overrides' => 'array',
            'overrides.*' => [Rule::in(['inherit', 'allow', 'deny'])],
        ]);

        $permissions->updateEmployeeOverrides($employee, $request->input('overrides', []));

        return redirect()->route('admin.role-permissions.index', ['employee_id' => $employee->id])
            ->with('success', 'Override permission admin berhasil diperbarui.');
    }
}
