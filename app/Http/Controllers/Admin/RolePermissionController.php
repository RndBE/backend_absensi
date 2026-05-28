<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function index(Request $request, AdminPermission $permissions)
    {
        $admin = Employee::find(session('admin_id'));
        $selectedEmployee = null;
        $roles = $permissions->roles();
        $editableRoles = $permissions->editableRoles();
        $isSuperadmin = in_array('superadmin', $permissions->roleSlugs($admin), true);

        $admins = Employee::query()
            ->when(!$isSuperadmin && !$permissions->can($admin, 'company.manage'), fn ($q) => $q->where('company_id', $admin->company_id))
            ->with(['department:id,name', 'roles:id,name,slug'])
            ->orderBy('full_name')
            ->get()
            ->filter(fn (Employee $employee) => $permissions->isAdminUser($employee))
            ->values();

        if ($request->employee_id) {
            $selectedEmployee = $admins->firstWhere('id', (int) $request->employee_id);
        }

        $roleStates = [];
        foreach (array_keys($roles) as $role) {
            $roleStates[$role] = $permissions->roleState($role);
        }

        $selectedOverrides = $selectedEmployee
            ? $permissions->overridesForEmployee($selectedEmployee)
            : [];

        return view('admin.role-permissions.index', [
            'groups' => $permissions->groupedPermissions(),
            'roles' => $roles,
            'editableRoles' => $editableRoles,
            'roleStates' => $roleStates,
            'admins' => $admins,
            'selectedEmployee' => $selectedEmployee,
            'selectedOverrides' => $selectedOverrides,
        ]);
    }

    public function updateRole(Request $request, string $role, AdminPermission $permissions)
    {
        abort_unless(in_array($role, $permissions->editableRoles(), true), 403);

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
        abort_unless($permissions->can($admin, '*') || $employee->company_id === $admin->company_id, 403);

        if (in_array('superadmin', $permissions->roleSlugs($employee), true)) {
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
