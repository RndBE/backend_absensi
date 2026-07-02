<?php

namespace Tests\Unit;

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

        Schema::dropIfExists('tax_settings');
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->json('value');
            $table->date('effective_date');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
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

    public function test_gross_up_tax_allowance_matches_final_pph21_deduction(): void
    {
        $this->seedTaxSettings();

        $result = (new Pph21Calculator('2024-06-01'))
            ->calculateMonthly(17_500_000, 'TK/0', 'gross_up', 0);

        $this->assertSame($result['pph21_deduction'], $result['tunjangan_pajak']);
    }

    public function test_final_month_exposes_refund_when_prior_pph_exceeds_progressive_tax(): void
    {
        $this->seedTaxSettings();

        $result = (new Pph21Calculator('2024-06-01'))
            ->calculateFinalMonth(
                avgBrutoMonthly: 2_000_000,
                ptkpStatus: 'TK/0',
                taxMethod: 'gross_up',
                bpjsEmployee: 0,
                monthsWorked: 6,
                taxAlreadyPaid: 150_000,
            );

        $this->assertEquals(0.0, $result['tax_for_period']);
        $this->assertEquals(0.0, $result['pph21_deduction']);
        $this->assertEquals(150_000.0, $result['pph21_refund']);
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
