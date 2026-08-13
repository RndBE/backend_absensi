<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kpi_level_id')->constrained('kpi_levels')->cascadeOnDelete();
            $table->enum('category', ['EX', 'CO', 'LD']);
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();

            // Bobot dalam persen terhadap kategorinya, bukan terhadap nilai akhir.
            // Jumlah per (level, kategori) harus 100 — divalidasi di FormRequest, bukan di DB.
            $table->decimal('weight', 5, 2)->default(0);

            // Indikator inti wajib diisi dengan bukti (Bab 9.1); pendukung boleh default 3.
            $table->boolean('is_core')->default(false);

            // Diisi sistem, bukan penilai. `auto_source` mis. 'cross_assessment' atau 'attendance'.
            $table->boolean('is_auto_filled')->default(false);
            $table->string('auto_source', 50)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['kpi_level_id', 'category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_indicators');
    }
};
