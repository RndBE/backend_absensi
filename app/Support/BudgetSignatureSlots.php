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

        foreach (self::approvers($chain) as $slot => $approver) {
            $slots[$slot] = (string) $approver->full_name;
        }

        return $slots;
    }

    /**
     * Approver yang menempati tiap kotak (selain Pengaju), dengan aturan yang sama seperti map().
     *
     * @return array<string, Employee>
     */
    public static function approvers(mixed $chain): array
    {
        $approvers = [];

        foreach (Collection::wrap($chain) as $step) {
            $approver = $step->approver ?? null;

            if (! $approver instanceof Employee) {
                continue;
            }

            $slot = self::slotFor($approver);

            // Kalau dua approver jatuh ke kotak yang sama, yang lebih awal di
            // rantai yang menang — approver berikutnya tidak menggeser siapa pun.
            if ($slot !== null && ! isset($approvers[$slot])) {
                $approvers[$slot] = $approver;
            }
        }

        return $approvers;
    }

    /**
     * Tanda tangan dan tanggal yang tercetak di kotak approver: hanya bila orang di kotak itu
     * sendiri yang menyetujui (bukan superadmin atas namanya), pada putaran persetujuan
     * terakhir (setelah penolakan terakhir, bila ada). Tanda tangannya diambil dari profil.
     * Kotak yang belum disetujui tidak ada di hasil dan tetap manual.
     *
     * @param  iterable<mixed>  $approvalLogs  approvalLogs anggaran, dengan relasi approver
     * @return array<string, array{signature: ?string, date: string}>
     */
    public static function signed(mixed $chain, iterable $approvalLogs): array
    {
        $logs = Collection::wrap($approvalLogs)->sortBy(fn ($log) => [$log->created_at, $log->id])->values();

        // Penolakan menutup putaran itu; persetujuan sebelumnya tidak berlaku lagi.
        $lastRejection = $logs->filter(fn ($log) => $log->action === 'rejected')->keys()->last();
        if ($lastRejection !== null) {
            $logs = $logs->slice($lastRejection + 1);
        }

        $signed = [];

        foreach (self::approvers($chain) as $slot => $approver) {
            $log = $logs->last(fn ($log) => $log->action === 'approved'
                && (int) $log->approver_id === (int) $approver->id
                && ($log->acted_by_id === null || (int) $log->acted_by_id === (int) $approver->id));

            if ($log === null) {
                continue;
            }

            $signed[$slot] = [
                'signature' => SignatureImage::url($log->approver?->signature ?? $approver->signature),
                'date' => $log->created_at->timezone(config('app.timezone'))->format('d / m / Y'),
            ];
        }

        return $signed;
    }

    /**
     * Kotak mana yang cocok untuk seorang approver, atau null kalau tidak ada
     * yang cocok — approver seperti itu tidak dicetak sama sekali.
     *
     * Jabatan spesifik diperiksa lebih dulu, baru jenjang (job_level), karena
     * Finance dan Admin bisa berada di jenjang mana pun.
     */
    public static function isFinance(Employee $approver): bool
    {
        $position = strtoupper((string) $approver->position);

        return str_contains($position, 'FINANCE') || str_contains($position, 'ACCOUNTING');
    }

    private static function slotFor(Employee $approver): ?string
    {
        $position = strtoupper((string) $approver->position);
        $level = (int) $approver->job_level;

        return match (true) {
            self::isFinance($approver)            => 'finance',

            str_contains($position, 'ADMIN')      => 'manager_admin',

            $level === 1                          => 'direktur',
            $level === 2                          => 'manager',
            $level === 3                          => 'pj',

            default                               => null,
        };
    }
}
