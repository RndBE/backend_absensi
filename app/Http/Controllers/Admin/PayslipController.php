<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRunDetail;
use App\Models\PayrollRun;
use App\Models\Company;
use App\Services\BpjsCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = PayrollRunDetail::with([
            'employee:id,full_name,employee_code,department_id,position',
            'employee.department:id,name',
            'payrollRun:id,period,status',
        ])->whereHas('payrollRun', function ($q) {
            $q->whereIn('status', ['published', 'locked']);
        })->whereHas('employee', function ($q) use ($admin) {
            $q->where('company_id', $admin->company_id);
        });

        if ($request->period) {
            $query->whereHas('payrollRun', function ($q) use ($request) {
                $q->where('period', $request->period);
            });
        }

        $payslips = $query->orderByDesc('id')->get();

        $periods = PayrollRun::whereIn('status', ['published', 'locked'])
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('admin.payslips.index', compact('payslips', 'periods'));
    }

    public function show($id)
    {
        $detail = PayrollRunDetail::with([
            'employee',
            'employee.department:id,name',
            'employee.activePayroll',
            'payrollRun:id,period,status',
        ])->findOrFail($id);

        $company  = Company::find($detail->employee->company_id);
        $bpjsData = $this->buildBpjsData($detail);

        return view('admin.payslips.show', compact('detail', 'company', 'bpjsData'));
    }

    public function downloadPdf($id)
    {
        $detail = PayrollRunDetail::with([
            'employee',
            'employee.department:id,name',
            'employee.activePayroll',
            'payrollRun:id,period,status',
        ])->findOrFail($id);

        $company  = Company::find($detail->employee->company_id);
        $bpjsData = $this->buildBpjsData($detail);

        // Convert logo to base64 for DomPDF inline embedding
        $logoBase64 = null;
        if ($company && $company->logo) {
            $logoPath = storage_path('app/public/' . $company->logo);
            if (file_exists($logoPath)) {
                $logoMime = mime_content_type($logoPath);
                $logoBase64 = 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath));
            }
        }

        $pdf = Pdf::loadView('admin.payslips.pdf', compact('detail', 'company', 'logoBase64', 'bpjsData'));
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Payslip_' . $detail->employee->employee_code . '_' . $detail->payrollRun->period . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Build structured BPJS benefit data for the payslip view.
     * Only includes programs with non-zero amounts (respects rate settings in DB).
     */
    private function buildBpjsData(PayrollRunDetail $detail): array
    {
        $payroll = $detail->employee->activePayroll;
        if (!$payroll) return ['items' => [], 'total' => 0];

        $periodDate = Carbon::parse($detail->payrollRun->period . '-01');
        $calc       = new BpjsCalculator($periodDate->format('Y-m-d'));
        $bpjs       = $calc->calculate((float) $payroll->basic_salary);

        $items = [];

        // Basis rows (gaji yang dipakai sebagai dasar perhitungan)
        $items[] = [
            'label'    => 'Rate BPJS Kesehatan',
            'amount'   => $bpjs['kesehatan']['basis'],
            'is_basis' => true,
        ];

        // Only show ketenagakerjaan basis if any program has contribution
        $tkHasContrib = $bpjs['jht']['company'] + $bpjs['jkk']['company'] + $bpjs['jkm']['company'] + $bpjs['jp']['company'] > 0;
        if ($tkHasContrib) {
            $items[] = [
                'label'    => 'Rate BPJS Ketenagakerjaan',
                'amount'   => $bpjs['jht']['basis'],
                'is_basis' => true,
            ];
        }

        // Company contributions — only if non-zero
        if ($bpjs['jkk']['company'] > 0) {
            $items[] = ['label' => 'JKK (Jaminan Kecelakaan Kerja)', 'amount' => $bpjs['jkk']['company'], 'is_basis' => false];
        }
        if ($bpjs['jkm']['company'] > 0) {
            $items[] = ['label' => 'JKM (Jaminan Kematian)', 'amount' => $bpjs['jkm']['company'], 'is_basis' => false];
        }
        if ($bpjs['jht']['company'] > 0) {
            $items[] = ['label' => 'JHT Perusahaan (Jaminan Hari Tua)', 'amount' => $bpjs['jht']['company'], 'is_basis' => false];
        }
        if ($bpjs['jp']['company'] > 0) {
            $items[] = ['label' => 'JP Perusahaan (Jaminan Pensiun)', 'amount' => $bpjs['jp']['company'], 'is_basis' => false];
        }
        if ($bpjs['kesehatan']['company'] > 0) {
            $items[] = ['label' => 'BPJS Kesehatan Perusahaan', 'amount' => $bpjs['kesehatan']['company'], 'is_basis' => false];
        }

        // Total = basis + all company contributions
        $total = collect($items)->sum('amount');

        return [
            'raw'   => $bpjs,
            'items' => $items,
            'total' => $total,
        ];
    }
}
