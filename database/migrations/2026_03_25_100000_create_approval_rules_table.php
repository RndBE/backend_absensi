<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('request_type'); // leave, overtime, attendance, data-change
            $table->integer('requester_min_level')->nullable(); // applies to requester with level >= this
            $table->integer('requester_max_level')->nullable(); // applies to requester with level <= this
            $table->integer('step_order'); // 1, 2, 3...
            $table->string('name'); // "Leader Approval", "Manager Approval"
            $table->integer('min_approver_level')->nullable(); // approver must have job_level <= this
            $table->enum('approver_role', ['admin', 'manager', 'any'])->default('any');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'request_type', 'requester_min_level', 'requester_max_level', 'step_order'], 'approval_rules_unique');
            $table->index('request_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
