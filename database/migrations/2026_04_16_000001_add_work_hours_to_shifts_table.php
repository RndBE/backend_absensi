<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            // Jumlah jam kerja standar (mis. 8 jam untuk karyawan umum)
            // Jika durasi shift melebihi ini, sisanya dihitung sebagai lembur otomatis
            $table->unsignedTinyInteger('work_hours')->nullable()->after('end_time')
                ->comment('Jam kerja standar per hari (mis. 8). NULL = tidak ada lembur otomatis.');

            // Flag untuk mengaktifkan auto-generate overtime request
            $table->boolean('auto_overtime')->default(false)->after('work_hours')
                ->comment('Jika true, sistem akan otomatis membuat lembur approved untuk jam di luar work_hours');
        });
    }

    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['work_hours', 'auto_overtime']);
        });
    }
};
