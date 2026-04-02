<?php

namespace App\Services;

use App\Models\TaxSetting;

class Pph21Calculator
{
    private array $brackets;
    private array $ptkpValues;
    private array $biayaJabatan;

    public function __construct(?string $effectiveDate = null)
    {
        $date = $effectiveDate ?? now()->format('Y-m-d');

        $brackets = TaxSetting::getEffective('pph21_brackets', $date);
        $this->brackets = $brackets ? $brackets->value : [
            ['min' => 0, 'max' => 60000000, 'rate' => 5],
            ['min' => 60000000, 'max' => 250000000, 'rate' => 15],
            ['min' => 250000000, 'max' => 500000000, 'rate' => 25],
            ['min' => 500000000, 'max' => 5000000000, 'rate' => 30],
            ['min' => 5000000000, 'max' => null, 'rate' => 35],
        ];

        $ptkp = TaxSetting::getEffective('ptkp_values', $date);
        $this->ptkpValues = $ptkp ? $ptkp->value : ['TK/0' => 54000000];

        $bj = TaxSetting::getEffective('biaya_jabatan', $date);
        $this->biayaJabatan = $bj ? $bj->value : ['percentage' => 5, 'max_monthly' => 500000, 'max_annual' => 6000000];
    }

    /**
     * Hitung PPh 21 bulanan
     *
     * @param float $brutoMonthly Penghasilan bruto bulanan (sudah termasuk tunjangan, lembur, dll)
     * @param string $ptkpStatus Status PTKP (TK/0, K/1, dll)
     * @param string $taxMethod gross | gross_up | nett
     * @param float $bpjsEmployee Total iuran BPJS yang ditanggung karyawan (pengurang)
     * @return array
     */
    public function calculateMonthly(float $brutoMonthly, string $ptkpStatus, string $taxMethod = 'gross', float $bpjsEmployee = 0): array
    {
        $ptkpAnnual = $this->ptkpValues[$ptkpStatus] ?? $this->ptkpValues['TK/0'] ?? 54000000;

        // Biaya jabatan (5%, max 500K/bln)
        $biayaJabatanPct = ($this->biayaJabatan['percentage'] ?? 5) / 100;
        $biayaJabatanMax = $this->biayaJabatan['max_monthly'] ?? 500000;
        $biayaJabatan = min($brutoMonthly * $biayaJabatanPct, $biayaJabatanMax);

        // Netto bulanan
        $nettoMonthly = $brutoMonthly - $biayaJabatan - $bpjsEmployee;

        // Annualize
        $nettoAnnual = $nettoMonthly * 12;

        // PKP (Penghasilan Kena Pajak)
        $pkp = max($nettoAnnual - $ptkpAnnual, 0);

        // Hitung pajak tahunan berdasarkan tarif progresif
        $taxAnnual = $this->calculateProgressiveTax($pkp);

        // PPh 21 bulanan
        $taxMonthly = round($taxAnnual / 12, 0);

        // Gross-up: iterasi untuk menemukan tunjangan pajak
        $tunjanganPajak = 0;
        if ($taxMethod === 'gross_up' && $taxMonthly > 0) {
            $tunjanganPajak = $this->grossUpIteration($brutoMonthly, $ptkpStatus, $bpjsEmployee);
            // Recalculate with tunjangan
            $brutoWithTunjangan = $brutoMonthly + $tunjanganPajak;
            $biayaJabatan = min($brutoWithTunjangan * $biayaJabatanPct, $biayaJabatanMax);
            $nettoMonthly = $brutoWithTunjangan - $biayaJabatan - $bpjsEmployee;
            $nettoAnnual = $nettoMonthly * 12;
            $pkp = max($nettoAnnual - $ptkpAnnual, 0);
            $taxAnnual = $this->calculateProgressiveTax($pkp);
            $taxMonthly = round($taxAnnual / 12, 0);
        }

        return [
            'bruto_monthly' => $brutoMonthly,
            'biaya_jabatan' => round($biayaJabatan, 0),
            'bpjs_employee' => round($bpjsEmployee, 0),
            'netto_monthly' => round($nettoMonthly, 0),
            'netto_annual' => round($nettoAnnual, 0),
            'ptkp_annual' => $ptkpAnnual,
            'ptkp_status' => $ptkpStatus,
            'pkp' => round($pkp, 0),
            'tax_annual' => round($taxAnnual, 0),
            'tax_monthly' => $taxMonthly,
            'tax_method' => $taxMethod,
            'tunjangan_pajak' => round($tunjanganPajak, 0),
            // For payroll component
            'pph21_deduction' => ($taxMethod === 'nett') ? 0 : $taxMonthly,
            'pph21_tunjangan' => $tunjanganPajak,
        ];
    }

    /**
     * Hitung pajak progresif berdasarkan PKP tahunan
     */
    public function calculateProgressiveTax(float $pkp): float
    {
        $tax = 0;
        $remaining = $pkp;

        foreach ($this->brackets as $bracket) {
            if ($remaining <= 0) break;

            $min = $bracket['min'];
            $max = $bracket['max'];
            $rate = $bracket['rate'] / 100;

            $taxable = ($max !== null)
                ? min($remaining, $max - $min)
                : $remaining;

            $tax += $taxable * $rate;
            $remaining -= $taxable;
        }

        return $tax;
    }

