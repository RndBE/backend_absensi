<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat tugas perbaikan: surat baru (nomor baru, alur approval sendiri) yang merujuk surat
 * lama yang sudah ditandatangani. Surat lama tidak diubah dan tidak otomatis dibatalkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->foreignId('revises_letter_id')->nullable()->after('company_id')
                ->constrained('assignment_letters')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revises_letter_id');
        });
    }
};
