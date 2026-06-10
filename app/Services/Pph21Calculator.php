<?php

namespace App\Services;

use App\Models\TaxSetting;

class Pph21Calculator
{
    private array $brackets;
    private array $ptkpValues;
    private array $biayaJabatan;
    private array $terTable;

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

        $ter = TaxSetting::getEffective('ter_monthly', $date);
        $this->terTable = $ter ? $ter->value : self::defaultTerTable();
    }

    /**
     * Tabel Tarif Efektif Rata-rata (TER) Bulanan — Lampiran PP No. 58 Tahun 2023.
     *
     * 127 baris: 44 (Kategori A) + 40 (Kategori B) + 41 (Kategori C).
     * Tiap baris: ['max' => batas atas penghasilan bruto bulanan, 'rate' => tarif %].
     * Baris terakhir tiap kategori menggunakan max=null (tak terbatas).
     *
     * Kategori A : PTKP TK/0, TK/1, K/0   (54jt & 58,5jt)
     * Kategori B : PTKP TK/2, TK/3, K/1, K/2 (63jt & 67,5jt)
     * Kategori C : PTKP K/3                (72jt)
     *
     * Sumber tunggal kebenaran — di-seed ke TaxSetting('ter_monthly') oleh TaxBpjsSeeder.
     */
    public static function defaultTerTable(): array
    {
        return [
            'A' => [
                ['max' => 5400000, 'rate' => 0],     ['max' => 5650000, 'rate' => 0.25],
                ['max' => 5950000, 'rate' => 0.5],   ['max' => 6300000, 'rate' => 0.75],
                ['max' => 6750000, 'rate' => 1],     ['max' => 7500000, 'rate' => 1.25],
                ['max' => 8550000, 'rate' => 1.5],   ['max' => 9650000, 'rate' => 1.75],
                ['max' => 10050000, 'rate' => 2],    ['max' => 10350000, 'rate' => 2.25],
                ['max' => 10700000, 'rate' => 2.5],  ['max' => 11050000, 'rate' => 3],
                ['max' => 11600000, 'rate' => 3.5],  ['max' => 12500000, 'rate' => 4],
                ['max' => 13750000, 'rate' => 5],    ['max' => 15100000, 'rate' => 6],
                ['max' => 16950000, 'rate' => 7],    ['max' => 19750000, 'rate' => 8],
                ['max' => 24150000, 'rate' => 9],    ['max' => 26450000, 'rate' => 10],
                ['max' => 28000000, 'rate' => 11],   ['max' => 30050000, 'rate' => 12],
                ['max' => 32400000, 'rate' => 13],   ['max' => 35400000, 'rate' => 14],
                ['max' => 39100000, 'rate' => 15],   ['max' => 43850000, 'rate' => 16],
                ['max' => 47800000, 'rate' => 17],   ['max' => 51400000, 'rate' => 18],
                ['max' => 56300000, 'rate' => 19],   ['max' => 62200000, 'rate' => 20],
                ['max' => 68600000, 'rate' => 21],   ['max' => 77500000, 'rate' => 22],
                ['max' => 89000000, 'rate' => 23],   ['max' => 103000000, 'rate' => 24],
                ['max' => 125000000, 'rate' => 25],  ['max' => 157000000, 'rate' => 26],
                ['max' => 206000000, 'rate' => 27],  ['max' => 337000000, 'rate' => 28],
                ['max' => 454000000, 'rate' => 29],  ['max' => 550000000, 'rate' => 30],
                ['max' => 695000000, 'rate' => 31],  ['max' => 910000000, 'rate' => 32],
                ['max' => 1400000000, 'rate' => 33], ['max' => null, 'rate' => 34],
            ],
            'B' => [
                ['max' => 6200000, 'rate' => 0],     ['max' => 6500000, 'rate' => 0.25],
                ['max' => 6850000, 'rate' => 0.5],   ['max' => 7300000, 'rate' => 0.75],
                ['max' => 9200000, 'rate' => 1],     ['max' => 10750000, 'rate' => 1.5],
                ['max' => 11250000, 'rate' => 2],    ['max' => 11600000, 'rate' => 2.5],
                ['max' => 12600000, 'rate' => 3],    ['max' => 13600000, 'rate' => 4],
                ['max' => 14950000, 'rate' => 5],    ['max' => 16400000, 'rate' => 6],
                ['max' => 18450000, 'rate' => 7],    ['max' => 21850000, 'rate' => 8],
                ['max' => 26000000, 'rate' => 9],    ['max' => 27700000, 'rate' => 10],
                ['max' => 29350000, 'rate' => 11],   ['max' => 31450000, 'rate' => 12],
                ['max' => 33950000, 'rate' => 13],   ['max' => 37100000, 'rate' => 14],
                ['max' => 41100000, 'rate' => 15],   ['max' => 45800000, 'rate' => 16],
                ['max' => 49500000, 'rate' => 17],   ['max' => 53800000, 'rate' => 18],
                ['max' => 58500000, 'rate' => 19],   ['max' => 64000000, 'rate' => 20],
                ['max' => 71000000, 'rate' => 21],   ['max' => 80000000, 'rate' => 22],
                ['max' => 93000000, 'rate' => 23],   ['max' => 109000000, 'rate' => 24],
                ['max' => 129000000, 'rate' => 25],  ['max' => 163000000, 'rate' => 26],
                ['max' => 211000000, 'rate' => 27],  ['max' => 374000000, 'rate' => 28],
                ['max' => 459000000, 'rate' => 29],  ['max' => 555000000, 'rate' => 30],
                ['max' => 704000000, 'rate' => 31],  ['max' => 957000000, 'rate' => 32],
                ['max' => 1410000000, 'rate' => 33], ['max' => null, 'rate' => 34],
            ],
            'C' => [
                ['max' => 6600000, 'rate' => 0],     ['max' => 6950000, 'rate' => 0.25],
                ['max' => 7350000, 'rate' => 0.5],   ['max' => 7800000, 'rate' => 0.75],
                ['max' => 8850000, 'rate' => 1],     ['max' => 9800000, 'rate' => 1.25],
                ['max' => 10950000, 'rate' => 1.5],  ['max' => 11200000, 'rate' => 1.75],
                ['max' => 12050000, 'rate' => 2],    ['max' => 12950000, 'rate' => 3],
                ['max' => 14150000, 'rate' => 4],    ['max' => 15550000, 'rate' => 5],
                ['max' => 17050000, 'rate' => 6],    ['max' => 19500000, 'rate' => 7],
                ['max' => 22700000, 'rate' => 8],    ['max' => 26600000, 'rate' => 9],
                ['max' => 28100000, 'rate' => 10],   ['max' => 30100000, 'rate' => 11],
                ['max' => 32600000, 'rate' => 12],   ['max' => 35400000, 'rate' => 13],
                ['max' => 38900000, 'rate' => 14],   ['max' => 43000000, 'rate' => 15],
                ['max' => 47400000, 'rate' => 16],   ['max' => 51200000, 'rate' => 17],
                ['max' => 55800000, 'rate' => 18],   ['max' => 60400000, 'rate' => 19],
                ['max' => 66700000, 'rate' => 20],   ['max' => 74500000, 'rate' => 21],
                ['max' => 83200000, 'rate' => 22],   ['max' => 95600000, 'rate' => 23],
                ['max' => 110000000, 'rate' => 24],  ['max' => 134000000, 'rate' => 25],
                ['max' => 169000000, 'rate' => 26],  ['max' => 221000000, 'rate' => 27],
                ['max' => 390000000, 'rate' => 28],  ['max' => 463000000, 'rate' => 29],
                ['max' => 561000000, 'rate' => 30],  ['max' => 709000000, 'rate' => 31],
                ['max' => 965000000, 'rate' => 32],  ['max' => 1420000000, 'rate' => 33],
                ['max' => null, 'rate' => 34],
            ],
        ];
    }

    /**
     * Petakan status PTKP ke kategori TER (A/B/C) sesuai PP 58/2023.
     *
     * Status K/I/n (penghasilan istri digabung) dipetakan mengikuti jumlah
     * tanggungan yang setara (K/I/0→K/0, K/I/1→K/1, dst).
     */
    public function terCategory(string $ptkpStatus): string
    {
        $map = [
            'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
            'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
            'K/3'  => 'C',
            // K/I (digabung) — ikuti jumlah tanggungan setara
            'K/I/0' => 'A', 'K/I/1' => 'B', 'K/I/2' => 'B', 'K/I/3' => 'C',
        ];

        return $map[$ptkpStatus] ?? 'A';
    }

    /**
     * Cari tarif efektif TER (dalam persen) untuk kategori & penghasilan bruto bulanan.
     */
    public function terRate(string $category, float $brutoMonthly): float
    {
        $rows = $this->terTable[$category] ?? $this->terTable['A'] ?? [];

        foreach ($rows as $row) {
            $max = $row['max'];
            if ($max === null || $brutoMonthly <= $max) {
                return (float) $row['rate'];
            }
        }

        // Fallback ke tarif tertinggi jika tabel tidak lengkap
        return (float) (end($rows)['rate'] ?? 0);
    }

    /**
     * Hitung PPh 21 Masa Pajak Januari–November dengan metode TER (PP 58/2023).
     *
     * Berlaku sejak 1 Januari 2024:
     *   PPh 21 = Penghasilan Bruto Bulanan × Tarif Efektif (TER)
     * Tanpa biaya jabatan, tanpa annualisasi, tanpa pengurang PTKP per bulan
     * (perhitungan tahunan progresif hanya dilakukan di Masa Desember).
     *
     * @param float  $brutoMonthly Penghasilan bruto bulanan (termasuk tunjangan, lembur, dll)
     * @param string $ptkpStatus   Status PTKP (TK/0, K/1, dll)
     * @param string $taxMethod    gross | gross_up | nett
     * @param float  $bpjsEmployee BPJS karyawan (info saja; tidak mengurangi dasar TER)
     * @return array
     */
    public function calculateMonthlyTER(float $brutoMonthly, string $ptkpStatus, string $taxMethod = 'gross', float $bpjsEmployee = 0): array
    {
        $category = $this->terCategory($ptkpStatus);

        $tunjanganPajak = 0;
        $brutoForTax    = $brutoMonthly;

        if ($taxMethod === 'gross_up') {
            $tunjanganPajak = $this->grossUpIterationTER($brutoMonthly, $category);
            $brutoForTax    = $brutoMonthly + $tunjanganPajak;
        }

        $rate       = $this->terRate($category, $brutoForTax);
        $taxMonthly = round($brutoForTax * $rate / 100, 0);

        return [
            'method'          => 'ter_monthly',
            'bruto_monthly'   => round($brutoMonthly, 0),
            'bpjs_employee'   => round($bpjsEmployee, 0),
            'ter_category'    => $category,
            'ter_rate'        => $rate,
            'ptkp_status'     => $ptkpStatus,
            'pkp'             => 0, // TER tidak memakai PKP per bulan
            'tax_monthly'     => $taxMonthly,
            'tax_method'      => $taxMethod,
            'tunjangan_pajak' => round($tunjanganPajak, 0),
            // Kompat dengan caller yang memakai pph21_deduction
            'pph21_deduction' => ($taxMethod === 'nett') ? 0 : $taxMonthly,
            'pph21_tunjangan' => $tunjanganPajak,
        ];
    }

    /**
     * Gross-up iteration untuk metode TER: cari tunjangan pajak sehingga
     * PPh 21 (= (bruto + tunjangan) × TER) ≈ tunjangan pajak.
     */
    private function grossUpIterationTER(float $brutoMonthly, string $category): float
    {
        $tunjangan = 0;
        for ($i = 0; $i < 20; $i++) {
            $bruto = $brutoMonthly + $tunjangan;
            $rate  = $this->terRate($category, $bruto);
            $tax   = round($bruto * $rate / 100, 0);

            if (abs($tax - $tunjangan) < 100) {
                break;
            }
            $tunjangan = $tax;
        }

        return $tunjangan;
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
     * Simulasi untuk kalkulator pajak (Masa Pajak Jan–Nov, metode TER).
     */
    public function simulate(float $grossMonthly, string $ptkpStatus, string $taxMethod = 'gross'): array
    {
        $bpjsCalc = new BpjsCalculator();
        $bpjs = $bpjsCalc->calculate($grossMonthly);
        $bpjsEmployeeTotal = $bpjs['employee_total'];

        $result = $this->calculateMonthlyTER($grossMonthly, $ptkpStatus, $taxMethod, $bpjsEmployeeTotal);
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