    /**
     * Gross-up iteration: cari tunjangan pajak yang tepat
     * Sehingga PPh 21 = tunjangan pajak (perusahaan menanggung)
     */
    private function grossUpIteration(float $brutoMonthly, string $ptkpStatus, float $bpjsEmployee): float
    {
        $ptkpAnnual = $this->ptkpValues[$ptkpStatus] ?? 54000000;
        $biayaJabatanPct = ($this->biayaJabatan['percentage'] ?? 5) / 100;
        $biayaJabatanMax = $this->biayaJabatan['max_monthly'] ?? 500000;

        // Initial estimate
        $tunjangan = 0;
        for ($i = 0; $i < 20; $i++) {
            $bruto = $brutoMonthly + $tunjangan;
            $bj = min($bruto * $biayaJabatanPct, $biayaJabatanMax);
            $netto = $bruto - $bj - $bpjsEmployee;
            $annual = $netto * 12;
            $pkp = max($annual - $ptkpAnnual, 0);
            $taxAnnual = $this->calculateProgressiveTax($pkp);
            $taxMonthly = round($taxAnnual / 12, 0);

            if (abs($taxMonthly - $tunjangan) < 100) break;
            $tunjangan = $taxMonthly;
        }

        return $tunjangan;
    }

    /**
     * Hitung PPh 21 Bulan Desember (Penghitungan Kembali Tahunan).
     *
     * Sesuai PER-16/PJ/2016 yang diperbarui PMK-168/PMK.03/2023:
     * Pada bulan Desember, pajak dihitung berdasarkan PENGHASILAN SEBENARNYA
     * selama setahun (bukan annualized × 12 dari bulan Desember saja).
     *
     * Rumus:
     *   Bruto Jan-Des  = Bruto Jan-Nov (actual) + Bruto Desember
     *   BJ Setahun     = min(Bruto Setahun × 5%, 6.000.000)
     *   Netto Setahun  = Bruto Setahun - BJ Setahun - BPJS Karyawan Setahun
     *   PKP            = Netto Setahun - PTKP
     *   Pajak Setahun  = tarif progresif atas PKP
     *   PPh21 Des      = Pajak Setahun - Pajak Jan-Nov yang sudah dipotong
     *
     * @param float  $brutoDecember         Penghasilan bruto bulan Desember
     * @param float  $brutoJanToNov         Total bruto Jan-Nov (dari payslip aktual)
     * @param float  $bpjsEmployeeMonthly   BPJS karyawan per bulan (× 12 untuk setahun)
     * @param string $ptkpStatus            Status PTKP
     * @param string $taxMethod             gross | gross_up | nett
     * @param float  $taxJanToNov           PPh 21 yang sudah dipotong Jan-Nov
     * @return array
     */
    public function calculateDecember(
        float  $brutoDecember,
        float  $brutoJanToNov,
        float  $bpjsEmployeeMonthly,
        string $ptkpStatus,
        string $taxMethod = 'gross',
        float  $taxJanToNov = 0
    ): array {
        $ptkpAnnual     = $this->ptkpValues[$ptkpStatus] ?? 54000000;
        $biayaBjMaxAnnual = $this->biayaJabatan['max_annual'] ?? 6000000;
        $biayaBjPct     = ($this->biayaJabatan['percentage'] ?? 5) / 100;

        // Total bruto setahun sesungguhnya (bukan × 12)
        $brutoAnnual = $brutoJanToNov + $brutoDecember;

        // Biaya jabatan setahun (5% dari total bruto, max 6 juta)
        $biayaJabatanAnnual = min($brutoAnnual * $biayaBjPct, $biayaBjMaxAnnual);

        // BPJS setahun (× 12 karena per bulan)
        $bpjsAnnual = $bpjsEmployeeMonthly * 12;

        // Netto setahun sesungguhnya
        $nettoAnnual = $brutoAnnual - $biayaJabatanAnnual - $bpjsAnnual;

        // PKP
        $pkp = max($nettoAnnual - $ptkpAnnual, 0);

        // Pajak setahun berdasarkan PKP aktual
        $taxAnnual = $this->calculateProgressiveTax($pkp);

        // PPh 21 Desember = sisa pajak yang belum dibayar
        $taxDecember = max(round($taxAnnual - $taxJanToNov, 0), 0);

        return [
            'method'                => 'december_annual',
            'bruto_december'        => round($brutoDecember, 0),
            'bruto_jan_to_nov'      => round($brutoJanToNov, 0),
            'bruto_annual_actual'   => round($brutoAnnual, 0),
            'biaya_jabatan_annual'  => round($biayaJabatanAnnual, 0),
            'bpjs_annual'           => round($bpjsAnnual, 0),
            'netto_annual_actual'   => round($nettoAnnual, 0),
            'ptkp_annual'           => $ptkpAnnual,
            'ptkp_status'           => $ptkpStatus,
            'pkp'                   => round($pkp, 0),
            'tax_annual'            => round($taxAnnual, 0),
            'tax_jan_to_nov'        => round($taxJanToNov, 0),
            'tax_december'          => $taxDecember,
            'tax_method'            => $taxMethod,
            // Compat with callers that use pph21_deduction
            'pph21_deduction'       => ($taxMethod === 'nett') ? 0 : $taxDecember,
            'tunjangan_pajak'       => 0,  // gross-up untuk Des tidak dihitung iterasi
            'note'                  => 'Dihitung berdasarkan penghasilan sebenarnya setahun (bukan × 12)',
        ];
    }

