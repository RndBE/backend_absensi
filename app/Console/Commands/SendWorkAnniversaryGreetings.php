<?php

namespace App\Console\Commands;

use App\Services\WorkAnniversaryService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendWorkAnniversaryGreetings extends Command
{
    protected $signature = 'anniversary:greet
        {date? : Tanggal acuan (Y-m-d). Default: hari ini}
        {--dry-run : Tampilkan penerima tanpa mengirim apa pun}';

    protected $description = 'Kirim apresiasi hari jadi kerja (email + in-app + FCM) untuk karyawan yang masa kerjanya genap sesuai kelipatan tahun';

    public function handle(): int
    {
        $dateArg = $this->argument('date');

        try {
            $date = $dateArg ? Carbon::parse($dateArg) : Carbon::today();
        } catch (\Exception $e) {
            $this->error("❌ Format tanggal tidak valid: {$dateArg}. Gunakan Y-m-d (contoh: 2026-08-26)");

            return Command::FAILURE;
        }

        $milestones = WorkAnniversaryService::milestones();
        $milestoneLabel = $milestones === [] ? 'setiap tahun' : implode(', ', $milestones).' tahun';

        if (! WorkAnniversaryService::isEnabled()) {
            $this->warn('⏸️  Apresiasi hari jadi sedang dinonaktifkan di Pengaturan Presensi.');

            return Command::SUCCESS;
        }

        $this->info("🎉 Apresiasi hari jadi kerja (acuan: {$date->format('d/m/Y')}, kelipatan: {$milestoneLabel}) ...");

        $celebrants = WorkAnniversaryService::celebrantsForDate($date);

        if ($celebrants->isEmpty()) {
            $this->line('Tidak ada yang berulang tahun kerja pada tanggal ini.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Nama', 'Departemen', 'Masuk', 'Tahun', 'Email'],
            $celebrants->map(fn (array $row) => [
                $row['employee']->full_name,
                $row['employee']->department?->name ?? '-',
                $row['employee']->join_date?->format('d/m/Y') ?? '-',
                $row['years'],
                $row['employee']->email ?: '(kosong)',
            ])->all()
        );

        if ($this->option('dry-run')) {
            $this->comment('🔍 Mode dry-run — tidak ada yang dikirim.');

            return Command::SUCCESS;
        }

        $result = WorkAnniversaryService::greetForDate($date);

        $this->table(
            ['Keterangan', 'Jumlah'],
            [
                ['Terkirim', $result['sent']],
                ['Dilewati (sudah diucapkan tahun ini)', $result['skipped']],
                ['Email gagal (in-app tetap terkirim)', $result['email_failed']],
            ]
        );

        return Command::SUCCESS;
    }
}
