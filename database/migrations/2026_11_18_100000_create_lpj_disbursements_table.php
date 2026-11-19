<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lpj_disbursements')) {
            return;
        }

        Schema::create('lpj_disbursements', function (Blueprint $table) {
            // Uang yang benar-benar ditransfer finance untuk LPJ ini. Sengaja terpisah dari lpj_items
            // (realisasi pengaju): finance mencatat di sini tanpa mengubah realisasi.
            $table->id();
            $table->foreignId('lpj_id')->constrained('lpjs')->cascadeOnDelete();
            $table->date('transferred_at');
            $table->decimal('amount', 15, 2);
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lpj_disbursements');
    }
};
