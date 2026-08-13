<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiCrossResult;
use App\Models\KpiFinalResult;
use App\Models\KpiImprovementPlan;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Support\KpiFollowUp;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

/**
 * Pemicu tindak lanjut Bab 10.1. Angka tanpa tindak lanjut membuat orang berhenti
 * mengisi dengan serius pada periode kedua — pemicunya harus benar-benar jalan.
 */
class KpiFollowUpTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private KpiPeriod $period;

    private KpiLevel $level;

    private Department $division;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createKpiSchema();

        $this->company = Company::create(['name' => 'PT Tindak Lanjut']);

        $this->level = KpiLevel::create([
            'company_id' => $this->company->id,
            'code' => 'L4',
            'name' => 'Staff',
            'is_assessed' => true,
            'weight_excellence' => 70,
            'weight_contribution' => 25,
            'weight_leadership' => 5,
        ]);

        $this->division = Department::create([
            'company_id' => $this->company->id,
            'name' => 'Gudang',
            'is_division' => true,
        ]);

        $this->period = KpiPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Periode Tindak Lanjut',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => KpiPeriod::STATUS_CALIBRATION,
        ]);
    }

    public function test_employee_below_three_gets_improvement_plan(): void
    {
        $employee = $this->employeeWithScore('kurang@x.test', 2.5000);

        $created = app(KpiFollowUp::class)->generateForPeriod($this->period);

        $this->assertGreaterThanOrEqual(1, $created);

        $plan = KpiImprovementPlan::where('subject_type', KpiImprovementPlan::SUBJECT_EMPLOYEE)
            ->where('subject_id', $employee->id)
            ->first();

        $this->assertNotNull($plan);
        $this->assertNotNull($plan->due_date);
    }

    public function test_employee_below_two_gets_formal_pip_with_review_date(): void
    {
        $employee = $this->employeeWithScore('buruk@x.test', 1.8000);

        app(KpiFollowUp::class)->generateForPeriod($this->period);

        $plan = KpiImprovementPlan::where('subject_id', $employee->id)
            ->where('subject_type', KpiImprovementPlan::SUBJECT_EMPLOYEE)
            ->first();

        $this->assertNotNull($plan);
        // PIP formal dievaluasi 3 bulan (Bab 10.1) — rencana biasa tidak punya review.
        $this->assertNotNull($plan->review_date, 'PIP formal wajib punya tanggal evaluasi.');
    }

    public function test_employee_at_or_above_three_gets_no_plan(): void
    {
        $employee = $this->employeeWithScore('cukup@x.test', 3.0000);

        app(KpiFollowUp::class)->generateForPeriod($this->period);

        $this->assertSame(
            0,
            KpiImprovementPlan::where('subject_id', $employee->id)
                ->where('subject_type', KpiImprovementPlan::SUBJECT_EMPLOYEE)
                ->count()
        );
    }

    /** Divisi dengan skor silang di bawah 3,0 wajib menyusun rencana perbaikan. */
    public function test_division_below_three_gets_plan(): void
    {
        KpiCrossResult::create([
            'kpi_period_id' => $this->period->id,
            'department_id' => $this->division->id,
            'employee_id' => null,
            'score_a_corrected' => 2.4000,
            'score_collaboration' => 2.4000,
            'quorum_met' => true,
        ]);

        app(KpiFollowUp::class)->generateForPeriod($this->period);

        $this->assertSame(
            1,
            KpiImprovementPlan::where('subject_type', KpiImprovementPlan::SUBJECT_DIVISION)
                ->where('subject_id', $this->division->id)
                ->count()
        );
    }

    /**
     * Skor divisi yang kuorumnya gagal tidak boleh menerbitkan rencana perbaikan —
     * itu menghukum divisi berdasarkan data yang kerangka sendiri nyatakan tidak dipakai.
     */
    public function test_division_without_quorum_gets_no_plan(): void
    {
        KpiCrossResult::create([
            'kpi_period_id' => $this->period->id,
            'department_id' => $this->division->id,
            'employee_id' => null,
            'score_a_raw' => 2.0000,
            'score_a_corrected' => null,
            'score_collaboration' => 3.0000,
            'quorum_met' => false,
        ]);

        app(KpiFollowUp::class)->generateForPeriod($this->period);

        $this->assertSame(
            0,
            KpiImprovementPlan::where('subject_type', KpiImprovementPlan::SUBJECT_DIVISION)->count()
        );
    }

    /** Menjalankan ulang setelah kalibrasi tidak boleh menimpa rencana yang sudah diisi. */
    public function test_regenerating_keeps_existing_plan_text_and_dates(): void
    {
        $employee = $this->employeeWithScore('ulang@x.test', 2.2000);

        $followUp = app(KpiFollowUp::class);
        $followUp->generateForPeriod($this->period);

        $plan = KpiImprovementPlan::where('subject_id', $employee->id)
            ->where('subject_type', KpiImprovementPlan::SUBJECT_EMPLOYEE)
            ->first();

        $plan->update([
            'plan_text' => 'Coaching mingguan bersama supervisor.',
            'due_date' => '2026-09-30',
            'status' => KpiImprovementPlan::STATUS_IN_PROGRESS,
        ]);

        $followUp->generateForPeriod($this->period);

        $plan->refresh();
        $this->assertSame('Coaching mingguan bersama supervisor.', $plan->plan_text);
        $this->assertSame('2026-09-30', $plan->due_date->format('Y-m-d'));
        $this->assertSame(KpiImprovementPlan::STATUS_IN_PROGRESS, $plan->status);
        $this->assertSame(1, KpiImprovementPlan::where('subject_id', $employee->id)->count());
    }

    private function employeeWithScore(string $email, float $finalScore): Employee
    {
        $employee = Employee::create([
            'employee_code' => substr(md5($email), 0, 8),
            'company_id' => $this->company->id,
            'full_name' => 'Karyawan '.$email,
            'email' => $email,
            'password' => 'secret',
            'department_id' => $this->division->id,
            'kpi_level_id' => $this->level->id,
            'is_active' => true,
        ]);

        KpiFinalResult::create([
            'kpi_period_id' => $this->period->id,
            'employee_id' => $employee->id,
            'final_score' => $finalScore,
            'grade' => KpiFinalResult::gradeFor($finalScore),
        ]);

        return $employee;
    }
}
