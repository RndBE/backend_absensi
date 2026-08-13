<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\KpiAssessment;

/**
 * Peta penilai Bab 2.1.
 *
 * | Dinilai | Penilai utama   | Bobot | Penilai pendukung        | Bobot |
 * |---------|-----------------|-------|--------------------------|-------|
 * | L4      | atasan langsung | 70%   | atasan dua tingkat, L2   | 30%   |
 * | L3      | atasan langsung | 100%  | —                        | —     |
 * | L2      | atasan langsung | 100%  | —                        | —     |
 *
 * "Atasan langsung" dibaca dari `employees.manager_id`, bukan `approver_id` — approver
 * dipakai alur persetujuan dokumen dan bisa berbeda dengan garis komando.
 *
 * ══ Dua penyimpangan yang disengaja dari Bab 2.1 ══
 *
 * Bab 2.1 memberi Direksi porsi pendukung 30% untuk L3. Di sini Direksi hanya menilai Manajer
 * L2, atas keputusan manajemen: seluruh Manajer melapor ke satu Direktur, jadi porsi pendukung
 * L3 membuat satu orang memegang belasan formulir sekaligus — beban yang Bab 9.1 sendiri
 * peringatkan akan membuat penilai mengisi sekadar selesai.
 *
 * Level penilai pendukung juga ikut diperiksa, tidak hanya posisinya di rantai. Tanpa itu, staf
 * L4 yang atasan langsungnya seorang Manajer (bukan Leader) menarik Direksi sebagai penilai
 * pendukung hanya karena Direksi kebetulan berada dua tingkat di atasnya.
 *
 * Harga dari keduanya: L3 dan sebagian L4 kini dinilai satu orang, sehingga peredam bias
 * penilai kedua hilang. Kalibrasi Bab 9.2 menjadi satu-satunya penyaring standar antar penilai
 * untuk mereka, jadi sesi itu tidak boleh dilewati.
 */
class KpiAssessorMap
{
    public const WEIGHT_PRIMARY = 70.0;

    public const WEIGHT_SUPPORTING = 30.0;

    public const WEIGHT_SOLE = 100.0;

    /** Level yang hanya punya satu penilai: atasan langsungnya, bobot penuh. */
    private const SINGLE_ASSESSOR_LEVELS = ['L2', 'L3'];

    /** Level yang sah menempati slot penilai pendukung, per level yang dinilai. */
    private const SUPPORTING_LEVEL = [
        'L4' => 'L2',
    ];

    /**
     * @return array<int, array{assessor_id:int, assessor_role:string, weight:float}>
     */
    public function for(Employee $employee): array
    {
        if ($this->isExcluded($employee)) {
            return [];
        }

        $manager = $employee->manager;

        if (! $manager) {
            return [];
        }

        $levelCode = $employee->kpiLevel?->code;

        if ($levelCode !== null && in_array($levelCode, self::SINGLE_ASSESSOR_LEVELS, true)) {
            return [$this->row($manager->id, KpiAssessment::ROLE_PRIMARY, self::WEIGHT_SOLE)];
        }

        $grandManager = $manager->manager;

        // Tanpa atasan dua tingkat, 30% itu tidak boleh hilang begitu saja — kalau dibiarkan,
        // nilai akhir orang tersebut hanya terbentuk dari 70% bobot dan tidak sebanding dengan
        // rekan selevelnya. Penilai utama mengambil alih seluruh bobot. Berlaku sama ketika
        // atasan dua tingkat ada tetapi levelnya bukan yang ditetapkan Bab 2.1: lebih baik satu
        // penilai yang memang mengenal pekerjaannya daripada menarik level yang tidak semestinya.
        if (! $grandManager || $grandManager->id === $manager->id || ! $this->isValidSupporting($levelCode, $grandManager)) {
            return [$this->row($manager->id, KpiAssessment::ROLE_PRIMARY, self::WEIGHT_SOLE)];
        }

        return [
            $this->row($manager->id, KpiAssessment::ROLE_PRIMARY, self::WEIGHT_PRIMARY),
            $this->row($grandManager->id, KpiAssessment::ROLE_SUPPORTING, self::WEIGHT_SUPPORTING),
        ];
    }

    /**
     * Level yang belum diisi tidak bisa diperiksa terhadap Bab 2.1, jadi dibiarkan lewat —
     * blockingReason() sudah menahan karyawan seperti itu sebelum daftar penilaian dibuat, dan
     * menolaknya di sini hanya akan menyembunyikan penyebab aslinya.
     */
    private function isValidSupporting(?string $levelCode, Employee $grandManager): bool
    {
        $expected = self::SUPPORTING_LEVEL[$levelCode] ?? null;

        if ($levelCode === null || $expected === null) {
            return true;
        }

        return $grandManager->kpiLevel?->code === $expected;
    }

    /**
     * Alasan seorang karyawan tidak bisa dinilai, untuk ditampilkan admin sebelum periode
     * dibuka. Null berarti siap.
     */
    public function blockingReason(Employee $employee): ?string
    {
        if ($this->isExcluded($employee)) {
            return 'Dikecualikan dari penilaian KPI.';
        }

        if (! $employee->kpi_level_id) {
            return 'Level KPI belum diisi.';
        }

        if (! $employee->kpiLevel?->is_assessed) {
            return 'Level '.$employee->kpiLevel?->code.' tidak masuk penilaian.';
        }

        if (! $employee->manager_id) {
            return 'Atasan langsung (manager) belum diisi.';
        }

        if ($employee->manager_id === $employee->id) {
            return 'Atasan langsung menunjuk ke dirinya sendiri.';
        }

        return null;
    }

    /**
     * Karyawan yang memang tidak pernah masuk penilaian: akun demo, akun sistem, tenaga alih
     * daya. Dipisahkan dari alasan penghalang lain supaya daftar "belum bisa dinilai" tetap
     * berisi hal yang bisa dibereskan admin, bukan bercampur peserta permanen di luar KPI.
     */
    public function isExcluded(Employee $employee): bool
    {
        return (bool) $employee->is_kpi_excluded;
    }

    /** @return array{assessor_id:int, assessor_role:string, weight:float} */
    private function row(int $assessorId, string $role, float $weight): array
    {
        return ['assessor_id' => $assessorId, 'assessor_role' => $role, 'weight' => $weight];
    }
}
