<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_reports', function (Blueprint $table) {
            // Snapshot batas pengumpulan LHP saat pertama disubmit. Disnapshot supaya
            // status telat tidak berubah kalau data libur/setting diubah belakangan.
            $table->date('submission_deadline')->nullable()->after('return_date');
            $table->boolean('is_late')->default(false)->after('submission_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('travel_reports', function (Blueprint $table) {
            $table->dropColumn(['submission_deadline', 'is_late']);
        });
    }
};
