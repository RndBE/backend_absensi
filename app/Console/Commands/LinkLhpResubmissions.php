<?php

namespace App\Console\Commands;

use App\Models\Lpj;
use App\Models\TravelReport;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: tautkan LHP yang ditolak SEBELUM fitur pengajuan ulang ada ke LHP yang
 * dibuat karyawan sebagai gantinya (isi travel_reports.resubmission_of_id).
 *
 * Dulu karyawan mengganti LHP yang ditolak dengan membuat LHP baru biasa, tanpa rujukan,
 * jadi LHP lama tampil seolah belum diajukan ulang. Pasangan ditebak dari: karyawan sama,
 * anggaran sama atau kota tujuan sama, dan LHP baru dibuat setelah penolakan dalam jangka
 * --days hari. Tebakan yang meleset bisa dilewati dengan --skip, pasangan yang tidak
 * tertebak bisa ditambah dengan --pair.
 *
 * Default hanya menampilkan rencana; baru menulis dengan --apply. Idempoten: LHP yang sudah
 * punya pengganti dan LHP yang sudah menjadi pengganti dilewati. Batas/status telat LHP
 * pengganti tidak diubah, karena itu data historis.
 */
class LinkLhpResubmissions extends Command
{
    protected $signature = 'lhp:link-resubmissions
        {--apply : Simpan tautannya (tanpa ini hanya menampilkan rencana)}
        {--days=14 : Jarak maksimal (hari) antara penolakan dan pembuatan LHP pengganti}
        {--pair=* : Pasangan manual ID_DITOLAK:ID_PENGGANTI, bisa diulang}
        {--skip=* : ID LHP ditolak yang tidak boleh ditautkan otomatis, bisa diulang}';

    protected $description = 'Tautkan LHP ditolak lama ke LHP pengganti yang dibuat sebelum fitur pengajuan ulang ada';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $days = max(0, (int) $this->option('days'));
        $skip = array_map('intval', (array) $this->option('skip'));

        $manual = $this->parsePairs((array) $this->option('pair'));
        if ($manual === null) {
            return Command::FAILURE;
        }

        // LHP yang sudah menjadi pengganti tidak boleh dipakai lagi (kolomnya unique).
        $used = TravelReport::whereNotNull('resubmission_of_id')->pluck('resubmission_of_id', 'id')->keys()->all();

        $rejected = TravelReport::where('status', 'rejected')
            ->whereDoesntHave('resubmission')
            ->with(['latestRejection', 'employee'])
            ->orderBy('id')
            ->get();

        $plan = [];
        $unmatched = [];

        foreach ($rejected as $original) {
            if (isset($manual[$original->id])) {
                $candidate = $this->validateManual($original, $manual[$original->id], $used);
                unset($manual[$original->id]);

                if (! $candidate) {
                    continue;
                }
                $how = 'manual';
            } elseif (in_array($original->id, $skip, true)) {
                $unmatched[] = [$original, 'dilewati (--skip)'];

                continue;
            } else {
                $candidate = $this->guess($original, $days, $used);
                $how = $candidate && $original->budget_request_id && $candidate->budget_request_id === $original->budget_request_id
                    ? 'anggaran sama'
                    : 'kota sama';

                if (! $candidate) {
                    $unmatched[] = [$original, 'tidak ada LHP pengganti yang cocok'];

                    continue;
                }
            }

            $used[] = $candidate->id;
            $plan[] = [$original, $candidate, $how];
        }

        // Pasangan manual yang LHP lamanya tidak termasuk daftar ditolak-tanpa-pengganti.
        foreach (array_keys($manual) as $originalId) {
            $this->error("--pair {$originalId}: LHP #{$originalId} tidak ditemukan, tidak berstatus ditolak, atau sudah punya pengganti.");
        }

        $this->info(($apply ? '' : '[DRY-RUN] ').'LHP ditolak tanpa pengganti: '.$rejected->count());

