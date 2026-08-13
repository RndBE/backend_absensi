<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiLevel;
use App\Support\KpiAssessorMap;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

class KpiAssessorMapTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private array $levels = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createKpiSchema();

        $this->company = Company::create(['name' => 'PT Peta Penilai']);

        foreach (['L1' => 'Direksi', 'L2' => 'Manajer', 'L3' => 'Leader', 'L4' => 'Staff'] as $code => $name) {
            $this->levels[$code] = KpiLevel::create([
                'company_id' => $this->company->id,
                'code' => $code,
                'name' => $name,
                'is_assessed' => $code !== 'L1',
                'weight_excellence' => $code === 'L1' ? 0 : 70,
                'weight_contribution' => $code === 'L1' ? 0 : 25,
                'weight_leadership' => $code === 'L1' ? 0 : 5,
            ]);
        }
    }

    /** L4 dinilai atasan langsung 70% dan atasan dua tingkat 30% (Bab 2.1). */
    public function test_staff_is_assessed_by_manager_and_grand_manager(): void
    {
        $direksi = $this->employee('direksi@example.test', 'L1');
        $manajer = $this->employee('manajer@example.test', 'L2', $direksi);
        $leader = $this->employee('leader@example.test', 'L3', $manajer);
        $staff = $this->employee('staff@example.test', 'L4', $leader);

        $rows = app(KpiAssessorMap::class)->for($staff->fresh());

        $this->assertCount(2, $rows);
        $this->assertSame($leader->id, $rows[0]['assessor_id']);
        $this->assertSame(KpiAssessment::ROLE_PRIMARY, $rows[0]['assessor_role']);
        $this->assertSame(70.0, $rows[0]['weight']);
        $this->assertSame($manajer->id, $rows[1]['assessor_id']);
        $this->assertSame(KpiAssessment::ROLE_SUPPORTING, $rows[1]['assessor_role']);
        $this->assertSame(30.0, $rows[1]['weight']);
    }

    /** L2 dinilai satu orang dengan bobot penuh. */
    public function test_manager_is_assessed_by_a_single_assessor(): void
    {
        $direksi = $this->employee('direksi2@example.test', 'L1');
        $manajer = $this->employee('manajer2@example.test', 'L2', $direksi);

        $rows = app(KpiAssessorMap::class)->for($manajer->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame($direksi->id, $rows[0]['assessor_id']);
        $this->assertSame(100.0, $rows[0]['weight']);
    }

    /** Tanpa atasan dua tingkat, 30% tidak boleh hangus — penilai utama menanggung penuh. */
    public function test_missing_grand_manager_gives_full_weight_to_primary(): void
    {
        $leader = $this->employee('leader3@example.test', 'L3');
        $staff = $this->employee('staff3@example.test', 'L4', $leader);

        $rows = app(KpiAssessorMap::class)->for($staff->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame($leader->id, $rows[0]['assessor_id']);
        $this->assertSame(100.0, $rows[0]['weight']);
    }

    /**
     * Bab 2.1 menempatkan L1 sebagai penilai L2 dan L3 saja. Staf L4 yang atasan langsungnya
     * seorang Manajer tidak boleh menarik Direksi sebagai penilai pendukung hanya karena
     * Direksi berada dua tingkat di atasnya.
     */
    public function test_staff_does_not_pull_direksi_as_supporting_assessor(): void
    {
        $direksi = $this->employee('direksi6@example.test', 'L1');
        $manajer = $this->employee('manajer6@example.test', 'L2', $direksi);
        $staff = $this->employee('staff6@example.test', 'L4', $manajer);

        $rows = app(KpiAssessorMap::class)->for($staff->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame($manajer->id, $rows[0]['assessor_id']);
        $this->assertSame(KpiAssessment::ROLE_PRIMARY, $rows[0]['assessor_role']);
        $this->assertSame(100.0, $rows[0]['weight']);
    }

    /**
     * L3 dinilai Manajernya saja dengan bobot penuh. Menyimpang dari Bab 2.1 yang memberi
     * Direksi porsi pendukung 30%: seluruh Manajer melapor ke satu Direktur, jadi porsi itu
     * menumpuk belasan formulir pada satu orang.
     */
    public function test_leader_is_assessed_by_manager_alone(): void
    {
        $direksi = $this->employee('direksi7@example.test', 'L1');
        $manajer = $this->employee('manajer7@example.test', 'L2', $direksi);
        $leader = $this->employee('leader7@example.test', 'L3', $manajer);

        $rows = app(KpiAssessorMap::class)->for($leader->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame($manajer->id, $rows[0]['assessor_id']);
        $this->assertSame(KpiAssessment::ROLE_PRIMARY, $rows[0]['assessor_role']);
        $this->assertSame(100.0, $rows[0]['weight']);
    }

    /**
     * L3 yang melapor langsung ke Direksi tetap dapat penilai — kalau slot ini dikosongkan,
     * orangnya hilang dari daftar penilaian tanpa alasan yang terlihat admin.
     */
    public function test_leader_reporting_straight_to_direksi_is_still_assessed(): void
    {
        $direksi = $this->employee('direksi9@example.test', 'L1');
        $leader = $this->employee('leader9@example.test', 'L3', $direksi);

        $rows = app(KpiAssessorMap::class)->for($leader->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame($direksi->id, $rows[0]['assessor_id']);
        $this->assertSame(100.0, $rows[0]['weight']);
    }

    /** Staf L4 yang atasan dua tingkatnya sesama L3 juga tidak dapat penilai pendukung. */
    public function test_supporting_assessor_must_be_manager_level_for_staff(): void
    {
        $leaderAtas = $this->employee('leader8@example.test', 'L3');
        $leaderBawah = $this->employee('leader8b@example.test', 'L3', $leaderAtas);
        $staff = $this->employee('staff8@example.test', 'L4', $leaderBawah);

        $rows = app(KpiAssessorMap::class)->for($staff->fresh());

        $this->assertCount(1, $rows);
        $this->assertSame($leaderBawah->id, $rows[0]['assessor_id']);
        $this->assertSame(100.0, $rows[0]['weight']);
    }

    public function test_employee_without_manager_has_no_assessor(): void
    {
        $staff = $this->employee('yatim@example.test', 'L4');

        $this->assertSame([], app(KpiAssessorMap::class)->for($staff->fresh()));
    }

    public function test_blocking_reason_reports_each_missing_prerequisite(): void
    {
        $map = app(KpiAssessorMap::class);

        $tanpaLevel = $this->employee('tanpalevel@example.test', null);
        $this->assertSame('Level KPI belum diisi.', $map->blockingReason($tanpaLevel->fresh()));

        $direksi = $this->employee('direksi4@example.test', 'L1');
        $this->assertStringContainsString('tidak masuk penilaian', $map->blockingReason($direksi->fresh()));

        $tanpaAtasan = $this->employee('tanpaatasan@example.test', 'L4');
        $this->assertSame('Atasan langsung (manager) belum diisi.', $map->blockingReason($tanpaAtasan->fresh()));

        $lengkap = $this->employee('lengkap@example.test', 'L4', $this->employee('bos@example.test', 'L3'));
        $this->assertNull($map->blockingReason($lengkap->fresh()));
    }

    /**
     * Karyawan yang dikecualikan tidak dapat penilai walaupun level dan atasannya lengkap —
     * akun demo dan akun sistem tidak boleh ikut terhitung.
     */
    public function test_excluded_employee_has_no_assessor(): void
    {
        $manajer = $this->employee('manajer5@example.test', 'L2');
        $demo = $this->employee('demo@example.test', 'L4', $manajer);
        $demo->update(['is_kpi_excluded' => true]);

        $map = app(KpiAssessorMap::class);

        $this->assertSame([], $map->for($demo->fresh()));
        $this->assertTrue($map->isExcluded($demo->fresh()));
    }

    /**
     * Alasan pengecualian dibedakan dari alasan penghalang lain: daftar "belum bisa dinilai"
     * adalah daftar kerja admin, dan peserta yang memang di luar KPI harus terbaca beda dari
     * karyawan yang levelnya belum disetel.
     */
    public function test_excluded_reason_takes_precedence_over_missing_prerequisites(): void
    {
        $tanpaApaPun = $this->employee('kecuali@example.test', null);
        $tanpaApaPun->update(['is_kpi_excluded' => true]);

        $this->assertSame(
            'Dikecualikan dari penilaian KPI.',
            app(KpiAssessorMap::class)->blockingReason($tanpaApaPun->fresh())
        );
    }

    private function employee(string $email, ?string $levelCode, ?Employee $manager = null): Employee
    {
        return Employee::create([
            'employee_code' => substr(md5($email), 0, 8),
            'company_id' => $this->company->id,
            'full_name' => 'Karyawan '.$email,
            'email' => $email,
            'password' => 'secret',
            'kpi_level_id' => $levelCode ? $this->levels[$levelCode]->id : null,
            'manager_id' => $manager?->id,
            'is_active' => true,
        ]);
    }
}
