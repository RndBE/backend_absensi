<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Pemilik tiap baris biaya anggaran, dipakai portal karyawan dan API mobile.
 *
 * Baris tanpa pemilik adalah biaya bersama (bensin, tol) dan dilaporkan pemilik anggaran di
 * LPJ-nya. Baris dengan pemilik adalah biaya pribadi seorang peserta (uang makan, tunjangan)
 * dan dilaporkan peserta itu. Pemilik anggaran sendiri tidak perlu dipilih: biayanya sama
 * dengan biaya bersama, jadi disimpan sebagai kosong.
 */
class BudgetItemOwners
{
    /**
     * Pemilik tiap baris, urut sesuai kunci $items: id peserta, atau null untuk biaya bersama.
     * Melempar ValidationException bila ada pemilik yang bukan peserta anggaran.
     *
     * @param  array<int|string, array<string, mixed>>  $items
     * @param  array<int, int|string>  $participantIds
     * @return array<int|string, int|null>
     */
    public static function resolve(array $items, int $budgetOwnerId, array $participantIds): array
    {
        $participants = array_map('intval', $participantIds);
        $owners = [];

        foreach ($items as $key => $item) {
            $owner = filled($item['employee_id'] ?? null) ? (int) $item['employee_id'] : null;

            if ($owner !== null && $owner !== $budgetOwnerId && ! in_array($owner, $participants, true)) {
                throw ValidationException::withMessages([
                    "items.$key.employee_id" => 'Pemilik biaya harus salah satu peserta anggaran. Tag orangnya di Tag Tim dulu.',
                ]);
            }

            $owners[$key] = $owner === $budgetOwnerId ? null : $owner;
        }

        return $owners;
    }
}
