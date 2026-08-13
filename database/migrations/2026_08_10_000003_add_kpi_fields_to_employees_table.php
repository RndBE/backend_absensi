<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `job_level` sengaja TIDAK dipakai ulang sebagai level KPI. Kolom itu integer bebas dan
     * sudah menjadi dasar App\Models\ApprovalRule::min_approver_level serta urutan tampilan;
     * mengikatnya ke L1–L4 akan mengubah perilaku approval yang sudah jalan.
     *
     * `kpi_level_id` berdiri sendiri, di-backfill dari job_level 1–4 sebagai tebakan awal.
     * Karyawan dengan job_level di luar 1–4 (atau kosong) dibiarkan null dan harus diisi admin.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('kpi_level_id')->nullable()->after('job_level')
                ->constrained('kpi_levels')->nullOnDelete();
            $table->boolean('is_cross_functional')->default(false)->after('kpi_level_id');
        });

        if (! Schema::hasTable('kpi_levels')) {
            return;
        }

        $levels = DB::table('kpi_levels')->get(['id', 'company_id', 'code']);

        foreach ($levels as $level) {
            $jobLevel = (int) substr($level->code, 1);

            if ($jobLevel < 1 || $jobLevel > 4) {
                continue;
            }

            DB::table('employees')
                ->where('company_id', $level->company_id)
                ->where('job_level', $jobLevel)
                ->update(['kpi_level_id' => $level->id]);
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kpi_level_id');
            $table->dropColumn('is_cross_functional');
        });
    }
};
