<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PayrollRunDetail;
use App\Services\BpjsCalculator;
use App\Support\DownloadFilename;
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
                    $deductions[] = ['name' => $comp['name'], 'amount' => (float) $comp['amount']];
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
            'employee:id,full_name,employee_code,company_id,department_id,position',
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

        $filename = DownloadFilename::sanitize('Payslip_' . $detail->employee->employee_code . '_' . $detail->payrollRun->period . '.pdf');

        return $pdf->download($filename);
    }

    private function buildBpjsData(PayrollRunDetail $detail): array
    {
        $payroll = $detail->employee->activePayroll;
        if (!$payroll) return ['items' => [], 'total' => 0];

        $periodDate = Carbon::parse($detail->payrollRun->period . '-01');
        $calc = new BpjsCalculator($periodDate->format('Y-m-d'));
        $bpjs = $calc->calculate((float) $payroll->basic_salary);

        $items = [
            [
                'label' => 'Rate BPJS Kesehatan',
                'amount' => $bpjs['kesehatan']['basis'],
                'is_basis' => true,
            ],
        ];

        $tkHasContrib = $bpjs['jht']['company'] + $bpjs['jkk']['company'] + $bpjs['jkm']['company'] + $bpjs['jp']['company'] > 0;
        if ($tkHasContrib) {
            $items[] = [
                'label' => 'Rate BPJS Ketenagakerjaan',
                'amount' => $bpjs['jht']['basis'],
                'is_basis' => true,
            ];
        }

        foreach ([
            'jkk' => 'JKK (Jaminan Kecelakaan Kerja)',
            'jkm' => 'JKM (Jaminan Kematian)',
            'jht' => 'JHT Perusahaan (Jaminan Hari Tua)',
            'jp' => 'JP Perusahaan (Jaminan Pensiun)',
            'kesehatan' => 'BPJS Kesehatan Perusahaan',
        ] as $key => $label) {
            if ($bpjs[$key]['company'] > 0) {
                $items[] = ['label' => $label, 'amount' => $bpjs[$key]['company'], 'is_basis' => false];
            }
        }

        return [
            'raw' => $bpjs,
            'items' => $items,
            'total' => collect($items)->sum('amount'),
        ];
    }
}
