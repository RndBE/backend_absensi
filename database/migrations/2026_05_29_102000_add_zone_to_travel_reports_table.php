<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_reports', function (Blueprint $table) {
            $table->unsignedInteger('distance_km')->nullable()->after('destination_city');
            $table->foreignId('travel_zone_id')->nullable()->constrained('travel_zones')->nullOnDelete()->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('travel_reports', function (Blueprint $table) {
            $table->dropForeign(['travel_zone_id']);
            $table->dropColumn(['distance_km', 'travel_zone_id']);
        });
    }
};
