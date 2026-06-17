<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeavePolicyController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));
        $policies = LeavePolicy::where('company_id', $admin->company_id)
            ->with(['leaveType', 'eligibleEmployees'])
            ->orderBy('id')
            ->get();

        $leaveTypes = LeaveType::all();
        $employees = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('role', 'employee')
            ->orderBy('full_name')
            ->get();

        return view('admin.leave-policies.index', compact('policies', 'leaveTypes', 'employees'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'days_per_year' => 'required|integer|min:1|max:365',
            'min_tenure_months' => 'required|integer|min:0',
            'max_carry_over' => 'required|integer|min:0',
            'is_prorated' => 'sometimes|boolean',
            'eligibility_type' => 'required|in:all,selected',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ]);

        $employeeIds = $this->validatedEligibleEmployeeIds($request, $admin->company_id);

        $policy = LeavePolicy::updateOrCreate(
            [
                'company_id' => $admin->company_id,
                'leave_type_id' => $request->leave_type_id,
            ],
            [
                'days_per_year' => $request->days_per_year,
                'min_tenure_months' => $request->min_tenure_months,
                'max_carry_over' => $request->max_carry_over,
                'is_prorated' => $request->boolean('is_prorated'),
                'eligibility_type' => $request->eligibility_type,
                'is_active' => true,
            ]
        );

        $policy->eligibleEmployees()->sync($request->eligibility_type === 'selected' ? $employeeIds : []);

        return back()->with('success', 'Kebijakan cuti berhasil disimpan.');
    }

    public function update(Request $request, LeavePolicy $leavePolicy)
    {
        $admin = Employee::find(session('admin_id'));
        abort_unless($admin && $leavePolicy->company_id === $admin->company_id, 403);

        $request->validate([
            'days_per_year' => 'required|integer|min:1|max:365',
            'min_tenure_months' => 'required|integer|min:0',
            'max_carry_over' => 'required|integer|min:0',
            'is_prorated' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'eligibility_type' => 'required|in:all,selected',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
        ]);

        $employeeIds = $this->validatedEligibleEmployeeIds($request, $admin->company_id);

        $leavePolicy->update([
            'days_per_year' => $request->days_per_year,
            'min_tenure_months' => $request->min_tenure_months,
            'max_carry_over' => $request->max_carry_over,
            'is_prorated' => $request->boolean('is_prorated'),
            'eligibility_type' => $request->eligibility_type,
            'is_active' => $request->boolean('is_active'),
        ]);

        $leavePolicy->eligibleEmployees()->sync($request->eligibility_type === 'selected' ? $employeeIds : []);

        return back()->with('success', 'Kebijakan cuti berhasil diperbarui.');
    }

    public function destroy(LeavePolicy $leavePolicy)
    {
        $admin = Employee::find(session('admin_id'));
        abort_unless($admin && $leavePolicy->company_id === $admin->company_id, 403);

        $leavePolicy->delete();
        return back()->with('success', 'Kebijakan cuti berhasil dihapus.');
    }

    private function validatedEligibleEmployeeIds(Request $request, int $companyId): array
    {
        if ($request->eligibility_type !== 'selected') {
            return [];
        }

        $employeeIds = array_values(array_unique(array_map('intval', $request->input('employee_ids', []))));

        if (empty($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Pilih minimal satu karyawan untuk kebijakan cuti karyawan tertentu.',
            ]);
        }

        $validCount = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('role', 'employee')
            ->whereIn('id', $employeeIds)
            ->count();

        if ($validCount !== count($employeeIds)) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Daftar karyawan berisi karyawan yang tidak valid untuk perusahaan ini.',
            ]);
        }

        return $employeeIds;
    }
}
