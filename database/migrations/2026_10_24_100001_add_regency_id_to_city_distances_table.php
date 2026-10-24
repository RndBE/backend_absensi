<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * city_distances kini terutama jadi alias: teks bebas yang sudah dikenali (alamat, nama tempat)
     * → kabupaten/kota-nya, supaya tidak dicari ulang. Jaraknya diambil dari regencies.
     */
    public function up(): void
    {
        Schema::table('city_distances', function (Blueprint $table) {
            $table->unsignedSmallInteger('regency_id')->nullable()->after('city_label');
            $table->unsignedInteger('distance_km')->nullable()->change();
        });

        // Cache lama per teks (hasil hitung otomatis) diganti jarak per kabupaten/kota; isinya
        // dihitung ulang saat dipakai. Koreksi manual dipertahankan.
        DB::table('city_distances')->where('source', 'routing')->delete();
    }

    public function down(): void
    {
        Schema::table('city_distances', function (Blueprint $table) {
            $table->dropColumn('regency_id');
        });
    }
};
