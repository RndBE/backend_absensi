<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\KpiIndicator;
use App\Models\KpiIndicatorRubric;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Support\KpiPeriodSnapshot;
use RuntimeException;
use Tests\Concerns\CreatesKpiSchema;
use Tests\TestCase;

class KpiPeriodSnapshotTest extends TestCase
{
    use CreatesKpiSchema;

    private Company $company;

    private KpiLevel $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createKpiSchema();

        $this->company = Company::create(['name' => 'PT Snapshot']);
        $this->level = KpiLevel::create([
            'company_id' => $this->company->id,
            'code' => 'L4',
            'name' => 'Staff',
            'is_assessed' => true,
            'weight_excellence' => 70,
            'weight_contribution' => 25,
            'weight_leadership' => 5,
        ]);
    }

    public function test_snapshot_copies_weights_and_freezes_rubric_text(): void
    {
        $indicator = $this->indicator('EX', 'EX-L4-01', 100);
        KpiIndicatorRubric::create(['kpi_indicator_id' => $indicator->id, 'score' => 5, 'description' => 'Teks rubrik awal']);
        $this->indicator('CO', 'CO-L4-01', 100);
        $this->indicator('LD', 'LD-L4-01', 100);

        $period = $this->period();
        app(KpiPeriodSnapshot::class)->build($period);

        $levelSnapshot = $period->levelSnapshots()->first();
        $this->assertSame('L4', $levelSnapshot->code);
        $this->assertSame('70.00', (string) $levelSnapshot->weight_excellence);

        $snapshot = $period->indicatorSnapshots()->where('code', 'EX-L4-01')->first();
        $this->assertSame('Teks rubrik awal', $snapshot->rubricFor(5));
    }

    /** Master berubah setelah periode dibuka — snapshot harus tetap seperti semula. */
    public function test_master_changes_do_not_leak_into_existing_snapshot(): void
    {
        $indicator = $this->indicator('EX', 'EX-L4-01', 100);
        KpiIndicatorRubric::create(['kpi_indicator_id' => $indicator->id, 'score' => 5, 'description' => 'Rubrik lama']);
        $this->indicator('CO', 'CO-L4-01', 100);
        $this->indicator('LD', 'LD-L4-01', 100);

        $period = $this->period();
        app(KpiPeriodSnapshot::class)->build($period);
        $period->update(['status' => KpiPeriod::STATUS_OPEN]);

        $indicator->update(['weight' => 55, 'name' => 'Nama baru']);
        $indicator->rubrics()->where('score', 5)->update(['description' => 'Rubrik baru']);
        $this->level->update(['weight_excellence' => 10, 'weight_contribution' => 45, 'weight_leadership' => 45]);

        $snapshot = $period->refresh()->indicatorSnapshots()->where('code', 'EX-L4-01')->first();
        $this->assertSame('100.00', (string) $snapshot->weight);
        $this->assertSame('Rubrik lama', $snapshot->rubricFor(5));
        $this->assertSame('70.00', (string) $period->levelSnapshots()->first()->weight_excellence);
    }

    public function test_build_is_rejected_when_indicator_weights_do_not_total_100(): void
    {
        $this->indicator('EX', 'EX-L4-01', 60);
        $this->indicator('CO', 'CO-L4-01', 100);
        $this->indicator('LD', 'LD-L4-01', 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Bobot indikator L4\/EX berjumlah 60/');

        app(KpiPeriodSnapshot::class)->build($this->period());
    }

    public function test_build_is_rejected_when_category_weights_do_not_total_100(): void
    {
        $this->level->update(['weight_excellence' => 60, 'weight_contribution' => 25, 'weight_leadership' => 5]);
        $this->indicator('EX', 'EX-L4-01', 100);
        $this->indicator('CO', 'CO-L4-01', 100);
        $this->indicator('LD', 'LD-L4-01', 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Bobot kategori L4 berjumlah 90/');

        app(KpiPeriodSnapshot::class)->build($this->period());
    }

    /** Kategori berbobot > 0 tanpa indikator aktif berarti skornya tak pernah terhitung. */
    public function test_build_is_rejected_when_weighted_category_has_no_indicator(): void
    {
        $this->indicator('EX', 'EX-L4-01', 100);
        $this->indicator('CO', 'CO-L4-01', 100);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Kategori LD pada L4 belum punya indikator aktif/');

        app(KpiPeriodSnapshot::class)->build($this->period());
    }

    public function test_weight_problems_lists_every_error_at_once(): void
    {
        $this->level->update(['weight_excellence' => 50, 'weight_contribution' => 25, 'weight_leadership' => 5]);
        $this->indicator('EX', 'EX-L4-01', 42);
        $this->indicator('CO', 'CO-L4-01', 100);
        $this->indicator('LD', 'LD-L4-01', 100);

        $problems = app(KpiPeriodSnapshot::class)->weightProblems($this->company->id);

        $this->assertCount(2, $problems);
        $this->assertStringContainsString('Bobot kategori L4', $problems[0]);
        $this->assertStringContainsString('Bobot indikator L4/EX', $problems[1]);
    }

    /** Membangun ulang snapshot tidak boleh menggandakan baris. */
    public function test_rebuild_replaces_previous_snapshot(): void
    {
        $this->indicator('EX', 'EX-L4-01', 100);
        $this->indicator('CO', 'CO-L4-01', 100);
        $this->indicator('LD', 'LD-L4-01', 100);

        $period = $this->period();
        $builder = app(KpiPeriodSnapshot::class);
        $builder->build($period);
        $builder->build($period);

        $this->assertSame(3, $period->indicatorSnapshots()->count());
        $this->assertSame(1, $period->levelSnapshots()->count());
    }

    private function indicator(string $category, string $code, float $weight): KpiIndicator
    {
        return KpiIndicator::create([
            'company_id' => $this->company->id,
            'kpi_level_id' => $this->level->id,
            'category' => $category,
            'code' => $code,
            'name' => 'Indikator '.$code,
            'weight' => $weight,
            'sort_order' => 1,
        ]);
    }

    private function period(): KpiPeriod
    {
        return KpiPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Periode Uji',
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
        ]);
    }
}
