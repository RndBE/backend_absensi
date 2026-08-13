<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiAssessmentScore;
use App\Models\KpiCrossItem;
use App\Models\KpiCrossLayerA;
use App\Models\KpiCrossLayerB;
use App\Models\KpiCrossResult;
use App\Models\KpiIndicator;
use App\Models\KpiLeniencyCorrection;
use App\Models\KpiPeriod;
use App\Models\KpiPeriodCrossItemSnapshot;
use Illuminate\Support\Collection;

/**
 * Perhitungan skor penilaian silang, Bab 7.9.
 *
 *   Langkah 1  Skor_A divisi   = Σ (rata-rata butir XA dari penilai sah × bobot butir)
 *   Langkah 2  Skor_B individu = Σ (rata-rata butir XB dari penilai sah × bobot butir)
 *   Langkah 3  Skor kolaborasi = campuran A dan B menurut level
 *   Langkah 4  Penyesuaian atasan ±1,0 (ditangani controller, lihat KpiCrossResult)
 *   Langkah 5  Mengisi indikator CO yang bertanda auto_source = cross_assessment
 *
 * Koreksi kemurahan hati diterapkan di sini, bukan ditulis ke baris skor, supaya angka
 * mentah penilai tetap utuh dan koreksinya bisa dibatalkan.
 */
class KpiCrossScoreCalculator
{
    private const PRECISION = 4;

    /** Kuorum Bab 7.7: minimal 3 penilai dari minimal 2 divisi berbeda. */
    public const MIN_ASSESSORS = 3;

    public const MIN_DIVISIONS = 2;

    /** Nilai bawaan saat kuorum tidak tercapai — ketiadaan data bukan berarti kinerja buruk. */
    public const DEFAULT_SCORE = 3.0;

    /**
     * Campuran Lapis A dan Lapis B per level (Bab 7.9 Langkah 3).
     * Format: [bobot A, bobot B]. L2 memiliki porsi A besar karena manajer bertanggung
     * jawab atas perilaku divisinya secara keseluruhan.
     */
    public const MIX = [
        'L2' => [50.0, 50.0],
        'L3' => [40.0, 60.0],
        'L4_cross_functional' => [50.0, 50.0],
        'L4' => [100.0, 0.0],
    ];

    /** @return array{divisions:int, employees:int} */
    public function computeForPeriod(KpiPeriod $period): array
    {
        $leniency = KpiLeniencyCorrection::where('kpi_period_id', $period->id)
            ->pluck('correction_value', 'assessor_id')
            ->map(fn ($v) => (float) $v)
            ->all();

        $weightsA = $this->itemWeights($period, KpiCrossItem::LAYER_DIVISION);
        $weightsB = $this->itemWeights($period, KpiCrossItem::LAYER_INDIVIDUAL);

        $divisions = $this->computeDivisionScores($period, $weightsA, $leniency);
        $employees = $this->computeIndividualScores($period, $weightsB, $leniency, $divisions);

        return ['divisions' => count($divisions), 'employees' => $employees];
    }

    /**
     * Langkah 1 — skor Lapis A per divisi.
     *
     * @return array<int, array{score:float|null, quorum:bool}> department_id => hasil
     */
    private function computeDivisionScores(KpiPeriod $period, array $weights, array $leniency): array
    {
        $submissions = KpiCrossLayerA::where('kpi_period_id', $period->id)
            ->valid()
            ->with('scores')
            ->get()
            ->groupBy('target_department_id');

        $results = [];

        foreach ($submissions as $departmentId => $rows) {
            $assessorCount = $rows->pluck('assessor_id')->unique()->count();
            $divisionCount = $rows->pluck('assessor_department_id')->unique()->count();
            $quorum = $assessorCount >= self::MIN_ASSESSORS && $divisionCount >= self::MIN_DIVISIONS;

            $raw = $this->weightedItemScore($rows, $weights, $leniency);
            $score = $quorum ? $raw : null;

            KpiCrossResult::updateOrCreate(
                [
                    'kpi_period_id' => $period->id,
                    'department_id' => $departmentId,
                    'employee_id' => null,
                ],
                [
                    'score_a_raw' => $raw,
                    'score_a_corrected' => $score,
                    'score_collaboration' => $score ?? self::DEFAULT_SCORE,
                    'quorum_met' => $quorum,
                    'assessor_count' => $assessorCount,
                    'division_count' => $divisionCount,
                    'computed_at' => now(),
                ]
            );

            $results[(int) $departmentId] = ['score' => $score, 'quorum' => $quorum];
        }

        return $results;
    }

