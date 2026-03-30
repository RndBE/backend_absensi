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
        return view('admin.tax.simulator');
    }

    public function simulate(Request $request)
    {
        $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            'ptkp_status' => 'required|string',
            'tax_method' => 'required|in:gross,gross_up,nett',
        ]);

        $calc = new Pph21Calculator();
        $result = $calc->simulate(
            (float) $request->gross_salary,
            $request->ptkp_status,
            $request->tax_method
        );

        return view('admin.tax.simulator', compact('result') + $request->only(['gross_salary', 'ptkp_status', 'tax_method']));
    }

    // === Bukti Potong (1721-A1) ===
    public function buktiPotong(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? date('Y');

        $certificates = TaxCertificate::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('tax_year', $year)
            ->with('employee')
            ->orderBy('certificate_number')
            ->get();

        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('full_name')->get();

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

        // Get all payroll run details for this employee in the year
        $details = PayrollRunDetail::where('employee_id', $empId)
            ->whereHas('payrollRun', function ($q) use ($year) {
                $q->where('period', 'like', $year . '-%')
                  ->where('status', 'finalized')
                  ->orWhere('status', 'published')
                  ->orWhere('status', 'locked');
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
            foreach ($comps as $comp) {
                if (str_contains($comp['name'] ?? '', 'PPh 21') && ($comp['type'] ?? '') === 'deduction') {
                    $tax += (float) ($comp['amount'] ?? 0);
                }
                if (str_contains($comp['name'] ?? '', 'BPJS (Karyawan)')) {
                    $bpjs += (float) ($comp['amount'] ?? 0);
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
            ];
        }

        $certNumber = '1.1-' . str_pad($empId, 6, '0', STR_PAD_LEFT) . '-' . $year;

        $cert = TaxCertificate::updateOrCreate(
            ['employee_id' => $empId, 'tax_year' => $year],
            [
                'certificate_number' => $certNumber,
                'gross_annual' => $grossAnnual,
                'tax_annual' => $taxAnnual,
                'bpjs_annual' => $bpjsAnnual,
                'nett_annual' => $grossAnnual - $taxAnnual - $bpjsAnnual,
                'monthly_details' => $monthlyDetails,
                'status' => 'draft',
            ]
        );

        return back()->with('success', "Bukti potong {$certNumber} berhasil digenerate.");
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

        $pph21Calc = new Pph21Calculator();
        $tax = $pph21Calc->calculateMonthly($bruto, $ptkpStatus, $taxMethod, $bpjs['employee_total']);

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
            ->with('employee')
            ->get();

        $filename = "efiling_pph21_{$year}.csv";
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => "attachment; filename=\"{$filename}\""];

        $callback = function () use ($certs) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['No', 'NPWP', 'Nama', 'Kode Objek Pajak', 'Penghasilan Bruto', 'PPh Dipotong', 'Kode Negara']);

            foreach ($certs as $i => $cert) {
                $emp = $cert->employee;
                $payroll = \App\Models\EmployeePayroll::where('employee_id', $emp->id)->where('is_active', true)->first();
                fputcsv($file, [
                    $i + 1,
                    $payroll->npwp ?? '-',
                    $emp->full_name,
                    '21-100-01', // Kode objek pajak pegawai tetap
                    $cert->gross_annual,
                    $cert->tax_annual,
                    'ID',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
