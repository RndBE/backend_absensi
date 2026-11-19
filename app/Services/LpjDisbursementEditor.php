<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Lpj;
use App\Models\LpjDisbursement;
use App\Support\LpjPresenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Finance mencatat uang yang ditransfer untuk sebuah LPJ, lengkap dengan bukti transfer per baris.
 * Urutan array menjadi urutan baris. Realisasi pengaju (lpj_items) tidak disentuh.
 */
class LpjDisbursementEditor
{
    public static function rules(): array
    {
        return [
            'disbursements' => 'nullable|array|max:50',
            'disbursements.*.id' => 'nullable|integer',
            'disbursements.*.transferred_at' => 'required|date',
            'disbursements.*.amount' => 'required|numeric|min:0|max:9999999999999',
            'disbursements.*.notes' => 'nullable|string|max:255',
            'disbursements.*.proof_file' => 'nullable|file|mimes:'.implode(',', [...LpjPresenter::PROOF_IMAGE_EXTENSIONS, 'pdf']).'|max:10240',
            'disbursements.*.remove_proof' => 'nullable|boolean',
        ];
    }

    public static function messages(): array
    {
        return [
            'disbursements.*.transferred_at.required' => 'Tanggal transfer tiap baris wajib diisi.',
            'disbursements.*.transferred_at.date' => 'Tanggal transfer tidak valid.',
            'disbursements.*.amount.required' => 'Jumlah transfer tiap baris wajib diisi.',
            'disbursements.*.amount.numeric' => 'Jumlah transfer harus berupa angka.',
            'disbursements.*.proof_file.mimes' => 'Bukti transfer harus berupa gambar (JPG, PNG, GIF, WEBP) atau PDF.',
            'disbursements.*.proof_file.max' => 'Ukuran bukti transfer maksimal 10 MB.',
        ];
    }

    /**
     * Baris dengan `id` diperbarui (bukti lama dipertahankan kecuali diganti atau dilepas), baris tanpa `id`
     * ditambahkan, dan baris lama yang tidak dikirim dihapus beserta buktinya.
     *
     * @param  array<int, array{id?: int|string|null, transferred_at: string, amount: int|float|string, notes?: string|null, proof_file?: UploadedFile|null, remove_proof?: mixed}>  $rows
     *
     * @throws InvalidArgumentException bila ada baris yang bukan milik LPJ ini
     */
    public static function save(Lpj $lpj, Employee $recorder, array $rows): void
    {
        $existing = $lpj->disbursements()->get()->keyBy('id');
        $rows = array_values($rows);
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);

        if ($submittedIds->diff($existing->keys())->isNotEmpty()) {
            throw new InvalidArgumentException('Ada baris yang bukan milik LPJ ini.');
        }

        $obsoleteFiles = [];
        $newFiles = [];

        try {
            DB::transaction(function () use ($lpj, $recorder, $rows, $existing, $submittedIds, &$obsoleteFiles, &$newFiles) {
                $existing->except($submittedIds->all())->each(function (LpjDisbursement $row) use (&$obsoleteFiles) {
                    if ($row->proof_file) {
                        $obsoleteFiles[] = $row->proof_file;
                    }
                    $row->delete();
                });

                foreach ($rows as $position => $row) {
                    $values = [
                        'transferred_at' => $row['transferred_at'],
                        'amount' => (float) $row['amount'],
                        'notes' => filled($row['notes'] ?? null) ? $row['notes'] : null,
                        'sort_order' => $position,
                    ];

                    $record = filled($row['id'] ?? null) ? $existing->get((int) $row['id']) : null;
                    $proof = $row['proof_file'] ?? null;

                    if ($proof instanceof UploadedFile) {
                        $values['proof_file'] = $proof->store('lpj-transfer-bukti', 'public');
                        $newFiles[] = $values['proof_file'];

                        if ($record?->proof_file) {
                            $obsoleteFiles[] = $record->proof_file;
                        }
                    } elseif ($record && filter_var($row['remove_proof'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $values['proof_file'] = null;

                        if ($record->proof_file) {
                            $obsoleteFiles[] = $record->proof_file;
                        }
                    }

                    $record
                        ? $record->update($values)
                        : $lpj->disbursements()->create($values + ['recorded_by' => $recorder->id]);
                }
            });
        } catch (\Throwable $e) {
            // Berkas dari transaksi yang gagal tidak boleh jadi yatim.
            Storage::disk('public')->delete($newFiles);

            throw $e;
        }

        Storage::disk('public')->delete($obsoleteFiles);
    }
}
