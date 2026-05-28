<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Support\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(Request $request, AdminPermission $permissions)
    {
        $admin = Employee::find(session('admin_id'));
        $roles = $permissions->roles();
        $isSuperadmin = in_array('superadmin', $permissions->roleSlugs($admin), true);

        $employees = Employee::query()
            ->when(!$isSuperadmin && !$permissions->can($admin, 'company.manage'), fn ($q) => $q->where('company_id', $admin->company_id))
            ->with(['department:id,name', 'roles:id,name,slug'])
            ->orderBy('full_name')
            ->get();

        $selectedEmployee = $request->employee_id
            ? $employees->firstWhere('id', (int) $request->employee_id)
            : null;

        return view('admin.roles.index', [
            'roles' => $roles,
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
        ]);
    }

    public function updateEmployee(Request $request, Employee $employee, AdminPermission $permissions)
    {
        $admin = Employee::find(session('admin_id'));
        abort_unless($permissions->can($admin, 'security.permissions.manage'), 403);
        abort_unless($permissions->can($admin, '*') || $employee->company_id === $admin->company_id, 403);

        $request->validate([
            'roles' => 'required|array|min:1',
            'roles.*' => [Rule::in(array_keys($permissions->roles()))],
        ]);

        $roleSlugs = array_values(array_unique($request->input('roles', [])));
        $roleIds = Role::whereIn('slug', $roleSlugs)->pluck('id', 'slug');

        $employee->roles()->sync($roleIds->values()->all());
        $employee->update(['role' => $roleSlugs[0]]);

        return redirect()->route('admin.roles.index', ['employee_id' => $employee->id])
            ->with('success', 'Role karyawan berhasil diperbarui.');
    }
}
