<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRunDetail;
use App\Models\PayrollRun;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = PayrollRunDetail::with([
            'employee:id,full_name,employee_code,department_id,position',
            'employee.department:id,name',
            'payrollRun:id,period,status,payroll_group_id',
            'payrollRun.payrollGroup:id,name',
        ])->whereHas('payrollRun', function ($q) {
            $q->whereIn('status', ['published', 'locked']);
        })->whereHas('employee', function ($q) use ($admin) {
            $q->where('company_id', $admin->company_id);
        });

        if ($request->search) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('full_name', 'like', "%{$request->search}%")
                  ->orWhere('employee_code', 'like', "%{$request->search}%");
            });
        }

        if ($request->period) {
            $query->whereHas('payrollRun', function ($q) use ($request) {
                $q->where('period', $request->period);
            });
        }

        $payslips = $query->orderByDesc('id')->paginate(20)->withQueryString();

        // Get available periods for filter
        $periods = PayrollRun::whereIn('status', ['published', 'locked'])
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('admin.payslips.index', compact('payslips', 'periods'));
    }

    public function show($id)
    {
        $detail = PayrollRunDetail::with([
            'employee:id,full_name,employee_code,department_id,position',
            'employee.department:id,name',
            'payrollRun:id,period,status,payroll_group_id',
            'payrollRun.payrollGroup:id,name',
        ])->findOrFail($id);

        return view('admin.payslips.show', compact('detail'));
    }

    public function downloadPdf($id)
    {
        $detail = PayrollRunDetail::with([
            'employee:id,full_name,employee_code,department_id,position',
            'employee.department:id,name',
            'payrollRun:id,period,status',
        ])->findOrFail($id);

        $pdf = Pdf::loadView('admin.payslips.pdf', compact('detail'));
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Payslip_' . $detail->employee->employee_code . '_' . $detail->payrollRun->period . '.pdf';

        return $pdf->download($filename);
    }
}
