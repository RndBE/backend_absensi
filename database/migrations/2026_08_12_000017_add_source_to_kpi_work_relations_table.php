<?php

use App\Models\KpiWorkRelation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menandai siapa pemilik setiap baris rantai kerja: seeder atau admin lewat antarmuka.
     *
     * Tanpa kolom ini, halaman Rantai Kerja adalah jebakan. KpiOrgWiringSeeder::buildWorkChains()
     * bersifat otoritatif — pasangan yang tidak tercantum di `$workChains` dinonaktifkan. Begitu
     * admin menambah rantai lewat antarmuka lalu seeder dijalankan lagi (hal biasa: `db:seed`,
     * pemasangan di mesin lain, penyetelan ulang organisasi), seluruh tambahan itu mati tanpa
     * pesan apa pun dan admin tidak punya cara menebak kenapa.
     *
     * Dengan kolom ini seeder hanya berkuasa atas barisnya sendiri, dan kedua sumber bisa hidup
     * berdampingan: seeder tetap otoritatif untuk peta hasil konfirmasi manajemen, admin bebas
     * menambah rantai baru tanpa takut tertimpa.
     */
    public function up(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->string('source', 16)->default(KpiWorkRelation::SOURCE_MANUAL)->after('label');
        });

        // Semua baris yang sudah ada dibuat seeder — 98 pasangan hasil konfirmasi manajemen
        // plus 6 yang sudah dinonaktifkan. Bawaan kolom 'manual' hanya berlaku untuk baris baru.
        DB::table('kpi_work_relations')->update(['source' => KpiWorkRelation::SOURCE_SEEDER]);
    }

    public function down(): void
    {
        Schema::table('kpi_work_relations', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
