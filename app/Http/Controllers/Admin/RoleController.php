<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Role;
use App\Models\RolePermission;
use App\Support\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        // Kolom lama employees.role berupa ENUM: role buatan admin tidak boleh ditulis ke sana.
        $employee->update(['role' => Role::legacySlugFor($roleSlugs)]);

        return redirect()->route('admin.roles.index', ['employee_id' => $employee->id])
            ->with('success', 'Role karyawan berhasil diperbarui.');
    }

    /** Tambah role baru dari tab Role di halaman Role Permission. */
    public function store(Request $request, AdminPermission $permissions)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:500',
            'copy_from' => ['nullable', Rule::in(array_keys($permissions->roles()))],
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah dipakai.',
        ]);

        $role = Role::create([
            'name' => trim($validated['name']),
            'slug' => $this->uniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        if (filled($validated['copy_from'] ?? null)) {
            $allowed = array_keys(array_filter($permissions->roleState($validated['copy_from'])));
            $permissions->updateRole($role->slug, $allowed);
        }

        return redirect()->route('admin.role-permissions.index', ['tab' => 'role'])
            ->with('success', "Role {$role->name} ditambahkan. Atur permission-nya di tab Permission, lalu pasang ke karyawan di menu Role.");
    }

    public function update(Request $request, Role $role)
    {
        if ($role->slug === 'superadmin') {
            return $this->backToRoleTab()->with('error', 'Role Superadmin tidak bisa diubah.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role->id)],
            'description' => 'nullable|string|max:500',
        ], [
            'name.required' => 'Nama role wajib diisi.',
            'name.unique' => 'Nama role sudah dipakai.',
        ]);

        // Slug sengaja tidak ikut berubah: dipakai sebagai kunci di role_permissions dan kode.
        $role->update([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        return $this->backToRoleTab()->with('success', "Role {$role->name} diperbarui.");
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return $this->backToRoleTab()->with('error', 'Role bawaan sistem tidak bisa dihapus karena dipakai logika aplikasi.');
        }

        $used = $role->employees()->count();
        if ($used > 0) {
            return $this->backToRoleTab()->with('error', "Role {$role->name} masih dipakai {$used} karyawan. Ganti role mereka dulu di menu Role.");
        }

        // Baris lama yang hanya berisi slug (tanpa role_id) ikut dibersihkan; yang ber-role_id
        // terhapus lewat foreign key cascade.
        RolePermission::where('role', $role->slug)->delete();
        $name = $role->name;
        $role->delete();

        return $this->backToRoleTab()->with('success', "Role {$name} dihapus.");
    }

    private function backToRoleTab()
    {
        return redirect()->route('admin.role-permissions.index', ['tab' => 'role']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $taken = array_merge(Role::LEGACY_SLUGS, array_keys(config('admin_permissions.roles', [])));
        $slug = $base;

        for ($i = 2; in_array($slug, $taken, true) || Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}_{$i}";
        }

        return $slug;
    }
}
