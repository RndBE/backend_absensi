<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('request_type', ['leave', 'overtime', 'attendance']);
            $table->integer('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();

            $table->foreign('approver_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unique(['employee_id', 'request_type', 'step_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_approvers');
    }
};
