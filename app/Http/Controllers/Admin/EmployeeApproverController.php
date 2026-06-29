<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use Illuminate\Http\Request;

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
        $request->validate([
            'chains' => 'required|array',
            'chains.leave' => 'nullable|array',
            'chains.leave.*' => 'integer|exists:employees,id',
            'chains.overtime' => 'nullable|array',
            'chains.overtime.*' => 'integer|exists:employees,id',
            'chains.attendance' => 'nullable|array',
            'chains.attendance.*' => 'integer|exists:employees,id',
            'chains.budget' => 'nullable|array',
            'chains.budget.*' => 'integer|exists:employees,id',
            'chains.travel_report' => 'nullable|array',
            'chains.travel_report.*' => 'integer|exists:employees,id',
            'chains.lpj' => 'nullable|array',
            'chains.lpj.*' => 'integer|exists:employees,id',
        ]);

        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)->findOrFail($employeeId);
        $chains = $request->chains;

        foreach (['leave', 'overtime', 'attendance', 'budget', 'travel_report', 'lpj'] as $type) {
            $approverIds = $chains[$type] ?? [];
            // Filter out empty/null values
            $approverIds = array_values(array_filter($approverIds, fn($id) => !empty($id)));
            EmployeeApprover::saveChain($employee->id, $type, $approverIds);
        }

        return redirect()->route('admin.employees.edit', $employeeId)
            ->with('success', 'Pengaturan approval berhasil disimpan.');
    }
}
