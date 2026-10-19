<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Support\AssignmentLetterInitialBoxes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pemeriksaan sebelum mengaktifkan paraf digital surat tugas (ASSIGNMENT_LETTER_REQUIRE_APPROVAL=true).
 * Hanya membaca; tidak mengubah data.
 *
 *   php artisan assignment-letters:precheck
 */
class AssignmentLetterPrecheck extends Command
{
    protected $signature = 'assignment-letters:precheck';

    protected $description = 'Cek kesiapan data untuk paraf digital surat tugas (atasan di rantai cuti, tanda tangan, draft lama). Hanya membaca.';

    public function handle(): int
    {
        if (! Schema::hasTable('assignment_letter_approvals')) {
            $this->error('Tabel assignment_letter_approvals belum ada. Jalankan php artisan migrate dulu.');

            return self::FAILURE;
        }

        $eligible = Employee::where('is_active', true)
            ->whereIn('employment_status', config('assignment_letters.eligible_employment_statuses'))
            ->get(['id', 'company_id', 'full_name', 'position', 'job_level']);

        $superiors = [];
        foreach ($eligible->groupBy('company_id') as $companyId => $group) {
            $superiors += AssignmentLetterInitialBoxes::superiorsFor((int) $companyId, $group->pluck('id')->all());
        }

        // Sama dengan aturan AssignmentLetterApprovalFlow::plan(): level di bawah 2 wajib punya atasan L3/L2.
        $blocked = $eligible->filter(fn (Employee $e) => ((int) $e->job_level === 0 || (int) $e->job_level > 2) && empty($superiors[$e->id]));

        $this->line("Karyawan layak diberi surat tugas: {$eligible->count()}");

        if ($blocked->isEmpty()) {
            $this->info('Semua punya atasan L3/L2 di rantai cuti (atau memang level 2 ke atas).');
        } else {
            $this->warn("Tanpa atasan L3/L2 di rantai cuti (surat yang memuat mereka TIDAK bisa diterbitkan): {$blocked->count()}");
            foreach ($blocked as $e) {
                $this->line("  - {$e->full_name} ({$e->position}, level ".($e->job_level ?: '-').')');
            }
            $this->line('  Lengkapi di menu Karyawan, Approver (tipe cuti), atau abaikan bila orangnya memang tidak diberi surat tugas.');
        }

        $active = Employee::where('is_active', true)->count();
        $noSignature = Employee::where('is_active', true)->whereNull('signature')->count();
        $this->line("Tanpa tanda tangan digital: {$noSignature} dari {$active} karyawan aktif");
        $this->line('  Atasan, HRD, dan GA HSE sebaiknya mengunggah tanda tangan (Profil, Tanda Tangan Digital) SEBELUM surat pertama diterbitkan:');
        $this->line('  yang memparaf tanpa tanda tangan hanya meninggalkan namanya di kotak paraf dan itu tidak berubah lagi.');

        $drafts = DB::table('assignment_letters')->where('status', 'draft')->whereNotNull('initial_boxes')->count();
        if ($drafts > 0) {
            $this->warn("Draft dengan kolom paraf yang diketik manual: {$drafts}");
            $this->line('  Kolom itu kini dianggap kolom TAMBAHAN (paraf basah) dan dicetak setelah kolom otomatis. Kosongkan di draft bila');
            $this->line('  isinya sudah mencakup HRD/GA HSE, supaya tidak tercetak dobel.');
        } else {
            $this->info('Tidak ada draft dengan kolom paraf manual.');
        }

        $legacy = DB::table('assignment_letters as l')
            ->where('l.status', 'issued')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('assignment_letter_approvals as a')->whereColumn('a.assignment_letter_id', 'l.id'))
            ->count();
        $this->line("Surat terbit lama (tanpa paraf digital): {$legacy}. Tidak diubah: tetap tercetak dengan kolom paraf lama dan pindaiannya bisa langsung diunggah.");

        $this->line('Mode saat ini: approval '.(config('assignment_letters.require_approval') ? 'WAJIB (paraf digital aktif)' : 'MATI (terbit langsung seperti dulu)').'.');

        return self::SUCCESS;
    }
}
