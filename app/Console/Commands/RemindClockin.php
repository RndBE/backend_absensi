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
    protected $signature = 'clockin:remind';

    protected $description = 'Kirim pengingat clock-in (WA + in-app + FCM) menjelang jam masuk shift bagi yang belum clock-in';

    public function handle(): int
    {
        $result = ClockinReminderService::remindForNow(now());

        $this->info(sprintf(
            'Clock-in reminder — terkirim: %d, dilewati: %d, WA gagal: %d',
            $result['sent'],
            $result['skipped'],
            $result['wa_failed'],
        ));

        return Command::SUCCESS;
    }
}
