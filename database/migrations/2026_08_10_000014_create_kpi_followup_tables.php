<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tindak lanjut hasil (Bab 9.4 dan 10.1).
     *
     * Angka tanpa tindak lanjut membuat orang berhenti mengisi dengan serius pada periode
     * kedua — dua tabel ini yang menahan hal itu.
     */
    public function up(): void
    {
        Schema::create('kpi_appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('submitted_at')->nullable();

            // Tenggat 7 hari kerja sejak hasil diterima; disimpan agar tidak dihitung ulang
            // tiap kali halaman dibuka dan tidak bergeser saat kalender libur berubah.
            $table->date('deadline_at')->nullable();

            $table->string('status', 20)->default('submitted'); // submitted | accepted | rejected
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('decided_by')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['kpi_period_id', 'employee_id'], 'kpi_appeals_unique');
            $table->index(['kpi_period_id', 'status']);
        });

        Schema::create('kpi_improvement_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->string('subject_type', 20); // division | employee
            $table->unsignedBigInteger('subject_id');
            $table->string('trigger_reason', 255);
            $table->decimal('trigger_score', 6, 4)->nullable();
            $table->text('plan_text')->nullable();
            $table->date('due_date')->nullable();
            $table->date('review_date')->nullable();
            $table->string('status', 20)->default('open'); // open | in_progress | done | overdue
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['kpi_period_id', 'subject_type', 'subject_id'], 'kpi_improvement_plans_unique');
            $table->index(['kpi_period_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_improvement_plans');
        Schema::dropIfExists('kpi_appeals');
    }
};
