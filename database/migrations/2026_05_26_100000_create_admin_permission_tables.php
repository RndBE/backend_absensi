<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->boolean('allowed')->default(false);
            $table->timestamps();

            $table->unique(['role', 'permission']);
            $table->index('role');
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

    public function down(): void
    {
        Schema::dropIfExists('employee_permission_overrides');
        Schema::dropIfExists('role_permissions');
    }
};
