<?php

namespace App\Console\Commands;

use App\Services\LhpReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RemindLhpSubmission extends Command
{
    protected $signature = 'lhp:remind {date? : Tanggal acuan (Y-m-d). Default: hari ini}';

    protected $description = 'Kirim pengingat pembuatan LHP: setelah pulang dan menjelang batas pengumpulan';

    public function handle(): int
    {
        $dateArg = $this->argument('date');

        try {
            $date = $dateArg ? Carbon::parse($dateArg) : Carbon::today();
        } catch (\Exception $e) {
            $this->error("❌ Format tanggal tidak valid: {$dateArg}. Gunakan Y-m-d (contoh: 2026-06-22)");
            return Command::FAILURE;
        }

        $this->info("🔔 Mengirim pengingat LHP (acuan: {$date->format('d/m/Y')}) ...");

        $result = LhpReminderService::remindForDate($date);

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Pengingat terkirim', $result['sent']],
                ['Dilewati (sudah ada/ sudah bikin LHP)', $result['skipped']],
            ]
        );

        return Command::SUCCESS;
    }
}
