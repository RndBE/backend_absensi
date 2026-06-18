<?php

namespace Tests\Unit;

use App\Models\BpjsSetting;
use App\Models\TaxSetting;
use App\Services\Pph21Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Pph21CalculatorTerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['tax_settings', 'bpjs_settings'] as $tableName) {
            Schema::dropIfExists($tableName);
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('key');
                $table->json('value');
                $table->date('effective_date');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function test_january_to_november_uses_monthly_ter_rate_from_ptkp_category(): void
    {
        $this->seedTaxSettings();

        $result = (new Pph21Calculator('2024-06-01'))
            ->calculateMonthly(17_500_000, 'TK/0', 'gross', 0);

        $this->assertSame('ter_monthly', $result['method']);
        $this->assertSame('A', $result['ter_category']);
        $this->assertSame(8.0, $result['ter_rate']);
        $this->assertSame(1_400_000.0, $result['tax_monthly']);
        $this->assertSame(1_400_000.0, $result['pph21_deduction']);
    }

    public function test_ter_category_follows_ptkp_status(): void
    {
        $this->seedTaxSettings();

        $result = (new Pph21Calculator('2024-06-01'))
            ->calculateMonthly(6_800_000, 'K/1', 'gross', 0);

        $this->assertSame('B', $result['ter_category']);
        $this->assertSame(0.5, $result['ter_rate']);
        $this->assertSame(34_000.0, $result['tax_monthly']);
    }

    public function test_simulate_adds_employer_kesehatan_jkk_jkm_to_tax_bruto(): void
    {
        // Flat 5% TER over a wide band so the benefit's effect on tax is deterministic.
        TaxSetting::create([
            'key' => 'pph21_ter_monthly',
            'effective_date' => '2024-01-01',
            'value' => [
                'ptkp_categories' => ['TK/0' => 'A'],
                'rates' => ['A' => [['min' => 0, 'max' => 100_000_000, 'rate' => 5]]],
            ],
            'description' => 'Flat TER',
        ]);
        TaxSetting::create([
            'key' => 'ptkp_values',
            'effective_date' => '2024-01-01',
            'value' => ['TK/0' => 54_000_000],
            'description' => 'PTKP',
        ]);

        foreach ([
            'kes_rate' => ['company' => 4, 'employee' => 1],
            'jht_rate' => ['company' => 3.7, 'employee' => 2],
            'jkk_rate' => ['company' => 0.24, 'employee' => 0],
            'jkm_rate' => ['company' => 0.3, 'employee' => 0],
        ] as $key => $value) {
            BpjsSetting::create(['key' => $key, 'value' => $value, 'effective_date' => '2024-01-01', 'is_active' => true]);
        }
        BpjsSetting::create(['key' => 'kes_cap', 'value' => ['salary_cap' => 12_000_000], 'effective_date' => '2024-01-01', 'is_active' => true]);

        $gross = 10_000_000;
        // Employer taxable benefit = Kesehatan 4% (400k) + JKK 0.24% (24k) + JKM 0.3% (30k) = 454k.
        $expectedTax = round(($gross + 454_000) * 0.05, 0);

        $result = (new Pph21Calculator('2024-06-01'))->simulate($gross, 'TK/0', 'gross');

        $this->assertSame($expectedTax, (float) $result['tax_monthly']);
        // Take-home must still be based on actual gross, not gross + employer benefit.
        $this->assertSame(
            round($gross - $result['tax_monthly'] - $result['bpjs_detail']['employee_total'], 0),
            (float) $result['take_home']
        );
    }

    private function seedTaxSettings(): void
    {
        TaxSetting::create([
            'key' => 'pph21_ter_monthly',
            'effective_date' => '2024-01-01',
            'value' => [
                'ptkp_categories' => [
                    'TK/0' => 'A',
                    'TK/1' => 'A',
                    'K/0' => 'A',
                    'TK/2' => 'B',
                    'TK/3' => 'B',
                    'K/1' => 'B',
                    'K/2' => 'B',
                    'K/3' => 'C',
                ],
                'rates' => [
                    'A' => [
                        ['min' => 0, 'max' => 5_400_000, 'rate' => 0],
                        ['min' => 16_950_000, 'max' => 19_750_000, 'rate' => 8],
                    ],
                    'B' => [
                        ['min' => 0, 'max' => 6_200_000, 'rate' => 0],
                        ['min' => 6_500_000, 'max' => 6_850_000, 'rate' => 0.5],
                    ],
                    'C' => [
                        ['min' => 0, 'max' => 6_600_000, 'rate' => 0],
                    ],
                ],
            ],
            'description' => 'TER test rates',
        ]);

        TaxSetting::create([
            'key' => 'pph21_brackets',
            'effective_date' => '2024-01-01',
            'value' => [
                ['min' => 0, 'max' => 60_000_000, 'rate' => 5],
                ['min' => 60_000_000, 'max' => 250_000_000, 'rate' => 15],
            ],
            'description' => 'Progressive test rates',
        ]);

        TaxSetting::create([
            'key' => 'ptkp_values',
            'effective_date' => '2024-01-01',
            'value' => ['TK/0' => 54_000_000, 'K/1' => 63_000_000],
            'description' => 'PTKP test values',
        ]);

        TaxSetting::create([
            'key' => 'biaya_jabatan',
            'effective_date' => '2024-01-01',
            'value' => ['percentage' => 5, 'max_monthly' => 500_000, 'max_annual' => 6_000_000],
            'description' => 'Biaya jabatan test values',
        ]);
    }
}
