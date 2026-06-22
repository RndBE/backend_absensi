<?php

namespace App\Console\Commands;

use App\Services\LpjReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RemindLpjSubmission extends Command
{
    protected $signature = 'lpj:remind {date? : Tanggal acuan (Y-m-d). Default: hari ini}';

    protected $description = 'Kirim pengingat pembuatan LPJ '.LpjReminderService::REMINDER_DAYS.' hari setelah tanggal pulang perjalanan';

    public function handle(): int
    {
        $dateArg = $this->argument('date');

        try {
            $date = $dateArg ? Carbon::parse($dateArg) : Carbon::today();
        } catch (\Exception $e) {
            $this->error("❌ Format tanggal tidak valid: {$dateArg}. Gunakan Y-m-d (contoh: 2026-06-22)");
            return Command::FAILURE;
        }

        $this->info("🔔 Mengirim pengingat LPJ (acuan: {$date->format('d/m/Y')}) ...");

        $result = LpjReminderService::remindForDate($date);

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Pengingat terkirim', $result['sent']],
                ['Dilewati (sudah ada/ tak lengkap)', $result['skipped']],
            ]
        );

        return Command::SUCCESS;
    }
}
