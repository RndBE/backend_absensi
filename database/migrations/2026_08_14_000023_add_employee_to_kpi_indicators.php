<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indikator milik satu orang, bukan satu level.
     *
     * General Excellence adalah "seberapa baik seseorang mengerjakan TUGAS INTI JABATANNYA"
     * (Bab 1.1). Tugas inti seorang welder tidak sama dengan tugas inti staf purchasing, jadi
     * menilai keduanya dengan delapan indikator yang sama membuat kategori berbobot terbesar —
     * 70% untuk L4 — jadi paling tidak spesifik. Keputusan manajemen 14 Agustus 2026: indikator
     * Excellence dibuat per orang.
     *
     * ══ Bawaan level tetap ada ══
     *
     * `employee_id` boleh NULL, dan yang NULL adalah indikator bawaan levelnya. Orang tanpa
     * indikator sendiri tetap dinilai dengan bawaan itu — kalau tidak, seluruh 31 karyawan harus
     * dirumuskan indikatornya lebih dulu sebelum periode mana pun bisa dibuka.
     *
     * Aturannya: kalau seseorang punya indikator sendiri di sebuah kategori, indikator itu
     * MENGGANTI seluruh set kategori tersebut dari level — bukan menambah. Menambah akan membuat
     * jumlah bobot kategori lewat dari 100 dan menggeser arti skornya. Satu-satunya tempat aturan
     * itu ditulis adalah App\Support\KpiIndicatorSet.
     *
     * Kolomnya sengaja tidak dibatasi kategori EX di basis data. Yang dibatasi antarmukanya, supaya
     * mekanismenya tetap umum tanpa memaksa migrasi lagi kalau nanti Leadership pun perlu per orang.
     */
    public function up(): void
    {
        Schema::table('kpi_indicators', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('kpi_level_id')
                ->constrained('employees')->cascadeOnDelete();

            $table->index(['employee_id', 'category', 'sort_order'], 'kpi_ind_employee_cat_idx');
        });

        /*
         * Snapshot ikut membawa pemiliknya. Tanpa ini, periode yang sudah dibuka tidak bisa lagi
         * menjawab "indikator siapa ini" begitu indikator sumbernya diubah atau dihapus — padahal
         * membekukan keadaan awal periode justru inti Bab 7.2.
         *
         * Kunci unik (kpi_period_id, code) tidak perlu disentuh: kode indikator sudah unik per
         * perusahaan, jadi indikator milik orang pun kodenya berbeda dari bawaan level.
         */
        Schema::table('kpi_period_indicator_snapshots', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->after('kpi_indicator_id')
                ->constrained('employees')->nullOnDelete();

            $table->index(['kpi_period_id', 'employee_id', 'category'], 'kpi_ind_snap_emp_cat_idx');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_period_indicator_snapshots', function (Blueprint $table) {
            $table->dropIndex('kpi_ind_snap_emp_cat_idx');
            $table->dropConstrainedForeignId('employee_id');
        });

        Schema::table('kpi_indicators', function (Blueprint $table) {
            $table->dropIndex('kpi_ind_employee_cat_idx');
            $table->dropConstrainedForeignId('employee_id');
        });
    }
};
