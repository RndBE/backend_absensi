<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use App\Models\ScheduleTemplate;
use App\Models\Holiday;
use App\Support\AttendanceLate;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $viewMode = $request->get('view', 'week'); // 'week' or 'month'

        if ($viewMode === 'month') {
            // Monthly view
            $month = $request->month
                ? Carbon::parse($request->month . '-01')
                : Carbon::now()->startOfMonth();
            $rangeStart = $month->copy()->startOfMonth();
            $rangeEnd = $month->copy()->endOfMonth();
            $prevParam = ['view' => 'month', 'month' => $month->copy()->subMonth()->format('Y-m')];
            $nextParam = ['view' => 'month', 'month' => $month->copy()->addMonth()->format('Y-m')];
            $todayParam = ['view' => 'month'];
            $rangeLabel = $month->translatedFormat('F Y');
        } else {
            // Weekly view
            $weekStart = $request->week
                ? Carbon::parse($request->week)->startOfWeek(Carbon::MONDAY)
                : Carbon::now()->startOfWeek(Carbon::MONDAY);
            $rangeStart = $weekStart;
            $rangeEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            $prevParam = ['view' => 'week', 'week' => $rangeStart->copy()->subWeek()->format('Y-m-d')];
            $nextParam = ['view' => 'week', 'week' => $rangeStart->copy()->addWeek()->format('Y-m-d')];
            $todayParam = ['view' => 'week'];
            $rangeLabel = $rangeStart->format('d M') . ' — ' . $rangeEnd->format('d M Y');
        }

        // Build dates array
        $dates = [];
        $d = $rangeStart->copy();
        while ($d->lte($rangeEnd)) {
            $dates[] = $d->copy();
            $d->addDay();
        }

        // Filters
        $departmentId = $request->department_id;
        $search = $request->search;

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

        // Load manual overrides for range
        $assignments = ScheduleAssignment::with('shift')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->get()
            ->groupBy(fn($a) => $a->employee_id . '-' . $a->date->format('Y-m-d'));

        // Shifts + departments for dropdowns
        $shifts = Shift::where('company_id', $admin->company_id)->orderBy('sort_order')->get();
        $departments = Department::where('company_id', $admin->company_id)->orderBy('name')->get();
        $templates = ScheduleTemplate::where('company_id', $admin->company_id)->get(['id', 'name']);

        // Holidays for range
        $holidays = Holiday::where('company_id', $admin->company_id)
            ->whereBetween('date', [$rangeStart->format('Y-m-d'), $rangeEnd->format('Y-m-d')])
            ->get()
            ->keyBy(fn($h) => $h->date->format('Y-m-d'));

        // All active employees for bulk assign modal (unfiltered)
        $allEmployees = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->with(['department:id,name'])
            ->orderBy('department_id')->orderBy('full_name')
            ->get();

        return view('admin.schedules.index', compact(
            'employees', 'allEmployees', 'dates', 'rangeStart', 'rangeEnd', 'rangeLabel',
            'assignments', 'shifts', 'departments', 'departmentId', 'search',
            'templates', 'holidays', 'viewMode', 'prevParam', 'nextParam', 'todayParam'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'date' => 'required|date',
        ]);

        ScheduleAssignment::updateOrCreate(
            ['employee_id' => $request->employee_id, 'date' => $request->date],
            ['shift_id' => $request->shift_id, 'notes' => $request->notes]
        );

        // Jika absensi pada tanggal ini sudah ada, hitung ulang status terlambatnya
        // terhadap shift yang baru (mis. tukeran shift yang diinput belakangan).
        if ($employee = Employee::find($request->employee_id)) {
            AttendanceLate::recalculate($employee, $request->date);
        }

        return back()->with('success', 'Jadwal berhasil disimpan.');
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'employee_ids'    => 'required|array|min:1',
            'employee_ids.*'  => 'exists:employees,id',
            'shift_id'        => 'required|exists:shifts,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'include_weekends'  => 'sometimes|boolean',
            'include_holidays'  => 'sometimes|boolean',
        ]);

        $admin = Employee::find(session('admin_id'));
        $start = Carbon::parse($request->start_date);
        $end   = Carbon::parse($request->end_date);
        $includeWeekends  = $request->boolean('include_weekends');
        $includeHolidays  = $request->boolean('include_holidays');
        $count = 0;

        // Load holidays for the date range
        $holidays = Holiday::where('company_id', $admin->company_id)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $employees = Employee::whereIn('id', $request->employee_ids)->get()->keyBy('id');
        $today = Carbon::today();

        foreach ($request->employee_ids as $empId) {
            $current = $start->copy();
            while ($current->lte($end)) {
                $dateStr = $current->format('Y-m-d');

                // Skip weekend jika tidak di-centang
                if (!$includeWeekends && $current->isWeekend()) {
                    $current->addDay();
                    continue;
                }

                // Skip hari libur HANYA jika include_holidays tidak di-centang
                if (!$includeHolidays && in_array($dateStr, $holidays)) {
                    $current->addDay();
                    continue;
                }

                ScheduleAssignment::updateOrCreate(
                    ['employee_id' => $empId, 'date' => $dateStr],
                    ['shift_id' => $request->shift_id]
                );
                $count++;

                // Hitung ulang status terlambat untuk absensi yang sudah ada (tanggal lampau/hari ini).
                if ($current->lte($today) && ($employee = $employees->get($empId))) {
                    AttendanceLate::recalculate($employee, $dateStr);
                }

                $current->addDay();
            }
        }

        $skipped = $includeHolidays ? '' : ' (hari libur di-skip)';
        return back()->with('success', "{$count} jadwal berhasil di-assign{$skipped}.");
    }

    public function destroy($id)
    {
        $assignment = ScheduleAssignment::findOrFail($id);
        $employeeId = $assignment->employee_id;
        $date = $assignment->date;
        $assignment->delete();

        // Setelah override dihapus, hitung ulang status terlambat terhadap jadwal fallback.
        if ($employee = Employee::find($employeeId)) {
            AttendanceLate::recalculate($employee, $date);
        }

        return back()->with('success', 'Jadwal berhasil dihapus.');
    }

    public function clearDay(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date' => 'required|date',
        ]);

        ScheduleAssignment::where('employee_id', $request->employee_id)
            ->where('date', $request->date)
            ->delete();

        if ($employee = Employee::find($request->employee_id)) {
            AttendanceLate::recalculate($employee, $request->date);
        }

        return back()->with('success', 'Jadwal pada tanggal tersebut berhasil dihapus.');
    }
}
