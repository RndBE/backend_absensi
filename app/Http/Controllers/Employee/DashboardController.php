<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
use App\Support\AttendanceOpenShift;
use App\Support\AttendanceLateExcuse;
use App\Support\PendingApprovalCounter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $today = Carbon::today();

        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->where('date', $today->toDateString())
            ->first();

        if (! $todayAttendance) {
            $yesterday = $today->copy()->subDay();
            $openYesterdayAttendance = Attendance::where('employee_id', $employee->id)
                ->where('date', $yesterday->toDateString())
                ->whereNotNull('clock_in')
                ->whereNull('clock_out')
                ->first();

            if ($openYesterdayAttendance && AttendanceOpenShift::isOvernight($employee, $yesterday)) {
                $todayAttendance = $openYesterdayAttendance;
            }
        }

        // Riwayat presensi dengan filter bulan (default bulan ini). Format query: Y-m.
        try {
            $historyPeriod = $request->filled('history_period')
                ? Carbon::createFromFormat('Y-m', (string) $request->query('history_period'))->startOfMonth()
                : $today->copy()->startOfMonth();
        } catch (\Throwable $e) {
            $historyPeriod = $today->copy()->startOfMonth();
        }
        // Tidak boleh memilih bulan di masa depan.
        if ($historyPeriod->greaterThan($today->copy()->startOfMonth())) {
            $historyPeriod = $today->copy()->startOfMonth();
        }

        $recentAttendances = Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $historyPeriod->year)
            ->whereMonth('date', $historyPeriod->month)
            ->orderBy('date', 'desc')
            ->get();

        // Tanggal izin (datang telat/pulang cepat) untuk bulan riwayat yang dipilih,
        // agar badge status di widget riwayat tetap akurat lintas bulan.
        $historyMonthStart = $historyPeriod->copy()->startOfMonth();
        $historyMonthEnd = $historyPeriod->copy()->endOfMonth();
        $historyLeaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $historyMonthEnd->toDateString())
            ->where('end_date', '>=', $historyMonthStart->toDateString())
            ->get();
        $historyLateExcuseDates = AttendanceLateExcuse::lateExcuseDates($historyLeaves, $historyMonthStart, $historyMonthEnd);
        $historyEarlyDepartureDates = AttendanceLateExcuse::earlyDepartureDates($historyLeaves, $historyMonthStart, $historyMonthEnd);

        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $approvedLeaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where('end_date', '>=', $monthStart->toDateString())
            ->get();
        $lateExcuseDates = AttendanceLateExcuse::lateExcuseDates($approvedLeaves, $monthStart, $monthEnd);
        $earlyDepartureDates = AttendanceLateExcuse::earlyDepartureDates($approvedLeaves, $monthStart, $monthEnd);

        // Perjalanan yang LHP-nya belum dibuat oleh karyawan ini (pemilik atau peserta).
        $pendingLhp = Schema::hasTable('budget_requests')
            ? BudgetRequest::query()
                ->where(function ($query) use ($employee) {
                    $query->where('employee_id', $employee->id)
                        ->orWhereHas('participants', fn ($q) => $q->where('employees.id', $employee->id));
                })
                ->whereIn('status', ['approved', 'paid'])
                ->whereNotNull('return_date')
                ->whereDoesntHave('travelReport', fn ($q) => $q->where('employee_id', $employee->id))
                ->with('employee:id,company_id')
                ->orderBy('return_date')
                ->get()
            : collect();

        return view('employee.dashboard', [
            'pendingLhp' => $pendingLhp,
            'historyPeriod' => $historyPeriod,
            'historyLateExcuseDates' => $historyLateExcuseDates,
            'historyEarlyDepartureDates' => $historyEarlyDepartureDates,
            'employee' => $employee,
            'today' => $today,
            'todayAttendance' => $todayAttendance,
            'recentAttendances' => $recentAttendances,
            'lateExcuseDates' => $lateExcuseDates,
            'earlyDepartureDates' => $earlyDepartureDates,
            'schedule' => $this->todaySchedule($employee, $today),
            'pendingApprovalCount' => app(PendingApprovalCounter::class)->countForApprover($employee),
            'settings' => [
                'office_latitude' => (float) Setting::getValue('office_latitude', '0'),
                'office_longitude' => (float) Setting::getValue('office_longitude', '0'),
                'office_radius_meters' => (int) Setting::getValue('office_radius_meters', '100'),
                'require_photo' => Setting::getValue('require_photo', '1') === '1',
                'require_gps' => Setting::getValue('require_gps', '1') === '1',
                'allow_remote_clockin' => Setting::getValue('allow_remote_clockin', '0') === '1',
                'face_verification_enabled' => Setting::getValue('face_verification_enabled', '1') === '1',
            ],
        ]);
    }

    private function todaySchedule(Employee $employee, Carbon $date): array
    {
        if (Schema::hasTable('schedule_assignments')) {
            $assignment = ScheduleAssignment::with('shift')
                ->where('employee_id', $employee->id)
                ->where('date', $date->toDateString())
                ->first();

            if ($assignment?->shift) {
                return $this->formatShift($assignment->shift->name, $assignment->shift->start_time, $assignment->shift->end_time, $assignment->shift->is_off);
            }
        }

        if (Schema::hasTable('holidays')) {
            $holiday = Holiday::where('company_id', $employee->company_id)
                ->where('date', $date->toDateString())
                ->first();

            if ($holiday) {
                return [
                    'name' => $holiday->name ?: 'Libur Nasional',
                    'time' => 'Libur Nasional',
                    'is_off' => true,
                ];
            }
        }

        if ($employee->schedule_template_id && Schema::hasTable('schedule_templates')) {
            $employee->loadMissing('scheduleTemplate.days.shift');
            $shift = $employee->scheduleTemplate?->getShiftForDay($date->dayOfWeekIso);

            if ($shift) {
                return $this->formatShift($employee->scheduleTemplate->name.' - '.$shift->name, $shift->start_time, $shift->end_time, $shift->is_off);
            }
        }

        if ($employee->work_schedule_id && Schema::hasTable('work_schedules')) {
            $employee->loadMissing('workSchedule');

            if ($employee->workSchedule) {
                return $this->formatShift($employee->workSchedule->name, $employee->workSchedule->start_time, $employee->workSchedule->end_time, false);
            }
        }

        return [
            'name' => 'Jadwal belum diatur',
            'time' => '-',
            'is_off' => false,
        ];
    }

    private function formatShift(string $name, ?string $startTime, ?string $endTime, bool $isOff): array
    {
        return [
            'name' => $isOff ? 'Libur' : $name,
            'time' => $isOff ? '-' : trim(substr((string) $startTime, 0, 5).' - '.substr((string) $endTime, 0, 5)),
            'is_off' => $isOff,
        ];
    }
}
