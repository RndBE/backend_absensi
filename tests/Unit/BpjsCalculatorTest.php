<?php

namespace Tests\Unit;

use App\Models\BpjsSetting;
use App\Services\BpjsCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BpjsCalculatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('bpjs_settings');
        Schema::create('bpjs_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->json('value');
            $table->date('effective_date');
            $table->string('npp')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $this->seedRates();
    }

    public function test_calculate_does_not_include_jaminan_pensiun(): void
    {
        $result = (new BpjsCalculator('2024-06-01'))->calculate(10_000_000);

        $this->assertArrayNotHasKey('jp', $result);
    }

    public function test_employee_total_excludes_jaminan_pensiun(): void
    {
        $result = (new BpjsCalculator('2024-06-01'))->calculate(10_000_000);

        // Employee side without JP = Kesehatan (1% x 10jt = 100k) + JHT (2% x 10jt = 200k) = 300k.
        // If JP (1% x 10jt = 100k) were still included it would be 400k.
        $this->assertSame(300_000.0, (float) $result['employee_total']);
    }

    public function test_company_total_excludes_jaminan_pensiun(): void
    {
        $result = (new BpjsCalculator('2024-06-01'))->calculate(10_000_000);

        // Company side without JP = Kesehatan 4% (400k) + JHT 3.7% (370k) + JKK 0.24% (24k) + JKM 0.3% (30k) = 824k.
        // JP company (2% x 10jt = 200k) must NOT be included.
        $this->assertSame(824_000.0, (float) $result['company_total']);
    }

    private function seedRates(): void
    {
        $rates = [
            'kes_rate' => ['company' => 4, 'employee' => 1],
            'jht_rate' => ['company' => 3.7, 'employee' => 2],
            'jkk_rate' => ['company' => 0.24, 'employee' => 0],
            'jkm_rate' => ['company' => 0.3, 'employee' => 0],
            'jp_rate'  => ['company' => 2, 'employee' => 1],
        ];

        foreach ($rates as $key => $value) {
            BpjsSetting::create([
                'key' => $key,
                'value' => $value,
                'effective_date' => '2024-01-01',
                'is_active' => true,
            ]);
        }

        BpjsSetting::create([
            'key' => 'kes_cap',
            'value' => ['salary_cap' => 12_000_000],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
        BpjsSetting::create([
            'key' => 'jp_cap',
            'value' => ['salary_cap' => 10_042_300],
            'effective_date' => '2024-01-01',
            'is_active' => true,
        ]);
    }
}
