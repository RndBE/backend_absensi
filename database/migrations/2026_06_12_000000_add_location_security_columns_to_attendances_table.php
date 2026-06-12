<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->decimal('clock_in_accuracy_meters', 8, 2)->nullable()->after('clock_in_lng');
            $table->boolean('clock_in_is_mocked')->default(false)->after('clock_in_accuracy_meters');
            $table->timestamp('clock_in_location_recorded_at')->nullable()->after('clock_in_is_mocked');
            $table->decimal('clock_out_accuracy_meters', 8, 2)->nullable()->after('clock_out_lng');
            $table->boolean('clock_out_is_mocked')->default(false)->after('clock_out_accuracy_meters');
            $table->timestamp('clock_out_location_recorded_at')->nullable()->after('clock_out_is_mocked');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn([
                'clock_in_accuracy_meters',
                'clock_in_is_mocked',
                'clock_in_location_recorded_at',
                'clock_out_accuracy_meters',
                'clock_out_is_mocked',
                'clock_out_location_recorded_at',
            ]);
        });
    }
};
