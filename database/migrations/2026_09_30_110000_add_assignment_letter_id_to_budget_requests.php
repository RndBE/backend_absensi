<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan anggaran merujuk surat tugas. Satu surat tugas dipakai satu anggaran aktif (peserta
 * lain ikut sebagai participants); anggaran yang ditolak melepas surat tugasnya supaya bisa
 * diajukan ulang. Aturan itu dijaga di App\Support\AssignmentLetterBudgetLink, bukan di indeks
 * unik, karena anggaran yang ditolak tetap menyimpan rujukannya sebagai riwayat.
 *
 * surat_tugas_no / surat_tugas_date tetap ada: diisi otomatis dari surat tugas, dan dipakai
 * data lama, cetak LPJ, ekspor Excel, serta aplikasi mobile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->foreignId('assignment_letter_id')->nullable()->after('surat_tugas_date')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('budget_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignment_letter_id');
        });
    }
};
