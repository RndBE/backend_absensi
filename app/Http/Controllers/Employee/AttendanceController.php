<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Api\AttendanceController as ApiAttendanceController;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Setting;
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
