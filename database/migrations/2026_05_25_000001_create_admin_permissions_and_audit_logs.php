<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group');
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->foreignId('admin_permission_id')->constrained('admin_permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role', 'admin_permission_id']);
            $table->index('role');
        });

        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('action');
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['employee_id', 'created_at']);
            $table->index('route_name');
        });

        $now = now();
        $permissions = collect(config('admin_permissions.permissions', []))->map(fn($permission) => [
            'key' => $permission['key'],
            'group' => $permission['group'],
            'name' => $permission['name'],
            'description' => $permission['description'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('admin_permissions')->insert($permissions);

        $adminPermissionIds = DB::table('admin_permissions')
            ->whereIn('key', config('admin_permissions.admin_default_permissions', []))
            ->pluck('id')
            ->map(fn($id) => [
                'role' => 'admin',
                'admin_permission_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($adminPermissionIds) {
            DB::table('admin_role_permissions')->insert($adminPermissionIds);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('admin_role_permissions');
        Schema::dropIfExists('admin_permissions');
    }
};
