<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Urutan kotak paraf di cetakan. Sebelumnya mengikuti id, tapi atasan yang baru ditambahkan saat
 * peserta berubah mendapat id lebih besar dan tercetak setelah HRD/yang lain dalam grupnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignment_letter_approvals', function (Blueprint $table) {
            $table->unsignedSmallInteger('sort_order')->default(0)->after('step_group');
        });

        // Baris yang sudah ada: urutan sesuai id (urutan pembuatan) per surat.
        $position = [];
        foreach (DB::table('assignment_letter_approvals')->orderBy('assignment_letter_id')->orderBy('step_group')->orderBy('id')->get(['id', 'assignment_letter_id']) as $row) {
            $position[$row->assignment_letter_id] = ($position[$row->assignment_letter_id] ?? -1) + 1;
            DB::table('assignment_letter_approvals')->where('id', $row->id)->update(['sort_order' => $position[$row->assignment_letter_id]]);
        }
    }

    public function down(): void
    {
        Schema::table('assignment_letter_approvals', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
