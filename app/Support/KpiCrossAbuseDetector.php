<?php

namespace App\Support;

use App\Models\KpiCrossLayerA;
use App\Models\KpiCrossLayerB;
use App\Models\KpiLeniencyCorrection;
use App\Models\KpiPeriod;
use Illuminate\Support\Collection;

/**
 * Aturan anti-penyalahgunaan Bab 7.8.
 *
 * Prinsip yang dipegang di sini: yang otomatis hanya yang tidak bisa salah tafsir.
 * Straight-lining dan koreksi kemurahan hati dijalankan sendiri oleh sistem; pembalasan
 * hanya DITANDAI untuk ditinjau HRD, karena di baliknya biasanya ada konflik nyata yang
 * perlu dimediasi — bukan data yang perlu dibuang.
 */
class KpiCrossAbuseDetector
{
    /** Ambang skor rendah timbal balik yang memicu peninjauan pembalasan (Bab 7.8a). */
    public const RETALIATION_THRESHOLD = 2.5;

    /** Skor rata-rata yang dianggap "sempurna" untuk deteksi persekongkolan (Bab 7.8c). */
    public const COLLUSION_MUTUAL_MINIMUM = 4.75;

    /** Selisih minimal terhadap penilai lain sebelum sebuah pasangan disebut bersekongkol. */
    public const COLLUSION_GAP = 1.0;

    /**
     * Jalankan seluruh pemeriksaan. Aman diulang: setiap langkah menghitung ulang dari
     * skor mentah, jadi memproses dua kali tidak menumpuk koreksi.
     *
     * @return array{straight_lining:int, collusion:int, leniency:int, retaliation:array<int, array<string, mixed>>}
     */
    public function process(KpiPeriod $period): array
    {
        $this->resetCorrections($period);

        return [
            'straight_lining' => $this->flagStraightLining($period),
            'collusion' => $this->trimCollusion($period),
            'leniency' => $this->computeLeniency($period),
            'retaliation' => $this->detectRetaliation($period),
        ];
    }

    /**
     * Buang hasil pemrosesan sebelumnya sebelum menghitung ulang. Hanya menyentuh penanda
     * yang dipasang SISTEM — pembuangan manual oleh HRD (alasan selain straight_lining
     * dan collusion) sengaja dibiarkan.
     */
    private function resetCorrections(KpiPeriod $period): void
    {
        $systemReasons = [KpiCrossLayerA::INVALID_STRAIGHT_LINING];

        KpiCrossLayerA::where('kpi_period_id', $period->id)
            ->whereIn('invalid_reason', $systemReasons)
            ->update(['is_valid' => true, 'invalid_reason' => null]);

        KpiCrossLayerB::where('kpi_period_id', $period->id)
            ->whereIn('invalid_reason', $systemReasons)
            ->update(['is_valid' => true, 'invalid_reason' => null]);

        foreach ([KpiCrossLayerA::class, KpiCrossLayerB::class] as $model) {
            $ids = $model::where('kpi_period_id', $period->id)->pluck('id');
            $relation = $model === KpiCrossLayerA::class
                ? \App\Models\KpiCrossLayerAScore::class
                : \App\Models\KpiCrossLayerBScore::class;
            $foreignKey = $model === KpiCrossLayerA::class
                ? 'kpi_cross_layer_a_id'
                : 'kpi_cross_layer_b_id';

            $relation::whereIn($foreignKey, $ids)
                ->whereNotNull('score_corrected')
                ->update(['score_corrected' => null, 'correction_reason' => null]);
        }

        KpiLeniencyCorrection::where('kpi_period_id', $period->id)->delete();
    }

