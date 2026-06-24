<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lpjs') || Schema::hasColumn('lpjs', 'kategori_notes')) {
            return;
        }

        Schema::table('lpjs', function (Blueprint $table) {
            // Catatan per kategori untuk tabel ringkasan (mis. "Reimbursement").
            $table->json('kategori_notes')->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lpjs') || ! Schema::hasColumn('lpjs', 'kategori_notes')) {
            return;
        }

        Schema::table('lpjs', function (Blueprint $table) {
            $table->dropColumn('kategori_notes');
        });
    }
};
