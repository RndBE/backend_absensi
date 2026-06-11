<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->unsignedSmallInteger('installment_count');
            $table->decimal('monthly_installment', 15, 2);
            $table->decimal('remaining_amount', 15, 2)->default(0);
            $table->string('start_period', 7)->nullable();
            $table->text('purpose')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_requests');
    }
};
