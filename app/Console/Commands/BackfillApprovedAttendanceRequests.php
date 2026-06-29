<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceRequest;
use Illuminate\Console\Command;

/**
 * Backfill: terapkan pengajuan presensi yang sudah disetujui (status=approved) ke
 * tabel attendances. Diperlukan untuk data lama yang disetujui SEBELUM perbaikan
 * (dulu approval presensi tidak menulis ke tabel attendances).
 *
 * Aman & idempoten:
 * - Default hanya mengisi yang BELUM punya jam (gap fill); record yang sudah berisi
 *   jam akan dilewati agar data asli tidak tertimpa. Pakai --force untuk menimpa.
 * - Pakai --dry-run untuk melihat dampaknya tanpa menulis apa pun.
 */
class BackfillApprovedAttendanceRequests extends Command
{
    protected $signature = 'attendance:backfill-approved
        {--dry-run : Tampilkan rencana tanpa menyimpan apa pun}
        {--force : Timpa juga record presensi yang sudah berisi jam}';

    protected $description = 'Terapkan pengajuan presensi yang sudah approved ke tabel attendances (untuk data lama sebelum perbaikan)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $approved = AttendanceRequest::where('status', 'approved')
            ->with('employee:id,full_name')
            ->orderBy('date')
            ->get();

        if ($approved->isEmpty()) {
            $this->info('✅ Tidak ada pengajuan presensi berstatus approved. Tidak ada yang perlu di-backfill.');

            return Command::SUCCESS;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Memeriksa '.$approved->count().' pengajuan presensi approved...');

        $applied = 0;
        $skipped = 0;
        $rows = [];

        foreach ($approved as $req) {
            $att = Attendance::where('employee_id', $req->employee_id)
                ->whereDate('date', $req->date->toDateString())
                ->first();
            $hasClock = $att && ($att->clock_in || $att->clock_out);

            // Lewati yang sudah berisi jam, kecuali --force.
            if ($hasClock && ! $force) {
                $skipped++;

                continue;
            }

            $rows[] = [
                $req->id,
                $req->employee?->full_name ?? '-',
                $req->date->toDateString(),
                $req->clock_in ?? '-',
                $req->clock_out ?? '-',
                $hasClock ? 'timpa (force)' : ($att ? 'isi record kosong' : 'buat baru'),
            ];

            if (! $dryRun) {
                $req->applyToAttendance();
            }
            $applied++;
        }

        if (! empty($rows)) {
            $this->table(['Req ID', 'Karyawan', 'Tanggal', 'In', 'Out', 'Aksi'], $rows);
        }

        $this->table(['Keterangan', 'Jumlah'], [
            ['Total approved', $approved->count()],
            [$dryRun ? 'Akan diterapkan' : 'Diterapkan', $applied],
            ['Dilewati (sudah ada jam)', $skipped],
        ]);

        if ($dryRun) {
            $this->warn('DRY-RUN: belum ada yang disimpan. Jalankan tanpa --dry-run untuk menerapkan.');
        } else {
            $this->info("✅ Selesai. {$applied} presensi diterapkan ke tabel attendances.");
        }

        return Command::SUCCESS;
    }
}
