<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Api\AttendanceController as ApiAttendanceController;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Setting;
use App\Support\AttendanceLateExcuse;
use App\Support\AttendanceOpenShift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function show(Request $request, string $type)
    {
        abort_unless(in_array($type, ['clock-in', 'clock-out'], true), 404);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $todayAttendance = Attendance::where('employee_id', $employee->id)
            ->where('date', Carbon::today()->toDateString())
            ->first();

        // Overnight shift: cek open attendance dari kemarin yang belum clock-out
        $overnightAttendance = null;
        if (! $todayAttendance) {
            $overnightAttendance = Attendance::where('employee_id', $employee->id)
                ->where('date', Carbon::yesterday()->toDateString())
                ->whereNotNull('clock_in')
                ->whereNull('clock_out')
                ->first();

            if ($overnightAttendance && ! AttendanceOpenShift::isOvernight($employee, Carbon::yesterday())) {
                $overnightAttendance = null;
            }
        }

        $activeAttendance = $todayAttendance ?? $overnightAttendance;

        return view('employee.attendance.show', [
            'employee' => $employee,
            'type' => $type,
            'title' => $type === 'clock-in' ? 'Clock In' : 'Clock Out',
            'endpoint' => $type === 'clock-in'
                ? route('employee.attendance.clock-in')
                : route('employee.attendance.clock-out'),
            'todayAttendance' => $activeAttendance,
            'settings' => [
                'office_latitude' => (float) Setting::getValue('office_latitude', '0'),
                'office_longitude' => (float) Setting::getValue('office_longitude', '0'),
                'office_radius_meters' => (int) Setting::getValue('office_radius_meters', '100'),
                'office_address' => Setting::getValue('office_address', ''),
                'require_photo' => Setting::getValue('require_photo', '1') === '1',
                'require_gps' => Setting::getValue('require_gps', '1') === '1',
                'allow_remote_clockin' => Setting::getValue('allow_remote_clockin', '0') === '1',
                'remote_requires_notes' => Setting::getValue('remote_requires_notes', '1') === '1',
            ],
        ]);
    }

    /**
     * Riwayat presensi per bulan. Dulu menempel di dashboard; dipindah ke halaman sendiri
     * supaya dashboard tidak menanggung query satu bulan penuh untuk tabel yang jarang
     * dibaca, dan supaya pemilihan bulan tidak lagi memuat ulang seluruh dashboard.
     */
    public function history(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $today = Carbon::today();

        // Format query: Y-m. Masukan yang tidak bisa dibaca jatuh ke bulan ini, bukan 500.
        try {
            $period = $request->filled('history_period')
                ? Carbon::createFromFormat('Y-m', (string) $request->query('history_period'))->startOfMonth()
                : $today->copy()->startOfMonth();
        } catch (\Throwable $e) {
            $period = $today->copy()->startOfMonth();
        }

        // Bulan di masa depan tidak boleh dipilih — belum ada presensinya.
        if ($period->greaterThan($today->copy()->startOfMonth())) {
            $period = $today->copy()->startOfMonth();
        }

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereYear('date', $period->year)
            ->whereMonth('date', $period->month)
            ->orderBy('date', 'desc')
            ->get();

        // Tanggal izin datang telat / pulang cepat pada bulan yang dilihat, supaya badge
        // status tetap akurat saat pengguna membuka bulan-bulan sebelumnya.
        $monthStart = $period->copy()->startOfMonth();
        $monthEnd = $period->copy()->endOfMonth();
        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where('end_date', '>=', $monthStart->toDateString())
            ->get();

        return view('employee.attendance.history', [
            'employee' => $employee,
            'period' => $period,
            'attendances' => $attendances,
            'lateExcuseDates' => AttendanceLateExcuse::lateExcuseDates($leaves, $monthStart, $monthEnd),
            'earlyDepartureDates' => AttendanceLateExcuse::earlyDepartureDates($leaves, $monthStart, $monthEnd),
        ]);
    }

    public function clockIn(Request $request)
    {
        return $this->asEmployeeRequest($request, fn (Request $request) => app(ApiAttendanceController::class)->clockIn($request));
    }

    public function clockOut(Request $request)
    {
        return $this->asEmployeeRequest($request, fn (Request $request) => app(ApiAttendanceController::class)->clockOut($request));
    }

    private function asEmployeeRequest(Request $request, callable $handler)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $request->setUserResolver(fn () => $employee);

        return $handler($request);
    }
}