    /**
     * Penilai yang memberi angka identik untuk seluruh butir — standar deviasi nol.
     * Kuesionernya dibuang dari perhitungan, tapi barisnya tetap ada agar bisa ditinjau.
     */
    public function flagStraightLining(KpiPeriod $period): int
    {
        $flagged = 0;

        foreach ([KpiCrossLayerA::class, KpiCrossLayerB::class] as $model) {
            $rows = $model::where('kpi_period_id', $period->id)
                ->submitted()
                ->with('scores')
                ->get();

            foreach ($rows as $row) {
                // Kuesioner dengan satu butir saja tidak bisa dinilai variasinya — dilewati,
                // bukan dianggap asal-asalan.
                if ($row->scores->count() < 2) {
                    continue;
                }

                if ($row->scoreDeviation() > 0.0) {
                    continue;
                }

                $row->update([
                    'is_valid' => false,
                    'invalid_reason' => KpiCrossLayerA::INVALID_STRAIGHT_LINING,
                ]);
                $flagged++;
            }
        }

        return $flagged;
    }

    /**
     * Dua divisi yang saling memberi skor nyaris sempurna sementara divisi lain menilai
     * mereka biasa saja. Skornya dipangkas ke rata-rata penilai lain — tidak dibuang,
     * karena mungkin saja kerja samanya memang bagus.
     *
     * @return int jumlah baris skor yang dipangkas
     */
    public function trimCollusion(KpiPeriod $period): int
    {
        $submissions = KpiCrossLayerA::where('kpi_period_id', $period->id)
            ->valid()
            ->with('scores')
            ->get();

        // Rata-rata skor satu kuesioner, dikelompokkan per pasangan divisi penilai → dinilai.
        $pairMeans = [];

        foreach ($submissions as $row) {
            $mean = $this->meanOf($row->scores);

            if ($mean === null) {
                continue;
            }

            $pairMeans[$row->assessor_department_id][$row->target_department_id][] = [
                'submission' => $row,
                'mean' => $mean,
            ];
        }

        $trimmed = 0;

        foreach ($pairMeans as $assessorDept => $targets) {
            foreach ($targets as $targetDept => $rows) {
                $forward = $this->averageOfColumn($rows, 'mean');
                $backward = $this->averageOfColumn($pairMeans[$targetDept][$assessorDept] ?? [], 'mean');

                if ($forward === null || $backward === null) {
                    continue;
                }

                if ($forward < self::COLLUSION_MUTUAL_MINIMUM || $backward < self::COLLUSION_MUTUAL_MINIMUM) {
                    continue;
                }

                // Pendapat divisi lain tentang divisi yang dinilai.
                $othersMean = $this->meanFromOtherAssessors($pairMeans, $targetDept, $assessorDept);

                if ($othersMean === null || ($forward - $othersMean) < self::COLLUSION_GAP) {
                    continue;
                }

                foreach ($rows as $row) {
                    foreach ($row['submission']->scores as $score) {
                        $score->update([
                            'score_corrected' => round($othersMean, 4),
                            'correction_reason' => 'collusion',
                        ]);
                        $trimmed++;
                    }
                }
            }
        }

        return $trimmed;
    }

    /**
     * Koreksi kemurahan hati (Bab 7.8d):
     *
     *   Skor terkoreksi = Skor mentah − (Rata-rata skor penilai − Rata-rata seluruh perusahaan)
     *
     * Hanya diterapkan bila selisihnya melebihi ambang; di bawah itu koreksi cuma menambah
     * kerumitan tanpa manfaat. Nilainya disimpan, bukan langsung ditulis ke tiap baris skor,
     * supaya bisa dibatalkan tanpa kehilangan angka aslinya.
     *
     * @return int jumlah penilai yang dikoreksi
     */
    public function computeLeniency(KpiPeriod $period): int
    {
        $perAssessor = $this->assessorScoreLists($period);

        if ($perAssessor === []) {
            return 0;
        }

        $allScores = array_merge(...array_values($perAssessor));
        $companyMean = array_sum($allScores) / count($allScores);

        $corrected = 0;

        foreach ($perAssessor as $assessorId => $scores) {
            $assessorMean = array_sum($scores) / count($scores);
            $difference = $assessorMean - $companyMean;

            if (abs($difference) <= KpiLeniencyCorrection::THRESHOLD) {
                continue;
            }

            KpiLeniencyCorrection::updateOrCreate(
                ['kpi_period_id' => $period->id, 'assessor_id' => $assessorId],
                [
                    'assessor_mean' => round($assessorMean, 4),
                    'company_mean' => round($companyMean, 4),
                    'correction_value' => round($difference, 4),
                ]
            );
            $corrected++;
        }

        return $corrected;
    }

