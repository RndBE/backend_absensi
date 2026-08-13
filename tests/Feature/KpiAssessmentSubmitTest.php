<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\KpiAssessmentController;
use App\Models\Company;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiIndicator;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Support\KpiPeriodSnapshot;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

class KpiAssessmentSubmitTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private KpiLevel $level;

    private KpiPeriod $period;

    private Employee $employee;

    private Employee $assessor;

    private KpiAssessment $assessment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createKpiSchema();

        $this->company = Company::create(['name' => 'PT Bukti']);
        $this->level = KpiLevel::create([
            'company_id' => $this->company->id,
            'code' => 'L4',
            'name' => 'Staff',
            'is_assessed' => true,
            'weight_excellence' => 70,
            'weight_contribution' => 25,
            'weight_leadership' => 5,
        ]);

        foreach (['EX', 'CO', 'LD'] as $category) {
            KpiIndicator::create([
                'company_id' => $this->company->id,
                'kpi_level_id' => $this->level->id,
                'category' => $category,
                'code' => $category.'-L4-01',
                'name' => 'Indikator '.$category,
                'weight' => 100,
                'sort_order' => 1,
            ]);
        }

        $this->period = KpiPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Semester I 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);

        app(KpiPeriodSnapshot::class)->build($this->period);
        $this->period->update(['status' => KpiPeriod::STATUS_FILLING]);

        $this->employee = $this->makeEmployee('bawahan@example.test');
        $this->assessor = $this->makeEmployee('atasan@example.test');

        $this->assessment = KpiAssessment::create([
            'kpi_period_id' => $this->period->id,
            'employee_id' => $this->employee->id,
            'assessor_id' => $this->assessor->id,
            'assessor_role' => KpiAssessment::ROLE_PRIMARY,
            'weight' => 100,
            'status' => KpiAssessment::STATUS_DRAFT,
        ]);

        session(['admin_id' => $this->assessor->id]);
    }

    /** Skor 5 tanpa contoh kejadian ditolak (Bab 1.3). */
    public function test_submit_is_rejected_when_high_score_has_no_evidence(): void
    {
        $response = $this->submit($this->allScores(5), []);

        $this->assertSame(KpiAssessment::STATUS_DRAFT, $this->assessment->refresh()->status);
        $this->assertStringContainsString('wajib disertai contoh kejadian', session('error'));
        $this->assertStringContainsString('EX-L4-01 (skor 5)', session('error'));
        $this->assertNotNull($response);
    }

    /** Skor 1 juga menuntut bukti — aturannya dua arah, bukan hanya untuk nilai bagus. */
    public function test_submit_is_rejected_when_low_score_has_no_evidence(): void
    {
        $this->submit($this->allScores(2), []);

        $this->assertSame(KpiAssessment::STATUS_DRAFT, $this->assessment->refresh()->status);
        $this->assertStringContainsString('wajib disertai contoh kejadian', session('error'));
    }

    /** Skor 3 dianggap sesuai standar dan tidak perlu dijelaskan. */
    public function test_submit_succeeds_when_all_scores_are_three_without_evidence(): void
    {
        $this->submit($this->allScores(3), []);

        $this->assertSame(KpiAssessment::STATUS_SUBMITTED, $this->assessment->refresh()->status);
        $this->assertNotNull($this->assessment->submitted_at);
    }

    public function test_submit_succeeds_when_extreme_scores_carry_evidence(): void
    {
        $evidence = [];

        foreach ($this->snapshotIds() as $id) {
            $evidence[$id] = 'Menyelesaikan proyek X lebih cepat dari tenggat.';
        }

        $this->submit($this->allScores(5), $evidence);

        $this->assertSame(KpiAssessment::STATUS_SUBMITTED, $this->assessment->refresh()->status);
    }

    public function test_submit_is_rejected_when_an_indicator_has_no_score(): void
    {
        $scores = $this->allScores(3);
        array_pop($scores);

        $this->submit($scores, []);

        $this->assertSame(KpiAssessment::STATUS_DRAFT, $this->assessment->refresh()->status);
        $this->assertStringContainsString('belum diberi skor', session('error'));
    }

    /** Draft boleh setengah terisi dan tidak menjalankan aturan bukti. */
    public function test_draft_save_accepts_partial_input(): void
    {
        $request = Request::create('/admin/kpi/assessments/'.$this->assessment->id.'/draft', 'POST', [
            'scores' => [$this->snapshotIds()[0] => 5],
        ]);

        app(KpiAssessmentController::class)->update($request, $this->assessment);

        $this->assertSame(KpiAssessment::STATUS_DRAFT, $this->assessment->refresh()->status);
        $this->assertSame(1, $this->assessment->scores()->whereNotNull('score_raw')->count());
    }

    /** Penilaian sudah terkirim tidak bisa ditimpa lewat jalur draft. */
    public function test_submitted_assessment_cannot_be_edited_again(): void
    {
        $this->submit($this->allScores(3), []);
        $this->assertSame(KpiAssessment::STATUS_SUBMITTED, $this->assessment->refresh()->status);

        $request = Request::create('/admin/kpi/assessments/'.$this->assessment->id.'/draft', 'POST', [
            'scores' => [$this->snapshotIds()[0] => 1],
        ]);

        app(KpiAssessmentController::class)->update($request, $this->assessment->refresh());

        $this->assertStringContainsString('sudah dikirim', session('error'));
        $this->assertSame(3, $this->assessment->scores()->first()->score_raw);
    }

    /** Orang lain tidak boleh membuka penilaian yang bukan miliknya. */
    public function test_other_employee_cannot_open_assessment(): void
    {
        $intruder = $this->makeEmployee('penyusup@example.test');
        session(['admin_id' => $intruder->id]);

        $this->expectException(HttpException::class);

        app(KpiAssessmentController::class)->edit($this->assessment);
    }

    // ────────────────────────────── helper ──────────────────────────────

    /** @return array<int, int> id snapshot indikator */
    private function snapshotIds(): array
    {
        return $this->period->indicatorSnapshots()->orderBy('code')->pluck('id')->all();
    }

    /** @return array<int, int> id snapshot => skor */
    private function allScores(int $score): array
    {
        return array_fill_keys($this->snapshotIds(), $score);
    }

    private function submit(array $scores, array $evidence)
    {
        $request = Request::create('/admin/kpi/assessments/'.$this->assessment->id.'/submit', 'POST', [
            'scores' => $scores,
            'evidence' => $evidence,
        ]);

        return app(KpiAssessmentController::class)->submit($request, $this->assessment);
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
}
