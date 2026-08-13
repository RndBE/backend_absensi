<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiAssessmentScore;
use App\Models\KpiFinalResult;
use App\Models\KpiIndicator;
use App\Models\KpiIndicatorRubric;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Support\KpiPeriodSnapshot;
use App\Support\KpiScoreCalculator;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

class KpiScoreCalculatorTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private KpiLevel $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createKpiSchema();

        $this->company = Company::create(['name' => 'PT Uji KPI']);

        $this->level = KpiLevel::create([
            'company_id' => $this->company->id,
            'code' => 'L4',
            'name' => 'Staff',
            'is_assessed' => true,
            'weight_excellence' => 70,
            'weight_contribution' => 25,
            'weight_leadership' => 5,
            'sort_order' => 4,
        ]);
    }

    /**
     * Contoh perhitungan Bab 8.3 dijalankan lewat kode sungguhan, bukan aritmetika terpisah.
     * Angka acuan: EX 3,77 · CO 3,72 · LD 3,30 · nilai akhir 3,73 · predikat C.
     */
    public function test_matches_framework_worked_example(): void
    {
        $this->seedIndicators([
            'EX' => [[20, 4], [15, 4], [12, 3], [12, 4], [11, 3], [10, 5], [10, 3], [10, 4]],
            'CO' => [[20, 3], [14, 4], [14, 5], [12, 4], [13, 3], [10, 4], [9, 3], [8, 4]],
            'LD' => [[40, 3], [30, 4], [30, 3]],
        ]);

        $result = $this->computeWithSingleAssessor();

        $this->assertNotNull($result);
        $this->assertSame('3.7700', (string) $result->score_excellence);
        $this->assertSame('3.7200', (string) $result->score_contribution);
        $this->assertSame('3.3000', (string) $result->score_leadership);
        $this->assertSame('3.7340', (string) $result->final_score);
        $this->assertSame('C', $result->grade);
        $this->assertSame('3,73', number_format((float) $result->final_score, 2, ',', '.'));
    }

    /** Penilai utama 70% dan pendukung 30% dirata-rata tertimbang, bukan dirata-rata biasa. */
    public function test_primary_and_supporting_assessor_are_weighted(): void
    {
        $this->seedIndicators([
            'EX' => [[100, null]],
            'CO' => [[100, null]],
            'LD' => [[100, null]],
        ]);

        $period = $this->openPeriod();
        $employee = $this->makeEmployee('staff@example.test');
        $snapshots = $period->indicatorSnapshots()->get()->keyBy('category');

        $primary = $this->makeAssessment($period, $employee, 'atasan@example.test', KpiAssessment::ROLE_PRIMARY, 70);
        $supporting = $this->makeAssessment($period, $employee, 'manajer@example.test', KpiAssessment::ROLE_SUPPORTING, 30);

        foreach ($snapshots as $snapshot) {
            $this->score($primary, $snapshot->id, 4);
            $this->score($supporting, $snapshot->id, 2);
        }

        $result = app(KpiScoreCalculator::class)->computeForEmployee($period, $employee);

        // (4 × 70 + 2 × 30) / 100 = 3,40 — rata-rata biasa akan menghasilkan 3,00.
        $this->assertSame('3.4000', (string) $result->final_score);
    }

    /** Pendukung tidak mengisi: bobotnya tidak ikut dihitung, penilai utama menanggung penuh. */
    public function test_unfilled_supporting_assessor_does_not_dilute_score(): void
    {
        $this->seedIndicators(['EX' => [[100, null]], 'CO' => [[100, null]], 'LD' => [[100, null]]]);

        $period = $this->openPeriod();
        $employee = $this->makeEmployee('staff2@example.test');
        $snapshots = $period->indicatorSnapshots()->get();

        $primary = $this->makeAssessment($period, $employee, 'atasan2@example.test', KpiAssessment::ROLE_PRIMARY, 70);
        $this->makeAssessment($period, $employee, 'manajer2@example.test', KpiAssessment::ROLE_SUPPORTING, 30);

        foreach ($snapshots as $snapshot) {
            $this->score($primary, $snapshot->id, 5);
        }

        $result = app(KpiScoreCalculator::class)->computeForEmployee($period, $employee);

        $this->assertSame('5.0000', (string) $result->final_score);
        $this->assertSame('A', $result->grade);
    }

    /** Indikator tanpa skor sama sekali memakai nilai bawaan 3, bukan 0 (Bab 11.4). */
    public function test_indicator_without_any_score_defaults_to_three(): void
    {
        $this->seedIndicators([
            'EX' => [[50, 5], [50, null]],
            'CO' => [[100, 5]],
            'LD' => [[100, 5]],
        ]);

        $result = $this->computeWithSingleAssessor();

        // EX = (5 × 50 + 3 × 50) / 100 = 4,00
        $this->assertSame('4.0000', (string) $result->score_excellence);
        $this->assertSame('5.0000', (string) $result->score_contribution);
    }

    public function test_grade_thresholds_use_unrounded_score(): void
    {
        $this->assertSame('A', KpiFinalResult::gradeFor(4.5));
        $this->assertSame('B', KpiFinalResult::gradeFor(4.4999));
        $this->assertSame('B', KpiFinalResult::gradeFor(4.0));
        $this->assertSame('C', KpiFinalResult::gradeFor(3.9999));
        $this->assertSame('D', KpiFinalResult::gradeFor(2.9999));
        $this->assertSame('E', KpiFinalResult::gradeFor(1.9999));
        $this->assertNull(KpiFinalResult::gradeFor(null));
    }

    /** Karyawan tanpa penilaian terkirim tidak menghasilkan baris apa pun. */
    public function test_employee_without_submitted_assessment_has_no_result(): void
    {
        $this->seedIndicators(['EX' => [[100, 4]], 'CO' => [[100, 4]], 'LD' => [[100, 4]]]);

        $period = $this->openPeriod();
        $employee = $this->makeEmployee('kosong@example.test');

        $this->assertNull(app(KpiScoreCalculator::class)->computeForEmployee($period, $employee));
        $this->assertSame(0, KpiFinalResult::count());
    }

    // ────────────────────────────── helper ──────────────────────────────

    /** @param array<string, array<int, array{0:int|float, 1:int|null}>> $sets kategori => [[bobot, skor]] */
    private function seedIndicators(array $sets): void
    {
        $this->scorePlan = [];

        foreach ($sets as $category => $rows) {
            foreach ($rows as $i => [$weight, $score]) {
                $indicator = KpiIndicator::create([
                    'company_id' => $this->company->id,
                    'kpi_level_id' => $this->level->id,
                    'category' => $category,
                    'code' => $category.'-L4-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                    'name' => 'Indikator '.$category.($i + 1),
                    'weight' => $weight,
                    'sort_order' => $i + 1,
                ]);

                KpiIndicatorRubric::create([
                    'kpi_indicator_id' => $indicator->id,
                    'score' => 3,
                    'description' => 'Sesuai harapan',
                ]);

                $this->scorePlan[$indicator->code] = $score;
            }
        }
    }

    /** @var array<string, int|null> kode indikator => skor yang akan diisi penilai */
    private array $scorePlan = [];

    private function openPeriod(): KpiPeriod
    {
        $period = KpiPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Semester Uji',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        app(KpiPeriodSnapshot::class)->build($period);
        $period->update(['status' => KpiPeriod::STATUS_OPEN]);

        return $period->refresh();
    }

    private function computeWithSingleAssessor(): ?KpiFinalResult
    {
        $period = $this->openPeriod();
        $employee = $this->makeEmployee('dinilai@example.test');
        $assessment = $this->makeAssessment($period, $employee, 'penilai@example.test', KpiAssessment::ROLE_PRIMARY, 100);

        foreach ($period->indicatorSnapshots()->get() as $snapshot) {
            $score = $this->scorePlan[$snapshot->code] ?? null;

            if ($score !== null) {
                $this->score($assessment, $snapshot->id, $score);
            }
        }

        return app(KpiScoreCalculator::class)->computeForEmployee($period, $employee);
    }

    private function makeEmployee(string $email): Employee
    {
        return Employee::create([
            'employee_code' => substr(md5($email), 0, 8),
            'company_id' => $this->company->id,
            'full_name' => 'Karyawan '.$email,
            'email' => $email,
            'password' => 'secret',
            'kpi_level_id' => $this->level->id,
            'is_active' => true,
        ]);
    }

    private function makeAssessment(KpiPeriod $period, Employee $employee, string $assessorEmail, string $role, float $weight): KpiAssessment
    {
        return KpiAssessment::create([
            'kpi_period_id' => $period->id,
            'employee_id' => $employee->id,
            'assessor_id' => $this->makeEmployee($assessorEmail)->id,
            'assessor_role' => $role,
            'weight' => $weight,
            'status' => KpiAssessment::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    private function score(KpiAssessment $assessment, int $snapshotId, int $score): void
    {
        KpiAssessmentScore::create([
            'kpi_assessment_id' => $assessment->id,
            'kpi_period_indicator_snapshot_id' => $snapshotId,
            'score_raw' => $score,
            'evidence_text' => $score === 3 ? null : 'Contoh kejadian.',
        ]);
    }
}
