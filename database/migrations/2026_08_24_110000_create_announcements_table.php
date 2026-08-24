<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengumuman HR yang tampil di Timeline dashboard karyawan.
 *
 * Beda mendasar dari isi Timeline yang lain: cuti dan ulang tahun DITURUNKAN dari data yang
 * sudah ada, sedangkan pengumuman adalah tulisan manusia — perlu disimpan, disunting, dan
 * punya masa berlaku.
 *
 * `expires_at` bukan hiasan. Timeline menampilkan seluruh riwayat tanpa batas mundur, jadi
 * pengumuman tanpa tanggal kedaluwarsa akan nangkring selamanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Disiapkan untuk penargetan per departemen, belum dipakai. NULL = seluruh
            // perusahaan, dan itu satu-satunya nilai yang dihasilkan versi ini.
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            // Kapan mulai tampil. Terisi saat disimpan; kolomnya ada supaya penjadwalan
            // bisa ditambahkan tanpa migrasi lagi.
            $table->dateTime('published_at');
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            // Jejak penulis. Pengumuman tampil ke seluruh kantor dan isinya teks bebas,
            // jadi harus selalu bisa ditelusuri siapa yang menulisnya.
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
