<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->boolean('is_exempt_penalty')->default(false)->after('is_active')->comment('Exempt from auto deductions (late/alpha)');
            $table->decimal('late_penalty_per_day', 15, 2)->default(50000)->after('is_exempt_penalty');
            $table->decimal('overtime_multiplier', 5, 2)->default(1)->after('late_penalty_per_day')->comment('Multiplier for overtime rate (1=normal, 0=no overtime)');
        });
    }

    public function down(): void
    {
        Schema::table('employee_payrolls', function (Blueprint $table) {
            $table->dropColumn(['is_exempt_penalty', 'late_penalty_per_day', 'overtime_multiplier']);
        });
    }
};
