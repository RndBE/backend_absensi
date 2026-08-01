<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Support\ApprovalChainInput;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'lpj' => 'LPJ',
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
        $admin = Employee::find(session('admin_id'));
        $request->merge([
            'approver_ids' => ApprovalChainInput::steps($request->input('approver_ids')),
        ]);

        $activeCompanyEmployee = fn () => Rule::exists('employees', 'id')
            ->where(fn ($query) => $query
                ->where('company_id', $admin->company_id)
                ->where('is_active', true));

        $validated = $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => ['integer', $activeCompanyEmployee()],
            'apply_types' => 'required|array|min:1',
            'apply_types.*' => 'in:leave,overtime,attendance,budget,travel_report,lpj',
            'approver_ids' => 'required|array|min:1',
            'approver_ids.*' => ['integer', $activeCompanyEmployee()],
        ]);

        $count = 0;
        foreach ($validated['employee_ids'] as $employeeId) {
            foreach ($validated['apply_types'] as $type) {
                EmployeeApprover::saveChain(
                    (int) $employeeId,
                    $type,
                    $validated['approver_ids']
                );
                $count++;
            }
        }

        $empCount = count($validated['employee_ids']);
        $typeCount = count($validated['apply_types']);

        return redirect()->route('admin.approval-rules.index', ['type' => $validated['apply_types'][0] ?? 'leave'])
            ->with('success', "Berhasil menerapkan approval chain ke {$empCount} karyawan × {$typeCount} tipe pengajuan.");
    }
}
