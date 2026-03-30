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
            $query->where('department_id', $request->department_id);
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
            'contract_end_date' => 'nullable|date',
            'role' => 'required|in:admin,manager,employee',
            'manager_id' => 'nullable|exists:employees,id',
            'approver_id' => 'nullable|exists:employees,id',
            'photo' => 'nullable|image|max:2048',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
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
            'role' => 'required|in:superadmin,admin,manager,employee',
            'photo' => 'nullable|image|max:2048',
            'birth_place' => 'nullable|string|max:255',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:Male,Female',
            'marital_status' => 'nullable|in:Single,Married,Divorced,Widowed',
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

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->update(['is_active' => false]);

        return redirect()->route('admin.employees.index')->with('success', 'Karyawan berhasil dinonaktifkan.');
    }
}
