<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skor mentah dan skor terkoreksi disimpan terpisah (Bab 11.4). `score_raw` beku setelah
     * assessment di-submit; setiap perubahan sesudahnya masuk ke `score_adjusted` dengan
     * alasan tertulis, sehingga koreksi bisa ditelusuri dan dibatalkan.
     *
     * Pola ini sama dengan `payroll_run_details.manual_overrides` yang sudah dipakai payroll.
     */
    public function up(): void
    {
        Schema::create('kpi_assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_assessment_id')->constrained('kpi_assessments')->cascadeOnDelete();
            $table->foreignId('kpi_period_indicator_snapshot_id', 'kpi_scores_indicator_fk')
                ->constrained('kpi_period_indicator_snapshots')->cascadeOnDelete();

            $table->unsignedTinyInteger('score_raw')->nullable();
            $table->text('evidence_text')->nullable();

            $table->unsignedTinyInteger('score_adjusted')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->text('adjusted_reason')->nullable();
            $table->timestamp('adjusted_at')->nullable();

            $table->timestamps();

            $table->foreign('adjusted_by')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['kpi_assessment_id', 'kpi_period_indicator_snapshot_id'], 'kpi_scores_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_assessment_scores');
    }
};
