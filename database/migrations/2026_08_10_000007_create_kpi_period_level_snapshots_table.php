<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dibuat otomatis saat status periode berubah menjadi 'open'. Setelah itu perhitungan
     * HANYA membaca snapshot — perubahan master `kpi_levels` tidak boleh menggeser hasil
     * periode yang sedang berjalan atau sudah selesai (Bab 11.4).
     *
     * Kolom code/name ikut disalin supaya baris snapshot tetap terbaca meski master dihapus.
     */
    public function up(): void
    {
        Schema::create('kpi_period_level_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('kpi_level_id')->nullable()->constrained('kpi_levels')->nullOnDelete();
            $table->string('code', 5);
            $table->string('name');
            $table->boolean('is_assessed')->default(true);
            $table->decimal('weight_excellence', 5, 2)->default(0);
            $table->decimal('weight_contribution', 5, 2)->default(0);
            $table->decimal('weight_leadership', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kpi_period_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_period_level_snapshots');
    }
};
