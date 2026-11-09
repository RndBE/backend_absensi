<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Lpj;
use App\Models\LpjRevision;
use App\Models\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Koreksi realisasi LPJ oleh finance, dipakai bersama oleh portal employee dan API mobile.
 * Hanya `realisasi` dan `keterangan` yang berubah; item, bukti, dan anggaran tetap milik karyawan.
 */
class LpjRealisationCorrector
{
    /**
     * @param  array<int, array{id: int|string, realisasi: int|float|string, keterangan?: string|null}>  $rows
     * @return LpjRevision|null  null bila tidak ada angka atau keterangan yang berubah
     *
     * @throws InvalidArgumentException bila ada item yang bukan milik LPJ ini
     */
    public static function correct(Lpj $lpj, Employee $editor, array $rows, ?string $notes = null): ?LpjRevision
    {
        $lpj->loadMissing(['employee', 'items']);

        $existing = $lpj->items->keyBy('id');
        $submittedIds = collect($rows)->pluck('id')->map(fn ($id) => (int) $id);

        if ($submittedIds->diff($existing->keys())->isNotEmpty()) {
            throw new InvalidArgumentException('Ada item yang bukan milik LPJ ini.');
        }

        $before = self::snapshot($lpj->items);

        DB::transaction(function () use ($lpj, $rows, $existing) {
            foreach ($rows as $row) {
                $existing->get((int) $row['id'])->update([
                    'realisasi' => (float) $row['realisasi'],
                    'keterangan' => filled($row['keterangan'] ?? null) ? $row['keterangan'] : null,
                ]);
            }

            $lpj->recalculate();
        });

        $after = self::snapshot($lpj->items()->get());

        if ($before === $after) {
            return null;
        }

        $revision = $lpj->revisions()->create([
            'editor_id' => $editor->id,
            'step_order' => (int) ($lpj->current_step ?? 1),
            'before' => $before,
            'after' => $after,
            'notes' => $notes,
        ]);

        self::notifyOwner($lpj, $editor, $before['total'], $after['total'], $notes);

        return $revision;
    }

    /** @return array{total: float, items: array<int, array{id: int, uraian: string, realisasi: float, keterangan: string}>} */
    private static function snapshot(Collection $items): array
    {
        $rows = $items->sortBy('id')->values()->map(fn ($item) => [
            'id' => (int) $item->id,
            'uraian' => (string) $item->uraian,
            'realisasi' => (float) $item->realisasi,
            'keterangan' => (string) ($item->keterangan ?? ''),
        ])->all();

        return [
            'total' => (float) array_sum(array_column($rows, 'realisasi')),
            'items' => $rows,
        ];
    }

    private static function notifyOwner(Lpj $lpj, Employee $editor, float $totalBefore, float $totalAfter, ?string $notes): void
    {
        if (! $lpj->employee) {
            return;
        }

        $format = fn (float $amount) => 'Rp '.number_format($amount, 0, ',', '.');
        $notification = Notification::create([
            'employee_id' => $lpj->employee_id,
            'title' => 'Realisasi LPJ Dikoreksi',
            'message' => "{$editor->full_name} mengoreksi realisasi LPJ {$lpj->nomor_lpj}: "
                .$format($totalBefore).' → '.$format($totalAfter)
                .(filled($notes) ? ". Catatan: {$notes}" : ''),
            'type' => 'info',
            'reference_type' => Lpj::class,
            'reference_id' => $lpj->id,
        ]);

        FcmService::sendToEmployee($lpj->employee, $notification->title, $notification->message);
    }
}
