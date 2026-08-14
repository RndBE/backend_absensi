<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\KpiWorkChainReviewer;
use App\Support\KpiWorkChainReviewLinks;
use Illuminate\Console\Command;

/**
 * Menerbitkan tautan tinjauan peta rantai kerja untuk para Manajer.
 *
 * Tautannya hanya bisa dilihat SEKALI, di keluaran perintah ini — yang tersimpan di basis data
 * cuma sha256-nya. Kalau tautannya hilang sebelum terkirim, terbitkan lagi; yang lama otomatis
 * dicabut supaya tidak ada dua tautan hidup untuk satu orang.
 */
class KpiIssueReviewLinks extends Command
{
    protected $signature = 'kpi:review-links
        {--days=30 : Masa berlaku tautan dalam hari}
        {--name=* : Terbitkan hanya untuk nama tertentu (boleh berulang)}
        {--revoke-all : Cabut semua tautan yang masih hidup, tanpa menerbitkan yang baru}
        {--list : Tampilkan tautan yang masih hidup beserta jejak pemakaiannya}';

    protected $description = 'Terbitkan tautan tinjauan rantai kerja tanpa login untuk para Manajer';

    public function handle(KpiWorkChainReviewLinks $links): int
    {
        if ($this->option('list')) {
            return $this->listActive();
        }

        if ($this->option('revoke-all')) {
            $n = KpiWorkChainReviewer::whereNull('revoked_at')->update(['revoked_at' => now()]);
            $this->info("{$n} tautan dicabut.");

            return self::SUCCESS;
        }

        $days = max(1, (int) $this->option('days'));
        $targets = $this->targets();

        if ($targets->isEmpty()) {
            $this->error('Tidak ada penerima yang cocok. Tanpa --name, perintah ini mengambil semua Manajer (level L2).');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Tautan hanya tampil sekali. Simpan sekarang sebelum menutup jendela ini.');
        $this->newLine();

        foreach ($targets as $employee) {
            $issued = $links->issue($employee, $days);

            $this->line("<options=bold>{$employee->full_name}</> — {$employee->position}");
            $this->line("  {$issued['url']}");
            $this->line("  <fg=gray>berlaku sampai {$issued['reviewer']->expires_at->translatedFormat('j F Y H:i')}</>");
            $this->newLine();
        }

        $this->info($targets->count().' tautan diterbitkan. Tautan lama untuk orang yang sama sudah dicabut.');

        return self::SUCCESS;
    }

    /** @return \Illuminate\Support\Collection<int, Employee> */
    private function targets()
    {
        $names = collect($this->option('name'))->filter();

        $query = Employee::where('is_active', true)->where('is_kpi_excluded', false);

        if ($names->isNotEmpty()) {
            return $query->whereIn('full_name', $names->all())->orderBy('full_name')->get();
        }

        // Bawaan: seluruh Manajer. Merekalah yang mengoordinasikan rantai kerja divisinya, jadi
        // merekalah yang bisa membenarkan atau membantah petanya.
        return $query
            ->whereHas('kpiLevel', fn ($q) => $q->where('code', 'L2'))
            ->orderBy('full_name')
            ->get();
    }

    private function listActive(): int
    {
        $rows = KpiWorkChainReviewer::with('employee')
            ->whereNull('revoked_at')
            ->orderBy('expires_at')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('Tidak ada tautan yang masih hidup.');

            return self::SUCCESS;
        }

        $this->table(
            ['Penerima', 'Berlaku sampai', 'Status', 'Dibuka', 'Terakhir', 'IP terakhir'],
            $rows->map(fn (KpiWorkChainReviewer $r) => [
                $r->employee?->full_name ?? '—',
                $r->expires_at->translatedFormat('j M Y'),
                $r->blockingReason() ?? 'berlaku',
                $r->use_count.'×',
                $r->last_used_at?->translatedFormat('j M H:i') ?? 'belum dibuka',
                $r->last_ip_address ?? '—',
            ])->all()
        );

        $this->line('<fg=gray>Tautannya sendiri tidak bisa ditampilkan ulang — hanya sha256-nya yang tersimpan.</>');

        return self::SUCCESS;
    }
}
