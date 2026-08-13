<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `departments` berbentuk pohon (lihat App\Support\DepartmentTree), tetapi penilaian silang
     * antar divisi butuh tahu simpul mana yang berperan sebagai DIVISI — unit yang saling menilai.
     *
     * Backfill di bawah menandai simpul akar sebagai divisi. Itu hanya titik awal: pada data
     * nyata satu akar bisa membawahi beberapa unit yang menurut kerangka KPI adalah divisi
     * terpisah (mis. PURCHASING dan FINANCE di bawah "FAT & SUPPLY CHAIN"). Admin wajib
     * meninjau ulang penandaan ini sebelum periode pertama dibuka.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('kpi_code', 20)->nullable()->after('name');
            $table->boolean('is_division')->default(false)->after('kpi_code');
            $table->boolean('is_shared_service')->default(false)->after('is_division');

            $table->index(['company_id', 'is_division']);
        });

        DB::table('departments')->whereNull('parent_id')->update(['is_division' => true]);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'is_division']);
            $table->dropColumn(['kpi_code', 'is_division', 'is_shared_service']);
        });
    }
};
