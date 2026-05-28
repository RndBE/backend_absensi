<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Http\Controllers\Admin\RoleController;
use App\Support\AdminPermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminRoleTablePermissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_permission_overrides');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('employee_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['employee_id', 'role_id']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission');
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role_id', 'permission']);
        });

        Schema::create('employee_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('permission');
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['employee_id', 'permission']);
        });

        foreach ([
            ['id' => 1, 'name' => 'Superadmin', 'slug' => 'superadmin'],
            ['id' => 2, 'name' => 'HR Admin', 'slug' => 'hr_admin'],
            ['id' => 3, 'name' => 'Payroll Admin', 'slug' => 'payroll_admin'],
            ['id' => 4, 'name' => 'Finance Admin', 'slug' => 'finance_admin'],
            ['id' => 5, 'name' => 'Manager', 'slug' => 'manager'],
            ['id' => 6, 'name' => 'Employee', 'slug' => 'employee'],
        ] as $role) {
            DB::table('roles')->insert($role + ['created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_employee_can_receive_admin_permission_from_role_table(): void
    {
        $employee = Employee::create([
            'full_name' => 'Payroll User',
            'email' => 'payroll@example.test',
            'password' => 'secret',
            'role' => 'employee',
        ]);

        DB::table('employee_roles')->insert([
            'employee_id' => $employee->id,
            'role_id' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue(app(AdminPermission::class)->can($employee->fresh(), 'payroll.runs.create'));
        $this->assertTrue(app(AdminPermission::class)->isAdminUser($employee->fresh()));
    }

    public function test_role_permission_updates_are_stored_by_role_id(): void
    {
        app(AdminPermission::class)->updateRole('payroll_admin', ['dashboard.view', 'payroll.runs.publish']);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => 3,
            'permission' => 'payroll.runs.publish',
            'allowed' => true,
        ]);
    }

    public function test_employee_override_can_deny_permission_from_any_assigned_role(): void
    {
        $employee = Employee::create([
            'full_name' => 'Finance User',
            'email' => 'finance@example.test',
            'password' => 'secret',
            'role' => 'employee',
        ]);

        DB::table('employee_roles')->insert([
            'employee_id' => $employee->id,
            'role_id' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_permission_overrides')->insert([
            'employee_id' => $employee->id,
            'permission' => 'budget.manage',
            'allowed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(app(AdminPermission::class)->can($employee->fresh(), 'budget.manage'));
    }

    public function test_settings_can_sync_employee_roles(): void
    {
        $admin = Employee::create([
            'full_name' => 'Super Admin',
            'email' => 'super@example.test',
            'password' => 'secret',
            'role' => 'superadmin',
        ]);
        DB::table('employee_roles')->insert([
            'employee_id' => $admin->id,
            'role_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $employee = Employee::create([
            'full_name' => 'Payroll Finance',
            'email' => 'payroll-finance@example.test',
            'password' => 'secret',
            'role' => 'employee',
        ]);

        session(['admin_id' => $admin->id]);

        $request = Request::create('/admin/roles/employees/'.$employee->id, 'PUT', [
            'roles' => ['payroll_admin', 'finance_admin'],
        ]);

        app(RoleController::class)->updateEmployee($request, $employee, app(AdminPermission::class));

        $this->assertSame(['finance_admin', 'payroll_admin'], $employee->fresh()->roles()->pluck('slug')->sort()->values()->all());
        $this->assertSame('payroll_admin', $employee->fresh()->role);
    }
}
