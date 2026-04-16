<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaxSetting;
use App\Models\BpjsSetting;

class TaxBpjsSeeder extends Seeder
{
    public function run(): void
    {
        // === PPh 21 Tarif Progresif UU HPP 2022 (berlaku 2024+) ===
        TaxSetting::firstOrCreate(
            ['key' => 'pph21_brackets', 'effective_date' => '2024-01-01'],
            [
                'value' => [
                    ['min' => 0, 'max' => 60000000, 'rate' => 5],
                    ['min' => 60000000, 'max' => 250000000, 'rate' => 15],
                    ['min' => 250000000, 'max' => 500000000, 'rate' => 25],
                    ['min' => 500000000, 'max' => 5000000000, 'rate' => 30],
                    ['min' => 5000000000, 'max' => null, 'rate' => 35],
                ],
                'description' => 'Tarif PPh 21 progresif UU HPP No. 7/2021',
            ]
        );

        // === PTKP 2024 ===
        TaxSetting::firstOrCreate(
            ['key' => 'ptkp_values', 'effective_date' => '2024-01-01'],
            [
                'value' => [
                    'TK/0' => 54000000, 'TK/1' => 58500000, 'TK/2' => 63000000, 'TK/3' => 67500000,
                    'K/0' => 58500000, 'K/1' => 63000000, 'K/2' => 67500000, 'K/3' => 72000000,
                    'K/I/0' => 112500000, 'K/I/1' => 117000000, 'K/I/2' => 121500000, 'K/I/3' => 126000000,
                ],
                'description' => 'PTKP sesuai PMK 101/PMK.010/2016 (masih berlaku 2024)',
            ]
        );

        // === Biaya Jabatan ===
        TaxSetting::firstOrCreate(
            ['key' => 'biaya_jabatan', 'effective_date' => '2024-01-01'],
            [
                'value' => ['percentage' => 5, 'max_monthly' => 500000, 'max_annual' => 6000000],
                'description' => 'Biaya jabatan 5% max Rp 500.000/bulan',
            ]
        );

        // === BPJS Kesehatan (5% of salary, 4% company + 1% employee, cap 12jt) ===
        BpjsSetting::firstOrCreate(
            ['key' => 'kes_rate', 'effective_date' => '2024-01-01'],
            ['value' => ['company' => 4, 'employee' => 1], 'description' => 'BPJS Kesehatan 5% (4% + 1%)']
        );
        BpjsSetting::firstOrCreate(
            ['key' => 'kes_cap', 'effective_date' => '2024-01-01'],
            ['value' => ['salary_cap' => 12000000], 'description' => 'Batas gaji BPJS Kesehatan Rp 12.000.000']
        );

        // === BPJS JHT (5.7% = 3.7% company + 2% employee) ===
        BpjsSetting::firstOrCreate(
            ['key' => 'jht_rate', 'effective_date' => '2024-01-01'],
            ['value' => ['company' => 3.7, 'employee' => 2], 'description' => 'BPJS JHT 5.7% (3.7% + 2%)']
        );

        // === BPJS JKK (0.24% - 1.74% by risk, company only) ===
        BpjsSetting::firstOrCreate(
            ['key' => 'jkk_rate', 'effective_date' => '2024-01-01'],
            ['value' => ['company' => 0.24, 'employee' => 0], 'description' => 'BPJS JKK risiko sangat rendah 0.24%']
        );

        // === BPJS JKM (0.3%, company only) ===
        BpjsSetting::firstOrCreate(
            ['key' => 'jkm_rate', 'effective_date' => '2024-01-01'],
            ['value' => ['company' => 0.3, 'employee' => 0], 'description' => 'BPJS JKM 0.3%']
        );

        // === BPJS JP (3% = 2% company + 1% employee, cap ~10jt) ===
        BpjsSetting::firstOrCreate(
            ['key' => 'jp_rate', 'effective_date' => '2024-01-01'],
            ['value' => ['company' => 2, 'employee' => 1], 'description' => 'BPJS JP 3% (2% + 1%)']
        );
        BpjsSetting::firstOrCreate(
            ['key' => 'jp_cap', 'effective_date' => '2024-01-01'],
            ['value' => ['salary_cap' => 10042300], 'description' => 'Batas gaji BPJS JP Rp 10.042.300']
        );
    }
}
