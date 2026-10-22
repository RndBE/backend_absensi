<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lpjs') || Schema::hasColumn('lpjs', 'transfer_date')) {
            return;
        }

        Schema::table('lpjs', function (Blueprint $table) {
            // Diisi finance saat menyetujui LPJ; tampil di header Excel LPJ.
            $table->date('transfer_date')->nullable()->after('kategori_notes');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lpjs') || ! Schema::hasColumn('lpjs', 'transfer_date')) {
            return;
        }

        Schema::table('lpjs', function (Blueprint $table) {
            $table->dropColumn('transfer_date');
        });
    }
};
