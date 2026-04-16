<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePayrollComponent;
use App\Models\PayrollComponent;
use Illuminate\Http\Request;

class PayrollComponentController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->type ?? 'all';
        $query = PayrollComponent::withCount('employeeComponents');

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $components = $query->orderBy('type')->orderBy('name')->get();
        return view('admin.payroll-components.index', compact('components', 'type'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:earning,deduction',
            'category'       => 'required|in:fixed,one-time,recurring',
            'default_amount' => 'required|numeric|min:0',
            'is_taxable'     => 'nullable|boolean',
        ]);

        PayrollComponent::create([
            'name'           => $request->name,
            'type'           => $request->type,
            'category'       => $request->category,
            'default_amount' => $request->default_amount,
            'is_taxable'     => $request->boolean('is_taxable'),
        ]);

        return back()->with('success', 'Komponen payroll berhasil dibuat.');
    }

    public function update(Request $request, $id)
    {
        $component = PayrollComponent::findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:earning,deduction',
            'category'       => 'required|in:fixed,one-time,recurring',
            'default_amount' => 'required|numeric|min:0',
            'is_taxable'     => 'nullable|boolean',
        ]);

        $component->update([
            'name'           => $request->name,
            'type'           => $request->type,
            'category'       => $request->category,
            'default_amount' => $request->default_amount,
            'is_taxable'     => $request->boolean('is_taxable'),
        ]);

        return back()->with('success', 'Komponen payroll berhasil diperbarui.');
    }

    public function toggle($id)
    {
        $component = PayrollComponent::findOrFail($id);
        $component->update(['is_active' => !$component->is_active]);
        return back()->with('success', 'Status komponen berhasil diubah.');
    }

    public function destroy($id)
    {
        $component = PayrollComponent::findOrFail($id);

        if ($component->employeeComponents()->exists()) {
            return back()->with('error', 'Tidak bisa hapus komponen yang sedang di-assign ke karyawan.');
        }

        $component->delete();
        return back()->with('success', 'Komponen payroll berhasil dihapus.');
    }

    // ─── Employee Assignment Management ───────────────────────────────────

    public function employees(Request $request, $id)
    {
        $component = PayrollComponent::findOrFail($id);

        // Already-assigned employee IDs
        $assignedIds = EmployeePayrollComponent::where('payroll_component_id', $id)
            ->pluck('employee_id')
            ->toArray();

        // Assigned list with search
        $assignedQuery = EmployeePayrollComponent::with('employee.department')
            ->where('payroll_component_id', $id);

        if ($request->search) {
            $assignedQuery->whereHas('employee', function ($q) use ($request) {
                $q->where('full_name', 'like', "%{$request->search}%")
                  ->orWhere('employee_code', 'like', "%{$request->search}%");
            });
        }

        $assignments = $assignedQuery->orderByDesc('is_active')->orderBy('id')->get();

        // Employees NOT yet assigned (for the add form)
        $unassigned = Employee::whereNotIn('id', $assignedIds)
            ->where('is_active', true)
            ->with('department:id,name')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'department_id']);

        return view('admin.payroll-components.employees', compact(
            'component', 'assignments', 'unassigned'
        ));
    }

    public function assignEmployee(Request $request, $id)
    {
        $component = PayrollComponent::findOrFail($id);

        $request->validate([
            'employee_ids'   => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'amount'         => 'nullable|numeric|min:0',
            'start_date'     => 'nullable|date',
        ]);

        $amount = $request->amount ?? $component->default_amount;
        $added  = 0;
        $skipped = 0;

        foreach ($request->employee_ids as $empId) {
            $exists = EmployeePayrollComponent::where('payroll_component_id', $id)
                ->where('employee_id', $empId)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            EmployeePayrollComponent::create([
                'payroll_component_id' => $id,
                'employee_id'          => $empId,
                'amount'               => $amount,
                'start_date'           => $request->start_date,
                'is_active'            => true,
            ]);
            $added++;
        }

        $msg = "Berhasil assign ke {$added} karyawan.";
        if ($skipped) $msg .= " {$skipped} karyawan dilewati (sudah ada).";

        return back()->with('success', $msg);
    }

    public function updateAssignment(Request $request, $id, $assignId)
    {
        $assignment = EmployeePayrollComponent::where('payroll_component_id', $id)
            ->findOrFail($assignId);

        $request->validate([
            'amount'     => 'required|numeric|min:0',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);

        $assignment->update([
            'amount'     => $request->amount,
            'start_date' => $request->filled('start_date') ? $request->start_date : $assignment->start_date,
            'end_date'   => $request->filled('end_date')   ? $request->end_date   : $assignment->end_date,
            'is_active'  => $request->boolean('is_active', $assignment->is_active),
        ]);

        return back()->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function removeAssignment($id, $assignId)
    {
        $assignment = EmployeePayrollComponent::where('payroll_component_id', $id)
            ->findOrFail($assignId);

        $assignment->delete();
        return back()->with('success', 'Karyawan berhasil dihapus dari komponen ini.');
    }
}
