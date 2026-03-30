<?php

namespace App\Services;

use App\Models\BpjsSetting;

class BpjsCalculator
{
    private array $rates;
    private array $caps;

    public function __construct(?string $effectiveDate = null)
    {
        $date = $effectiveDate ?? now()->format('Y-m-d');

        $this->rates = [];
        $this->caps = [];

        foreach (['kes_rate', 'jht_rate', 'jkk_rate', 'jkm_rate', 'jp_rate'] as $key) {
            $setting = BpjsSetting::getEffective($key, $date);
            $this->rates[$key] = $setting ? $setting->value : ['company' => 0, 'employee' => 0];
        }

        foreach (['kes_cap', 'jp_cap'] as $key) {
            $setting = BpjsSetting::getEffective($key, $date);
            $this->caps[$key] = $setting ? $setting->value : ['salary_cap' => 0];
        }
    }

    /**
     * Hitung semua iuran BPJS
     *
     * @param float $basicSalary Gaji pokok + tunjangan tetap
     * @return array
     */
    public function calculate(float $basicSalary): array
    {
        $result = [];
        $companyTotal = 0;
        $employeeTotal = 0;

        // BPJS Kesehatan
        $kesCap = $this->caps['kes_cap']['salary_cap'] ?? 12000000;
        $kesBasis = min($basicSalary, $kesCap);
        $kesCompany = round($kesBasis * ($this->rates['kes_rate']['company'] ?? 4) / 100, 0);
        $kesEmployee = round($kesBasis * ($this->rates['kes_rate']['employee'] ?? 1) / 100, 0);
        $result['kesehatan'] = ['basis' => $kesBasis, 'company' => $kesCompany, 'employee' => $kesEmployee];
        $companyTotal += $kesCompany;
        $employeeTotal += $kesEmployee;

        // BPJS JHT
        $jhtCompany = round($basicSalary * ($this->rates['jht_rate']['company'] ?? 3.7) / 100, 0);
        $jhtEmployee = round($basicSalary * ($this->rates['jht_rate']['employee'] ?? 2) / 100, 0);
        $result['jht'] = ['basis' => $basicSalary, 'company' => $jhtCompany, 'employee' => $jhtEmployee];
        $companyTotal += $jhtCompany;
        $employeeTotal += $jhtEmployee;

        // BPJS JKK (company only)
        $jkkCompany = round($basicSalary * ($this->rates['jkk_rate']['company'] ?? 0.24) / 100, 0);
        $result['jkk'] = ['basis' => $basicSalary, 'company' => $jkkCompany, 'employee' => 0];
        $companyTotal += $jkkCompany;

        // BPJS JKM (company only)
        $jkmCompany = round($basicSalary * ($this->rates['jkm_rate']['company'] ?? 0.3) / 100, 0);
        $result['jkm'] = ['basis' => $basicSalary, 'company' => $jkmCompany, 'employee' => 0];
        $companyTotal += $jkmCompany;

        // BPJS JP
        $jpCap = $this->caps['jp_cap']['salary_cap'] ?? 10042300;
        $jpBasis = min($basicSalary, $jpCap);
        $jpCompany = round($jpBasis * ($this->rates['jp_rate']['company'] ?? 2) / 100, 0);
        $jpEmployee = round($jpBasis * ($this->rates['jp_rate']['employee'] ?? 1) / 100, 0);
        $result['jp'] = ['basis' => $jpBasis, 'company' => $jpCompany, 'employee' => $jpEmployee];
        $companyTotal += $jpCompany;
        $employeeTotal += $jpEmployee;

        $result['company_total'] = $companyTotal;
        $result['employee_total'] = $employeeTotal;
        $result['grand_total'] = $companyTotal + $employeeTotal;

        return $result;
    }
}