    /** Langkah 2 & 3 — skor Lapis B lalu skor kolaborasi individu. */
    private function computeIndividualScores(KpiPeriod $period, array $weights, array $leniency, array $divisions): int
    {
        $byEmployee = KpiCrossLayerB::where('kpi_period_id', $period->id)
            ->valid()
            ->with('scores')
            ->get()
            ->groupBy('target_employee_id');

        // Karyawan yang tidak dinilai personal tetap dapat skor dari nilai divisinya —
        // staf L4 umumnya memang tidak berinteraksi langsung dengan divisi lain.
        $employees = Employee::query()
            ->where('company_id', $period->company_id)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->whereNotNull('kpi_level_id')
            ->with('kpiLevel')
            ->get();

        $saved = 0;

        foreach ($employees as $employee) {
            $departmentId = $this->divisionOf($employee);

            if (! $departmentId) {
                continue;
            }

            $divisionScore = $divisions[$departmentId]['score'] ?? null;
            $rows = $byEmployee->get($employee->id);

            $scoreB = null;
            $assessorCount = 0;
            $divisionCount = 0;
            $quorumB = false;

            if ($rows) {
                $assessorCount = $rows->pluck('assessor_id')->unique()->count();
                $divisionCount = $rows->pluck('assessor_department_id')->unique()->count();
                $quorumB = $assessorCount >= self::MIN_ASSESSORS && $divisionCount >= self::MIN_DIVISIONS;
                $rawB = $this->weightedItemScore($rows, $weights, $leniency);
                $scoreB = $quorumB ? $rawB : null;
            }

            [$mixA, $mixB] = $this->mixFor($employee);

            $collaboration = $this->blend(
                $divisionScore,
                $scoreB,
                $mixA,
                $mixB
            );

            KpiCrossResult::updateOrCreate(
                [
                    'kpi_period_id' => $period->id,
                    'department_id' => $departmentId,
                    'employee_id' => $employee->id,
                ],
                [
                    'score_a_raw' => $divisions[$departmentId]['score'] ?? null,
                    'score_a_corrected' => $divisionScore,
                    'score_b_raw' => $rows ? $this->weightedItemScore($rows, $weights, $leniency) : null,
                    'score_b_corrected' => $scoreB,
                    'score_collaboration' => $collaboration,
                    'quorum_met' => ($divisions[$departmentId]['quorum'] ?? false) || $quorumB,
                    'assessor_count' => $assessorCount,
                    'division_count' => $divisionCount,
                    'computed_at' => now(),
                ]
            );

            $saved++;
        }

        return $saved;
    }

    /**
     * Langkah 5 — tuliskan skor kolaborasi ke indikator CO yang ditandai auto_source
     * cross_assessment. Ditulis hanya ke assessment penilai UTAMA, sama seperti auto-fill
     * absensi, agar tampilan tidak menyiratkan penilai pendukung ikut menilainya.
     *
     * @return int jumlah baris skor indikator yang terisi
     */
    public function applyToIndicators(KpiPeriod $period): int
    {
        $results = KpiCrossResult::where('kpi_period_id', $period->id)
            ->individual()
            ->get()
            ->keyBy('employee_id');

        if ($results->isEmpty()) {
            return 0;
        }

        $assessments = KpiAssessment::where('kpi_period_id', $period->id)
            ->where('assessor_role', KpiAssessment::ROLE_PRIMARY)
            ->whereIn('employee_id', $results->keys())
            ->with('employee:id,kpi_level_id')
            ->get();

        $indicators = $period->indicatorSnapshots()
            ->where('is_auto_filled', true)
            ->where('auto_source', KpiIndicator::SOURCE_CROSS_ASSESSMENT)
            ->with('levelSnapshot:id,kpi_level_id')
            ->get()
            ->groupBy(fn ($snapshot) => $snapshot->levelSnapshot?->kpi_level_id);

        $filled = 0;

        foreach ($assessments as $assessment) {
            $result = $results->get($assessment->employee_id);
            $score = $result?->effectiveScore();

            if ($score === null) {
                continue;
            }

            foreach ($indicators->get($assessment->employee?->kpi_level_id) ?? [] as $indicator) {
                KpiAssessmentScore::updateOrCreate(
                    [
                        'kpi_assessment_id' => $assessment->id,
                        'kpi_period_indicator_snapshot_id' => $indicator->id,
                    ],
                    [
                        // Skor indikator berskala bulat 1–5; skor kolaborasi berdesimal,
                        // jadi dibulatkan di sini. Angka desimalnya tetap tersimpan utuh
                        // di kpi_cross_results untuk penelusuran.
                        'score_raw' => (int) round($score),
                        'evidence_text' => $this->evidenceFor($result),
                    ]
                );
                $filled++;
            }
        }

        return $filled;
    }

