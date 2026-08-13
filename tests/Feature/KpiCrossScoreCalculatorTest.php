<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiCrossItem;
use App\Models\KpiCrossLayerA;
use App\Models\KpiCrossLayerAScore;
use App\Models\KpiCrossLayerB;
use App\Models\KpiCrossLayerBScore;
use App\Models\KpiCrossResult;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Models\KpiPeriodCrossItemSnapshot;
use App\Support\KpiCrossScoreCalculator;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

class KpiCrossScoreCalculatorTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private KpiPeriod $period;

    private array $levels = [];

    private array $divisions = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createKpiSchema();

        $this->company = Company::create(['name' => 'PT Silang']);

        foreach (['L2', 'L3', 'L4'] as $code) {
            $this->levels[$code] = KpiLevel::create([
                'company_id' => $this->company->id,
                'code' => $code,
                'name' => $code,
                'is_assessed' => true,
                'weight_excellence' => 70,
                'weight_contribution' => 25,
                'weight_leadership' => 5,
            ]);
        }

        foreach (['Produksi', 'Gudang', 'QC'] as $name) {
            $this->divisions[$name] = Department::create([
                'company_id' => $this->company->id,
                'name' => $name,
                'is_division' => true,
            ]);
        }

        $this->period = KpiPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Semester Silang',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => KpiPeriod::STATUS_FILLING,
        ]);

        // Dua butir per lapis, bobot 60/40, supaya pembobotan benar-benar teruji —
        // dengan bobot sama, kesalahan pembobotan tidak akan terlihat.
        $this->snapshotItem(KpiCrossItem::LAYER_DIVISION, 'XA-01', 60);
        $this->snapshotItem(KpiCrossItem::LAYER_DIVISION, 'XA-02', 40);
        $this->snapshotItem(KpiCrossItem::LAYER_INDIVIDUAL, 'XB-01', 60);
        $this->snapshotItem(KpiCrossItem::LAYER_INDIVIDUAL, 'XB-02', 40);
    }

    /** Rata-rata per butir dulu, baru ditimbang bobot butir (Bab 7.9 Langkah 1). */
    public function test_division_score_is_weighted_by_item_weight(): void
    {
        $target = $this->divisions['Gudang'];

        // Tiga penilai dari dua divisi berbeda → kuorum terpenuhi.
        $this->layerA('p1@x.test', 'Produksi', $target, ['XA-01' => 5, 'XA-02' => 1]);
        $this->layerA('p2@x.test', 'Produksi', $target, ['XA-01' => 5, 'XA-02' => 1]);
        $this->layerA('q1@x.test', 'QC', $target, ['XA-01' => 5, 'XA-02' => 1]);

        app(KpiCrossScoreCalculator::class)->computeForPeriod($this->period);

        $result = KpiCrossResult::divisionLevel()->where('department_id', $target->id)->first();

        // (5 × 60 + 1 × 40) / 100 = 3,40 — rata-rata biasa akan memberi 3,00.
        $this->assertTrue($result->quorum_met);
        $this->assertSame('3.4000', (string) $result->score_a_corrected);
    }

    /** Kuorum Bab 7.7: minimal 3 penilai DARI minimal 2 divisi berbeda. */
    public function test_quorum_fails_when_all_assessors_come_from_one_division(): void
    {
        $target = $this->divisions['Gudang'];

        $this->layerA('a1@x.test', 'Produksi', $target, ['XA-01' => 5, 'XA-02' => 5]);
        $this->layerA('a2@x.test', 'Produksi', $target, ['XA-01' => 5, 'XA-02' => 4]);
        $this->layerA('a3@x.test', 'Produksi', $target, ['XA-01' => 5, 'XA-02' => 5]);

        app(KpiCrossScoreCalculator::class)->computeForPeriod($this->period);

        $result = KpiCrossResult::divisionLevel()->where('department_id', $target->id)->first();

        $this->assertFalse($result->quorum_met);
        $this->assertNull($result->score_a_corrected);
        // Hasil tidak dipakai; nilai bawaan 3 dipasang, bukan 0 (Bab 7.7 & 11.4).
        $this->assertSame('3.0000', (string) $result->score_collaboration);
        $this->assertNotNull($result->score_a_raw, 'Skor mentah tetap disimpan untuk penelusuran.');
    }

    public function test_quorum_fails_with_fewer_than_three_assessors(): void
    {
        $target = $this->divisions['Gudang'];

        $this->layerA('a1@x.test', 'Produksi', $target, ['XA-01' => 4, 'XA-02' => 4]);
        $this->layerA('b1@x.test', 'QC', $target, ['XA-01' => 4, 'XA-02' => 4]);

        app(KpiCrossScoreCalculator::class)->computeForPeriod($this->period);

        $result = KpiCrossResult::divisionLevel()->where('department_id', $target->id)->first();

        $this->assertFalse($result->quorum_met);
        $this->assertSame(2, $result->assessor_count);
    }

    /** L3 memakai campuran 40% Lapis A dan 60% Lapis B (Bab 7.9 Langkah 3). */
    public function test_leader_collaboration_blends_division_and_individual(): void
    {
        $gudang = $this->divisions['Gudang'];
        $leader = $this->employee('leader@x.test', 'L3', $gudang);

        // Lapis A untuk Gudang → 5,00
        foreach ([['a1@x.test', 'Produksi'], ['a2@x.test', 'Produksi'], ['a3@x.test', 'QC']] as [$mail, $div]) {
            $this->layerA($mail, $div, $gudang, ['XA-01' => 5, 'XA-02' => 5]);
        }

        // Lapis B untuk leader → 2,00
        foreach ([['b1@x.test', 'Produksi'], ['b2@x.test', 'Produksi'], ['b3@x.test', 'QC']] as [$mail, $div]) {
            $this->layerB($mail, $div, $leader, ['XB-01' => 2, 'XB-02' => 2]);
        }

        app(KpiCrossScoreCalculator::class)->computeForPeriod($this->period);

        $result = KpiCrossResult::individual()->where('employee_id', $leader->id)->first();

        // (5 × 40 + 2 × 60) / 100 = 3,20
        $this->assertSame('3.2000', (string) $result->score_collaboration);
    }

    /** Staf L4 biasa mengikuti nilai divisinya — tidak dinilai personal. */
    public function test_regular_staff_follows_division_score(): void
    {
        $gudang = $this->divisions['Gudang'];
        $staff = $this->employee('staff@x.test', 'L4', $gudang);

        foreach ([['a1@x.test', 'Produksi'], ['a2@x.test', 'Produksi'], ['a3@x.test', 'QC']] as [$mail, $div]) {
            $this->layerA($mail, $div, $gudang, ['XA-01' => 4, 'XA-02' => 4]);
        }

        app(KpiCrossScoreCalculator::class)->computeForPeriod($this->period);

        $result = KpiCrossResult::individual()->where('employee_id', $staff->id)->first();

        $this->assertSame('4.0000', (string) $result->score_collaboration);
        $this->assertNull($result->score_b_corrected);
    }

    /** L4 lintas fungsi memakai campuran 50/50 karena tugasnya memang lintas divisi. */
    public function test_cross_functional_staff_uses_fifty_fifty_mix(): void
    {
        $gudang = $this->divisions['Gudang'];
        $admin = $this->employee('adminguadang@x.test', 'L4', $gudang, true);

        foreach ([['a1@x.test', 'Produksi'], ['a2@x.test', 'Produksi'], ['a3@x.test', 'QC']] as [$mail, $div]) {
            $this->layerA($mail, $div, $gudang, ['XA-01' => 5, 'XA-02' => 5]);
        }

        foreach ([['b1@x.test', 'Produksi'], ['b2@x.test', 'Produksi'], ['b3@x.test', 'QC']] as [$mail, $div]) {
            $this->layerB($mail, $div, $admin, ['XB-01' => 3, 'XB-02' => 3]);
        }

        app(KpiCrossScoreCalculator::class)->computeForPeriod($this->period);

        $result = KpiCrossResult::individual()->where('employee_id', $admin->id)->first();

        $this->assertSame('4.0000', (string) $result->score_collaboration);
    }

    /** Kuesioner yang ditandai tidak sah tidak boleh ikut dihitung. */
    public function test_invalid_submission_is_excluded(): void
    {
        $target = $this->divisions['Gudang'];

        $this->layerA('a1@x.test', 'Produksi', $target, ['XA-01' => 5, 'XA-02' => 5]);
        $this->layerA('a2@x.test', 'QC', $target, ['XA-01' => 5, 'XA-02' => 5]);
        $invalid = $this->layerA('a3@x.test', 'QC', $target, ['XA-01' => 1, 'XA-02' => 1]);
        $invalid->update(['is_valid' => false, 'invalid_reason' => 'straight_lining']);

        app(KpiCrossScoreCalculator::class)->computeForPeriod($this->period);

        $result = KpiCrossResult::divisionLevel()->where('department_id', $target->id)->first();

        // Hanya 2 penilai sah tersisa → kuorum gugur, tapi skor mentahnya murni 5,00.
        $this->assertSame(2, $result->assessor_count);
        $this->assertSame('5.0000', (string) $result->score_a_raw);
    }

    /** Penyesuaian atasan ditambahkan ke skor kolaborasi dan dijepit ke rentang 1–5. */
    public function test_superior_adjustment_is_clamped_to_scale(): void
    {
        $result = KpiCrossResult::create([
            'kpi_period_id' => $this->period->id,
            'department_id' => $this->divisions['Gudang']->id,
            'employee_id' => $this->employee('x@x.test', 'L3', $this->divisions['Gudang'])->id,
            'score_collaboration' => 4.6,
            'superior_adjustment' => 1.0,
        ]);

        $this->assertSame(5.0, $result->effectiveScore());
    }

    /** Penyesuaian yang masih menunggu persetujuan belum boleh berlaku. */
    public function test_pending_adjustment_is_not_applied(): void
    {
        $result = KpiCrossResult::create([
            'kpi_period_id' => $this->period->id,
            'department_id' => $this->divisions['Gudang']->id,
            'employee_id' => $this->employee('y@x.test', 'L3', $this->divisions['Gudang'])->id,
            'score_collaboration' => 3.0,
            'superior_adjustment' => 1.0,
            'adjustment_needs_approval' => true,
        ]);

        $this->assertTrue($result->adjustmentPending());
        $this->assertSame(3.0, $result->effectiveScore());

        $result->update(['adjustment_approved_by' => 1]);
        $this->assertSame(4.0, $result->refresh()->effectiveScore());
    }

    // ────────────────────────────── helper ──────────────────────────────

    private function snapshotItem(string $layer, string $code, float $weight): void
    {
        KpiPeriodCrossItemSnapshot::create([
            'kpi_period_id' => $this->period->id,
            'layer' => $layer,
            'code' => $code,
            'name' => $code,
            'question' => 'Pertanyaan '.$code,
            'weight' => $weight,
            'sort_order' => 1,
        ]);
    }

    private function layerA(string $email, string $assessorDivision, Department $target, array $scores): KpiCrossLayerA
    {
        $assessor = $this->employee($email, 'L3', $this->divisions[$assessorDivision]);

        $submission = KpiCrossLayerA::create([
            'kpi_period_id' => $this->period->id,
            'assessor_id' => $assessor->id,
            'assessor_department_id' => $this->divisions[$assessorDivision]->id,
            'target_department_id' => $target->id,
            'comment_positive' => 'Bagus',
            'comment_improvement' => 'Perlu perbaikan',
            'submitted_at' => now(),
        ]);

        foreach ($scores as $code => $score) {
            KpiCrossLayerAScore::create([
                'kpi_cross_layer_a_id' => $submission->id,
                'item_code' => $code,
                'score' => $score,
            ]);
        }

        return $submission;
    }

    private function layerB(string $email, string $assessorDivision, Employee $target, array $scores): KpiCrossLayerB
    {
        $assessor = $this->employee($email, 'L3', $this->divisions[$assessorDivision]);

        $submission = KpiCrossLayerB::create([
            'kpi_period_id' => $this->period->id,
            'assessor_id' => $assessor->id,
            'assessor_department_id' => $this->divisions[$assessorDivision]->id,
            'target_employee_id' => $target->id,
            'submitted_at' => now(),
        ]);

        foreach ($scores as $code => $score) {
            KpiCrossLayerBScore::create([
                'kpi_cross_layer_b_id' => $submission->id,
                'item_code' => $code,
                'score' => $score,
            ]);
        }

        return $submission;
    }

    private function employee(string $email, string $levelCode, Department $department, bool $crossFunctional = false): Employee
    {
        return Employee::firstOrCreate(
            ['email' => $email],
            [
                'employee_code' => substr(md5($email), 0, 8),
                'company_id' => $this->company->id,
                'full_name' => 'Karyawan '.$email,
                'password' => 'secret',
                'department_id' => $department->id,
                'kpi_level_id' => $this->levels[$levelCode]->id,
                'is_cross_functional' => $crossFunctional,
                'is_active' => true,
            ]
        );
    }
}
