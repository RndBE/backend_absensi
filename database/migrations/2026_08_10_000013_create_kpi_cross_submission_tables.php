<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Isian penilaian silang.
     *
     * `assessor_id` TETAP disimpan (Bab 11.4: anonim di permukaan, dapat ditelusuri untuk
     * penyalahgunaan). Kewajiban menyembunyikannya ada di lapisan tampilan — halaman hasil
     * pihak yang dinilai tidak boleh pernah menyertakan kolom ini.
     *
     * `is_valid` = false dipakai hasil deteksi anti-penyalahgunaan (Bab 7.8). Barisnya tidak
     * dihapus supaya HRD tetap bisa meninjau alasan pembuangannya.
     */
    public function up(): void
    {
        Schema::create('kpi_cross_layer_a', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('assessor_department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('target_department_id', 'kpi_layer_a_target_fk')
                ->constrained('departments')->cascadeOnDelete();

            $table->text('comment_positive')->nullable();
            $table->text('comment_improvement')->nullable();

            $table->boolean('is_valid')->default(true);
            $table->string('invalid_reason', 100)->nullable();
            $table->boolean('comment_hidden')->default(false);
            $table->string('hidden_reason', 100)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['kpi_period_id', 'assessor_id', 'target_department_id'], 'kpi_layer_a_unique');
            $table->index(['kpi_period_id', 'target_department_id'], 'kpi_layer_a_target_idx');
        });

        Schema::create('kpi_cross_layer_a_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_cross_layer_a_id', 'kpi_layer_a_scores_parent_fk')
                ->constrained('kpi_cross_layer_a')->cascadeOnDelete();
            $table->string('item_code', 20);
            $table->unsignedTinyInteger('score');

            // Hasil pemangkasan persekongkolan (Bab 7.8c). Skor mentah tidak pernah ditimpa
            // supaya koreksi bisa ditelusuri dan dibatalkan.
            $table->decimal('score_corrected', 6, 4)->nullable();
            $table->string('correction_reason', 50)->nullable();

            $table->timestamps();

            $table->unique(['kpi_cross_layer_a_id', 'item_code'], 'kpi_layer_a_scores_unique');
        });

        Schema::create('kpi_cross_layer_b', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('assessor_department_id', 'kpi_layer_b_assessor_dept_fk')
                ->constrained('departments')->cascadeOnDelete();
            $table->foreignId('target_employee_id')->constrained('employees')->cascadeOnDelete();

            $table->text('comment')->nullable();

            $table->boolean('is_valid')->default(true);
            $table->string('invalid_reason', 100)->nullable();
            $table->boolean('comment_hidden')->default(false);
            $table->string('hidden_reason', 100)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['kpi_period_id', 'assessor_id', 'target_employee_id'], 'kpi_layer_b_unique');
            $table->index(['kpi_period_id', 'target_employee_id'], 'kpi_layer_b_target_idx');
        });

        Schema::create('kpi_cross_layer_b_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_cross_layer_b_id', 'kpi_layer_b_scores_parent_fk')
                ->constrained('kpi_cross_layer_b')->cascadeOnDelete();
            $table->string('item_code', 20);
            $table->unsignedTinyInteger('score');
            $table->decimal('score_corrected', 6, 4)->nullable();
            $table->string('correction_reason', 50)->nullable();
            $table->timestamps();

            $table->unique(['kpi_cross_layer_b_id', 'item_code'], 'kpi_layer_b_scores_unique');
        });

        /**
         * Hasil akhir penilaian silang. Baris dengan `employee_id` null adalah hasil DIVISI
         * (Lapis A); baris dengan `employee_id` terisi adalah skor kolaborasi individu.
         */
        Schema::create('kpi_cross_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->cascadeOnDelete();

            $table->decimal('score_a_raw', 6, 4)->nullable();
            $table->decimal('score_a_corrected', 6, 4)->nullable();
            $table->decimal('score_b_raw', 6, 4)->nullable();
            $table->decimal('score_b_corrected', 6, 4)->nullable();
            $table->decimal('score_collaboration', 6, 4)->nullable();

            $table->decimal('superior_adjustment', 5, 2)->nullable();
            $table->text('adjustment_reason')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            $table->boolean('adjustment_needs_approval')->default(false);
            $table->unsignedBigInteger('adjustment_approved_by')->nullable();

            $table->boolean('quorum_met')->default(false);
            $table->unsignedSmallInteger('assessor_count')->default(0);
            $table->unsignedSmallInteger('division_count')->default(0);
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->foreign('adjusted_by')->references('id')->on('employees')->nullOnDelete();
            $table->foreign('adjustment_approved_by')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['kpi_period_id', 'department_id', 'employee_id'], 'kpi_cross_results_unique');
        });

        // Koreksi kemurahan hati per penilai (Bab 7.8d).
        Schema::create('kpi_leniency_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('assessor_mean', 6, 4);
            $table->decimal('company_mean', 6, 4);
            $table->decimal('correction_value', 6, 4);
            $table->timestamps();

            $table->unique(['kpi_period_id', 'assessor_id'], 'kpi_leniency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_leniency_corrections');
        Schema::dropIfExists('kpi_cross_results');
        Schema::dropIfExists('kpi_cross_layer_b_scores');
        Schema::dropIfExists('kpi_cross_layer_b');
        Schema::dropIfExists('kpi_cross_layer_a_scores');
        Schema::dropIfExists('kpi_cross_layer_a');
    }
};
