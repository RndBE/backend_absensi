<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->integer('days_per_year')->default(12);
            $table->integer('min_tenure_months')->default(12); // min months worked
            $table->integer('max_carry_over')->default(0); // max days carried to next year
            $table->boolean('is_prorated')->default(false); // prorate for mid-year hires
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'leave_type_id']);
        });

        // Add carry_over column to leave_balances
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->integer('carry_over')->default(0)->after('total_days');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn('carry_over');
        });
    }
};
