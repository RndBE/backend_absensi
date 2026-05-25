<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPermission;
use App\Models\AdminRolePermission;
use App\Services\AdminPermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolePermissionController extends Controller
{
    public function index()
    {
        AdminPermissionService::syncDefaults();

        $role = 'admin';
        $permissionGroups = AdminPermissionService::groupedPermissions();
        $selectedPermissions = AdminPermissionService::rolePermissionKeys($role)->all();

        return view('admin.role-permissions.index', compact('role', 'permissionGroups', 'selectedPermissions'));
    }

    public function update(Request $request)
    {
        AdminPermissionService::syncDefaults();

        $validKeys = AdminPermission::pluck('key')->all();

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($validKeys)],
        ]);

        $role = $validated['role'];
        $permissionKeys = $validated['permissions'] ?? [];
        $permissionIds = AdminPermission::whereIn('key', $permissionKeys)->pluck('id')->all();

        DB::transaction(function () use ($role, $permissionIds) {
            AdminRolePermission::where('role', $role)->delete();

            foreach ($permissionIds as $permissionId) {
                AdminRolePermission::create([
                    'role' => $role,
                    'admin_permission_id' => $permissionId,
                ]);
            }
        });

        return redirect()
            ->route('admin.role-permissions.index')
            ->with('success', 'Role permission berhasil diperbarui.');
    }
}
