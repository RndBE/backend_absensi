<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lpj_items') || Schema::hasColumn('lpj_items', 'kategori')) {
            return;
        }

        Schema::table('lpj_items', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('uraian');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('lpj_items') || ! Schema::hasColumn('lpj_items', 'kategori')) {
            return;
        }

        Schema::table('lpj_items', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
