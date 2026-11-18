<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lpjs', function (Blueprint $table) {
            // Pengajuan ulang = LPJ baru yang merujuk LPJ yang ditolak. LPJ lama tetap tersimpan
            // sebagai arsip. Unique: satu LPJ ditolak hanya punya satu pengganti.
            $table->foreignId('resubmission_of_id')->nullable()->unique()->after('travel_report_id')
                ->constrained('lpjs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lpjs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resubmission_of_id');
        });
    }
};
