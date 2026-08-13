<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rantai kerja nyata antar orang — siapa menyerahkan apa kepada siapa.
     *
     * Terpisah dari `kpi_division_relations` karena isinya beda jenis. Relasi divisi menentukan
     * SIAPA MENILAI SIAPA dan terikat Bab 7.3: berbasis divisi, maksimal enam mitra, wajib dua
     * arah. Tabel ini tidak menentukan penilaian apa pun; ia hanya mencatat alur kerja yang
     * sesungguhnya berjalan, di tingkat orang, supaya bisa dipetakan dan ditinjau.
     *
     * Pemisahan itu disengaja. Menyatukan keduanya berarti setiap koreksi peta kerja ikut
     * menggeser siapa yang menilai siapa di tengah periode — tepat yang dilarang Bab 7.2.
     */
    public function up(): void
    {
        Schema::create('kpi_work_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('from_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('to_employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('label', 80);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Satu pasang orang boleh punya beberapa rantai berbeda (misal SPK dan invoice),
            // tetapi rantai yang sama tidak boleh tercatat dua kali.
            $table->unique(['from_employee_id', 'to_employee_id', 'label'], 'kpi_work_relations_pair_label_unique');
            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_work_relations');
    }
};
