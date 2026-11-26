<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asal clock in / clock out: "app" (aplikasi HP) atau "web" (portal karyawan).
 *
 * Dipakai untuk mengukur siapa yang masih presensi lewat portal sebelum jalur itu ditutup
 * (pengaturan `allow_web_clockin`). NULL berarti jam itu tidak diisi karyawan sendiri: input
 * manual admin, clock-out otomatis, koreksi presensi, atau data dari sebelum kolom ini ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('clock_in_source', 10)->nullable()->after('clock_in_location_recorded_at');
            $table->string('clock_out_source', 10)->nullable()->after('clock_out_location_recorded_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn(['clock_in_source', 'clock_out_source']);
        });
    }
};
