<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu penilai menilai satu orang pada satu periode.
     *
     * Bab 2.1 memberi dua penilai untuk L4 dan L3 (utama 70%, pendukung 30%) dan satu untuk
     * L2 (100%). `weight` disimpan per baris, bukan disimpulkan dari `assessor_role`, supaya
     * pembagian bobot bisa berbeda antar perusahaan tanpa mengubah kode.
     */
    public function up(): void
    {
        Schema::create('kpi_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedBigInteger('assessor_id');
            $table->string('assessor_role', 20); // primary | supporting
            $table->decimal('weight', 5, 2)->default(100);
            $table->string('status', 20)->default('draft'); // draft | submitted
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign('assessor_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unique(['kpi_period_id', 'employee_id', 'assessor_id'], 'kpi_assessments_unique');
            $table->index(['assessor_id', 'status']);
            $table->index(['kpi_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_assessments');
    }
};
