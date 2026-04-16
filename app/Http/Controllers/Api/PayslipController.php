<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayrollRunDetail;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'employee:id,full_name,employee_code,department_id,position',
            'employee.department:id,name',
            'payrollRun:id,period,status',
        ])->where('employee_id', $employee->id)
          ->whereHas('payrollRun', function ($q) {
              $q->whereIn('status', ['published', 'locked']);
          })
          ->findOrFail($id);

        $pdf = Pdf::loadView('admin.payslips.pdf', compact('detail'));
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Payslip_' . $detail->employee->employee_code . '_' . $detail->payrollRun->period . '.pdf';

        return $pdf->download($filename);
    }
}
