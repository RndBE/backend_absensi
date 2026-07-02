<?php

namespace Tests\Unit;

use App\Services\Pph21Calculator;
use Tests\TestCase;

class Pph21TaxableBrutoTest extends TestCase
{
    /**
     * Premi pemberi kerja JKK, JKM, dan BPJS Kesehatan (4%) adalah objek PPh 21
     * (kenikmatan) sehingga masuk bruto. JHT & JP pemberi kerja dikecualikan.
     */
    public function test_taxable_bruto_includes_taxable_employer_premi_and_excludes_jht_jp(): void
    {
        $basic = 9_624_387.0;
        $components = [
            ['name' => 'Tunjangan Transportasi', 'type' => 'earning', 'is_taxable' => true, 'amount' => 1_250_000],
            ['name' => 'Tunjangan Keluarga', 'type' => 'earning', 'is_taxable' => true, 'amount' => 500_000],
            ['name' => 'Tunjangan Fasilitas Kesehatan', 'type' => 'earning', 'is_taxable' => true, 'amount' => 192_488],
            // Potongan karyawan — bukan bruto
            ['name' => 'BPJS Kesehatan', 'type' => 'deduction', 'is_taxable' => false, 'amount' => 96_244],
            ['name' => 'JHT Karyawan', 'type' => 'deduction', 'is_taxable' => false, 'amount' => 192_488],
            // Premi pemberi kerja — objek pajak
            ['name' => 'BPJS Kesehatan Perusahaan', 'type' => 'info', 'is_taxable' => false, 'amount' => 384_975],
            ['name' => 'JKK Perusahaan', 'type' => 'info', 'is_taxable' => false, 'amount' => 23_099],
            ['name' => 'JKM Perusahaan', 'type' => 'info', 'is_taxable' => false, 'amount' => 28_873],
            // Premi pemberi kerja — BUKAN objek pajak (ditangguhkan)
            ['name' => 'JHT Perusahaan', 'type' => 'info', 'is_taxable' => false, 'amount' => 356_102],
            ['name' => 'JP Perusahaan', 'type' => 'info', 'is_taxable' => false, 'amount' => 0],
        ];

        // basic + 3 tunjangan taxable + (BPJS Kes 4% + JKK + JKM)
        $expected = 9_624_387.0 + 1_250_000 + 500_000 + 192_488 + 384_975 + 23_099 + 28_873;

        $this->assertSame(12_003_822.0, $expected); // sanity kertas kerja
        $this->assertSame(
            $expected,
            Pph21Calculator::taxableBrutoFromComponents($basic, $components)
        );
    }

    public function test_taxable_bruto_excludes_non_taxable_earnings(): void
    {
        $components = [
            ['name' => 'Tunjangan Taxable', 'type' => 'earning', 'is_taxable' => true, 'amount' => 1_000_000],
            ['name' => 'Reimbursement Murni', 'type' => 'earning', 'is_taxable' => false, 'amount' => 500_000],
        ];

        $this->assertSame(
            6_000_000.0, // basic 5jt + 1jt taxable saja
            Pph21Calculator::taxableBrutoFromComponents(5_000_000.0, $components)
        );
    }

    public function test_taxable_employer_premi_sums_only_kesehatan_jkk_jkm(): void
    {
        $bpjs = [
            'kesehatan' => ['company' => 384_975, 'employee' => 96_244],
            'jht' => ['company' => 356_102, 'employee' => 192_488],
            'jkk' => ['company' => 23_099, 'employee' => 0],
            'jkm' => ['company' => 28_873, 'employee' => 0],
            'jp' => ['company' => 0, 'employee' => 0],
        ];

        $this->assertSame(
            436_947.0, // 384975 + 23099 + 28873
            Pph21Calculator::taxableEmployerBpjs($bpjs)
        );
    }
}
