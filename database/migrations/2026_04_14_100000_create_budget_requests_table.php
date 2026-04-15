<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['budget', 'reimbursement']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'in_review', 'approved', 'rejected', 'paid'])->default('pending');
            $table->integer('current_step')->default(1);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_requests');
    }
};
