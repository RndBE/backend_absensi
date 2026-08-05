<?php

namespace Tests\Unit;

use App\Models\PayrollRunDetail;
use App\Support\PayrollManualOverrides;
use PHPUnit\Framework\TestCase;

class PayrollManualOverridesTest extends TestCase
{
    public function test_manual_addition_survives_while_unrelated_automatic_value_refreshes(): void
    {
        $service = new PayrollManualOverrides;
        $before = [
            $this->component('Lembur', 'earning', 100_000, true),
        ];
        $submitted = [
            $this->component('Lembur', 'earning', 100_000, true),
            $this->component('Rate BPJS Kesehatan', 'info', 2_624_387, false),
        ];

        $ledger = $service->capture(null, $before, $submitted);
        $merged = $service->apply([
            $this->component('Lembur', 'earning', 300_000, true),
        ], $ledger);

        $this->assertSame(300_000.0, $this->amount($merged, 'Lembur'));
        $this->assertSame(2_624_387.0, $this->amount($merged, 'Rate BPJS Kesehatan'));
    }

    public function test_changed_automatic_component_overrides_fresh_generated_value(): void
    {
        $service = new PayrollManualOverrides;
        $before = [$this->component('BPJS Kesehatan', 'deduction', 52_488, true)];
        $submitted = [$this->component('BPJS Kesehatan', 'deduction', 60_000, true)];

        $ledger = $service->capture(null, $before, $submitted);
        $merged = $service->apply([
            $this->component('BPJS Kesehatan', 'deduction', 70_000, true),
        ], $ledger);

        $this->assertSame(60_000.0, $this->amount($merged, 'BPJS Kesehatan'));
    }

    public function test_unchanged_non_automatic_assignment_is_not_frozen_as_manual_override(): void
    {
        $service = new PayrollManualOverrides;
        $before = [$this->component('Tunjangan Jabatan', 'earning', 500_000, false)];

        $ledger = $service->capture(null, $before, $before);
        $merged = $service->apply([
            $this->component('Tunjangan Jabatan', 'earning', 750_000, false),
        ], $ledger);

        $this->assertArrayNotHasKey('components', $ledger);
        $this->assertSame(750_000.0, $this->amount($merged, 'Tunjangan Jabatan'));
    }

    public function test_removed_automatic_component_stays_removed(): void
    {
        $service = new PayrollManualOverrides;
        $before = [$this->component('Potongan Terlambat', 'deduction', 50_000, true)];

        $ledger = $service->capture(null, $before, []);
        $merged = $service->apply([
            $this->component('Potongan Terlambat', 'deduction', 100_000, true),
        ], $ledger);

        $this->assertNull($this->find($merged, 'Potongan Terlambat'));
    }

    public function test_removed_non_automatic_assignment_stays_removed(): void
    {
        $service = new PayrollManualOverrides;
        $before = [$this->component('Tunjangan Jabatan', 'earning', 500_000, false)];

        $ledger = $service->capture(null, $before, []);
        $merged = $service->apply([
            $this->component('Tunjangan Jabatan', 'earning', 750_000, false),
        ], $ledger);

        $this->assertArrayHasKey('earning|tunjangan jabatan', $ledger['removed']);
        $this->assertNull($this->find($merged, 'Tunjangan Jabatan'));
    }

    public function test_basic_salary_override_and_totals_are_applied(): void
    {
        $service = new PayrollManualOverrides;
        $ledger = $service->capture(null, [], [], 5_000_000, 5_500_000);
        $basicSalary = $service->basicSalary(6_000_000, $ledger);
        $totals = $service->totals($basicSalary, [
            $this->component('Tunjangan', 'earning', 250_000, false),
            $this->component('Potongan', 'deduction', 100_000, false),
            $this->component('Rate BPJS', 'info', 2_624_387, false),
        ]);

        $this->assertSame(5_500_000.0, $basicSalary);
        $this->assertSame([
            'total_earning' => 5_750_000.0,
            'total_deduction' => 100_000.0,
            'net_salary' => 5_650_000.0,
        ], $totals);
    }

    public function test_legacy_manual_detail_conservatively_preserves_different_old_values(): void
    {
        $service = new PayrollManualOverrides;
        $old = new PayrollRunDetail([
            'basic_salary' => 5_000_000,
            'components' => [
                $this->component('Lembur', 'earning', 100_000, true),
                $this->component('Rate BPJS Kesehatan', 'info', 2_624_387, false),
            ],
            'is_manual_edited' => true,
        ]);
        $fresh = new PayrollRunDetail([
            'basic_salary' => 5_000_000,
            'components' => [
                $this->component('Lembur', 'earning', 300_000, true),
            ],
        ]);

        $ledger = $service->deriveLegacy($old, $fresh);
        $merged = $service->apply($fresh->components, $ledger);

        $this->assertSame(100_000.0, $this->amount($merged, 'Lembur'));
        $this->assertSame(2_624_387.0, $this->amount($merged, 'Rate BPJS Kesehatan'));
    }

    private function component(string $name, string $type, float $amount, bool $isAuto): array
    {
        return [
            'id' => null,
            'name' => $name,
            'type' => $type,
            'category' => $type === 'info' ? 'info' : 'recurring',
            'amount' => $amount,
            'is_taxable' => false,
            'is_auto' => $isAuto,
            'detail' => '',
        ];
    }

    private function amount(array $components, string $name): float
    {
        return (float) ($this->find($components, $name)['amount'] ?? 0);
    }

    private function find(array $components, string $name): ?array
    {
        foreach ($components as $component) {
            if (($component['name'] ?? null) === $name) {
                return $component;
            }
        }

        return null;
    }
}