        if ($plan !== []) {
            $this->table(
                ['Ditolak', 'Karyawan', 'Tujuan', 'Ditolak pada', 'Pengganti', 'Tujuan pengganti', 'Dibuat', 'Status', 'Dasar', 'LPJ dipindah'],
                array_map(fn ($row) => [
                    '#'.$row[0]->id,
                    $row[0]->employee?->full_name ?? '#'.$row[0]->employee_id,
                    $row[0]->destination_city,
                    $this->rejectedAt($row[0])->format('Y-m-d H:i'),
                    '#'.$row[1]->id,
                    $row[1]->destination_city,
                    $row[1]->created_at->format('Y-m-d H:i'),
                    $row[1]->status,
                    $row[2],
                    Lpj::where('travel_report_id', $row[0]->id)->count(),
                ], $plan),
            );
        }

        foreach ($unmatched as [$original, $reason]) {
            $this->line("  #{$original->id} {$original->destination_city} ({$original->employee?->full_name}): {$reason}");
        }

        if (! $apply) {
            $this->comment('Belum ada yang disimpan. Periksa tabel di atas, lalu jalankan ulang dengan --apply (tambahkan --skip / --pair bila ada tebakan yang salah).');

            return Command::SUCCESS;
        }

        DB::transaction(function () use ($plan) {
            foreach ($plan as [$original, $replacement]) {
                // Query builder, bukan Eloquent, supaya updated_at LHP pengganti tetap asli.
                DB::table('travel_reports')->where('id', $replacement->id)->update(['resubmission_of_id' => $original->id]);
                // Sama dengan alur pengajuan ulang: LPJ ikut pindah ke LHP yang berlaku.
                Lpj::where('travel_report_id', $original->id)->update(['travel_report_id' => $replacement->id]);
            }
        });

        $this->info(count($plan).' LHP ditolak berhasil ditautkan ke penggantinya.');

        return Command::SUCCESS;
    }

    /** Waktu penolakan: dari log approval; fallback ke updated_at untuk data tanpa log. */
    private function rejectedAt(TravelReport $report): Carbon
    {
        return $report->latestRejection?->created_at ?? $report->updated_at;
    }

    /** LHP pengganti paling awal yang dibuat setelah penolakan, dalam jangka $days hari. */
    private function guess(TravelReport $original, int $days, array $used): ?TravelReport
    {
        $rejectedAt = $this->rejectedAt($original);

        $candidates = TravelReport::where('employee_id', $original->employee_id)
            ->where('id', '>', $original->id)
            ->whereNull('resubmission_of_id')
            ->whereNotIn('id', $used)
            ->where('created_at', '>=', $rejectedAt)
            ->where('created_at', '<=', $rejectedAt->copy()->addDays($days))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return $candidates->first(fn ($candidate) => $this->sameTrip($original, $candidate));
    }

    private function sameTrip(TravelReport $original, TravelReport $candidate): bool
    {
        if ($original->budget_request_id && $candidate->budget_request_id) {
            return $original->budget_request_id === $candidate->budget_request_id;
        }

        return $this->normalizeCity($original->destination_city) === $this->normalizeCity($candidate->destination_city);
    }

    private function normalizeCity(?string $city): string
    {
        return preg_replace('/[^a-z0-9]+/', '', mb_strtolower((string) $city));
    }

    private function validateManual(TravelReport $original, int $replacementId, array $used): ?TravelReport
    {
        $replacement = TravelReport::find($replacementId);

        $error = match (true) {
            ! $replacement => "LHP #{$replacementId} tidak ditemukan.",
            $replacement->id === $original->id => 'LHP tidak bisa menjadi pengganti dirinya sendiri.',
            $replacement->employee_id !== $original->employee_id => "LHP #{$replacementId} milik karyawan lain.",
            $replacement->resubmission_of_id !== null || in_array($replacement->id, $used, true) => "LHP #{$replacementId} sudah menjadi pengganti LHP lain.",
            default => null,
        };

        if ($error) {
            $this->error("--pair {$original->id}:{$replacementId}: {$error}");

            return null;
        }

        return $replacement;
    }

    /** @return array<int, int>|null  ID ditolak => ID pengganti, null bila format salah */
    private function parsePairs(array $pairs): ?array
    {
        $parsed = [];

        foreach ($pairs as $pair) {
            if (! preg_match('/^\s*(\d+)\s*:\s*(\d+)\s*$/', $pair, $match)) {
                $this->error("Format --pair salah: \"{$pair}\". Contoh: --pair=9:11");

                return null;
            }
            $parsed[(int) $match[1]] = (int) $match[2];
        }

        return $parsed;
    }
}