    /**
     * Pasangan divisi yang saling memberi skor rendah. Sengaja TIDAK diubah otomatis —
     * Bab 7.8a meminta kedua kepala divisi dipanggil, karena biasanya memang ada konflik
     * nyata yang perlu diselesaikan.
     *
     * @return array<int, array{department_id:int, partner_id:int, score_to_partner:float, score_from_partner:float}>
     */
    public function detectRetaliation(KpiPeriod $period): array
    {
        $means = [];

        foreach (KpiCrossLayerA::where('kpi_period_id', $period->id)->valid()->with('scores')->get() as $row) {
            $mean = $this->meanOf($row->scores);

            if ($mean !== null) {
                $means[$row->assessor_department_id][$row->target_department_id][] = $mean;
            }
        }

        $pairs = [];
        $seen = [];

        foreach ($means as $from => $targets) {
            foreach ($targets as $to => $values) {
                $key = min($from, $to).'-'.max($from, $to);

                if (isset($seen[$key]) || ! isset($means[$to][$from])) {
                    continue;
                }

                $forward = array_sum($values) / count($values);
                $backwardValues = $means[$to][$from];
                $backward = array_sum($backwardValues) / count($backwardValues);

                if ($forward >= self::RETALIATION_THRESHOLD || $backward >= self::RETALIATION_THRESHOLD) {
                    continue;
                }

                $seen[$key] = true;
                $pairs[] = [
                    'department_id' => (int) $from,
                    'partner_id' => (int) $to,
                    'score_to_partner' => round($forward, 4),
                    'score_from_partner' => round($backward, 4),
                ];
            }
        }

        return $pairs;
    }

    /** @return array<int, array<int, float>> assessor_id => daftar skor butir */
    private function assessorScoreLists(KpiPeriod $period): array
    {
        $lists = [];

        foreach (KpiCrossLayerA::where('kpi_period_id', $period->id)->valid()->with('scores')->get() as $row) {
            foreach ($row->scores as $score) {
                $lists[$row->assessor_id][] = $score->effectiveScore();
            }
        }

        foreach (KpiCrossLayerB::where('kpi_period_id', $period->id)->valid()->with('scores')->get() as $row) {
            foreach ($row->scores as $score) {
                $lists[$row->assessor_id][] = $score->effectiveScore();
            }
        }

        return $lists;
    }

    private function meanOf(Collection $scores): ?float
    {
        if ($scores->isEmpty()) {
            return null;
        }

        return $scores->sum(fn ($s) => $s->effectiveScore()) / $scores->count();
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function averageOfColumn(array $rows, string $column): ?float
    {
        if ($rows === []) {
            return null;
        }

        return array_sum(array_column($rows, $column)) / count($rows);
    }

    /** Rata-rata penilaian terhadap $targetDept dari semua divisi selain $excludeDept. */
    private function meanFromOtherAssessors(array $pairMeans, int $targetDept, int $excludeDept): ?float
    {
        $values = [];

        foreach ($pairMeans as $assessorDept => $targets) {
            if ($assessorDept === $excludeDept || ! isset($targets[$targetDept])) {
                continue;
            }

            foreach ($targets[$targetDept] as $row) {
                $values[] = $row['mean'];
            }
        }

        return $values === [] ? null : array_sum($values) / count($values);
    }
}
