<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\Company;
use App\Models\Department;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $query = Employee::where('company_id', $admin->company_id)
            ->with(['department:id,name', 'workSchedule:id,name']);

        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->department_id) {
            // Include child departments when filtering by parent
            $deptIds = Department::where('id', $request->department_id)
                ->orWhere('parent_id', $request->department_id)
                ->pluck('id');
            $query->whereIn('department_id', $deptIds);
        }

        if ($request->status) {
            $query->where('employment_status', $request->status);
        }

        $employees = $query->orderBy('full_name')->paginate(15)->withQueryString();
        $departments = Department::where('company_id', $admin->company_id)->get();

        return view('admin.employees.index', compact('employees', 'departments'));
    }

    public function create()
    {
        $admin = Employee::find(session('admin_id'));
        $departments = Department::where('company_id', $admin->company_id)->get();
        $workSchedules = WorkSchedule::where('company_id', $admin->company_id)->get();
        $managers = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->orderBy('job_level')->orderBy('full_name')
            ->get();

        return view('admin.employees.create', compact('departments', 'workSchedules', 'managers'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'employee_code' => 'required|unique:employees',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|min:8',
            'department_id' => 'required|exists:departments,id',
            'work_schedule_id' => 'nullable|exists:work_schedules,id',
            'position' => 'nullable|string',
            'job_level' => 'nullable|integer',
            'employment_status' => 'required|in:permanent,contract,intern,probation',
            'join_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'role' => 'required|in:admin,manager,employee',
            'manager_id' => 'nullable|exists:employees,id',
            'approver_id' => 'nullable|exists:employees,id',
            'photo' => 'nullable|image|max:2048',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'religion' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:10',
            'ktp_address' => 'nullable|string',
            'residential_address' => 'nullable|string',
        ]);

        $data = array_merge($request->except('photo'), [
            'company_id' => $admin->company_id,
            'is_active' => true,
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('employees/photos', 'public');
        }

        Employee::create($data);

        return redirect()->route('admin.employees.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function show($id)
    {
        $employee = Employee::with(['department', 'workSchedule', 'manager'])->findOrFail($id);

        // Load approval chains
        $approvalChains = [];
        foreach (['leave', 'overtime', 'attendance'] as $type) {
            $approvalChains[$type] = EmployeeApprover::getChain($id, $type);
        }

        return view('admin.employees.show', compact('employee', 'approvalChains'));
    }

    public function edit($id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::findOrFail($id);
        $departments = Department::where('company_id', $admin->company_id)->get();
        $workSchedules = WorkSchedule::where('company_id', $admin->company_id)->get();
        $managers = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('id', '!=', $id)
            ->orderBy('job_level')->orderBy('full_name')
            ->get();

        // Load approval chains
        $approvalChains = [];
        foreach (['leave', 'overtime', 'attendance'] as $type) {
            $approvalChains[$type] = EmployeeApprover::getChain($id, $type);
        }

        return view('admin.employees.edit', compact('employee', 'departments', 'workSchedules', 'managers', 'approvalChains'));
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $request->validate([
            'employee_code' => 'required|unique:employees,employee_code,' . $id,
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:employees,email,' . $id,
            'department_id' => 'required|exists:departments,id',
            'employment_status' => 'required|in:permanent,contract,intern,probation',
            'join_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'role' => 'required|in:superadmin,admin,manager,employee',
            'photo' => 'nullable|image|max:2048',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'marital_status' => 'nullable|in:single,married,divorced,widowed',
            'blood_type' => 'nullable|in:A,B,AB,O',
            'religion' => 'nullable|string|max:50',
            'nik' => 'nullable|string|max:20',
            'postal_code' => 'nullable|string|max:10',
            'ktp_address' => 'nullable|string',
            'residential_address' => 'nullable|string',
        ]);

        $data = $request->except(['password', 'photo', 'remove_photo']);
        if ($request->filled('password')) {
            $data['password'] = $request->password;
        }

        // Handle photo
        if ($request->boolean('remove_photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = null;
        } elseif ($request->hasFile('photo')) {
            if ($employee->photo) {
                Storage::disk('public')->delete($employee->photo);
            }
            $data['photo'] = $request->file('photo')->store('employees/photos', 'public');
        }

        $employee->update($data);

        return redirect()->route('admin.employees.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function resign($id)
    {
        $employee = Employee::with(['department', 'activePayroll'])->findOrFail($id);

        if (!$employee->is_active) {
            return redirect()->route('admin.employees.index')
                ->with('error', 'Karyawan ini sudah tidak aktif.');
        }

        // Calculate months worked this year for PPh21 preview
        $joinThisYear = \Carbon\Carbon::parse($employee->join_date ?? now()->startOfYear());
        $startOfYear  = now()->startOfYear();
        $monthsWorked = (int) max(1, $startOfYear->lt($joinThisYear)
            ? $joinThisYear->diffInMonths(now()) + 1
            : now()->month);

        $pph21Preview = null;
        $payroll      = $employee->activePayroll;
        if ($payroll) {
            $bpjsCalc = new \App\Services\BpjsCalculator(now()->format('Y-m-d'));
            $bpjs     = $bpjsCalc->calculate((float) $payroll->basic_salary);

            $pph21Calc   = new \App\Services\Pph21Calculator(now()->format('Y-m-d'));
            $pph21Preview = $pph21Calc->calculateFinalMonth(
                avgBrutoMonthly : (float) $payroll->basic_salary,
                ptkpStatus      : $payroll->ptkp_status ?? 'TK/0',
                taxMethod       : $payroll->tax_method  ?? 'gross',
                bpjsEmployee    : $bpjs['employee_total'],
                monthsWorked    : $monthsWorked,
                taxAlreadyPaid  : 0  // ideally summed from payroll_run_details this year
            );
        }

        return view('admin.employees.resign', compact('employee', 'monthsWorked', 'pph21Preview'));
    }

    public function processResign(Request $request, $id)
    {
        $employee = Employee::with(['activePayroll', 'payrollComponents'])->findOrFail($id);

        $request->validate([
            'resign_date'       => 'required|date',
            'last_working_date' => 'required|date|after_or_equal:resign_date',
            'resign_reason'     => 'required|in:voluntary,termination,contract_end,retirement,passed_away',
            'resign_notes'      => 'nullable|string|max:1000',
        ]);

        // 1. Update employee status
        $employee->update([
            'is_active'         => false,
            'resign_date'       => $request->resign_date,
            'last_working_date' => $request->last_working_date,
            'resign_reason'     => $request->resign_reason,
            'resign_notes'      => $request->resign_notes,
        ]);

        // 2. Deactivate all payroll component assignments
        \App\Models\EmployeePayrollComponent::where('employee_id', $id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'end_date'  => $request->last_working_date,
            ]);

        // 3. Deactivate active EmployeePayroll
        \App\Models\EmployeePayroll::where('employee_id', $id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return redirect()->route('admin.employees.show', $id)
            ->with('success', 'Proses resign berhasil dicatat. Karyawan telah dinonaktifkan.');
    }
}
