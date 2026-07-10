<?php

namespace App\Console\Commands;

use App\Services\ClockinReminderService;
use Illuminate\Console\Command;

/**
 * Kirim pengingat clock-in menjelang jam masuk shift (WA + in-app + FCM) bagi karyawan
 * yang terjadwal kerja tapi belum clock-in. Dijadwalkan tiap menit di routes/console.php;
 * toggle aktif/nonaktif & menit "sebelum jam masuk" dibaca dari Pengaturan Presensi.
 */
class RemindClockin extends Command
{
    protected $signature = 'clockin:remind {--show-empty : Tetap cetak ringkasan walau tak ada yang dikirim}';

    protected $description = 'Kirim pengingat clock-in (WA + in-app + FCM) menjelang jam masuk shift bagi yang belum clock-in';

    public function handle(): int
    {
        $result = ClockinReminderService::remindForNow(now());

        // Dijadwalkan tiap menit. Kalau tak ada yang jatuh tempo, DIAM — kalau tidak, log
        // menumpuk ~1.440 baris "terkirim: 0" per hari tanpa memberi informasi apa pun.
        $adaKejadian = $result['in_app'] > 0 || $result['wa_sent'] > 0 || $result['wa_failed'] > 0;

        if ($adaKejadian || $this->option('show-empty')) {
            $baris = sprintf(
                '[%s] Clock-in reminder — WA terkirim: %d, WA gagal: %d, in-app: %d, dilewati: %d',
                now()->format('Y-m-d H:i'),
                $result['wa_sent'],
                $result['wa_failed'],
                $result['in_app'],
                $result['skipped'],
            );

            $result['wa_failed'] > 0 ? $this->warn($baris) : $this->info($baris);
        }

        return Command::SUCCESS;
    }
}
