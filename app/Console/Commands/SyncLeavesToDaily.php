<?php

namespace App\Console\Commands;

use App\Jobs\SyncLeaveToDailyJob;
use App\Models\LeaveRequest;
use App\Support\DailyLeaveSync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Jaring pengaman sinkron cuti ke DailyCloseApp: kirim ulang kondisi terkini untuk
 * pengajuan yang relevan, menambal yang lolos saat hook gagal (server mati, Daily
 * sedang down sampai retry habis, dsb.).
 *
 * Dua arah, bukan cuma kirim ulang. SyncLeaveToDailyJob menentukan sendiri arahnya,
 * jadi pengajuan yang ACC-nya sudah dicabut ikut dikirimi DELETE. Ini yang menutup
 * kasus paling berbahaya: ACC dicabut, DELETE gagal, entri "cuti" tertinggal di Daily,
 * dan orangnya berhenti ditagih laporan harian tanpa ada yang tahu — karena tidak ada
 * yang akan melapor soal kondisi yang menguntungkan dirinya.
 */
class SyncLeavesToDaily extends Command
{
    protected $signature = 'daily:sync-leaves
        {--days=30 : Sapu pengajuan yang tanggalnya atau perubahan statusnya masuk N hari terakhir}
        {--dry-run : Tampilkan yang akan dikirim tanpa mengantre job}';

    protected $description = 'Kirim ulang status cuti/sakit yang sudah di-ACC ke DailyCloseApp (menambal sinkron yang gagal)';

    public function handle(): int
    {
        if (! filled(config('services.daily.url')) || ! filled(config('services.daily.internal_secret'))) {
            $this->error('DAILY_APP_URL / DAILY_INTERNAL_SECRET belum lengkap. Tidak ada yang dikirim.');

            return Command::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $since = Carbon::now()->subDays($days)->startOfDay();

        $leaves = LeaveRequest::with('leaveType')
            ->where(function ($query) use ($since) {
                // Yang masih approved: disapu berdasarkan tanggal cutinya, termasuk yang
                // masih di depan. Yang statusnya berubah: disapu berdasarkan updated_at,
                // supaya pencabutan ACC atas cuti lama pun tetap tertangkap.
                $query->where('end_date', '>=', $since->toDateString())
                    ->orWhere('updated_at', '>=', $since);
            })
            ->orderBy('id')
            ->get()
            // Jenis yang tidak dipetakan tidak pernah dikirim, jadi tidak ada juga yang
            // perlu dihapus. Membuangnya di sini menghindari HTTP sia-sia untuk tiap
            // WFH dan izin datang terlambat.
            ->filter(fn (LeaveRequest $leave) => DailyLeaveSync::shouldSync($leave));

        if ($leaves->isEmpty()) {
            $this->info("Tidak ada pengajuan cuti/sakit dalam {$days} hari terakhir yang perlu disinkronkan.");

            return Command::SUCCESS;
        }

        $push = $leaves->where('status', 'approved');
        $revoke = $leaves->where('status', '!=', 'approved');

        $this->info(($dryRun ? '[DRY-RUN] ' : '')."Menyinkronkan {$leaves->count()} pengajuan ke Daily "
            ."({$push->count()} dicatat, {$revoke->count()} dipastikan terhapus)...");

        if ($dryRun) {
            $this->table(
                ['ID', 'Jenis', 'Type Daily', 'Status', 'Rentang', 'Aksi'],
                $leaves->map(fn (LeaveRequest $leave) => [
                    $leave->id,
                    $leave->leaveType?->name,
                    DailyLeaveSync::typeFor($leave),
                    $leave->status,
                    $leave->start_date->toDateString().' s/d '.$leave->end_date->toDateString(),
                    $leave->status === 'approved' ? 'sync' : 'delete',
                ])->all()
            );

            $this->comment('Jalankan ulang tanpa --dry-run untuk mengantre pengirimannya.');

            return Command::SUCCESS;
        }

        foreach ($leaves as $leave) {
            SyncLeaveToDailyJob::dispatch($leave->id);
        }

        $this->info("✅ {$leaves->count()} job sinkron diantrekan.");

        return Command::SUCCESS;
    }
}
