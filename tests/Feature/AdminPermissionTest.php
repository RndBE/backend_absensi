<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_payroll_permission_cannot_open_payroll_routes_and_menu_is_hidden(): void
    {
        $admin = $this->admin('admin');

        DB::table('admin_role_permissions')->where('role', 'admin')->delete();

        $dashboard = $this->withSession(['admin_id' => $admin->id])
            ->get(route('admin.dashboard'));

        $dashboard->assertOk();
        $dashboard->assertDontSee('Run Payroll');

        $response = $this->withSession(['admin_id' => $admin->id])
            ->get(route('admin.payroll-runs.index'));

        $response->assertForbidden();
    }

    public function test_admin_with_payroll_permission_can_open_payroll_routes(): void
    {
        $admin = $this->admin('admin');

        DB::table('admin_role_permissions')->where('role', 'admin')->delete();
        $this->grantRolePermission('admin', 'payroll.view');

        $response = $this->withSession(['admin_id' => $admin->id])
            ->get(route('admin.payroll-runs.index'));

        $response->assertOk();
        $response->assertSee('Run Payroll');
    }

    public function test_superadmin_can_open_role_permission_settings(): void
    {
        $admin = $this->admin('superadmin');

        $response = $this->withSession(['admin_id' => $admin->id])
            ->get(route('admin.role-permissions.index'));

        $response->assertOk();
        $response->assertSee('Role Permission');
    }

    public function test_superadmin_can_remove_default_admin_permissions(): void
    {
        $admin = $this->admin('superadmin');

        $response = $this->withSession(['admin_id' => $admin->id])
            ->put(route('admin.role-permissions.update'), [
                'role' => 'admin',
                'permissions' => ['employees.view'],
            ]);

        $response->assertRedirect(route('admin.role-permissions.index'));

        $this->assertDatabaseHas('admin_role_permissions', [
            'role' => 'admin',
            'admin_permission_id' => DB::table('admin_permissions')->where('key', 'employees.view')->value('id'),
        ]);

        $this->assertDatabaseMissing('admin_role_permissions', [
            'role' => 'admin',
            'admin_permission_id' => DB::table('admin_permissions')->where('key', 'payroll.view')->value('id'),
        ]);
    }

    private function admin(string $role): Employee
    {
        $company = Company::create(['name' => 'Permission Company']);

        return Employee::create([
            'employee_code' => strtoupper($role) . '-001',
            'company_id' => $company->id,
            'full_name' => ucfirst($role) . ' User',
            'email' => $role . '-permission@example.test',
            'password' => 'password',
            'employment_status' => 'permanent',
            'is_active' => true,
            'role' => $role,
        ]);
    }

    private function grantRolePermission(string $role, string $permissionKey): void
    {
        $permissionId = DB::table('admin_permissions')->where('key', $permissionKey)->value('id');

        DB::table('admin_role_permissions')->insert([
            'role' => $role,
            'admin_permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
