<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Support\ApprovalChainInput;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeApproverController extends Controller
{
    public function index($employeeId)
    {
        $admin = Employee::find(session('admin_id'));

        $chains = [];
        foreach (['leave', 'overtime', 'attendance', 'budget', 'travel_report', 'lpj'] as $type) {
            $chains[$type] = EmployeeApprover::getChain($employeeId, $type);
        }

        return response()->json(['success' => true, 'data' => $chains]);
    }

    public function store(Request $request, $employeeId)
    {
        $admin = Employee::find(session('admin_id'));
        $request->merge([
            'chains' => ApprovalChainInput::chains($request->input('chains')),
        ]);

        $validApprover = Rule::exists('employees', 'id')
            ->where(fn ($query) => $query
                ->where('company_id', $admin->company_id)
                ->where('is_active', true));

        $validated = $request->validate([
            'chains' => 'required|array',
            'chains.leave' => 'nullable|array',
            'chains.leave.*' => ['integer', $validApprover],
            'chains.overtime' => 'nullable|array',
            'chains.overtime.*' => ['integer', $validApprover],
            'chains.attendance' => 'nullable|array',
            'chains.attendance.*' => ['integer', $validApprover],
            'chains.budget' => 'nullable|array',
            'chains.budget.*' => ['integer', $validApprover],
            'chains.travel_report' => 'nullable|array',
            'chains.travel_report.*' => ['integer', $validApprover],
            'chains.lpj' => 'nullable|array',
            'chains.lpj.*' => ['integer', $validApprover],
        ]);

        $employee = Employee::where('company_id', $admin->company_id)->findOrFail($employeeId);
        $chains = $validated['chains'];

        foreach (['leave', 'overtime', 'attendance', 'budget', 'travel_report', 'lpj'] as $type) {
            $approverIds = $chains[$type] ?? [];
            EmployeeApprover::saveChain($employee->id, $type, $approverIds);
        }

        return redirect()->route('admin.employees.edit', $employeeId)
            ->with('success', 'Pengaturan approval berhasil disimpan.');
    }
}
