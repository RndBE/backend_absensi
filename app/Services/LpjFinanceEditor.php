<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Lpj;
use App\Models\LpjItem;
use App\Models\LpjRevision;
use App\Models\Notification;
use App\Support\LpjPresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Finance mengedit tabel pengeluaran LPJ pada step aktifnya: ubah isi baris, tambah baris, hapus baris,
 * ganti atau lepas bukti. Dipakai bersama oleh portal employee dan API mobile.
 *
 * Yang tidak bisa diubah: anggaran (pemasukan) dan data umum LPJ. Tiap penyimpanan yang mengubah sesuatu
 * dicatat sebagai LpjRevision dan pengaju diberi tahu.
 */
class LpjFinanceEditor
{
    /** Aturan validasi baris yang sama untuk portal dan API. */
    public static function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.kategori' => 'required|string|in:'.implode(',', array_keys(LpjItem::CATEGORIES)),
            'items.*.uraian' => 'required|string|max:255',
            'items.*.satuan' => 'nullable|string|max:50',
            'items.*.volume' => 'nullable|numeric|min:0|max:99999999',
            'items.*.realisasi' => 'required|numeric|min:0|max:9999999999999',
            'items.*.keterangan' => 'nullable|string|max:255',
            'items.*.bukti_file' => 'nullable|file|mimes:'.implode(',', [...LpjPresenter::PROOF_IMAGE_EXTENSIONS, 'pdf']).'|max:10240',
            'items.*.remove_bukti' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    public static function messages(): array
    {
        return [
            'items.required' => 'LPJ minimal punya satu baris pengeluaran.',
            'items.min' => 'LPJ minimal punya satu baris pengeluaran.',
            'items.*.kategori.required' => 'Kategori tiap baris pengeluaran wajib dipilih.',
            'items.*.kategori.in' => 'Kategori pengeluaran tidak valid.',
            'items.*.uraian.required' => 'Uraian tiap baris pengeluaran wajib diisi.',
            'items.*.realisasi.required' => 'Jumlah realisasi tiap baris wajib diisi.',
            'items.*.bukti_file.mimes' => 'Bukti harus berupa gambar (JPG, PNG, GIF, WEBP) atau PDF.',
            'items.*.bukti_file.max' => 'Ukuran bukti maksimal 10 MB.',
        ];
    }

    /**
     * @param  array<int, array{id?: int|string|null, kategori: string, uraian: string, satuan?: string|null, volume?: int|float|string|null, realisasi: int|float|string, keterangan?: string|null, bukti_file?: UploadedFile|null, remove_bukti?: mixed}>  $rows
     *         Urutan array menjadi urutan baris. Baris tanpa `id` ditambahkan; item lama yang tidak
     *         ada di $rows dihapus.
     * @return LpjRevision|null  null bila tidak ada yang berubah
     *
     * @throws InvalidArgumentException bila ada baris yang bukan milik LPJ ini
     */
    public static function update(Lpj $lpj, Employee $editor, array $rows, ?string $notes = null): ?LpjRevision
    {
        $lpj->loadMissing(['employee', 'items']);

        $existing = $lpj->items->keyBy('id');
        $rows = array_values($rows);
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);

        if ($submittedIds->diff($existing->keys())->isNotEmpty()) {
            throw new InvalidArgumentException('Ada baris yang bukan milik LPJ ini.');
        }

        $before = self::snapshot($lpj->items);
        $obsoleteFiles = [];
        $newFiles = [];

        try {
            DB::transaction(function () use ($lpj, $rows, $existing, $submittedIds, &$obsoleteFiles, &$newFiles) {
                // Baris yang dibuang ikut membuang buktinya.
                $existing->except($submittedIds->all())->each(function (LpjItem $item) use (&$obsoleteFiles) {
                    if ($item->bukti_file) {
                        $obsoleteFiles[] = $item->bukti_file;
                    }
                    $item->delete();
                });

                foreach ($rows as $position => $row) {
                    $values = [
                        'kategori' => $row['kategori'],
                        'uraian' => $row['uraian'],
                        'satuan' => filled($row['satuan'] ?? null) ? $row['satuan'] : null,
                        'volume' => filled($row['volume'] ?? null) ? (float) $row['volume'] : 1,
                        'realisasi' => (float) $row['realisasi'],
                        'keterangan' => filled($row['keterangan'] ?? null) ? $row['keterangan'] : null,
                        'sort_order' => $position,
                    ];

                    $item = filled($row['id'] ?? null) ? $existing->get((int) $row['id']) : null;
                    $proof = $row['bukti_file'] ?? null;

                    if ($proof instanceof UploadedFile) {
                        $values['bukti_file'] = $proof->store('lpj-bukti', 'public');
                        $newFiles[] = $values['bukti_file'];

                        if ($item?->bukti_file) {
                            $obsoleteFiles[] = $item->bukti_file;
                        }
                    } elseif ($item && filter_var($row['remove_bukti'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $values['bukti_file'] = null;

                        if ($item->bukti_file) {
                            $obsoleteFiles[] = $item->bukti_file;
                        }
                    }

                    $item
                        ? $item->update($values)
                        // Anggaran ada di sisi pemasukan; baris realisasi tidak membawanya.
                        : $lpj->items()->create($values + ['anggaran' => 0, 'harga_satuan' => 0]);
                }

                $lpj->recalculate();
            });
        } catch (\Throwable $e) {
            // Berkas yang sudah tersimpan untuk transaksi yang gagal tidak boleh jadi yatim.
            Storage::disk('public')->delete($newFiles);

            throw $e;
        }

        Storage::disk('public')->delete($obsoleteFiles);

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

    /** @return array{total: float, items: array<int, array<string, mixed>>} */
    private static function snapshot(Collection $items): array
    {
        $rows = $items->sortBy('id')->values()->map(fn (LpjItem $item) => [
            'id' => (int) $item->id,
            'kategori' => (string) $item->kategori,
            'uraian' => (string) $item->uraian,
            'satuan' => (string) ($item->satuan ?? ''),
            'volume' => (float) $item->volume,
            'realisasi' => (float) $item->realisasi,
            'keterangan' => (string) ($item->keterangan ?? ''),
            'bukti' => $item->bukti_file ? basename($item->bukti_file) : '',
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
            'title' => 'Rincian LPJ Diubah Finance',
            'message' => "{$editor->full_name} mengubah rincian pengeluaran LPJ {$lpj->nomor_lpj}: "
                .$format($totalBefore).' → '.$format($totalAfter)
                .(filled($notes) ? ". Catatan: {$notes}" : ''),
            'type' => 'info',
            'reference_type' => Lpj::class,
            'reference_id' => $lpj->id,
        ]);

        FcmService::sendToEmployee($lpj->employee, $notification->title, $notification->message);
    }
}
