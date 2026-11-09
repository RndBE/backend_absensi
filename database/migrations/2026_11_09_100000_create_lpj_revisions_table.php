<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lpj_revisions', function (Blueprint $table) {
            // Jejak koreksi realisasi LPJ oleh finance. Terpisah dari approval_logs karena log itu
            // dipakai menentukan status tiap step.
            $table->id();
            $table->foreignId('lpj_id')->constrained('lpjs')->cascadeOnDelete();
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
        Schema::dropIfExists('lpj_revisions');
    }
};
