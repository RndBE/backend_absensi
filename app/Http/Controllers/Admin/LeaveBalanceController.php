<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class LeaveBalanceController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? now()->year;
        $departmentId = $request->department_id;
        $search = $request->search;

        $query = LeaveBalance::with(['employee.department', 'leaveType'])
            ->whereHas('employee', function ($q) use ($admin, $departmentId, $search) {
                $q->where('company_id', $admin->company_id)->where('is_active', true);
                if ($departmentId) $q->where('department_id', $departmentId);
                if ($search) $q->where('full_name', 'like', "%{$search}%");
            })
            ->where('year', $year);

        $balances = $query->get()
            ->groupBy('employee_id');

        $leaveTypes = LeaveType::all();
        $departments = Department::where('company_id', $admin->company_id)->orderBy('name')->get();

        return view('admin.leave-balances.index', compact(
            'balances', 'year', 'leaveTypes', 'departments',
            'departmentId', 'search'
        ));
    }

    public function generate(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? now()->year;

        Artisan::call('leave:generate-annual', [
            'year' => $year,
            '--company' => $admin->company_id,
        ]);

        return back()->with('success', "Saldo cuti tahun {$year} berhasil di-generate! " . Artisan::output());
    }

    public function update(Request $request, LeaveBalance $leaveBalance)
    {
        $request->validate([
            'total_days' => 'required|integer|min:0',
            'carry_over' => 'required|integer|min:0',
            'used_days' => 'required|integer|min:0',
        ]);

        $total = $request->total_days + $request->carry_over;
        $remaining = $total - $request->used_days;

        $leaveBalance->update([
            'total_days' => $request->total_days,
            'carry_over' => $request->carry_over,
            'used_days' => $request->used_days,
            'remaining_days' => max(0, $remaining),
        ]);

        return back()->with('success', 'Saldo cuti berhasil diperbarui.');
    }
}
