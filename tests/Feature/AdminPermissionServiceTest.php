<?php

namespace Tests\Feature;

use App\Support\AdminPermission;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPermissionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_permission_overrides');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role', 'permission']);
        });

        Schema::create('employee_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('permission');
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['employee_id', 'permission']);
        });
    }

    public function test_employee_override_can_deny_role_permission(): void
    {
        DB::table('employees')->insert([
            'id' => 10,
            'company_id' => 1,
            'email' => 'admin@example.test',
            'password' => 'secret',
            'full_name' => 'Payroll Admin',
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role' => 'admin',
            'permission' => 'payroll.runs.publish',
            'allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_permission_overrides')->insert([
            'employee_id' => 10,
            'permission' => 'payroll.runs.publish',
            'allowed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = \App\Models\Employee::findOrFail(10);

        $this->assertFalse(app(AdminPermission::class)->can($admin, 'payroll.runs.publish'));
    }

    public function test_employee_override_can_allow_permission_missing_from_role(): void
    {
        DB::table('employees')->insert([
            'id' => 11,
            'company_id' => 1,
            'email' => 'manager@example.test',
            'password' => 'secret',
            'full_name' => 'Finance Manager',
            'role' => 'manager',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role' => 'manager',
            'permission' => 'reports.payroll.view',
            'allowed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('employee_permission_overrides')->insert([
            'employee_id' => 11,
            'permission' => 'reports.payroll.view',
            'allowed' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = \App\Models\Employee::findOrFail(11);

        $this->assertTrue(app(AdminPermission::class)->can($manager, 'reports.payroll.view'));
    }

    public function test_superadmin_always_has_permission(): void
    {
        DB::table('employees')->insert([
            'id' => 12,
            'company_id' => 1,
            'email' => 'super@example.test',
            'password' => 'secret',
            'full_name' => 'Super Admin',
            'role' => 'superadmin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $superadmin = \App\Models\Employee::findOrFail(12);

        $this->assertTrue(app(AdminPermission::class)->can($superadmin, 'anything.at.all'));
    }
}
