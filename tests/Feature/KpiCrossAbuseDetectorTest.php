<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiCrossLayerA;
use App\Models\KpiCrossLayerAScore;
use App\Models\KpiLeniencyCorrection;
use App\Models\KpiPeriod;
use App\Support\KpiCrossAbuseDetector;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

class KpiCrossAbuseDetectorTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private KpiPeriod $period;

    private array $divisions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createKpiSchema();

        $this->company = Company::create(['name' => 'PT Abuse']);

        foreach (['Produksi', 'Gudang', 'QC', 'Keuangan'] as $name) {
            $this->divisions[$name] = Department::create([
                'company_id' => $this->company->id,
                'name' => $name,
                'is_division' => true,
            ]);
        }

        $this->period = KpiPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Periode Abuse',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => KpiPeriod::STATUS_PROCESSING,
        ]);
    }

    /** Angka identik untuk seluruh butir — standar deviasi nol (Bab 7.8b). */
    public function test_straight_lining_questionnaire_is_flagged_invalid(): void
    {
        $lazy = $this->layerA('malas@x.test', 'Produksi', 'Gudang', [3, 3, 3, 3]);
        $normal = $this->layerA('rajin@x.test', 'QC', 'Gudang', [4, 2, 5, 3]);

        $report = app(KpiCrossAbuseDetector::class)->process($this->period);

        $this->assertSame(1, $report['straight_lining']);
        $this->assertFalse($lazy->refresh()->is_valid);
        $this->assertSame('straight_lining', $lazy->invalid_reason);
        $this->assertTrue($normal->refresh()->is_valid);
    }

    /** Semua 5 juga straight-lining — bukan hanya semua 3. */
    public function test_all_maximum_scores_also_count_as_straight_lining(): void
    {
        $row = $this->layerA('pemurah@x.test', 'Produksi', 'Gudang', [5, 5, 5, 5]);

        app(KpiCrossAbuseDetector::class)->process($this->period);

        $this->assertFalse($row->refresh()->is_valid);
    }

    /** Menjalankan ulang tidak boleh menumpuk penanda atau koreksi. */
    public function test_processing_twice_is_idempotent(): void
    {
        $this->layerA('malas@x.test', 'Produksi', 'Gudang', [3, 3, 3, 3]);
        $this->layerA('rajin@x.test', 'QC', 'Gudang', [4, 2, 5, 3]);

        $detector = app(KpiCrossAbuseDetector::class);
        $first = $detector->process($this->period);
        $second = $detector->process($this->period);

        $this->assertSame($first['straight_lining'], $second['straight_lining']);
        $this->assertSame(
            KpiCrossLayerA::where('is_valid', false)->count(),
            1,
            'Kuesioner yang sama tidak boleh ditandai dua kali.'
        );
    }

    /** Pasangan divisi yang saling memberi skor rendah hanya DITANDAI, tidak dibuang. */
    public function test_retaliation_pair_is_reported_but_not_invalidated(): void
    {
        $a = $this->layerA('p@x.test', 'Produksi', 'Gudang', [2, 1, 2, 3]);
        $b = $this->layerA('g@x.test', 'Gudang', 'Produksi', [1, 2, 3, 2]);

        $report = app(KpiCrossAbuseDetector::class)->process($this->period);

        $this->assertCount(1, $report['retaliation']);
        $this->assertTrue($a->refresh()->is_valid, 'Data pembalasan tidak boleh dibuang otomatis.');
        $this->assertTrue($b->refresh()->is_valid);

        $pair = $report['retaliation'][0];
        $this->assertLessThan(KpiCrossAbuseDetector::RETALIATION_THRESHOLD, $pair['score_to_partner']);
        $this->assertLessThan(KpiCrossAbuseDetector::RETALIATION_THRESHOLD, $pair['score_from_partner']);
    }

    /** Pasangan yang saling menilai baik tidak boleh muncul sebagai pembalasan. */
    public function test_healthy_pair_is_not_reported_as_retaliation(): void
    {
        $this->layerA('p@x.test', 'Produksi', 'Gudang', [4, 3, 5, 4]);
        $this->layerA('g@x.test', 'Gudang', 'Produksi', [4, 5, 3, 4]);

        $report = app(KpiCrossAbuseDetector::class)->process($this->period);

        $this->assertSame([], $report['retaliation']);
    }

    /**
     * Dua divisi saling memberi nilai sempurna sementara divisi lain menilai biasa saja —
     * skornya dipangkas ke rata-rata penilai lain, bukan dibuang (Bab 7.8c).
     */
    public function test_colluding_pair_scores_are_trimmed_to_other_assessors_mean(): void
    {
        // Produksi ⇄ Gudang saling sempurna.
        $collude = $this->layerA('p@x.test', 'Produksi', 'Gudang', [5, 5, 5, 4]);
        $this->layerA('g@x.test', 'Gudang', 'Produksi', [5, 5, 4, 5]);

        // Divisi lain menilai Gudang biasa saja.
        $this->layerA('q1@x.test', 'QC', 'Gudang', [3, 2, 3, 4]);
        $this->layerA('k1@x.test', 'Keuangan', 'Gudang', [3, 3, 2, 4]);

        $report = app(KpiCrossAbuseDetector::class)->process($this->period);

        $this->assertGreaterThan(0, $report['collusion']);

        $trimmed = KpiCrossLayerAScore::where('kpi_cross_layer_a_id', $collude->id)->get();
        $this->assertTrue($trimmed->every(fn ($s) => $s->score_corrected !== null));
        $this->assertSame('collusion', $trimmed->first()->correction_reason);

        // Skor mentah tidak boleh ditimpa — koreksi harus dapat dibatalkan (Bab 11.4).
        $this->assertSame(5, $trimmed->first()->score);
    }

    /** Koreksi kemurahan hati hanya berlaku bila selisihnya melebihi 0,5 (Bab 7.8d). */
    public function test_leniency_correction_only_applies_beyond_threshold(): void
    {
        // Keuangan sangat murah nilai; tiga divisi lain menilai wajar.
        $this->layerA('murah@x.test', 'Keuangan', 'Gudang', [5, 5, 5, 4]);
        $this->layerA('wajar1@x.test', 'QC', 'Gudang', [3, 2, 3, 2]);
        $this->layerA('wajar2@x.test', 'Produksi', 'Gudang', [2, 3, 2, 3]);
        $this->layerA('wajar3@x.test', 'Produksi', 'QC', [3, 2, 3, 2]);

        $report = app(KpiCrossAbuseDetector::class)->process($this->period);

        $this->assertGreaterThanOrEqual(1, $report['leniency']);

        $correction = KpiLeniencyCorrection::whereHas(
            'assessor',
            fn ($q) => $q->where('email', 'murah@x.test')
        )->first();

        $this->assertNotNull($correction, 'Penilai yang jauh lebih murah harus dikoreksi.');
        $this->assertGreaterThan(
            KpiLeniencyCorrection::THRESHOLD,
            abs((float) $correction->correction_value)
        );
    }

    /** Selisih kecil tidak dikoreksi — koreksi kecil hanya menambah kerumitan. */
    public function test_similar_assessors_get_no_leniency_correction(): void
    {
        $this->layerA('a@x.test', 'Produksi', 'Gudang', [3, 4, 3, 4]);
        $this->layerA('b@x.test', 'QC', 'Gudang', [4, 3, 4, 3]);
        $this->layerA('c@x.test', 'Keuangan', 'Gudang', [3, 3, 4, 4]);

        $report = app(KpiCrossAbuseDetector::class)->process($this->period);

        $this->assertSame(0, $report['leniency']);
        $this->assertSame(0, KpiLeniencyCorrection::count());
    }

    private function layerA(string $email, string $assessorDivision, string $targetDivision, array $scores): KpiCrossLayerA
    {
        $assessor = Employee::firstOrCreate(
            ['email' => $email],
            [
                'employee_code' => substr(md5($email), 0, 8),
                'company_id' => $this->company->id,
                'full_name' => 'Penilai '.$email,
                'password' => 'secret',
                'department_id' => $this->divisions[$assessorDivision]->id,
                'is_active' => true,
            ]
        );

        $submission = KpiCrossLayerA::create([
            'kpi_period_id' => $this->period->id,
            'assessor_id' => $assessor->id,
            'assessor_department_id' => $this->divisions[$assessorDivision]->id,
            'target_department_id' => $this->divisions[$targetDivision]->id,
            'comment_positive' => 'Bagus',
            'comment_improvement' => 'Perlu perbaikan',
            'submitted_at' => now(),
        ]);

        foreach ($scores as $index => $score) {
            KpiCrossLayerAScore::create([
                'kpi_cross_layer_a_id' => $submission->id,
                'item_code' => 'XA-0'.($index + 1),
                'score' => $score,
            ]);
        }

        return $submission;
    }
}
