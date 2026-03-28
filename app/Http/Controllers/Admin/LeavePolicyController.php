<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeavePolicyController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));
        $policies = LeavePolicy::where('company_id', $admin->company_id)
            ->with('leaveType')
            ->orderBy('id')
            ->get();

        $leaveTypes = LeaveType::all();

        return view('admin.leave-policies.index', compact('policies', 'leaveTypes'));
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
        ]);

        LeavePolicy::updateOrCreate(
            [
                'company_id' => $admin->company_id,
                'leave_type_id' => $request->leave_type_id,
            ],
            [
                'days_per_year' => $request->days_per_year,
                'min_tenure_months' => $request->min_tenure_months,
                'max_carry_over' => $request->max_carry_over,
                'is_prorated' => $request->boolean('is_prorated'),
                'is_active' => true,
            ]
        );

        return back()->with('success', 'Kebijakan cuti berhasil disimpan.');
    }

    public function update(Request $request, LeavePolicy $leavePolicy)
    {
        $request->validate([
            'days_per_year' => 'required|integer|min:1|max:365',
            'min_tenure_months' => 'required|integer|min:0',
            'max_carry_over' => 'required|integer|min:0',
            'is_prorated' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
        ]);

        $leavePolicy->update([
            'days_per_year' => $request->days_per_year,
            'min_tenure_months' => $request->min_tenure_months,
            'max_carry_over' => $request->max_carry_over,
            'is_prorated' => $request->boolean('is_prorated'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Kebijakan cuti berhasil diperbarui.');
    }

    public function destroy(LeavePolicy $leavePolicy)
    {
        $leavePolicy->delete();
        return back()->with('success', 'Kebijakan cuti berhasil dihapus.');
    }
}
