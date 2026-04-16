<?php

namespace App\Console\Commands;

use App\Services\AutoOvertimeService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateAutoOvertime extends Command
{
    protected $signature = 'overtime:auto-generate {date? : Tanggal target (Y-m-d). Default: hari ini}';

    protected $description = 'Generate lembur otomatis (approved) untuk shift yang durasinya melebihi jam kerja standar';

    public function handle(): int
    {
        $dateArg = $this->argument('date');

        try {
            $date = $dateArg ? Carbon::parse($dateArg) : Carbon::today();
        } catch (\Exception $e) {
            $this->error("❌ Format tanggal tidak valid: {$dateArg}. Gunakan format Y-m-d (contoh: 2026-04-16)");
            return Command::FAILURE;
        }

        $this->info("🔄 Generate lembur otomatis untuk tanggal: {$date->format('d/m/Y')} ...");

        $result = AutoOvertimeService::generateForDate($date);

        $this->info("✅ Selesai!");
        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Lembur baru dibuat', $result['generated']],
                ['Dilewati (sudah ada)', $result['skipped']],
            ]
        );

        return Command::SUCCESS;
    }
}
