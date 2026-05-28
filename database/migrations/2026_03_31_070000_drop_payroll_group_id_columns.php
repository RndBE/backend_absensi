<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop payroll_group_id from payroll_runs
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->dropForeign(['payroll_group_id']);
            $table->dropColumn('payroll_group_id');
        });

        // Drop payroll_group_id from employee_payrolls
        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->dropForeign(['payroll_group_id']);
            $table->dropColumn('payroll_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->foreignId('payroll_group_id')->nullable()->after('period')->constrained()->nullOnDelete();
        });

        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->foreignId('payroll_group_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
        });
    }
};
