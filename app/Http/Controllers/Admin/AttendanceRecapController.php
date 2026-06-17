<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceRecapController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $date = $request->date ? Carbon::parse($request->date) : Carbon::today();
        $departmentId = $request->department_id;
        $search = $request->search;
        $filterStatus = $request->status;

        // Check if holiday
        $holiday = Holiday::where('company_id', $admin->company_id)
            ->where('date', $date->format('Y-m-d'))
            ->first();

        // Load employees with their templates
        $query = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->with(['department:id,name', 'scheduleTemplate.days.shift']);

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }
        if ($search) {
            $query->where('full_name', 'like', "%{$search}%");
        }

        $employees = $query->orderBy('department_id')->orderBy('full_name')->get();

        // Load attendances for this date
        $attendances = Attendance::where('date', $date->format('Y-m-d'))
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get()
            ->keyBy('employee_id');

        // Load approved leaves covering this date
        $leaves = LeaveRequest::with('leaveType')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('status', 'approved')
            ->where('start_date', '<=', $date->format('Y-m-d'))
            ->where('end_date', '>=', $date->format('Y-m-d'))
            ->get()
            ->keyBy('employee_id');

        // Load manual schedule overrides for this date
        $overrides = ScheduleAssignment::with('shift')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('date', $date->format('Y-m-d'))
            ->get()
            ->keyBy('employee_id');

        // Build recap rows
        $rows = [];
        $stats = ['hadir' => 0, 'terlambat' => 0, 'cuti' => 0, 'alpha' => 0, 'off' => 0, 'libur' => 0];

        foreach ($employees as $emp) {
            $row = [
                'employee' => $emp,
                'shift' => null,
                'attendance' => null,
                'leave' => null,
                'status' => 'no_schedule', // no_schedule, off, holiday, leave, present, late, absent
                'status_label' => '-',
                'clock_in' => null,
                'clock_out' => null,
            ];

            // Determine shift for this day
            if ($holiday) {
                $row['status'] = 'holiday';
                $row['status_label'] = 'Libur Nasional';
                $stats['libur']++;
            } else {
                // Shift: override > template
                $override = $overrides->get($emp->id);
                if ($override) {
                    $row['shift'] = $override->shift;
                } elseif ($emp->scheduleTemplate) {
                    $row['shift'] = $emp->scheduleTemplate->getShiftForDay($date->dayOfWeekIso);
                }

                $shift = $row['shift'];

                if (!$shift) {
                    $row['status'] = 'no_schedule';
                    $row['status_label'] = 'Tidak Ada Jadwal';
                } elseif ($shift->is_off) {
                    $row['status'] = 'off';
                    $row['status_label'] = 'Off / Libur';
                    $stats['off']++;
                } else {
                    // Has schedule — check leave first
                    $leave = $leaves->get($emp->id);
                    if ($leave) {
                        $row['status'] = 'leave';
                        $row['status_label'] = 'Cuti: ' . ($leave->leaveType->name ?? 'Cuti');
                        $row['leave'] = $leave;
                        $stats['cuti']++;
                    } else {
                        // Check attendance
                        $att = $attendances->get($emp->id);
                        if ($att) {
                            $row['attendance'] = $att;
                            $row['clock_in'] = $att->clock_in;
                            $row['clock_out'] = $att->clock_out;
                            if ($att->review_status === 'pending') {
                                $row['status'] = 'review';
                                $row['status_label'] = 'Butuh Review';
                            } elseif ($att->review_status === 'rejected' || $att->status === 'absent') {
                                $row['status'] = 'absent';
                                $row['status_label'] = 'Alpha';
                                $stats['alpha']++;
                            } elseif ($att->is_late) {
                                $row['status'] = 'late';
                                $row['status_label'] = 'Terlambat';
                                $stats['terlambat']++;
                            } else {
                                $row['status'] = 'present';
                                $row['status_label'] = 'Hadir';
                                $stats['hadir']++;
                            }
                        } else {
                            // No attendance, is it future or past?
                            if ($date->isFuture()) {
                                $row['status'] = 'scheduled';
                                $row['status_label'] = 'Terjadwal';
                            } else {
                                $row['status'] = 'absent';
                                $row['status_label'] = 'Alpha';
                                $stats['alpha']++;
                            }
                        }
                    }
                }
            }

            // Status filter
            if ($filterStatus && $row['status'] !== $filterStatus) {
                continue;
            }

            $rows[] = $row;
        }

        $departments = Department::where('company_id', $admin->company_id)->orderBy('name')->get();

        return view('admin.attendance-recap.index', compact(
            'rows', 'date', 'holiday', 'stats', 'departments',
            'departmentId', 'search', 'filterStatus'
        ));
    }

    public function update(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'status' => 'required|in:present,absent,leave,holiday',
        ]);

        // Auto-calculate is_late based on shift start_time
        $isLate = false;
        if ($request->clock_in) {
            $emp = Employee::with('scheduleTemplate.days.shift')->find($request->employee_id);
            $date = Carbon::parse($request->date);

            // Get shift: override > template
            $override = ScheduleAssignment::with('shift')
                ->where('employee_id', $emp->id)
                ->where('date', $date->format('Y-m-d'))
                ->first();

            $shift = $override?->shift
                ?? $emp->scheduleTemplate?->getShiftForDay($date->dayOfWeekIso);

            if ($shift && !$shift->is_off && $shift->start_time) {
                $isLate = $request->clock_in > substr($shift->start_time, 0, 5);
            }
        }

        Attendance::updateOrCreate(
            [
                'employee_id' => $request->employee_id,
                'date' => $request->date,
            ],
            [
                'clock_in' => $request->clock_in,
                'clock_out' => $request->clock_out,
                'status' => $request->status,
                'is_late' => $isLate,
            ]
        );

        return back()->with('success', 'Data presensi berhasil diperbarui.');
    }

    public function employeeDetail(Request $request, $id)
    {
        $admin = Employee::find(session('admin_id'));
        $employee = Employee::where('company_id', $admin->company_id)
            ->with(['department:id,name', 'scheduleTemplate.days.shift'])
            ->findOrFail($id);

        $period = $request->period ? Carbon::parse($request->period . '-01') : Carbon::today()->startOfMonth();
        $startOfMonth = $period->copy()->startOfMonth();
        $endOfMonth = $period->copy()->endOfMonth();
        $daysInMonth = $period->daysInMonth;

        // Load template days
        $templateDays = [];
        if ($employee->scheduleTemplate) {
            foreach ($employee->scheduleTemplate->days as $day) {
                $templateDays[$day->day_of_week] = $day->shift;
            }
        }

        // Load overrides
        $overrides = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($o) => $o->date->format('Y-m-d'));

        // Load attendances
        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($a) => $a->date->format('Y-m-d'));

        // Load holidays
        $holidays = Holiday::where('company_id', $admin->company_id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        // Load leaves
        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endOfMonth->format('Y-m-d'))
            ->where('end_date', '>=', $startOfMonth->format('Y-m-d'))
            ->get();

        $dayNames = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        $rows = [];
        $stats = ['hadir' => 0, 'terlambat' => 0, 'alpha' => 0, 'cuti' => 0, 'off' => 0, 'libur' => 0];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $date = $startOfMonth->copy()->addDays($d - 1);
            $dateStr = $date->format('Y-m-d');
            $dow = $date->dayOfWeekIso;

            $holiday = $holidays[$dateStr] ?? null;
            $att = $attendances[$dateStr] ?? null;

            // Only resolve shift when NOT a holiday
            $shift = null;
            if (!$holiday) {
                $shift = (isset($overrides[$dateStr]) ? $overrides[$dateStr]->shift : null)
                    ?? ($templateDays[$dow] ?? null);
            }

            // Check leave
            $leave = $leaves->first(function ($l) use ($date) {
                return $date->between($l->start_date, $l->end_date);
            });

            $status = 'no_schedule';
            $statusLabel = '-';
            if ($holiday) {
                $status = 'holiday';
                $statusLabel = $holiday->name;
                $stats['libur']++;
            } elseif ($leave) {
                $status = 'leave';
                $statusLabel = $leave->leaveType->name ?? 'Cuti';
                $stats['cuti']++;
            } elseif ($shift && $shift->is_off) {
                $status = 'off';
                $statusLabel = 'OFF';
                $stats['off']++;
            } elseif ($att && $att->status === 'present') {
                if ($att->is_late) {
                    $status = 'late';
                    $statusLabel = 'Terlambat';
                    $stats['terlambat']++;
                } else {
                    $status = 'present';
                    $statusLabel = 'Hadir';
                }
                $stats['hadir']++;
            } elseif ($shift && !$shift->is_off && $date->lte(Carbon::today())) {
                $status = 'absent';
                $statusLabel = 'Alpha';
                $stats['alpha']++;
            }

            $rows[] = [
                'date' => $date,
                'day' => $d,
                'day_name' => $dayNames[$dow],
                'shift' => $shift,
                'attendance' => $att,
                'holiday' => $holiday,
                'leave' => $leave,
                'status' => $status,
                'status_label' => $statusLabel,
            ];
        }

        return view('admin.attendance-recap.employee-detail', compact(
            'employee', 'period', 'rows', 'stats'
        ));
    }
}
