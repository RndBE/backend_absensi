<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Menentukan nama yang tercetak di tiap kotak tanda tangan Form Pengajuan Anggaran.
 *
 * Pemetaannya berdasarkan JABATAN approver, bukan urutan langkah di rantai approval.
 * Cara lama (langkah 1 = PJ/Leader, langkah 2 = Manager, langkah 3 = Finance, dst)
 * salah begitu pengaju tidak punya salah satu peran: seluruh rantai bergeser naik.
 * Contoh nyata — rantai ILHAM YOGA PRATAMA hanya 2 langkah (Hardware Manager, lalu
 * Accounting & Finance), tapi tercetak sebagai PJ/Leader = Hardware Manager dan
 * Manager = Accounting & Finance. Dua-duanya salah kotak.
 *
 * Kotak yang tidak ada orangnya dibiarkan KOSONG, tidak diisi approver lain. Ini
 * dokumen yang ditandatangani: kotak kosong bisa diisi tangan, nama yang salah
 * ikut tertandatangani.
 */
final class BudgetSignatureSlots
{
    /**
     * @param  iterable<mixed>  $chain  hasil EmployeeApprover::getChain(..., 'budget')
     * @return array{pengaju: string, pj: string, manager: string, finance: string, manager_admin: string, direktur: string}
     */
    public static function map(?Employee $pengaju, mixed $chain): array
    {
        $slots = [
            'pengaju'       => $pengaju?->full_name ?? '',
            'pj'            => '',
            'manager'       => '',
            'finance'       => '',
            'manager_admin' => '',
            'direktur'      => '',
        ];

        foreach (Collection::wrap($chain) as $step) {
            $approver = $step->approver ?? null;

            if (! $approver instanceof Employee) {
                continue;
            }

            $slot = self::slotFor($approver);

            // Kalau dua approver jatuh ke kotak yang sama, yang lebih awal di
            // rantai yang menang — approver berikutnya tidak menggeser siapa pun.
            if ($slot !== null && $slots[$slot] === '') {
                $slots[$slot] = (string) $approver->full_name;
            }
        }

        return $slots;
    }

    /**
     * Kotak mana yang cocok untuk seorang approver, atau null kalau tidak ada
     * yang cocok — approver seperti itu tidak dicetak sama sekali.
     *
     * Jabatan spesifik diperiksa lebih dulu, baru jenjang (job_level), karena
     * Finance dan Admin bisa berada di jenjang mana pun.
     */
    private static function slotFor(Employee $approver): ?string
    {
        $position = strtoupper((string) $approver->position);
        $level = (int) $approver->job_level;

        return match (true) {
            str_contains($position, 'FINANCE'),
            str_contains($position, 'ACCOUNTING') => 'finance',

            str_contains($position, 'ADMIN')      => 'manager_admin',

            $level === 1                          => 'direktur',
            $level === 2                          => 'manager',
            $level === 3                          => 'pj',

            default                               => null,
        };
    }
}
