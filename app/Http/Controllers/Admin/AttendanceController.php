<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function realtime(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $today = Carbon::today();

        $attendances = Attendance::with(['employee:id,full_name,photo,department_id,position,employment_status', 'employee.department:id,name'])
            ->whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)
            ->orderBy('clock_in', 'desc')
            ->get();

        return view('admin.attendance.realtime', compact('attendances'));
    }

    public function history(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = Attendance::with(['employee:id,full_name,employee_code,department_id,employment_status', 'employee.department:id,name'])
            ->whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id));

        if ($request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }
        if ($request->employee_id) {
            $query->where('employee_id', $request->employee_id);
        }
        if ($request->employment_status) {
            $query->whereHas('employee', fn($q) => $q->where('employment_status', $request->employment_status));
        }

        $attendances = $query->orderBy('date', 'desc')->orderBy('clock_in', 'desc')->paginate(20)->withQueryString();
        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->select('id', 'full_name', 'employment_status')->get();

        return view('admin.attendance.history', compact('attendances', 'employees'));
    }
}
