<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_request_revisions', function (Blueprint $table) {
            // Jejak perubahan item anggaran oleh approver (mis. HR mengisi tunjangan luar kota).
            // Sengaja terpisah dari approval_logs: log itu dipakai untuk menentukan status tiap step.
            $table->id();
            $table->foreignId('budget_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedTinyInteger('step_order')->nullable();
            $table->json('before');
            $table->json('after');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_request_revisions');
    }
};