    /**
     * Hitung PPh 21 bulan terakhir untuk karyawan resign di pertengahan tahun.
     *
     * Sesuai PMK-168/PMK.03/2023:
     * - Disetahunkan berdasarkan jumlah bulan aktual bekerja (bukan selalu x12)
     * - Pajak bulan terakhir = total pajak setahun (berdasarkan M bulan) - pajak yang sudah dibayar
     *
     * @param float  $avgBrutoMonthly   Rata-rata penghasilan bruto per bulan (atau bulan terakhir)
     * @param string $ptkpStatus        Status PTKP (TK/0, K/1, dll)
     * @param string $taxMethod         gross | gross_up | nett
     * @param float  $bpjsEmployee      Iuran BPJS karyawan per bulan
     * @param int    $monthsWorked      Jumlah bulan bekerja di tahun ini (1-12)
     * @param float  $taxAlreadyPaid    PPh 21 yang sudah dipotong bulan-bulan sebelumnya
     * @return array
     */
    public function calculateFinalMonth(
        float  $avgBrutoMonthly,
        string $ptkpStatus,
        string $taxMethod,
        float  $bpjsEmployee,
        int    $monthsWorked,
        float  $taxAlreadyPaid = 0
    ): array {
        $ptkpAnnual     = $this->ptkpValues[$ptkpStatus] ?? 54000000;
        $biayaBjPct     = ($this->biayaJabatan['percentage'] ?? 5) / 100;
        $biayaBjMax     = $this->biayaJabatan['max_monthly'] ?? 500000;

        $monthsWorked = max(1, min(12, $monthsWorked));

        // Biaya jabatan per bulan (capped)
        $biayaJabatan = min($avgBrutoMonthly * $biayaBjPct, $biayaBjMax);

        // Netto bulanan
        $nettoMonthly = $avgBrutoMonthly - $biayaJabatan - $bpjsEmployee;

        // Disetahunkan berdasarkan M bulan (bukan × 12)
        $nettoAnnualized = $nettoMonthly * $monthsWorked;

        // PKP berdasarkan periode aktual
        $pkp = max($nettoAnnualized - $ptkpAnnual, 0);

        // Pajak setahun (atas M bulan)
        $taxForPeriod = $this->calculateProgressiveTax($pkp);

        // PPh 21 bulan terakhir = selisih dari yang belum dibayar
        $taxFinalMonth = max(round($taxForPeriod - $taxAlreadyPaid, 0), 0);

        return [
            'avg_bruto_monthly'   => $avgBrutoMonthly,
            'months_worked'       => $monthsWorked,
            'biaya_jabatan'       => round($biayaJabatan, 0),
            'bpjs_employee'       => round($bpjsEmployee, 0),
            'netto_monthly'       => round($nettoMonthly, 0),
            'netto_annualized'    => round($nettoAnnualized, 0),  // netto × M (bukan × 12)
            'ptkp_annual'         => $ptkpAnnual,
            'ptkp_status'         => $ptkpStatus,
            'pkp'                 => round($pkp, 0),
            'tax_for_period'      => round($taxForPeriod, 0),     // pajak atas M bulan
            'tax_already_paid'    => round($taxAlreadyPaid, 0),
            'tax_final_month'     => $taxFinalMonth,              // yang harus dipotong bulan terakhir
            'tax_method'          => $taxMethod,
            'note'                => "Dihitung berdasarkan {$monthsWorked} bulan bekerja (bukan 12 bulan)",
        ];
    }

    /**
     * Simulasi untuk kalkulator pajak
     */
    public function simulate(float $grossMonthly, string $ptkpStatus, string $taxMethod = 'gross'): array
    {
        $bpjsCalc = new BpjsCalculator();
        $bpjs = $bpjsCalc->calculate($grossMonthly);
        $bpjsEmployeeTotal = $bpjs['employee_total'];

        $result = $this->calculateMonthly($grossMonthly, $ptkpStatus, $taxMethod, $bpjsEmployeeTotal);
        $result['bpjs_detail'] = $bpjs;

        // Take-home pay
        if ($taxMethod === 'gross') {
            $result['take_home'] = round($grossMonthly - $result['tax_monthly'] - $bpjsEmployeeTotal, 0);
        } elseif ($taxMethod === 'gross_up') {
            $result['take_home'] = round($grossMonthly - $bpjsEmployeeTotal, 0);
        } else { // nett
            $result['take_home'] = round($grossMonthly - $bpjsEmployeeTotal, 0);
        }

        return $result;
    }
}
