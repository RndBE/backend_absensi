<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar tampil/sembunyi untuk pengumuman.
 *
 * Menggantikan peran lama `expires_at` sebagai cara menurunkan pengumuman dari Timeline.
 * Tanggal kedaluwarsa memaksa penulis memikirkan "sampai kapan" pada saat dia justru sedang
 * memikirkan isi; saklar bisa dibalik kapan saja tanpa menebak apa pun.
 *
 * Default `true`: pengumuman yang baru ditulis memang untuk ditampilkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true)->after('is_pinned');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
