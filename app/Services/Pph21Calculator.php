<?php

namespace App\Services;

use App\Models\TaxSetting;

class Pph21Calculator
{
    private array $brackets;
    private array $ptkpValues;
    private array $biayaJabatan;
    private array $terMonthly;

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

        $ter = TaxSetting::getEffective('pph21_ter_monthly', $date);
        $this->terMonthly = $ter ? $ter->value : self::defaultTerMonthly();
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
        return $this->calculateTerMonthly($brutoMonthly, $ptkpStatus, $taxMethod, $bpjsEmployee);
    }

    private function calculateTerMonthly(float $brutoMonthly, string $ptkpStatus, string $taxMethod, float $bpjsEmployee): array
    {
        $ptkpAnnual = $this->ptkpValues[$ptkpStatus] ?? $this->ptkpValues['TK/0'] ?? 54000000;

        $biayaJabatanPct = ($this->biayaJabatan['percentage'] ?? 5) / 100;
        $biayaJabatanMax = $this->biayaJabatan['max_monthly'] ?? 500000;
        $biayaJabatan = min($brutoMonthly * $biayaJabatanPct, $biayaJabatanMax);
        $nettoMonthly = $brutoMonthly - $biayaJabatan - $bpjsEmployee;
        $nettoAnnual = $nettoMonthly * 12;
        $pkp = max($nettoAnnual - $ptkpAnnual, 0);

        $terCategory = $this->terCategoryForPtkp($ptkpStatus);
        $terRate = $this->lookupTerMonthlyRate($brutoMonthly, $terCategory);
        $taxMonthly = round($brutoMonthly * ($terRate / 100), 0);
        $taxAnnualEstimate = $taxMonthly * 12;

        $tunjanganPajak = 0;
        if ($taxMethod === 'gross_up' && $taxMonthly > 0) {
            $tunjanganPajak = $this->grossUpTerIteration($brutoMonthly, $ptkpStatus);
            $brutoWithTunjangan = $brutoMonthly + $tunjanganPajak;
            $biayaJabatan = min($brutoWithTunjangan * $biayaJabatanPct, $biayaJabatanMax);
            $nettoMonthly = $brutoWithTunjangan - $biayaJabatan - $bpjsEmployee;
            $nettoAnnual = $nettoMonthly * 12;
            $pkp = max($nettoAnnual - $ptkpAnnual, 0);
            $terRate = $this->lookupTerMonthlyRate($brutoWithTunjangan, $terCategory);
            $taxMonthly = round($brutoWithTunjangan * ($terRate / 100), 0);
            $taxAnnualEstimate = $taxMonthly * 12;
        }

        return [
            'method' => 'ter_monthly',
            'bruto_monthly' => $brutoMonthly,
            'biaya_jabatan' => round($biayaJabatan, 0),
            'bpjs_employee' => round($bpjsEmployee, 0),
            'netto_monthly' => round($nettoMonthly, 0),
            'netto_annual' => round($nettoAnnual, 0),
            'ptkp_annual' => $ptkpAnnual,
            'ptkp_status' => $ptkpStatus,
            'pkp' => round($pkp, 0),
            'tax_annual' => round($taxAnnualEstimate, 0),
            'tax_monthly' => $taxMonthly,
            'ter_category' => $terCategory,
            'ter_rate' => $terRate,
            'tax_method' => $taxMethod,
            'tunjangan_pajak' => round($tunjanganPajak, 0),
            'pph21_deduction' => ($taxMethod === 'nett') ? 0 : $taxMonthly,
            'pph21_tunjangan' => $tunjanganPajak,
            'note' => 'Jan-Nov menggunakan TER bulanan sesuai PP 58/2023; Desember dihitung ulang tahunan.',
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

    private function grossUpTerIteration(float $brutoMonthly, string $ptkpStatus): float
    {
        $category = $this->terCategoryForPtkp($ptkpStatus);
        $tunjangan = 0;

        for ($i = 0; $i < 20; $i++) {
            $bruto = $brutoMonthly + $tunjangan;
            $rate = $this->lookupTerMonthlyRate($bruto, $category) / 100;
            $tax = round($bruto * $rate, 0);

            if (abs($tax - $tunjangan) < 100) {
                return $tax;
            }

            $tunjangan = $tax;
        }

        return $tunjangan;
    }

    public function terCategoryForPtkp(string $ptkpStatus): string
    {
        $categories = $this->terMonthly['ptkp_categories'] ?? [];

        return $categories[$ptkpStatus] ?? 'A';
    }

    public function lookupTerMonthlyRate(float $brutoMonthly, string $category): float
    {
        $rates = $this->terMonthly['rates'][$category] ?? $this->terMonthly['rates']['A'] ?? [];

        foreach ($rates as $row) {
            $min = (float) ($row['min'] ?? 0);
            $max = $row['max'] ?? null;

            if ($brutoMonthly <= $min) {
                continue;
            }

            if ($max === null || $brutoMonthly <= (float) $max) {
                return (float) ($row['rate'] ?? 0);
            }
        }

        $last = end($rates);

        return (float) (($last['rate'] ?? 0));
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

        // Premi pemberi kerja Kesehatan + JKK + JKM = penambah penghasilan bruto (kena pajak),
        // tapi tidak menambah take-home karyawan. JHT pemberi kerja bukan penambah.
        $taxableEmployerBenefit = $bpjs['kesehatan']['company'] + $bpjs['jkk']['company'] + $bpjs['jkm']['company'];

        $result = $this->calculateMonthly($grossMonthly + $taxableEmployerBenefit, $ptkpStatus, $taxMethod, $bpjsEmployeeTotal);
        $result['bpjs_detail'] = $bpjs;
        $result['gross_input'] = round($grossMonthly, 0);
        $result['taxable_employer_benefit'] = round($taxableEmployerBenefit, 0);

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

    public static function defaultTerMonthly(): array
    {
        return [
            'ptkp_categories' => [
                'TK/0' => 'A',
                'TK/1' => 'A',
                'K/0' => 'A',
                'TK/2' => 'B',
                'TK/3' => 'B',
                'K/1' => 'B',
                'K/2' => 'B',
                'K/3' => 'C',
                'K/I/0' => 'C',
                'K/I/1' => 'C',
                'K/I/2' => 'C',
                'K/I/3' => 'C',
            ],
            'rates' => [
                'A' => [
                    ['min' => 0, 'max' => 5400000, 'rate' => 0],
                    ['min' => 5400000, 'max' => 5650000, 'rate' => 0.25],
                    ['min' => 5650000, 'max' => 5950000, 'rate' => 0.5],
                    ['min' => 5950000, 'max' => 6300000, 'rate' => 0.75],
                    ['min' => 6300000, 'max' => 6750000, 'rate' => 1],
                    ['min' => 6750000, 'max' => 7500000, 'rate' => 1.25],
                    ['min' => 7500000, 'max' => 8550000, 'rate' => 1.5],
                    ['min' => 8550000, 'max' => 9650000, 'rate' => 1.75],
                    ['min' => 9650000, 'max' => 10050000, 'rate' => 2],
                    ['min' => 10050000, 'max' => 10350000, 'rate' => 2.25],
                    ['min' => 10350000, 'max' => 10700000, 'rate' => 2.5],
                    ['min' => 10700000, 'max' => 11050000, 'rate' => 3],
                    ['min' => 11050000, 'max' => 11600000, 'rate' => 3.5],
                    ['min' => 11600000, 'max' => 12500000, 'rate' => 4],
                    ['min' => 12500000, 'max' => 13750000, 'rate' => 5],
                    ['min' => 13750000, 'max' => 15100000, 'rate' => 6],
                    ['min' => 15100000, 'max' => 16950000, 'rate' => 7],
                    ['min' => 16950000, 'max' => 19750000, 'rate' => 8],
                    ['min' => 19750000, 'max' => 24150000, 'rate' => 9],
                    ['min' => 24150000, 'max' => 26450000, 'rate' => 10],
                    ['min' => 26450000, 'max' => 28000000, 'rate' => 11],
                    ['min' => 28000000, 'max' => 30050000, 'rate' => 12],
                    ['min' => 30050000, 'max' => 32400000, 'rate' => 13],
                    ['min' => 32400000, 'max' => 35400000, 'rate' => 14],
                    ['min' => 35400000, 'max' => 39100000, 'rate' => 15],
                    ['min' => 39100000, 'max' => 43850000, 'rate' => 16],
                    ['min' => 43850000, 'max' => 47800000, 'rate' => 17],
                    ['min' => 47800000, 'max' => 51400000, 'rate' => 18],
                    ['min' => 51400000, 'max' => 56300000, 'rate' => 19],
                    ['min' => 56300000, 'max' => 62200000, 'rate' => 20],
                    ['min' => 62200000, 'max' => 68600000, 'rate' => 21],
                    ['min' => 68600000, 'max' => 77500000, 'rate' => 22],
                    ['min' => 77500000, 'max' => 89000000, 'rate' => 23],
                    ['min' => 89000000, 'max' => 103000000, 'rate' => 24],
                    ['min' => 103000000, 'max' => 125000000, 'rate' => 25],
                    ['min' => 125000000, 'max' => 157000000, 'rate' => 26],
                    ['min' => 157000000, 'max' => 206000000, 'rate' => 27],
                    ['min' => 206000000, 'max' => 337000000, 'rate' => 28],
                    ['min' => 337000000, 'max' => 454000000, 'rate' => 29],
                    ['min' => 454000000, 'max' => 550000000, 'rate' => 30],
                    ['min' => 550000000, 'max' => 695000000, 'rate' => 31],
                    ['min' => 695000000, 'max' => 910000000, 'rate' => 32],
                    ['min' => 910000000, 'max' => 1400000000, 'rate' => 33],
                    ['min' => 1400000000, 'max' => null, 'rate' => 34],
                ],
                'B' => [
                    ['min' => 0, 'max' => 6200000, 'rate' => 0],
                    ['min' => 6200000, 'max' => 6500000, 'rate' => 0.25],
                    ['min' => 6500000, 'max' => 6850000, 'rate' => 0.5],
                    ['min' => 6850000, 'max' => 7300000, 'rate' => 0.75],
                    ['min' => 7300000, 'max' => 9200000, 'rate' => 1],
                    ['min' => 9200000, 'max' => 10750000, 'rate' => 1.5],
                    ['min' => 10750000, 'max' => 11250000, 'rate' => 2],
                    ['min' => 11250000, 'max' => 11600000, 'rate' => 2.5],
                    ['min' => 11600000, 'max' => 12600000, 'rate' => 3],
                    ['min' => 12600000, 'max' => 13600000, 'rate' => 4],
                    ['min' => 13600000, 'max' => 14950000, 'rate' => 5],
                    ['min' => 14950000, 'max' => 16400000, 'rate' => 6],
                    ['min' => 16400000, 'max' => 18450000, 'rate' => 7],
                    ['min' => 18450000, 'max' => 21850000, 'rate' => 8],
                    ['min' => 21850000, 'max' => 26000000, 'rate' => 9],
                    ['min' => 26000000, 'max' => 27700000, 'rate' => 10],
                    ['min' => 27700000, 'max' => 29350000, 'rate' => 11],
                    ['min' => 29350000, 'max' => 31450000, 'rate' => 12],
                    ['min' => 31450000, 'max' => 33950000, 'rate' => 13],
                    ['min' => 33950000, 'max' => 37100000, 'rate' => 14],
                    ['min' => 37100000, 'max' => 41100000, 'rate' => 15],
                    ['min' => 41100000, 'max' => 45800000, 'rate' => 16],
                    ['min' => 45800000, 'max' => 49500000, 'rate' => 17],
                    ['min' => 49500000, 'max' => 53800000, 'rate' => 18],
                    ['min' => 53800000, 'max' => 58500000, 'rate' => 19],
                    ['min' => 58500000, 'max' => 64000000, 'rate' => 20],
                    ['min' => 64000000, 'max' => 71000000, 'rate' => 21],
                    ['min' => 71000000, 'max' => 80000000, 'rate' => 22],
                    ['min' => 80000000, 'max' => 93000000, 'rate' => 23],
                    ['min' => 93000000, 'max' => 109000000, 'rate' => 24],
                    ['min' => 109000000, 'max' => 129000000, 'rate' => 25],
                    ['min' => 129000000, 'max' => 163000000, 'rate' => 26],
                    ['min' => 163000000, 'max' => 211000000, 'rate' => 27],
                    ['min' => 211000000, 'max' => 374000000, 'rate' => 28],
                    ['min' => 374000000, 'max' => 459000000, 'rate' => 29],
                    ['min' => 459000000, 'max' => 555000000, 'rate' => 30],
                    ['min' => 555000000, 'max' => 704000000, 'rate' => 31],
                    ['min' => 704000000, 'max' => 957000000, 'rate' => 32],
                    ['min' => 957000000, 'max' => 1405000000, 'rate' => 33],
                    ['min' => 1405000000, 'max' => null, 'rate' => 34],
                ],
                'C' => [
                    ['min' => 0, 'max' => 6600000, 'rate' => 0],
                    ['min' => 6600000, 'max' => 6950000, 'rate' => 0.25],
                    ['min' => 6950000, 'max' => 7350000, 'rate' => 0.5],
                    ['min' => 7350000, 'max' => 7800000, 'rate' => 0.75],
                    ['min' => 7800000, 'max' => 8850000, 'rate' => 1],
                    ['min' => 8850000, 'max' => 9800000, 'rate' => 1.25],
                    ['min' => 9800000, 'max' => 10950000, 'rate' => 1.5],
                    ['min' => 10950000, 'max' => 11200000, 'rate' => 1.75],
                    ['min' => 11200000, 'max' => 12050000, 'rate' => 2],
                    ['min' => 12050000, 'max' => 12950000, 'rate' => 3],
                    ['min' => 12950000, 'max' => 14150000, 'rate' => 4],
                    ['min' => 14150000, 'max' => 15550000, 'rate' => 5],
                    ['min' => 15550000, 'max' => 17050000, 'rate' => 6],
                    ['min' => 17050000, 'max' => 19500000, 'rate' => 7],
                    ['min' => 19500000, 'max' => 22700000, 'rate' => 8],
                    ['min' => 22700000, 'max' => 26600000, 'rate' => 9],
                    ['min' => 26600000, 'max' => 28100000, 'rate' => 10],
                    ['min' => 28100000, 'max' => 30100000, 'rate' => 11],
                    ['min' => 30100000, 'max' => 32600000, 'rate' => 12],
                    ['min' => 32600000, 'max' => 35400000, 'rate' => 13],
                    ['min' => 35400000, 'max' => 38900000, 'rate' => 14],
                    ['min' => 38900000, 'max' => 43000000, 'rate' => 15],
                    ['min' => 43000000, 'max' => 47400000, 'rate' => 16],
                    ['min' => 47400000, 'max' => 51200000, 'rate' => 17],
                    ['min' => 51200000, 'max' => 55800000, 'rate' => 18],
                    ['min' => 55800000, 'max' => 60400000, 'rate' => 19],
                    ['min' => 60400000, 'max' => 66700000, 'rate' => 20],
                    ['min' => 66700000, 'max' => 74500000, 'rate' => 21],
                    ['min' => 74500000, 'max' => 83200000, 'rate' => 22],
                    ['min' => 83200000, 'max' => 95600000, 'rate' => 23],
                    ['min' => 95600000, 'max' => 110000000, 'rate' => 24],
                    ['min' => 110000000, 'max' => 134000000, 'rate' => 25],
                    ['min' => 134000000, 'max' => 169000000, 'rate' => 26],
                    ['min' => 169000000, 'max' => 221000000, 'rate' => 27],
                    ['min' => 221000000, 'max' => 390000000, 'rate' => 28],
                    ['min' => 390000000, 'max' => 463000000, 'rate' => 29],
                    ['min' => 463000000, 'max' => 561000000, 'rate' => 30],
                    ['min' => 561000000, 'max' => 709000000, 'rate' => 31],
                    ['min' => 709000000, 'max' => 965000000, 'rate' => 32],
                    ['min' => 965000000, 'max' => 1419000000, 'rate' => 33],
                    ['min' => 1419000000, 'max' => null, 'rate' => 34],
                ],
            ],
        ];
    }
}
