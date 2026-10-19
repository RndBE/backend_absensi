<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur persetujuan dipindah ke SESUDAH surat terbit: status pending_approval dan approved tidak
 * dipakai lagi. Surat yang tertahan di dua status itu belum bernomor, jadi dikembalikan ke draft
 * (barisnya persetujuan lama dibuang; paraf diminta ulang begitu surat diterbitkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('assignment_letters')->whereIn('status', ['pending_approval', 'approved'])->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('assignment_letter_approvals')) {
            DB::table('assignment_letter_approvals')->whereIn('assignment_letter_id', $ids)->delete();
        }

        DB::table('assignment_letters')->whereIn('id', $ids)->update([
            'status' => 'draft',
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
        ]);
    }

    public function down(): void
    {
        // Tidak ada yang perlu dikembalikan: status lama sudah tidak dikenal aplikasi.
    }
};
