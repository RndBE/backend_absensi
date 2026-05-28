<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $roles = [
        'superadmin' => 'Superadmin',
        'hr_admin' => 'HR Admin',
        'payroll_admin' => 'Payroll Admin',
        'finance_admin' => 'Finance Admin',
        'manager' => 'Manager',
        'employee' => 'Employee',
    ];

    public function up(): void
    {
        if (Schema::hasTable('employees')) {
            try {
                DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('superadmin','hr_admin','payroll_admin','finance_admin','manager','employee','admin') DEFAULT 'employee'");
            } catch (\Throwable $e) {
                // Non-MySQL test databases keep role as a plain string.
            }
        }

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(true);
            $table->timestamps();
        });

        $now = now();
        foreach ($this->roles as $slug => $name) {
            DB::table('roles')->insert([
                'name' => $name,
                'slug' => $slug,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('employee_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['employee_id', 'role_id']);
        });

        if (Schema::hasTable('employees')) {
            $roleIds = DB::table('roles')->pluck('id', 'slug');
            DB::table('employees')->select('id', 'role')->orderBy('id')->chunk(100, function ($employees) use ($roleIds, $now) {
                foreach ($employees as $employee) {
                    $slug = $employee->role === 'admin' ? 'hr_admin' : $employee->role;
                    $roleId = $roleIds[$slug] ?? $roleIds['employee'] ?? null;
                    if (!$roleId) {
                        continue;
                    }

                    DB::table('employee_roles')->updateOrInsert(
                        ['employee_id' => $employee->id, 'role_id' => $roleId],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }
            });
        }

        if (Schema::hasTable('role_permissions') && !Schema::hasColumn('role_permissions', 'role_id')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->cascadeOnDelete();
            });

            DB::table('role_permissions')->select('id', 'role')->orderBy('id')->chunk(100, function ($permissions) {
                foreach ($permissions as $permission) {
                    $slug = $permission->role === 'admin' ? 'hr_admin' : $permission->role;
                    $roleId = DB::table('roles')->where('slug', $slug)->value('id');
                    if ($roleId) {
                        DB::table('role_permissions')->where('id', $permission->id)->update(['role_id' => $roleId]);
                    }
                }
            });

            Schema::table('role_permissions', function (Blueprint $table) {
                $table->unique(['role_id', 'permission'], 'role_permissions_role_id_permission_unique');
                $table->index('role_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('role_permissions') && Schema::hasColumn('role_permissions', 'role_id')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->dropUnique('role_permissions_role_id_permission_unique');
                $table->dropIndex(['role_id']);
                $table->dropConstrainedForeignId('role_id');
            });
        }

        Schema::dropIfExists('employee_roles');
        Schema::dropIfExists('roles');

        if (Schema::hasTable('employees')) {
            try {
                DB::statement("ALTER TABLE employees MODIFY COLUMN role ENUM('superadmin','admin','manager','employee') DEFAULT 'employee'");
            } catch (\Throwable $e) {
                // Non-MySQL test databases keep role as a plain string.
            }
        }
    }
};
