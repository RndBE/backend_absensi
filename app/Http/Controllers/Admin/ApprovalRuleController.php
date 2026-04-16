<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use Illuminate\Http\Request;

class ApprovalRuleController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $activeType = $request->type ?? 'leave';

        $types = [
            'leave' => 'Cuti',
            'overtime' => 'Lembur',
            'attendance' => 'Presensi',
            'budget' => 'Anggaran',
            'travel_report' => 'LHP',
        ];

        // Get all employees with their approval chains for the active type
        $employees = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->with('department:id,name')
            ->orderBy('department_id')
            ->orderBy('full_name')
            ->get();

        // Get all chains for active type in this company
        $allChains = EmployeeApprover::whereIn('employee_id', $employees->pluck('id'))
            ->where('request_type', $activeType)
            ->with('approver:id,full_name,position,job_level')
            ->orderBy('step_order')
            ->get()
            ->groupBy('employee_id');

        // Stats
        $configured = $allChains->count();
        $unconfigured = $employees->count() - $configured;

        return view('admin.approval-rules.index', compact(
            'employees', 'allChains', 'types', 'activeType', 'configured', 'unconfigured'
        ));
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'integer|exists:employees,id',
            'apply_types' => 'required|array|min:1',
            'apply_types.*' => 'in:leave,overtime,attendance,budget,travel_report',
            'approver_ids' => 'required|array|min:1',
            'approver_ids.*' => 'integer|exists:employees,id',
        ]);

        $count = 0;
        foreach ($request->employee_ids as $employeeId) {
            foreach ($request->apply_types as $type) {
                EmployeeApprover::saveChain(
                    (int) $employeeId,
                    $type,
                    $request->approver_ids
                );
                $count++;
            }
        }

        $empCount = count($request->employee_ids);
        $typeCount = count($request->apply_types);

        return redirect()->route('admin.approval-rules.index', ['type' => $request->apply_types[0] ?? 'leave'])
            ->with('success', "Berhasil menerapkan approval chain ke {$empCount} karyawan × {$typeCount} tipe pengajuan.");
    }
}
