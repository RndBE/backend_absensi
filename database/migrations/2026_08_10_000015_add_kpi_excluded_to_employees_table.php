<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pembeda antara "belum disetel" dan "memang tidak pernah dinilai".
     *
     * Daftar karyawan yang belum bisa dinilai di halaman periode adalah daftar kerja admin —
     * isinya harus bisa dihabiskan. Akun demo, akun sistem, dan tenaga alih daya tidak akan
     * pernah punya level KPI, jadi kalau hanya mengandalkan `kpi_level_id` null mereka
     * menempel permanen di daftar itu dan menutupi karyawan yang benar-benar perlu disetel.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('is_kpi_excluded')->default(false)->after('is_cross_functional');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('is_kpi_excluded');
        });
    }
};
