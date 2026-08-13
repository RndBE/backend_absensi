<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master penilaian silang antar divisi (Bab 7).
     *
     * Butir XA/XB disimpan sebagai BARIS, bukan kolom `score_xa01 … score_xa08`. Kerangka
     * membolehkan admin mengubah butir dan bobotnya; kalau butir jadi kolom, setiap
     * perubahan menuntut migration baru — bertentangan dengan janji "dapat diubah admin".
     */
    public function up(): void
    {
        // Matriks relasi kerja Bab 7.3 — dibuat sekali, ditinjau setahun sekali.
        Schema::create('kpi_division_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->unsignedBigInteger('partner_department_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('partner_department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->unique(['department_id', 'partner_department_id'], 'kpi_division_relations_unique');
            $table->index(['company_id', 'is_active']);
        });

        // Butir penilaian silang: Lapis A (divisi) dan Lapis B (individu).
        Schema::create('kpi_cross_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('layer', 1); // A | B
            $table->string('code', 20);
            $table->string('name');
            $table->text('question');
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'layer', 'sort_order']);
        });

        // Dibekukan saat periode dibuka, sama seperti indikator KPI.
        Schema::create('kpi_period_cross_item_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('kpi_cross_item_id', 'kpi_cross_snap_item_fk')
                ->nullable()->constrained('kpi_cross_items')->nullOnDelete();
            $table->string('layer', 1);
            $table->string('code', 20);
            $table->string('name');
            $table->text('question');
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kpi_period_id', 'code'], 'kpi_cross_snap_unique');
            $table->index(['kpi_period_id', 'layer', 'sort_order'], 'kpi_cross_snap_layer_idx');
        });

        /**
         * Penilai resmi per divisi (Bab 7.4). Jumlahnya sengaja dibatasi 3–5 orang per divisi;
         * membuka pengisian untuk semua orang menurunkan mutu jawaban.
         */
        Schema::create('kpi_cross_assessors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_period_id')->constrained('kpi_periods')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('can_assess_individual')->default(false); // Lapis B, bukan hanya Lapis A
            $table->timestamps();

            $table->unique(['kpi_period_id', 'employee_id'], 'kpi_cross_assessors_unique');
            $table->index(['kpi_period_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_cross_assessors');
        Schema::dropIfExists('kpi_period_cross_item_snapshots');
        Schema::dropIfExists('kpi_cross_items');
        Schema::dropIfExists('kpi_division_relations');
    }
};
