<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Menyinkronkan status absensi dengan izin parsial yang sudah di-ACC
 * (izin datang terlambat -> 'late_excuse', izin pulang cepat -> 'early_departure').
 *
 * Berbeda dengan cuti penuh, izin parsial tetap dihitung HADIR. Status ini
 * disimpan langsung di kolom attendances.status agar konsisten di semua tempat.
 */
class AttendanceLeaveSync
{
    /**
     * Status absensi yang sesuai untuk sebuah izin parsial, atau null jika
     * bukan izin parsial (mis. cuti penuh/sakit/WFH).
     */
    public static function targetStatusFor(?LeaveRequest $leave): ?string
    {
        if (AttendanceLateExcuse::isLateArrivalLeave($leave)) {
            return AttendanceLateExcuse::LATE_EXCUSE_STATUS;
        }

        if (AttendanceLateExcuse::isEarlyDepartureLeave($leave)) {
            return AttendanceLateExcuse::EARLY_DEPARTURE_STATUS;
        }

        return null;
    }

    /**
     * Saat izin parsial di-ACC: tandai absensi yang SUDAH ada (sudah clock-in)
     * pada rentang tanggal izin dengan status izin terkait.
     */
    public static function apply(LeaveRequest $leave): void
    {
        $leave->loadMissing('leaveType');
        $target = self::targetStatusFor($leave);

        if (! $target) {
            return;
        }

        self::eachDate($leave, function (string $date) use ($leave, $target) {
            Attendance::where('employee_id', $leave->employee_id)
                ->whereDate('date', $date)
                ->whereNotNull('clock_in')
                ->where('status', 'present')
                ->update(['status' => $target]);
        });
    }

    /**
     * Saat izin parsial dibatalkan/ditolak: kembalikan status absensi ke 'present'
     * hanya untuk record yang sebelumnya kita ubah (statusnya == target izin).
     */
    public static function revert(LeaveRequest $leave): void
    {
        $leave->loadMissing('leaveType');
        $target = self::targetStatusFor($leave);

        if (! $target) {
            return;
        }

        self::eachDate($leave, function (string $date) use ($leave, $target) {
            Attendance::where('employee_id', $leave->employee_id)
                ->whereDate('date', $date)
                ->whereNotNull('clock_in')
                ->where('status', $target)
                ->update(['status' => 'present']);
        });
    }

    /**
     * Status izin parsial yang berlaku untuk seorang karyawan pada tanggal tertentu,
     * berdasarkan izin yang sudah di-ACC. Dipakai saat clock-in/clock-out.
     */
    public static function approvedTargetForDate(Employee|int $employee, CarbonInterface|string $date): ?string
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;
        $dateString = $date instanceof CarbonInterface ? $date->toDateString() : Carbon::parse($date)->toDateString();

        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $dateString)
            ->where('end_date', '>=', $dateString)
            ->get();

        foreach ($leaves as $leave) {
            if ($target = self::targetStatusFor($leave)) {
                return $target;
            }
        }

        return null;
    }

    private static function eachDate(LeaveRequest $leave, callable $callback): void
    {
        $cursor = Carbon::parse($leave->start_date)->startOfDay();
        $until = Carbon::parse($leave->end_date)->startOfDay();

        while ($cursor->lte($until)) {
            $callback($cursor->toDateString());
            $cursor->addDay();
        }
    }
}
