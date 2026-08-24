<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran pengumuman: satu foto, satu berkas, satu tautan.
 *
 * Sengaja kolom, bukan tabel lampiran tersendiri. Kebutuhan nyatanya "ini fotonya", "ini
 * surat edarannya", "ini tautan formulirnya" — masing-masing satu. Tabel terpisah baru
 * berguna kalau satu pengumuman perlu banyak berkas sekaligus, dan itu belum terjadi.
 *
 * Berkas disimpan di disk `local` (privat), bukan `public`. Pengumuman internal tidak boleh
 * bisa dibuka siapa pun yang menebak URL-nya; penyajiannya lewat rute yang memeriksa
 * perusahaan si pembaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('body');
            $table->string('file_path')->nullable()->after('image_path');
            // Nama asli disimpan supaya unduhan memakai nama yang dikenali pembaca,
            // bukan nama acak hasil penyimpanan.
            $table->string('file_name')->nullable()->after('file_path');
            $table->unsignedInteger('file_size')->nullable()->after('file_name');
            $table->string('file_mime')->nullable()->after('file_size');
            $table->string('link_url', 2048)->nullable()->after('file_mime');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'file_path', 'file_name', 'file_size', 'file_mime', 'link_url']);
        });
    }
};
