<?php

namespace Database\Seeders;

use App\Models\PayrollComponent;
use Illuminate\Database\Seeder;

class PayrollComponentSeeder extends Seeder
{
    public function run(): void
    {
        $components = [
            // Earnings
            ['name' => 'Tunjangan Makan', 'type' => 'earning', 'category' => 'fixed', 'is_taxable' => true, 'is_active' => true],
            ['name' => 'Tunjangan Transport', 'type' => 'earning', 'category' => 'fixed', 'is_taxable' => true, 'is_active' => true],
            ['name' => 'Tunjangan Komunikasi', 'type' => 'earning', 'category' => 'fixed', 'is_taxable' => true, 'is_active' => true],
            ['name' => 'Tunjangan Jabatan', 'type' => 'earning', 'category' => 'fixed', 'is_taxable' => true, 'is_active' => true],
            ['name' => 'Tunjangan Kehadiran', 'type' => 'earning', 'category' => 'fixed', 'is_taxable' => true, 'is_active' => true],
            ['name' => 'Lembur', 'type' => 'earning', 'category' => 'recurring', 'is_taxable' => true, 'is_auto' => true, 'is_active' => true],
            ['name' => 'Bonus', 'type' => 'earning', 'category' => 'one-time', 'is_taxable' => true, 'is_active' => true],
            ['name' => 'THR', 'type' => 'earning', 'category' => 'one-time', 'is_taxable' => true, 'is_active' => true],

            // Deductions
            ['name' => 'Potongan Keterlambatan', 'type' => 'deduction', 'category' => 'recurring', 'is_taxable' => false, 'is_auto' => true, 'is_active' => true],
            ['name' => 'Potongan Alpha', 'type' => 'deduction', 'category' => 'recurring', 'is_taxable' => false, 'is_auto' => true, 'is_active' => true],
            ['name' => 'BPJS Kesehatan', 'type' => 'deduction', 'category' => 'fixed', 'is_taxable' => false, 'is_active' => true],
            ['name' => 'BPJS Ketenagakerjaan', 'type' => 'deduction', 'category' => 'fixed', 'is_taxable' => false, 'is_active' => true],
            ['name' => 'Potongan Pinjaman', 'type' => 'deduction', 'category' => 'recurring', 'is_taxable' => false, 'is_active' => true],
            ['name' => 'Potongan Lain-lain', 'type' => 'deduction', 'category' => 'one-time', 'is_taxable' => false, 'is_active' => true],

            // Tentative / One-time
            ['name' => 'Denda Kerapian', 'type' => 'deduction', 'category' => 'one-time', 'is_taxable' => false, 'is_active' => true],
            ['name' => 'Denda Ketidakdisiplinan', 'type' => 'deduction', 'category' => 'one-time', 'is_taxable' => false, 'is_active' => true],
            ['name' => 'Denda Pelanggaran', 'type' => 'deduction', 'category' => 'one-time', 'is_taxable' => false, 'is_active' => true],
            ['name' => 'Bonus Proyek', 'type' => 'earning', 'category' => 'one-time', 'is_taxable' => true, 'is_active' => true],
            ['name' => 'Insentif Kehadiran', 'type' => 'earning', 'category' => 'one-time', 'is_taxable' => true, 'is_active' => true],
        ];

        foreach ($components as $comp) {
            PayrollComponent::firstOrCreate(
                ['name' => $comp['name']],
                $comp
            );
        }
    }
}
