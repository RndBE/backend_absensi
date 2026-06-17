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
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->where('date', $today)
            ->orderBy('clock_in', 'desc')
            ->get();

        return view('admin.attendance.realtime', compact('attendances'));
    }

    public function history(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = Attendance::with([
            'employee:id,full_name,employee_code,department_id,employment_status',
            'employee.department:id,name',
            'reviewer:id,full_name',
        ])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id));

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
            $query->whereHas('employee', fn ($q) => $q->where('employment_status', $request->employment_status));
        }
        if ($request->review_status) {
            $query->where('review_status', $request->review_status);
        }

        $attendances = $query->orderBy('date', 'desc')->orderBy('clock_in', 'desc')->paginate(20)->withQueryString();
        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->select('id', 'full_name', 'employment_status')->get();
        $reviewSummary = [
            'pending' => Attendance::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
                ->where('review_status', 'pending')
                ->count(),
            'rejected' => Attendance::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
                ->where('review_status', 'rejected')
                ->count(),
        ];

        return view('admin.attendance.history', compact('attendances', 'employees', 'reviewSummary'));
    }

    public function approveSecurityReview(Request $request, int $id)
    {
        $request->validate(['review_notes' => ['nullable', 'string', 'max:500']]);

        $attendance = $this->findAttendanceForCurrentCompany($id);
        $admin = Employee::find(session('admin_id'));

        $attendance->update([
            'review_status' => 'approved',
            'status' => 'present',
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return back()->with('success', 'Presensi mencurigakan berhasil di-approve.');
    }

    public function rejectSecurityReview(Request $request, int $id)
    {
        $request->validate(['review_notes' => ['nullable', 'string', 'max:500']]);

        $attendance = $this->findAttendanceForCurrentCompany($id);
        $admin = Employee::find(session('admin_id'));

        $attendance->update([
            'review_status' => 'rejected',
            'status' => 'absent',
            'is_late' => false,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $request->input('review_notes'),
        ]);

        return back()->with('success', 'Presensi mencurigakan ditolak dan tidak dihitung sebagai hadir.');
    }

    private function findAttendanceForCurrentCompany(int $id): Attendance
    {
        $admin = Employee::find(session('admin_id'));

        return Attendance::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->findOrFail($id);
    }
}
