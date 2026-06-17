<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_policies', function (Blueprint $table) {
            $table->string('eligibility_type', 20)->default('all')->after('is_prorated');
        });

        Schema::create('leave_policy_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['leave_policy_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_policy_employees');

        Schema::table('leave_policies', function (Blueprint $table) {
            $table->dropColumn('eligibility_type');
        });
    }
};
