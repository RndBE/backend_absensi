<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_reports', function (Blueprint $table) {
            // Pengajuan ulang = LHP baru yang merujuk LHP yang ditolak. LHP lama tetap
            // tersimpan sebagai arsip. Unique: satu LHP ditolak hanya punya satu pengganti.
            $table->foreignId('resubmission_of_id')->nullable()->unique()->after('budget_request_id')
                ->constrained('travel_reports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('travel_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resubmission_of_id');
        });
    }
};
