<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tujuan perjalanan pada pengajuan anggaran: ke mana jarak (dan zona uang makan) dihitung,
     * titiknya, dan asalnya, supaya approver bisa memeriksanya di peta. Titik yang dipilih
     * sendiri di peta (distance_source = map) tidak bisa diverifikasi server.
     */
    public function up(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->string('destination_label')->nullable()->after('travel_zone_id');
            $table->decimal('destination_lat', 10, 7)->nullable()->after('destination_label');
            $table->decimal('destination_lng', 10, 7)->nullable()->after('destination_lat');
            $table->string('distance_source', 20)->nullable()->after('destination_lng'); // regency|place|map|text|letter
            $table->unsignedSmallInteger('destination_regency_id')->nullable()->after('distance_source');
        });
    }

    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->dropColumn(['destination_label', 'destination_lat', 'destination_lng', 'distance_source', 'destination_regency_id']);
        });
    }
};
