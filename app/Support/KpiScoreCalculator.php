<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiFinalResult;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Models\KpiPeriodIndicatorSnapshot;
use App\Models\KpiPeriodLevelSnapshot;
use Illuminate\Support\Collection;

/**
 * Perhitungan nilai akhir Bab 8.
 *
 *   Skor indikator  = rata-rata tertimbang skor antar penilai (bobot penilai Bab 2.1)
 *   Skor kategori   = Σ (skor indikator × bobot indikator) / 100
 *   Nilai akhir     = Σ (skor kategori × bobot kategori) / 100
 *
 * Seluruh bobot dan daftar indikator dibaca dari SNAPSHOT periode, bukan master, supaya
 * hasil tetap dapat direproduksi setelah master berubah.
 */
class KpiScoreCalculator
{
    /** Presisi internal Bab 8.4. Tampilan dibulatkan ke 2 desimal di view. */
    private const PRECISION = 4;

    /** Nilai bawaan bila tidak ada penilai yang mengisi indikator (Bab 11.4). */
    public const DEFAULT_SCORE = 3.0;

    /**
     * Hitung satu karyawan dan simpan hasilnya. Mengembalikan null bila karyawan tidak
     * punya penilaian yang sudah di-submit sama sekali — lebih baik tidak ada baris hasil
     * daripada baris berisi skor bawaan yang terlihat seperti penilaian sungguhan.
     */
    public function computeForEmployee(KpiPeriod $period, Employee $employee): ?KpiFinalResult
    {
        $assessments = KpiAssessment::query()
            ->where('kpi_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->submitted()
            ->with('scores')
            ->get();

        if ($assessments->isEmpty()) {
            return null;
        }

        $levelSnapshot = KpiPeriodLevelSnapshot::query()
            ->where('kpi_period_id', $period->id)
            ->where('kpi_level_id', $employee->kpi_level_id)
            ->first();

        if (! $levelSnapshot) {
            return null;
        }

        $indicators = KpiPeriodIndicatorSnapshot::query()
            ->where('kpi_period_level_snapshot_id', $levelSnapshot->id)
            ->orderBy('sort_order')
            ->get();

        $indicatorScores = $this->indicatorScores($assessments, $indicators);
        $categoryScores = [];

        foreach (array_keys(KpiLevel::CATEGORIES) as $category) {
            $categoryScores[$category] = $this->categoryScore(
                $indicators->where('category', $category),
                $indicatorScores
            );
        }

        $final = $this->finalScore($categoryScores, $levelSnapshot->categoryWeights());

        return KpiFinalResult::updateOrCreate(
            ['kpi_period_id' => $period->id, 'employee_id' => $employee->id],
            [
                'kpi_period_level_snapshot_id' => $levelSnapshot->id,
                'score_excellence' => $categoryScores[KpiLevel::CATEGORY_EXCELLENCE],
                'score_contribution' => $categoryScores[KpiLevel::CATEGORY_CONTRIBUTION],
                'score_leadership' => $categoryScores[KpiLevel::CATEGORY_LEADERSHIP],
                'final_score' => $final,
                'grade' => KpiFinalResult::gradeFor($final),
                'computed_at' => now(),
            ]
        );
    }

    /**
     * Hitung seluruh karyawan yang punya penilaian pada periode ini.
     *
     * @return int jumlah baris hasil yang tersimpan
     */
    public function computeForPeriod(KpiPeriod $period): int
    {
        $employeeIds = KpiAssessment::query()
            ->where('kpi_period_id', $period->id)
            ->submitted()
            ->distinct()
            ->pluck('employee_id');

        $saved = 0;

        Employee::query()
            ->whereIn('id', $employeeIds)
            ->with('kpiLevel')
            ->chunkById(100, function ($employees) use ($period, &$saved) {
                foreach ($employees as $employee) {
                    if ($this->computeForEmployee($period, $employee)) {
                        $saved++;
                    }
                }
            });

        return $saved;
    }

    /**
     * Skor tiap indikator: rata-rata tertimbang antar penilai. Penilai yang mengosongkan
     * satu indikator tidak ikut menyeret rata-rata — bobotnya juga tidak dihitung, sehingga
     * penilai yang benar-benar mengisi menanggung 100% indikator itu.
     *
     * @param  Collection<int, KpiAssessment>  $assessments
     * @param  Collection<int, KpiPeriodIndicatorSnapshot>  $indicators
     * @return array<int, float> id snapshot indikator => skor
     */
    private function indicatorScores(Collection $assessments, Collection $indicators): array
    {
        $sum = [];
        $weight = [];

        foreach ($assessments as $assessment) {
            foreach ($assessment->scores as $score) {
                $value = $score->effectiveScore();

                if ($value === null) {
                    continue;
                }

                $id = $score->kpi_period_indicator_snapshot_id;
                $sum[$id] = ($sum[$id] ?? 0) + ($value * (float) $assessment->weight);
                $weight[$id] = ($weight[$id] ?? 0) + (float) $assessment->weight;
            }
        }

        $result = [];

        foreach ($indicators as $indicator) {
            $result[$indicator->id] = ($weight[$indicator->id] ?? 0) > 0
                ? $sum[$indicator->id] / $weight[$indicator->id]
                : self::DEFAULT_SCORE;
        }

        return $result;
    }

    /**
     * @param  Collection<int, KpiPeriodIndicatorSnapshot>  $indicators
     * @param  array<int, float>  $indicatorScores
     */
    private function categoryScore(Collection $indicators, array $indicatorScores): float
    {
        $totalWeight = (float) $indicators->sum('weight');

        if ($totalWeight <= 0) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($indicators as $indicator) {
            $sum += ($indicatorScores[$indicator->id] ?? self::DEFAULT_SCORE) * (float) $indicator->weight;
        }

        // Dibagi total bobot sebenarnya, bukan 100 mati. Kalau admin menyimpan bobot yang
        // belum berjumlah 100, hasilnya tetap berada di skala 1–5 dan tidak diam-diam mengecil.
        return round($sum / $totalWeight, self::PRECISION);
    }

    /**
     * @param  array<string, float>  $categoryScores
     * @param  array<string, float>  $categoryWeights
     */
    private function finalScore(array $categoryScores, array $categoryWeights): float
    {
        $totalWeight = array_sum($categoryWeights);

        if ($totalWeight <= 0) {
            return 0.0;
        }

        $sum = 0.0;

        foreach ($categoryWeights as $category => $weight) {
            $sum += ($categoryScores[$category] ?? 0.0) * $weight;
        }

        return round($sum / $totalWeight, self::PRECISION);
    }
}
