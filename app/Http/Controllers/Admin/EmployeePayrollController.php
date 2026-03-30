<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePayrollComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollGroup;
use Illuminate\Http\Request;

class EmployeePayrollController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->with(['department:id,name', 'activePayroll.payrollGroup']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('full_name', 'like', "%{$request->search}%")
                  ->orWhere('employee_code', 'like', "%{$request->search}%");
            });
        }

        if ($request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        $employees = $query->orderBy('full_name')->paginate(20)->withQueryString();

        $departments = \App\Models\Department::where('company_id', $admin->company_id)->orderBy('name')->get(['id', 'name']);

        return view('admin.employee-payrolls.index', compact('employees', 'departments'));
    }

    public function edit($id)
    {
        $employee = Employee::with([
            'department:id,name',
            'activePayroll.payrollGroup',
            'payroll' => fn($q) => $q->orderBy('effective_date', 'desc'),
            'payrollComponents' => fn($q) => $q->where('is_active', true)->with('component'),
        ])->findOrFail($id);

        $groups = PayrollGroup::where('is_active', true)->orderBy('name')->get();
        $components = PayrollComponent::where('is_active', true)->orderBy('type')->orderBy('name')->get();

        return view('admin.employee-payrolls.edit', compact('employee', 'groups', 'components'));
    }

    public function updatePayroll(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'payroll_group_id' => 'nullable|exists:payroll_groups,id',
            'basic_salary' => 'required|numeric|min:0',
            'payment_schedule' => 'required|in:monthly,biweekly,weekly',
            'payment_method' => 'required|in:transfer,cash',
            'bank_name' => 'nullable|string',
            'bank_account_number' => 'nullable|string',
            'bank_account_name' => 'nullable|string',
            'npwp' => 'nullable|string',
            'ptkp_status' => 'nullable|string',
            'bpjs_kesehatan' => 'nullable|string',
            'bpjs_ketenagakerjaan' => 'nullable|string',
            'effective_date' => 'required|date',
        ]);

        // Deactivate old payroll records
        EmployeePayroll::where('employee_id', $id)->where('is_active', true)->update(['is_active' => false]);

        // Create new payroll record
        EmployeePayroll::create(array_merge(
            $request->only([
                'payroll_group_id', 'basic_salary', 'payment_schedule', 'payment_method',
                'bank_name', 'bank_account_number', 'bank_account_name',
                'npwp', 'ptkp_status', 'bpjs_kesehatan', 'bpjs_ketenagakerjaan',
                'effective_date',
            ]),
            ['employee_id' => $id, 'is_active' => true]
        ));

        return redirect()->route('admin.employee-payrolls.edit', $id)->with('success', 'Data payroll karyawan berhasil disimpan.');
    }

    public function assignComponent(Request $request, $id)
    {
        $request->validate([
            'payroll_component_id' => 'required|exists:payroll_components,id',
            'amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        EmployeePayrollComponent::create([
            'employee_id' => $id,
            'payroll_component_id' => $request->payroll_component_id,
            'amount' => $request->amount,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
        ]);

        return redirect()->route('admin.employee-payrolls.edit', $id)->with('success', 'Komponen berhasil di-assign.');
    }

    public function updateComponent(Request $request, $employeeId, $componentId)
    {
        $epc = EmployeePayrollComponent::where('employee_id', $employeeId)->findOrFail($componentId);

        $request->validate([
            'amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $epc->update($request->only('amount', 'start_date', 'end_date'));
        return redirect()->route('admin.employee-payrolls.edit', $employeeId)->with('success', 'Komponen berhasil diperbarui.');
    }

    public function toggleComponent($employeeId, $componentId)
    {
        $epc = EmployeePayrollComponent::where('employee_id', $employeeId)->findOrFail($componentId);
        $epc->update(['is_active' => !$epc->is_active]);
        return redirect()->route('admin.employee-payrolls.edit', $employeeId)->with('success', 'Status komponen berhasil diubah.');
    }

    public function bulkAssign(Request $request)
    {
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
            'payroll_component_id' => 'required|exists:payroll_components,id',
            'amount' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $count = 0;
        foreach ($request->employee_ids as $empId) {
            EmployeePayrollComponent::create([
                'employee_id' => $empId,
                'payroll_component_id' => $request->payroll_component_id,
                'amount' => $request->amount,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);
            $count++;
        }

        return back()->with('success', "Komponen berhasil di-assign ke {$count} karyawan.");
    }
}
