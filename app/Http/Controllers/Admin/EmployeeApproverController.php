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
        foreach (['leave', 'overtime', 'attendance'] as $type) {
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
        ]);

        $employee = Employee::findOrFail($employeeId);
        $chains = $request->chains;

        foreach (['leave', 'overtime', 'attendance'] as $type) {
            $approverIds = $chains[$type] ?? [];
            // Filter out empty/null values
            $approverIds = array_values(array_filter($approverIds, fn($id) => !empty($id)));
            EmployeeApprover::saveChain($employee->id, $type, $approverIds);
        }

        return redirect()->route('admin.employees.edit', $employeeId)
            ->with('success', 'Pengaturan approval berhasil disimpan.');
    }
}
