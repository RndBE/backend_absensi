<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_zones', function (Blueprint $table) {
            $table->unsignedInteger('min_km')->default(0)->after('name');
            $table->unsignedInteger('max_km')->nullable()->after('min_km');
        });
    }

    public function down(): void
    {
        Schema::table('travel_zones', function (Blueprint $table) {
            $table->dropColumn(['min_km', 'max_km']);
        });
    }
};
