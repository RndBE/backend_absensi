<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skema minimum untuk menguji modul KPI di atas sqlite in-memory.
 *
 * Mengikuti kebiasaan test lain di repo ini: tabel dibangun langsung, bukan lewat
 * `migrate`, supaya test tidak ikut menanggung migration lama yang khusus MySQL.
 * Bentuk kolomnya sengaja dijaga sama dengan migration 2026_08_10_*.
 */
trait CreatesKpiSchema
{
    protected function createKpiSchema(): void
    {
        Schema::dropIfExists('kpi_improvement_plans');
        Schema::dropIfExists('kpi_appeals');
        Schema::dropIfExists('kpi_leniency_corrections');
        Schema::dropIfExists('kpi_cross_results');
        Schema::dropIfExists('kpi_cross_layer_b_scores');
        Schema::dropIfExists('kpi_cross_layer_b');
        Schema::dropIfExists('kpi_cross_layer_a_scores');
        Schema::dropIfExists('kpi_cross_layer_a');
        Schema::dropIfExists('kpi_cross_assessors');
        Schema::dropIfExists('kpi_period_cross_item_snapshots');
        Schema::dropIfExists('kpi_cross_items');
        Schema::dropIfExists('kpi_division_relations');
        Schema::dropIfExists('kpi_final_results');
        Schema::dropIfExists('kpi_assessment_scores');
        Schema::dropIfExists('kpi_assessments');
        Schema::dropIfExists('kpi_period_indicator_snapshots');
        Schema::dropIfExists('kpi_period_level_snapshots');
        Schema::dropIfExists('kpi_periods');
        Schema::dropIfExists('kpi_indicator_rubrics');
        Schema::dropIfExists('kpi_indicators');
        Schema::dropIfExists('kpi_levels');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('kpi_code', 20)->nullable();
            $table->boolean('is_division')->default(false);
            $table->boolean('is_shared_service')->default(false);
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('position')->nullable();
            $table->integer('job_level')->nullable();
            $table->unsignedBigInteger('kpi_level_id')->nullable();
            $table->boolean('is_cross_functional')->default(false);
            $table->boolean('is_kpi_excluded')->default(false);
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('code', 5);
            $table->string('name');
            $table->boolean('is_assessed')->default(true);
            $table->decimal('weight_excellence', 5, 2)->default(0);
            $table->decimal('weight_contribution', 5, 2)->default(0);
            $table->decimal('weight_leadership', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('kpi_level_id');
            // NULL = indikator bawaan level; terisi = milik satu orang (App\Support\KpiIndicatorSet).
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('category', 2);
            $table->string('code', 20);
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('weight', 5, 2)->default(0);
            $table->boolean('is_core')->default(false);
            $table->boolean('is_auto_filled')->default(false);
            $table->string('auto_source', 50)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_indicator_rubrics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_indicator_id');
            $table->unsignedTinyInteger('score');
            $table->text('description');
            $table->timestamps();
        });

        Schema::create('kpi_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('cross_fill_start')->nullable();
            $table->date('cross_fill_end')->nullable();
            $table->date('fill_start')->nullable();
            $table->date('fill_end')->nullable();
            $table->string('status', 20)->default('draft');
            $table->boolean('is_trial')->default(false);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_period_level_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('kpi_level_id')->nullable();
            $table->string('code', 5);
            $table->string('name');
            $table->boolean('is_assessed')->default(true);
            $table->decimal('weight_excellence', 5, 2)->default(0);
            $table->decimal('weight_contribution', 5, 2)->default(0);
            $table->decimal('weight_leadership', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('kpi_period_indicator_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('kpi_indicator_id')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('kpi_period_level_snapshot_id');
            $table->string('category', 2);
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
        });

        Schema::create('kpi_assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('assessor_id');
            $table->string('assessor_role', 20);
            $table->decimal('weight', 5, 2)->default(100);
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_assessment_scores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_assessment_id');
            $table->unsignedBigInteger('kpi_period_indicator_snapshot_id');
            $table->unsignedTinyInteger('score_raw')->nullable();
            $table->text('evidence_text')->nullable();
            $table->unsignedTinyInteger('score_adjusted')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();
            $table->text('adjusted_reason')->nullable();
            $table->timestamp('adjusted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_final_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('kpi_period_level_snapshot_id')->nullable();
            $table->decimal('score_excellence', 6, 4)->nullable();
            $table->decimal('score_contribution', 6, 4)->nullable();
            $table->decimal('score_leadership', 6, 4)->nullable();
            $table->decimal('final_score', 6, 4)->nullable();
            $table->string('grade', 1)->nullable();
            $table->decimal('calibrated_score', 6, 4)->nullable();
            $table->text('calibration_note')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();
        });

        $this->createKpiCrossSchema();
    }

    /** Tabel penilaian silang dan tindak lanjut (Fase 3–5). */
    protected function createKpiCrossSchema(): void
    {
        Schema::create('kpi_division_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('partner_department_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_cross_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('layer', 1);
            $table->string('code', 20);
            $table->string('name');
            $table->text('question');
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('kpi_period_cross_item_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('kpi_cross_item_id')->nullable();
            $table->string('layer', 1);
            $table->string('code', 20);
            $table->string('name');
            $table->text('question');
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('kpi_cross_assessors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('employee_id');
            $table->boolean('can_assess_individual')->default(false);
            $table->timestamps();
        });

        foreach (['a' => 'target_department_id', 'b' => 'target_employee_id'] as $layer => $targetColumn) {
            Schema::create("kpi_cross_layer_{$layer}", function (Blueprint $table) use ($layer, $targetColumn) {
                $table->id();
                $table->unsignedBigInteger('kpi_period_id');
                $table->unsignedBigInteger('assessor_id');
                $table->unsignedBigInteger('assessor_department_id');
                $table->unsignedBigInteger($targetColumn);

                if ($layer === 'a') {
                    $table->text('comment_positive')->nullable();
                    $table->text('comment_improvement')->nullable();
                } else {
                    $table->text('comment')->nullable();
                }

                $table->boolean('is_valid')->default(true);
                $table->string('invalid_reason', 100)->nullable();
                $table->boolean('comment_hidden')->default(false);
                $table->string('hidden_reason', 100)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();
            });

            Schema::create("kpi_cross_layer_{$layer}_scores", function (Blueprint $table) use ($layer) {
                $table->id();
                $table->unsignedBigInteger("kpi_cross_layer_{$layer}_id");
                $table->string('item_code', 20);
                $table->unsignedTinyInteger('score');
                $table->decimal('score_corrected', 6, 4)->nullable();
                $table->string('correction_reason', 50)->nullable();
                $table->timestamps();
            });
        }

        Schema::create('kpi_cross_results', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('department_id');
            $table->unsignedBigInteger('employee_id')->nullable();
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
        });

        Schema::create('kpi_leniency_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('assessor_id');
            $table->decimal('assessor_mean', 6, 4);
            $table->decimal('company_mean', 6, 4);
            $table->decimal('correction_value', 6, 4);
            $table->timestamps();
        });

        Schema::create('kpi_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->unsignedBigInteger('employee_id');
            $table->text('reason');
            $table->timestamp('submitted_at')->nullable();
            $table->date('deadline_at')->nullable();
            $table->string('status', 20)->default('submitted');
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kpi_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_period_id');
            $table->string('subject_type', 20);
            $table->unsignedBigInteger('subject_id');
            $table->string('trigger_reason', 255);
            $table->decimal('trigger_score', 6, 4)->nullable();
            $table->text('plan_text')->nullable();
            $table->date('due_date')->nullable();
            $table->date('review_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }
}
