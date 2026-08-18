<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\KpiIndicator;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Support\KpiIndicatorSet;
use App\Support\KpiPeriodSnapshot;
use RuntimeException;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

/**
 * Indikator General Excellence bisa dibuat per orang, karena tugas inti tiap jabatan berbeda.
 *
 * Yang diuji di sini adalah aturan penggantinya: milik orang MENGGANTI set kategori milik level,
 * bukan menambah. Kalau menambah, jumlah bobot kategori lewat dari 100 dan skornya berubah arti
 * tanpa ada yang menyadari.
 */
class KpiIndicatorSetTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private KpiLevel $level;

    private Employee $welder;

    private Employee $purchasing;

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

        $this->welder = $this->employee('Welder');
        $this->purchasing = $this->employee('Purchasing');

        // Bawaan level: dua indikator per kategori, masing-masing berjumlah 100.
        foreach (['EX', 'CO', 'LD'] as $category) {
            foreach ([60, 40] as $i => $weight) {
                $this->indicator($category, $category.'-L4-0'.($i + 1), $weight);
            }
        }
    }

    public function test_employee_without_own_indicators_uses_level_default(): void
    {
        $period = $this->openPeriod();

        $set = app(KpiIndicatorSet::class)->forEmployee($period, $this->purchasing);

        $this->assertCount(6, $set);
        $this->assertSame(['CO-L4-01', 'CO-L4-02', 'EX-L4-01', 'EX-L4-02', 'LD-L4-01', 'LD-L4-02'],
            $set->pluck('code')->sort()->values()->all());
    }

    public function test_own_indicators_replace_the_level_set_for_that_category(): void
    {
        $this->indicator('EX', 'EX-WELD-01', 70, $this->welder);
        $this->indicator('EX', 'EX-WELD-02', 30, $this->welder);

        $period = $this->openPeriod();
        $set = app(KpiIndicatorSet::class)->forEmployee($period, $this->welder);

        $excellence = $set->where('category', 'EX')->pluck('code')->values()->all();

        $this->assertSame(['EX-WELD-01', 'EX-WELD-02'], $excellence, 'Indikator level tidak boleh ikut terbawa.');
        $this->assertSame(100.0, (float) $set->where('category', 'EX')->sum('weight'));
    }

    public function test_categories_without_own_indicators_keep_the_level_default(): void
    {
        $this->indicator('EX', 'EX-WELD-01', 100, $this->welder);

        $period = $this->openPeriod();
        $set = app(KpiIndicatorSet::class)->forEmployee($period, $this->welder);

        $this->assertSame(['CO-L4-01', 'CO-L4-02'], $set->where('category', 'CO')->pluck('code')->values()->all());
        $this->assertSame(['LD-L4-01', 'LD-L4-02'], $set->where('category', 'LD')->pluck('code')->values()->all());
    }

    public function test_one_employee_does_not_see_another_employees_indicators(): void
    {
        $this->indicator('EX', 'EX-WELD-01', 100, $this->welder);

        $period = $this->openPeriod();
        $set = app(KpiIndicatorSet::class)->forEmployee($period, $this->purchasing);

        $this->assertNotContains('EX-WELD-01', $set->pluck('code')->all());
        $this->assertSame(['EX-L4-01', 'EX-L4-02'], $set->where('category', 'EX')->pluck('code')->values()->all());
    }

    public function test_own_indicator_weights_must_total_one_hundred(): void
    {
        $this->indicator('EX', 'EX-WELD-01', 60, $this->welder);
        $this->indicator('EX', 'EX-WELD-02', 30, $this->welder);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Bobot indikator EX milik Welder berjumlah 90%/');

        $this->openPeriod();
    }

    public function test_snapshot_freezes_the_owner_so_later_edits_do_not_move_it(): void
    {
        $own = $this->indicator('EX', 'EX-WELD-01', 100, $this->welder);
        $period = $this->openPeriod();

        // Indikator sumbernya dialihkan ke orang lain SETELAH periode dibuka.
        $own->update(['employee_id' => $this->purchasing->id]);

        $welderSet = app(KpiIndicatorSet::class)->forEmployee($period, $this->welder);
        $purchasingSet = app(KpiIndicatorSet::class)->forEmployee($period, $this->purchasing);

        $this->assertContains('EX-WELD-01', $welderSet->pluck('code')->all(), 'Snapshot harus tetap milik pemilik saat periode dibuka.');
        $this->assertNotContains('EX-WELD-01', $purchasingSet->pluck('code')->all());
    }

    private function employee(string $name): Employee
    {
        return Employee::create([
            'company_id' => $this->company->id,
            'full_name' => $name,
            'email' => strtolower($name).'@uji.test',
            'password' => 'x',
            'kpi_level_id' => $this->level->id,
            'is_active' => true,
        ]);
    }

    private function indicator(string $category, string $code, float $weight, ?Employee $owner = null): KpiIndicator
    {
        return KpiIndicator::create([
            'company_id' => $this->company->id,
            'kpi_level_id' => $this->level->id,
            'employee_id' => $owner?->id,
            'category' => $category,
            'code' => $code,
            'name' => $code,
            'weight' => $weight,
            'sort_order' => 1,
        ]);
    }

    private function openPeriod(): KpiPeriod
    {
        $period = KpiPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Semester Uji',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => KpiPeriod::STATUS_DRAFT,
        ]);

        app(KpiPeriodSnapshot::class)->build($period);

        return $period;
    }
}
