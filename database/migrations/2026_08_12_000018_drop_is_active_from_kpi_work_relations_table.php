<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pasangan rantai kerja dihapus, tidak lagi ditandai nonaktif.
     *
     * Keputusan manajemen 2026-08-12. Penonaktifan dipilih semula agar peta lama bisa
     * direkonstruksi saat hasil KPI ditinjau ulang, tetapi alasan itu tidak berlaku di tabel ini:
     * tidak ada perhitungan KPI mana pun yang membaca `kpi_work_relations`, jadi tidak ada angka
     * yang bergantung padanya, dan riwayat peta versi seeder sudah tersimpan di `$workChains`
     * lewat git. Yang tertinggal hanyalah baris nonaktif yang tidak bisa dilihat di halaman mana
     * pun dan tidak bisa diaktifkan siapa pun — bobot mati yang membingungkan.
     *
     * Setelah kolomnya dicabut, satu-satunya arti sebuah baris adalah: relasi ini berlaku.
     */
    public function up(): void
    {
        // Baris nonaktif adalah pasangan yang sudah dicabut manajemen — di bawah model baru
        // artinya terhapus. Dibuang lebih dulu, kalau tidak semuanya ikut hidup kembali begitu
        // kolom penandanya hilang.
        DB::table('kpi_work_relations')->where('is_active', false)->delete();

        // Indeks pengganti dibuat LEBIH DULU. Indeks lama (company_id, is_active) dipakai foreign
        // key company_id, dan MySQL menolak mencabut indeks terakhir yang menopang sebuah FK:
        // "Cannot drop index ...: needed in a foreign key constraint". Indeks baru berawalan
        // company_id, jadi begitu ada, FK-nya punya penopang lain dan yang lama boleh dicabut.
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->index(['company_id', 'label']);
        });

        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }

    /**
     * Kolomnya bisa dikembalikan, isinya tidak. Baris yang sudah terhapus tidak tercatat di mana
     * pun selain riwayat git `$workChains`, jadi seluruh baris yang tersisa dianggap aktif.
     */
    public function down(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'label']);
            $table->boolean('is_active')->default(true)->after('source');
            $table->index(['company_id', 'is_active']);
        });
    }
};
