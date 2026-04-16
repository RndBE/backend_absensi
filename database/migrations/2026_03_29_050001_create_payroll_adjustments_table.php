<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add resign_date to employees
        Schema::table('employees', function (Blueprint $table) {
            $table->date('resign_date')->nullable()->after('join_date');
        });

        // Payroll adjustments table
        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('payroll_run_id')->nullable();
            $table->enum('type', ['adjustment', 'correction', 'backpay', 'arrears', 'retroactive']);
            $table->enum('earning_type', ['earning', 'deduction']);
            $table->string('name'); // e.g. "Backpay Maret 2026", "Koreksi Lembur"
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference_period', 7)->nullable()->comment('Period being adjusted (YYYY-MM)');
            $table->string('target_period', 7)->comment('Period to apply adjustment (YYYY-MM)');
            $table->enum('status', ['pending', 'applied', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('payroll_run_id')->references('id')->on('payroll_runs')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('resign_date');
        });
    }
};
