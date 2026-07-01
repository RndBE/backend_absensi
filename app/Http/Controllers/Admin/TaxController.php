<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TaxSetting;
use App\Models\BpjsSetting;
use App\Models\TaxCertificate;
use App\Models\PayrollRunDetail;
use App\Services\Pph21Calculator;
use App\Services\BpjsCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    // === Tax Settings ===
    public function settings()
    {
        $taxSettings = TaxSetting::where('is_active', true)->orderBy('key')->orderByDesc('effective_date')->get();
        $bpjsSettings = BpjsSetting::where('is_active', true)->orderBy('key')->orderByDesc('effective_date')->get();
        return view('admin.tax.settings', compact('taxSettings', 'bpjsSettings'));
    }

    public function updateSetting(Request $request, $id)
    {
        $setting = TaxSetting::findOrFail($id);
        $type = $request->setting_type;

        if ($type === 'brackets') {
            $brackets = [];
            foreach ($request->brackets as $b) {
                $brackets[] = [
                    'min' => (int) $b['min'],
                    'max' => $b['max'] !== '' && $b['max'] !== null ? (int) $b['max'] : null,
                    'rate' => (float) $b['rate'],
                ];
            }
            $setting->update(['value' => $brackets]);
        } elseif ($type === 'ptkp') {
            $ptkp = [];
            foreach ($request->ptkp as $status => $val) {
                $ptkp[$status] = (int) $val;
            }
            $setting->update(['value' => $ptkp]);
        } elseif ($type === 'biaya_jabatan') {
            $setting->update(['value' => [
                'percentage' => (float) $request->bj_percentage,
                'max_monthly' => (int) $request->bj_max_monthly,
                'max_annual' => (int) $request->bj_max_annual,
            ]]);
        }

        return back()->with('success', 'Setting berhasil diupdate.');
    }

    public function updateBpjsSetting(Request $request, $id)
    {
        $setting = BpjsSetting::findOrFail($id);
        $setting->update(['value' => json_decode($request->value, true), 'npp' => $request->npp]);
        return back()->with('success', 'BPJS setting berhasil diupdate.');
    }

    public function updateBpjsAll(Request $request)
    {
        foreach ($request->bpjs as $key => $data) {
            // Update rate
            $setting = BpjsSetting::find($data['id']);
            if ($setting) {
                $setting->update([
                    'value' => [
                        'company' => (float) $data['company'],
                        'employee' => (float) $data['employee'],
                    ],
                    'npp' => $request->npp,
                ]);
            }
            // Update cap if exists
            if (!empty($data['cap_id']) && isset($data['salary_cap'])) {
                $cap = BpjsSetting::find($data['cap_id']);
                if ($cap) {
                    $cap->update(['value' => ['salary_cap' => (int) $data['salary_cap']]]);
                }
            }
        }

        return back()->with('success', 'Semua tarif BPJS berhasil diupdate.');
    }

    // === Salary Tax Calculator / Simulator ===
    public function simulator()
    {
        return view('admin.tax.simulator', [
            'period_month' => now()->format('Y-m'),
        ]);
    }

    public function simulate(Request $request)
    {
        $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            'ptkp_status' => 'required|string',
            'tax_method' => 'required|in:gross,gross_up,nett',
            'period_month' => 'nullable|date_format:Y-m',
        ]);

        $periodMonth = $request->input('period_month', now()->format('Y-m'));
        $calc = new Pph21Calculator($periodMonth.'-01');
        $result = $calc->simulate(
            (float) $request->gross_salary,
            $request->ptkp_status,
            $request->tax_method
        );

        return view('admin.tax.simulator', compact('result') + $request->only(['gross_salary', 'ptkp_status', 'tax_method']) + [
            'period_month' => $periodMonth,
        ]);
    }

    // === Bukti Potong (1721-A1) ===
    public function buktiPotong(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? date('Y');

        $certificates = TaxCertificate::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('tax_year', $year)
            ->with('employee.activePayroll')
            ->orderBy('certificate_number')
            ->get();

        $employees = Employee::where('company_id', $admin->company_id)
            ->where(function ($q) use ($year) {
                $q->where('is_active', true)
                    ->orWhereHas('payrollRunDetails.payrollRun', function ($q) use ($year) {
                        $q->where('period', 'like', $year.'-%')
                            ->whereIn('status', ['finalized', 'published', 'locked']);
                    });
            })
            ->orderBy('full_name')
            ->get();

        return view('admin.tax.bukti-potong', compact('certificates', 'employees', 'year'));
    }

    public function generateBuktiPotong(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'tax_year' => 'required|integer|min:2020',
        ]);

        $empId = $request->employee_id;
        $year = $request->tax_year;
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::with('activePayroll')
            ->where('company_id', $admin->company_id)
            ->findOrFail($empId);

        $existing = TaxCertificate::where('employee_id', $empId)
            ->where('tax_year', $year)
            ->first();

        if ($existing?->status === 'final') {
            return back()->with('error', 'Bukti potong sudah final dan tidak bisa digenerate ulang.');
        }

        // Get all payroll run details for this employee in the year
        $details = PayrollRunDetail::where('employee_id', $empId)
            ->whereHas('payrollRun', function ($q) use ($year) {
                $q->where('period', 'like', $year . '-%')
                  ->whereIn('status', ['finalized', 'published', 'locked']);
            })
            ->with('payrollRun')
            ->get();

        if ($details->isEmpty()) {
            return back()->with('error', 'Tidak ada data payroll finalized untuk tahun ' . $year);
        }

        $grossAnnual = 0;
        $taxAnnual = 0;
        $bpjsAnnual = 0;
        $monthlyDetails = [];

        foreach ($details as $detail) {
            $period = $detail->payrollRun->period;
            $comps = is_array($detail->components) ? $detail->components : json_decode($detail->components, true);

            $tax = 0;
            $bpjs = 0;
            $taxComponents = [];
            $bpjsComponents = [];
            foreach ($comps as $comp) {
                $amount = (float) ($comp['amount'] ?? 0);
                if ($this->isPph21Deduction($comp)) {
                    $tax += $amount;
                    $taxComponents[] = [
                        'name' => $comp['name'] ?? 'PPh 21',
                        'amount' => $amount,
                    ];
                }
                if ($this->isEmployeeBpjsDeduction($comp)) {
                    $bpjs += $amount;
                    $bpjsComponents[] = [
                        'name' => $comp['name'] ?? 'BPJS Karyawan',
                        'amount' => $amount,
                    ];
                }
            }

            $grossAnnual += (float) $detail->total_earning;
            $taxAnnual += $tax;
            $bpjsAnnual += $bpjs;
            $monthlyDetails[$period] = [
                'gross' => (float) $detail->total_earning,
                'tax' => $tax,
                'bpjs' => $bpjs,
                'net' => (float) $detail->net_salary,
                'tax_components' => $taxComponents,
                'bpjs_components' => $bpjsComponents,
            ];
        }

        $certNumber = '1.1-' . str_pad($empId, 6, '0', STR_PAD_LEFT) . '-' . $year;
        $certificateDetails = [
            'employee' => [
                'nik' => $employee->nik,
                'npwp' => $employee->activePayroll?->npwp ?: $employee->npwp_16 ?: $employee->npwp_15,
                'ptkp' => $employee->activePayroll?->ptkp_status ?: $employee->ptkp,
                'position' => $employee->position,
                'join_date' => optional($employee->join_date)->format('Y-m-d'),
                'resign_date' => optional($employee->resign_date)->format('Y-m-d'),
            ],
            'tax' => [
                'object_code' => '21-100-01',
                'year' => (int) $year,
                'period_start' => array_key_first($monthlyDetails),
                'period_end' => array_key_last($monthlyDetails),
            ],
            'months' => $monthlyDetails,
        ];

        $cert = TaxCertificate::updateOrCreate(
            ['employee_id' => $empId, 'tax_year' => $year],
            [
                'certificate_number' => $certNumber,
                'gross_annual' => $grossAnnual,
                'tax_annual' => $taxAnnual,
                'bpjs_annual' => $bpjsAnnual,
                'nett_annual' => $grossAnnual - $taxAnnual - $bpjsAnnual,
                'monthly_details' => $certificateDetails,
                'status' => 'draft',
            ]
        );

        return back()->with('success', "Bukti potong {$certNumber} berhasil digenerate.");
    }

    public function showBuktiPotong($id)
    {
        $certificate = $this->findCompanyCertificate($id);

        return view('admin.tax.bukti-potong-show', compact('certificate'));
    }

    public function finalizeBuktiPotong($id)
    {
        $certificate = $this->findCompanyCertificate($id);
        $certificate->update(['status' => 'final']);

        return back()->with('success', "Bukti potong {$certificate->certificate_number} berhasil difinalisasi.");
    }

    public function downloadBuktiPotong($id)
    {
        $certificate = $this->findCompanyCertificate($id);
        $company = $certificate->employee->company;

        $pdf = Pdf::loadView('admin.tax.bukti-potong-pdf', compact('certificate', 'company'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("Bukti_Potong_{$certificate->certificate_number}.pdf");
    }

    // === Tax Recalculate ===
    public function recalculate(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'period' => 'required|string|size:7',
        ]);

        $detail = PayrollRunDetail::where('employee_id', $request->employee_id)
            ->whereHas('payrollRun', fn($q) => $q->where('period', $request->period)->where('status', 'draft'))
            ->first();

        if (!$detail) {
            return back()->with('error', 'Payroll detail tidak ditemukan atau bukan draft.');
        }

        // Recalculate tax from current components
        $comps = is_array($detail->components) ? $detail->components : json_decode($detail->components, true);
        $bruto = 0;
        $filteredComps = [];

        foreach ($comps as $comp) {
            // Skip old tax components
            if (str_contains($comp['name'] ?? '', 'PPh 21') || str_contains($comp['name'] ?? '', 'Tunjangan Pajak') || str_contains($comp['name'] ?? '', 'BPJS')) {
                continue;
            }
            $filteredComps[] = $comp;
            if (($comp['type'] ?? '') === 'earning') {
                $bruto += (float) ($comp['amount'] ?? 0);
            }
        }

        $payroll = \App\Models\EmployeePayroll::where('employee_id', $request->employee_id)->where('is_active', true)->first();
        if (!$payroll) return back()->with('error', 'Data payroll tidak ditemukan.');

        $ptkpStatus = $payroll->ptkp_status ?? 'TK/0';
        $taxMethod = $payroll->tax_method ?? 'gross_up';

        $bpjsCalc = new BpjsCalculator();
        $bpjs = $bpjsCalc->calculate((float) $payroll->basic_salary);

        $pph21Calc = new Pph21Calculator($request->period.'-01');
        $periodMonth = (int) substr($request->period, 5, 2);

        if ($periodMonth === 12) {
            $year = (int) substr($request->period, 0, 4);
            $prevDetails = PayrollRunDetail::whereHas('payrollRun', function ($q) use ($year) {
                $q->whereYear('period', $year)
                    ->whereMonth('period', '<=', 11)
                    ->where('status', '!=', 'draft');
            })->where('employee_id', $request->employee_id)->get();

            $brutoJanToNov = 0;
            $taxJanToNov = 0;
            foreach ($prevDetails as $prevDetail) {
                $brutoJanToNov += (float) $prevDetail->total_earning;
                $prevComps = is_array($prevDetail->components) ? $prevDetail->components : json_decode($prevDetail->components, true) ?? [];
                foreach ($prevComps as $prevComp) {
                    if (str_contains($prevComp['name'] ?? '', 'PPh') && ($prevComp['type'] ?? '') === 'deduction') {
                        $taxJanToNov += (float) ($prevComp['amount'] ?? 0);
                    }
                }
            }

            $tax = $pph21Calc->calculateDecember(
                brutoDecember: $bruto,
                brutoJanToNov: $brutoJanToNov,
                bpjsEmployeeMonthly: $bpjs['employee_total'],
                ptkpStatus: $ptkpStatus,
                taxMethod: $taxMethod,
                taxJanToNov: $taxJanToNov
            );
        } else {
            $tax = $pph21Calc->calculateMonthly($bruto, $ptkpStatus, $taxMethod, $bpjs['employee_total']);
        }

        $totalEarning = $bruto;
        $totalDeduction = 0;

        foreach ($filteredComps as $c) {
            if (($c['type'] ?? '') === 'deduction') $totalDeduction += (float) ($c['amount'] ?? 0);
        }

        // Re-add BPJS & PPh 21
        if ($bpjs['employee_total'] > 0) {
            $filteredComps[] = ['id' => null, 'name' => 'BPJS (Karyawan)', 'type' => 'deduction', 'category' => 'recurring', 'amount' => $bpjs['employee_total'], 'is_auto' => true, 'is_taxable' => false, 'detail' => 'Recalculated'];
            $totalDeduction += $bpjs['employee_total'];
        }

        if ($taxMethod === 'gross_up' && $tax['tunjangan_pajak'] > 0) {
            $filteredComps[] = ['id' => null, 'name' => 'Tunjangan Pajak (Gross Up)', 'type' => 'earning', 'category' => 'recurring', 'amount' => $tax['tunjangan_pajak'], 'is_auto' => true, 'is_taxable' => true, 'detail' => 'Recalculated'];
            $totalEarning += $tax['tunjangan_pajak'];
        }

        if ($tax['pph21_deduction'] > 0) {
            $filteredComps[] = ['id' => null, 'name' => 'PPh 21', 'type' => 'deduction', 'category' => 'recurring', 'amount' => $tax['pph21_deduction'], 'is_auto' => true, 'is_taxable' => false, 'detail' => 'Recalculated'];
            $totalDeduction += $tax['pph21_deduction'];
        }

        $detail->update([
            'total_earning' => $totalEarning,
            'total_deduction' => $totalDeduction,
            'net_salary' => $totalEarning - $totalDeduction,
            'components' => $filteredComps,
        ]);

        return back()->with('success', 'Tax recalculated.');
    }

    // === E-Filing Export (CSV) ===
    public function exportEfiling(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? date('Y');

        $certs = TaxCertificate::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('tax_year', $year)
            ->with('employee.activePayroll')
            ->get();

        $filename = "efiling_pph21_{$year}.csv";
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($certs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['No', 'NPWP', 'Nama', 'Kode Objek Pajak', 'Penghasilan Bruto', 'PPh Dipotong', 'Kode Negara']);

            foreach ($certs as $i => $cert) {
                $emp = $cert->employee;
                $payroll = $emp->activePayroll;
                $details = $cert->monthly_details ?? [];
                $tax = $details['tax'] ?? [];
                fputcsv($file, [
                    $i + 1,
                    $payroll?->npwp ?? $emp->npwp_16 ?? $emp->npwp_15 ?? $emp->nik ?? '-',
                    $emp->full_name,
                    $tax['object_code'] ?? '21-100-01', // Kode objek pajak pegawai tetap
                    $cert->gross_annual,
                    $cert->tax_annual,
                    'ID',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function findCompanyCertificate($id): TaxCertificate
    {
        $admin = Employee::find(session('admin_id'));

        return TaxCertificate::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->with(['employee.company', 'employee.activePayroll'])
            ->findOrFail($id);
    }

    private function isPph21Deduction(array $component): bool
    {
        return ($component['type'] ?? null) === 'deduction'
            && str_contains(strtolower($component['name'] ?? ''), 'pph 21');
    }

    private function isEmployeeBpjsDeduction(array $component): bool
    {
        if (($component['type'] ?? null) !== 'deduction') {
            return false;
        }

        $name = strtolower($component['name'] ?? '');
        if (str_contains($name, 'perusahaan')) {
            return false;
        }

        return str_contains($name, 'bpjs')
            || preg_match('/\b(jht|jkk|jkm|jp)\b/i', $name) === 1;
    }
}
