<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->integer('pre_shift_duration')->default(0)->comment('minutes');
            $table->integer('pre_shift_break')->default(0)->comment('minutes');
            $table->integer('post_shift_duration')->default(0)->comment('minutes');
            $table->integer('post_shift_break')->default(0)->comment('minutes');
            $table->integer('total_duration')->default(0)->comment('minutes');
            $table->text('reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
