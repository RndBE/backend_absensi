<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Status kehadiran HARI INI untuk sekumpulan karyawan — dipakai manager di portal untuk
 * melihat sekilas siapa yang sudah masuk dan siapa yang belum.
 *
 * Sengaja terpisah dari MonthlyAttendance: manager boleh membuka periode bulan lalu, tapi
 * pertanyaan "siapa yang belum absen" selalu tentang hari ini.
 *
 * Perbedaan penting dari rekap bulanan: hari yang sedang berjalan TIDAK pernah dicap "alpha".
 * Orang yang belum absen jam 07:00 mungkin baru datang jam 08:00. Yang ditandai merah hanyalah
 * yang jam masuk shift-nya SUDAH lewat tapi belum juga clock-in.
 *
 * Semua query di-batch: 4 query untuk berapa pun jumlah anggota tim.
 */
class TodayTeamStatus
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, array{label:string, tone:string, clock_in:?string, clock_out:?string}>
     *                                    Kunci = employee_id. `tone`: hadir|telat|belum|terlewat|izin|off|libur|kosong
     */
    public static function for(Collection $employees, ?Carbon $today = null): Collection
    {
        if ($employees->isEmpty()) {
            return collect();
        }

        $today ??= Carbon::today();
        $dateStr = $today->toDateString();
        $ids = $employees->pluck('id')->all();

        $attendances = Attendance::whereIn('employee_id', $ids)
            ->whereDate('date', $dateStr)
            ->get()
            ->keyBy('employee_id');

        $overrides = ScheduleAssignment::with('shift')
            ->whereIn('employee_id', $ids)
            ->whereDate('date', $dateStr)
            ->get()
            ->keyBy('employee_id');

        $liburPerusahaan = Holiday::whereIn('company_id', $employees->pluck('company_id')->unique()->all())
            ->whereDate('date', $dateStr)
            ->pluck('company_id')
            ->flip();

        $leaves = LeaveRequest::with('leaveType')
            ->whereIn('employee_id', $ids)
            ->where('status', 'approved')
            ->where('start_date', '<=', $dateStr)
            ->where('end_date', '>=', $dateStr)
            ->get()
            ->groupBy('employee_id');

        return $employees->mapWithKeys(fn (Employee $e) => [
            $e->id => self::statusFor($e, $today, $attendances, $overrides, $liburPerusahaan, $leaves),
        ]);
    }

    /** @return array{label:string, tone:string, clock_in:?string, clock_out:?string} */
    private static function statusFor(
        Employee $employee,
        Carbon $today,
        Collection $attendances,
        Collection $overrides,
        Collection $liburPerusahaan,
        Collection $leaves,
    ): array {
        $att = $attendances->get($employee->id);
        $clockIn = $att?->clock_in ? substr((string) $att->clock_in, 0, 5) : null;
        $clockOut = $att?->clock_out ? substr((string) $att->clock_out, 0, 5) : null;

        $leaveList = $leaves->get($employee->id) ?? collect();
        $leave = AttendanceLateExcuse::firstForDate($leaveList, $today);
        $izinParsial = AttendanceLateExcuse::isLateArrivalLeave($leave)
            || AttendanceLateExcuse::isEarlyDepartureLeave($leave);

        $override = $overrides->get($employee->id)?->shift;
        $libur = $liburPerusahaan->has($employee->company_id);

        // Override menang atas libur; template hanya berlaku di hari biasa.
        $shift = $override;
        if (! $shift && ! $libur && (! $leave || $izinParsial)) {
            $shift = $employee->scheduleTemplateOn($today)?->getShiftForDay($today->dayOfWeekIso);
        }

        // Cuti penuh mengalahkan segalanya selain presensi yang sudah tercatat.
        if ($leave && ! $izinParsial && ! $att) {
            return self::row('Cuti: '.($leave->leaveType->name ?? 'Cuti'), 'izin', null, null);
        }

        if ($att && $clockIn) {
            $telat = $att->is_late && ! AttendanceLateExcuse::manualPermissionStatusLabel($att->status);

            return self::row($telat ? 'Terlambat' : 'Hadir', $telat ? 'telat' : 'hadir', $clockIn, $clockOut);
        }

        if ($libur && ! $shift) {
            return self::row('Libur', 'libur', null, null);
        }

        if ($shift && $shift->is_off) {
            return self::row('Off', 'off', null, null);
        }

        if (! $shift) {
            return self::row('Tidak Ada Jadwal', 'kosong', null, null);
        }

        // Terjadwal masuk tapi belum clock-in. Hari ini masih berjalan — jangan cap alpha.
        $mulai = $shift->start_time
            ? Carbon::parse($today->toDateString().' '.$shift->start_time)
            : null;

        return $mulai && now()->gt($mulai)
            ? self::row('Belum absen', 'terlewat', null, null)
            : self::row('Belum waktunya', 'belum', null, null);
    }

    /** @return array{label:string, tone:string, clock_in:?string, clock_out:?string} */
    private static function row(string $label, string $tone, ?string $clockIn, ?string $clockOut): array
    {
        return ['label' => $label, 'tone' => $tone, 'clock_in' => $clockIn, 'clock_out' => $clockOut];
    }
}
