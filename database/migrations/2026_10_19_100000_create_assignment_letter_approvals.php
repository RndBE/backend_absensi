<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Approval surat tugas: atasan L3/L2 tiap peserta (paralel), lalu HRD, lalu GA HSE. Direktur
 * tidak ikut: ia menandatangani surat secara basah setelah surat terbit.
 *
 * Satu baris = satu kotak paraf di surat. Baris dibangun ulang tiap surat diajukan, jadi
 * pengajuan ulang setelah penolakan atau penarikan selalu mulai dari nol. Riwayat keputusan
 * ikut dicatat di approval_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_letter_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_letter_id')->constrained()->cascadeOnDelete();
            // level3 / level2 = atasan peserta; hrd / ga_hse = pemegang izin approve_hrd / approve_hse.
            $table->string('role', 20);
            // 1 = atasan (paralel), 2 = HRD, 3 = GA HSE. Grup berikutnya baru jalan setelah grup sebelumnya selesai.
            $table->unsignedTinyInteger('step_group');
            $table->string('label');
            // Atasan yang ditunjuk; kosong untuk HRD & GA HSE (siapa pun pemegang izinnya).
            $table->foreignId('approver_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->foreignId('acted_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('acted_at')->nullable();
            $table->string('notes', 1000)->nullable();
            // Salinan tanda tangan saat disetujui (disk public), supaya surat tak berubah kalau ttd diganti.
            $table->string('signature_path')->nullable();
            $table->timestamps();

            // Nama eksplisit: nama otomatis untuk indeks pertama 65 karakter, MySQL maksimal 64.
            $table->index(['assignment_letter_id', 'step_group'], 'al_approvals_letter_group_index');
            $table->index(['approver_id', 'status'], 'al_approvals_approver_status_index');
        });

        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('employees')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('submitted_by');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('employees')->nullOnDelete();
            $table->string('rejection_reason', 1000)->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_letters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn(['submitted_at', 'approved_at', 'rejected_at', 'rejection_reason']);
        });

        Schema::dropIfExists('assignment_letter_approvals');
    }
};
