<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollRunDetail;
use App\Services\BpjsCalculator;
use App\Support\PayslipFilename;
use App\Support\PayslipLoanSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $employee = $request->user();

        $query = PayrollRunDetail::with([
            'payrollRun:id,period,status',
        ])->where('employee_id', $employee->id)
          ->whereHas('payrollRun', function ($q) {
              $q->whereIn('status', ['published', 'locked']);
          });

        if ($request->period) {
            $query->whereHas('payrollRun', function ($q) use ($request) {
                $q->where('period', $request->period);
            });
        }

        $payslips = $query->orderByDesc('id')->get()->map(function ($detail) {
            return [
                'id' => $detail->id,
                'period' => $detail->payrollRun->period,
                'basic_salary' => (float) $detail->basic_salary,
                'total_earning' => (float) $detail->total_earning,
                'total_deduction' => (float) $detail->total_deduction,
                'net_salary' => (float) $detail->net_salary,
                'status' => $detail->payrollRun->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $payslips,
        ]);
    }

    public function show(Request $request, $id)
    {
        $employee = $request->user()->load('company');

        $detail = PayrollRunDetail::with(['payrollRun:id,period,status'])
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', function ($q) {
                $q->whereIn('status', ['published', 'locked']);
            })
            ->findOrFail($id);

        $earnings = [];
        $deductions = [];

        if ($detail->components) {
            foreach ($detail->components as $comp) {
                if ($comp['type'] === 'earning') {
                    $earnings[] = ['name' => $comp['name'], 'amount' => (float) $comp['amount']];
                } else {
                    $deductions[] = [
                        'name' => $comp['name'],
                        'amount' => (float) $comp['amount'],
                        'loan' => PayslipLoanSummary::forComponent($comp),
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $detail->id,
                'period' => $detail->payrollRun->period,
                'company_name' => $employee->company?->name ?? '-',
                'employee_name' => $employee->full_name,
                'employee_code' => $employee->employee_code,
                'department' => $employee->department->name ?? '-',
                'position' => $employee->position ?? '-',
                'basic_salary' => (float) $detail->basic_salary,
                'earnings' => $earnings,
                'deductions' => $deductions,
                'loan_summary' => PayslipLoanSummary::fromComponents($detail->components),
                'total_earning' => (float) $detail->total_earning,
                'total_deduction' => (float) $detail->total_deduction,
                'net_salary' => (float) $detail->net_salary,
            ],
        ]);
    }

    public function downloadPdf(Request $request, $id)
    {
        $employee = $request->user();

        $detail = PayrollRunDetail::with([
            'employee',
            'employee.department:id,name',
            'employee.activePayroll',
            'payrollRun:id,period,status',
        ])->where('employee_id', $employee->id)
          ->whereHas('payrollRun', function ($q) {
              $q->whereIn('status', ['published', 'locked']);
          })
          ->findOrFail($id);

        $company = Company::find($detail->employee->company_id);
        $bpjsData = $this->buildBpjsData($detail);
        $loanSummary = PayslipLoanSummary::fromComponents($detail->components);
        $logoBase64 = $this->buildLogoBase64($company);

        $pdf = Pdf::loadView('admin.payslips.pdf', compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary'));
        $pdf->setPaper('A4', 'portrait');

        $filename = PayslipFilename::make($detail->employee->employee_code, $detail->payrollRun->period);

        return $pdf->download($filename);
    }

    private function buildLogoBase64(?Company $company): ?string
    {
        if (! $company?->logo) {
            return null;
        }

        $logoPath = storage_path('app/public/' . $company->logo);
        if (! file_exists($logoPath)) {
            return null;
        }

        $logoMime = mime_content_type($logoPath);

        return 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    private function buildBpjsData(PayrollRunDetail $detail): array
    {
        $payroll = $detail->employee->activePayroll;
        if (! $payroll) {
            return ['items' => [], 'total' => 0];
        }

        $periodDate = Carbon::parse($detail->payrollRun->period . '-01');
        $bpjs = (new BpjsCalculator($periodDate->format('Y-m-d')))->calculate((float) $payroll->basic_salary);
        $bpjs = \App\Support\PayrollBpjs::applyEligibility($bpjs, $payroll, $periodDate);
        // Karyawan resign: JKK/JKM/JHT tetap DITAMPILKAN sebagai Rp 0 (bukan disembunyikan).
        $resigned = \App\Support\PayrollBpjs::isResignedInMonth($detail->employee, $periodDate);

        $items = \App\Support\PayrollBpjs::benefitItems($bpjs, $resigned);

        return [
            'raw' => $bpjs,
            'items' => $items,
            'total' => collect($items)->sum('amount'),
        ];
    }
}
