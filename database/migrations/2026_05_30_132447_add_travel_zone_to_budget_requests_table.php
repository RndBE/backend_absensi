<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->unsignedInteger('distance_km')->nullable()->after('surat_tugas_date');
            $table->foreignId('travel_zone_id')->nullable()->after('distance_km')
                ->constrained('travel_zones')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->dropForeign(['travel_zone_id']);
            $table->dropColumn(['distance_km', 'travel_zone_id']);
        });
    }
};
