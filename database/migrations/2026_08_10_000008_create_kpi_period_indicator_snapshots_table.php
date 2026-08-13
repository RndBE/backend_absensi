<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasangan snapshot untuk indikator. Selain bobot dan penanda inti, teks rubrik ikut
     * dibekukan di kolom `rubrics` (JSON, skor => deskripsi). Kerangka membolehkan admin
     * mengubah rubrik kapan saja; tanpa pembekuan, hasil lama tidak bisa dijelaskan ulang
     * karena patokan yang dipakai penilai saat itu sudah hilang.
     */
    public function up(): void
    {
        Schema::create('kpi_period_indicator_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('kpi_indicator_id')->nullable()->constrained('kpi_indicators')->nullOnDelete();
            // Dideklarasikan terpisah, bukan lewat foreignId()->constrained(): nama constraint
            // bawaan Laravel (kpi_period_indicator_snapshots_kpi_period_level_snapshot_id_foreign)
            // panjangnya 67 karakter, melewati batas 64 karakter MySQL. Argumen kedua
            // foreignId() hanya menamai index, bukan constraint-nya.
            $table->unsignedBigInteger('kpi_period_level_snapshot_id');

            $table->enum('category', ['EX', 'CO', 'LD']);
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->boolean('is_core')->default(false);
            $table->boolean('is_auto_filled')->default(false);
            $table->string('auto_source', 50)->nullable();
            $table->json('rubrics')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('kpi_period_level_snapshot_id', 'kpi_ind_snap_level_fk')
                ->references('id')->on('kpi_period_level_snapshots')->cascadeOnDelete();

            $table->unique(['kpi_period_id', 'code']);
            $table->index(['kpi_period_level_snapshot_id', 'category', 'sort_order'], 'kpi_ind_snap_level_cat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_period_indicator_snapshots');
    }
};
