<?php

namespace App\Support;

use App\Models\KpiCrossResult;
use App\Models\KpiFinalResult;
use App\Models\KpiImprovementPlan;
use App\Models\KpiPeriod;
use Illuminate\Support\Carbon;

/**
 * Tabel pemicu tindak lanjut wajib (Bab 10.1).
 *
 * | Pemicu                        | Tindakan                      | Tenggat  |
 * |-------------------------------|-------------------------------|----------|
 * | Divisi skor silang < 3,0      | Rencana perbaikan proses      | 2 minggu |
 * | Individu nilai akhir < 3,0    | Rencana perbaikan + coaching  | 2 minggu |
 * | Individu nilai akhir < 2,0    | PIP formal, evaluasi 3 bulan  | 1 minggu |
 */
class KpiFollowUp
{
    /** Ambang rencana perbaikan individual. */
    public const INDIVIDUAL_THRESHOLD = 3.0;

    /** Ambang Performance Improvement Plan formal. */
    public const PIP_THRESHOLD = 2.0;

    /** Ambang rencana perbaikan proses tingkat divisi. */
    public const DIVISION_THRESHOLD = 3.0;

    /**
     * Buat rencana perbaikan untuk seluruh pemicu yang terpenuhi pada satu periode.
     *
     * @return int jumlah baris rencana yang tercatat
     */
    public function generateForPeriod(KpiPeriod $period, ?int $createdBy = null): int
    {
        return $this->forEmployees($period, $createdBy) + $this->forDivisions($period, $createdBy);
    }

    private function forEmployees(KpiPeriod $period, ?int $createdBy): int
    {
        $count = 0;

        KpiFinalResult::where('kpi_period_id', $period->id)
            ->chunkById(200, function ($results) use ($period, $createdBy, &$count) {
                foreach ($results as $result) {
                    $score = $result->effectiveScore();

                    if ($score === null || $score >= self::INDIVIDUAL_THRESHOLD) {
                        continue;
                    }

                    $isPip = $score < self::PIP_THRESHOLD;

                    $this->record(
                        $period,
                        KpiImprovementPlan::SUBJECT_EMPLOYEE,
                        (int) $result->employee_id,
                        $isPip
                            ? 'Nilai akhir < 2,0 — Performance Improvement Plan formal, evaluasi 3 bulan'
                            : 'Nilai akhir < 3,0 — rencana perbaikan kinerja individual dan coaching terjadwal',
                        $score,
                        $isPip ? 1 : 2,
                        $isPip ? 3 : null,
                        $createdBy
                    );

                    $count++;
                }
            });

        return $count;
    }

    /**
     * Baris divisi pada kpi_cross_results adalah baris tanpa employee_id — Lapis A menilai
     * divisi, Lapis B menilai individu.
     *
     * Hanya `score_a_corrected` yang dipakai: nilai itu sudah lewat koreksi anti-penyalahgunaan
     * dan bernilai null ketika kuorum penilai tidak terpenuhi. `score_a_raw` sengaja tidak
     * dipakai sebagai cadangan — menghukum divisi dengan angka yang tidak kuorum persis yang
     * dicegah Bab 7.8.
     */
    private function forDivisions(KpiPeriod $period, ?int $createdBy): int
    {
        if (! class_exists(KpiCrossResult::class)) {
            return 0;
        }

        $count = 0;

        foreach (KpiCrossResult::where('kpi_period_id', $period->id)->divisionLevel()->get() as $row) {
            $score = $row->score_a_corrected;

            if ($score === null || (float) $score >= self::DIVISION_THRESHOLD) {
                continue;
            }

            $this->record(
                $period,
                KpiImprovementPlan::SUBJECT_DIVISION,
                (int) $row->department_id,
                'Skor silang divisi < 3,0 — rencana perbaikan proses, disetujui direksi dan ditinjau periode berikutnya',
                (float) $score,
                2,
                null,
                $createdBy
            );

            $count++;
        }

        return $count;
    }

    /**
     * Idempoten: pemicu boleh dijalankan ulang setelah kalibrasi tanpa menggandakan baris.
     *
     * Tenggat dan tanggal tinjauan hanya ditulis saat baris pertama kali dibuat — menjalankan
     * ulang tidak boleh menggeser tanggal yang sudah disepakati HRD dengan karyawan, dan tidak
     * boleh menimpa isi rencana yang sudah ditulis.
     */
    private function record(
        KpiPeriod $period,
        string $subjectType,
        int $subjectId,
        string $reason,
        float $score,
        int $dueWeeks,
        ?int $reviewMonths,
        ?int $createdBy
    ): void {
        $plan = KpiImprovementPlan::updateOrCreate(
            [
                'kpi_period_id' => $period->id,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
            ],
            [
                'trigger_reason' => $reason,
                'trigger_score' => $score,
            ]
        );

        if (! $plan->wasRecentlyCreated) {
            return;
        }

        // Tenggat dihitung dari saat rencana dibuat, bukan dari akhir periode: kewajiban
        // menyusun rencana baru mulai berjalan ketika hasilnya keluar.
        $today = Carbon::today();

        $plan->forceFill([
            'due_date' => $today->copy()->addWeeks($dueWeeks),
            'review_date' => $reviewMonths ? $today->copy()->addMonths($reviewMonths) : null,
            'status' => KpiImprovementPlan::STATUS_OPEN,
            'created_by' => $createdBy,
        ])->save();
    }
}