    /**
     * Campuran A/B untuk seorang karyawan. L4 lintas fungsi dipisahkan karena posisinya
     * memang berurusan harian dengan divisi lain (admin gudang, staf pembelian, QC, dll).
     *
     * @return array{0:float, 1:float}
     */
    public function mixFor(Employee $employee): array
    {
        $code = $employee->kpiLevel?->code;

        if ($code === 'L4' && $employee->is_cross_functional) {
            return self::MIX['L4_cross_functional'];
        }

        return self::MIX[$code] ?? self::MIX['L4'];
    }

    /**
     * Gabungkan skor A dan B. Bila salah satu tidak tersedia (kuorum tidak tercapai atau
     * memang tidak dinilai personal), yang tersisa menanggung penuh — bukan diisi nol,
     * karena ketiadaan data bukan penilaian buruk.
     */
    private function blend(?float $scoreA, ?float $scoreB, float $mixA, float $mixB): float
    {
        $hasA = $scoreA !== null && $mixA > 0;
        $hasB = $scoreB !== null && $mixB > 0;

        if ($hasA && $hasB) {
            return round(($scoreA * $mixA + $scoreB * $mixB) / ($mixA + $mixB), self::PRECISION);
        }

        if ($hasA) {
            return round($scoreA, self::PRECISION);
        }

        if ($hasB) {
            return round($scoreB, self::PRECISION);
        }

        return self::DEFAULT_SCORE;
    }

    /**
     * Rata-rata per butir dulu, baru ditimbang bobot butir. Urutan ini penting: menimbang
     * lebih dulu lalu merata-rata akan membuat penilai yang mengisi lebih banyak butir
     * punya pengaruh lebih besar.
     *
     * @param  Collection<int, KpiCrossLayerA|KpiCrossLayerB>  $submissions
     * @param  array<string, float>  $weights
     * @param  array<int, float>  $leniency
     */
    private function weightedItemScore(Collection $submissions, array $weights, array $leniency): ?float
    {
        $perItem = [];

        foreach ($submissions as $submission) {
            $correction = $leniency[$submission->assessor_id] ?? 0.0;

            foreach ($submission->scores as $score) {
                $value = $score->effectiveScore() - $correction;
                $perItem[$score->item_code][] = max(1.0, min(5.0, $value));
            }
        }

        if ($perItem === []) {
            return null;
        }

        $sum = 0.0;
        $totalWeight = 0.0;

        foreach ($perItem as $code => $values) {
            $weight = $weights[$code] ?? 0.0;

            if ($weight <= 0) {
                continue;
            }

            $sum += (array_sum($values) / count($values)) * $weight;
            $totalWeight += $weight;
        }

        return $totalWeight > 0 ? round($sum / $totalWeight, self::PRECISION) : null;
    }

    /** @return array<string, float> kode butir => bobot, diambil dari snapshot periode. */
    private function itemWeights(KpiPeriod $period, string $layer): array
    {
        return KpiPeriodCrossItemSnapshot::where('kpi_period_id', $period->id)
            ->layer($layer)
            ->pluck('weight', 'code')
            ->map(fn ($w) => (float) $w)
            ->all();
    }

    /** Divisi karyawan: simpul ber-`is_division` terdekat ke atas dari departemennya. */
    private function divisionOf(Employee $employee): ?int
    {
        static $cache = [];

        $departmentId = $employee->department_id;

        if (! $departmentId) {
            return null;
        }

        if (array_key_exists($departmentId, $cache)) {
            return $cache[$departmentId];
        }

        $current = Department::find($departmentId);
        $guard = 0;

        while ($current && ! $current->is_division && $current->parent_id && $guard++ < 10) {
            $current = Department::find($current->parent_id);
        }

        return $cache[$departmentId] = $current?->is_division ? (int) $current->id : (int) $departmentId;
    }

    private function evidenceFor(KpiCrossResult $result): string
    {
        if (! $result->quorum_met) {
            return sprintf(
                'Penilaian silang: kuorum tidak tercapai (%d penilai dari %d divisi) — dipakai nilai bawaan %s.',
                $result->assessor_count,
                $result->division_count,
                number_format(self::DEFAULT_SCORE, 2, ',', '.')
            );
        }

        return sprintf(
            'Otomatis dari penilaian silang antar divisi: skor kolaborasi %s (%d penilai dari %d divisi).',
            number_format((float) $result->effectiveScore(), 2, ',', '.'),
            $result->assessor_count,
            $result->division_count
        );
    }
}
