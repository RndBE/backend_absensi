<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hasil akhir per karyawan per periode.
     *
     * `final_score` (hasil perhitungan) dipisahkan dari `calibrated_score` (hasil sesi
     * kalibrasi Bab 9.2) supaya keadaan sebelum dan sesudah kalibrasi tetap bisa ditelusuri.
     * Presisi 4 desimal sesuai Bab 8.4 — predikat ditentukan dari angka ini, bukan dari
     * angka yang sudah dibulatkan ke 2 desimal untuk tampilan.
     */
    public function up(): void
    {
        Schema::create('kpi_final_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('kpi_period_level_snapshot_id', 'kpi_results_level_fk')
                ->nullable()->constrained('kpi_period_level_snapshots')->nullOnDelete();

            $table->decimal('score_excellence', 6, 4)->nullable();
            $table->decimal('score_contribution', 6, 4)->nullable();
            $table->decimal('score_leadership', 6, 4)->nullable();
            $table->decimal('final_score', 6, 4)->nullable();
            $table->string('grade', 1)->nullable();

            $table->decimal('calibrated_score', 6, 4)->nullable();
            $table->text('calibration_note')->nullable();

            $table->string('status', 20)->default('draft'); // draft | calibrated | approved
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->foreign('approved_by')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['kpi_period_id', 'employee_id'], 'kpi_final_results_unique');
            $table->index(['kpi_period_id', 'grade']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_final_results');
    }
};
