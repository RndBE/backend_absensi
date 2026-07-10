<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\ScheduleAssignment;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Riwayat presensi satu karyawan dalam satu bulan, berikut rekap agregatnya.
 *
 * Diekstrak dari Api\AttendanceController@schedule agar aturan "hari ini dihitung apa"
 * hidup di satu tempat. Aturannya berlapis dan mudah menyimpang kalau disalin:
 *
 *   libur nasional tanpa shift   → libur
 *   cuti penuh                   → cuti          (izin parsial tetap dihitung hadir)
 *   shift OFF                    → off
 *   ada presensi                 → hadir (+terlambat bila is_late & bukan izin telat)
 *   terjadwal kerja, tak absen,
 *   dan tanggalnya sudah lewat   → alpha
 *
 * Override `schedule_assignments` menang atas libur; template hanya berlaku di hari biasa,
 * dan diresolusi PER TANGGAL karena jadwal seseorang bisa berubah di tengah bulan.
 */
class MonthlyAttendance
{
    private const DAY_NAMES = ['', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    /**
     * @param  bool  $includeSensitive  Sertakan foto & koordinat GPS. Dimatikan untuk tampilan
     *                                  manager di portal — dia butuh kehadiran, bukan wajah & lokasi.
     * @return array{stats: array<string,int>, days: array<int,array<string,mixed>>}
     */
    public static function build(Employee $employee, Carbon $period, bool $includeSensitive = true): array
    {
        $startOfMonth = $period->copy()->startOfMonth();
        $endOfMonth = $period->copy()->endOfMonth();

        $employee->loadMissing(Employee::scheduleTemplateEagerLoads());

        $overrides = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn ($o) => Carbon::parse($o->date)->format('Y-m-d'));

        $attendances = Attendance::where('employee_id', $employee->id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->date)->format('Y-m-d'));

        $holidays = Holiday::where('company_id', $employee->company_id)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->get()
            ->keyBy(fn ($h) => Carbon::parse($h->date)->format('Y-m-d'));

        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $endOfMonth->format('Y-m-d'))
            ->where('end_date', '>=', $startOfMonth->format('Y-m-d'))
            ->get();

        $days = [];
        $stats = ['hadir' => 0, 'terlambat' => 0, 'alpha' => 0, 'cuti' => 0, 'off' => 0, 'libur' => 0];

        for ($d = 1; $d <= $period->daysInMonth; $d++) {
            $date = $startOfMonth->copy()->addDays($d - 1);
            $dateStr = $date->format('Y-m-d');
            $dow = $date->dayOfWeekIso;

            $holiday = $holidays[$dateStr] ?? null;

            $leave = AttendanceLateExcuse::firstForDate($leaves, $date);
            $lateExcuse = AttendanceLateExcuse::isLateArrivalLeave($leave) ? $leave : null;
            $earlyDeparture = AttendanceLateExcuse::isEarlyDepartureLeave($leave) ? $leave : null;
            $partialDayLeave = $lateExcuse || $earlyDeparture;

            // Override menang atas libur; template hanya berlaku di hari biasa.
            $shift = null;
            if (! $leave || $partialDayLeave) {
                if (isset($overrides[$dateStr])) {
                    $shift = $overrides[$dateStr]->shift;
                } elseif (! $holiday) {
                    $shift = $employee->scheduleTemplateOn($date)?->getShiftForDay($dow);
                }
            }

            $att = $attendances[$dateStr] ?? null;

            if ($holiday && ! $shift) {
                $stats['libur']++;
            } elseif ($leave && ! $partialDayLeave) {
                $stats['cuti']++;
            } elseif ($shift && $shift->is_off) {
                $stats['off']++;
            } elseif ($att) {
                if (! AttendanceLateExcuse::manualPermissionStatusLabel($att->status) && $att->is_late && ! $lateExcuse) {
                    $stats['terlambat']++;
                }
                $stats['hadir']++;
            } elseif ($shift && ! $shift->is_off && $date->lte(Carbon::today())) {
                $stats['alpha']++;
            }

            $days[] = [
                'date' => $dateStr,
                'day' => $d,
                'day_name' => self::DAY_NAMES[$dow],
                'is_today' => $date->isToday(),
                'holiday' => $holiday ? $holiday->name : null,
                'leave' => $leave && ! $partialDayLeave ? ['type' => $leave->leaveType->name ?? 'Cuti'] : null,
                'late_excuse' => $lateExcuse ? ['type' => $lateExcuse->leaveType->name ?? AttendanceLateExcuse::SHORT_LABEL] : null,
                'early_leave' => $earlyDeparture ? ['type' => $earlyDeparture->leaveType->name ?? AttendanceLateExcuse::EARLY_DEPARTURE_SHORT_LABEL] : null,
                'shift' => $shift ? [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time ? substr($shift->start_time, 0, 5) : null,
                    'end_time' => $shift->end_time ? substr($shift->end_time, 0, 5) : null,
                    'color' => $shift->color,
                    'is_off' => $shift->is_off,
                ] : null,
                'attendance' => $att ? self::attendancePayload($att, $lateExcuse, $earlyDeparture, $includeSensitive) : null,
            ];
        }

        return ['stats' => $stats, 'days' => $days];
    }

    /**
     * Urutan kunci dipertahankan persis seperti respons API sebelumnya — aplikasi mobile
     * sudah memakainya. Kunci sensitif dibuang, bukan disusun ulang.
     *
     * @return array<string,mixed>
     */
    private static function attendancePayload(Attendance $att, $lateExcuse, $earlyDeparture, bool $includeSensitive): array
    {
        $payload = [
            'id' => $att->id,
            'clock_in' => $att->clock_in,
            'clock_out' => $att->clock_out,
            'clock_in_photo' => $att->clock_in_photo,
            'clock_out_photo' => $att->clock_out_photo,
            'clock_in_lat' => $att->clock_in_lat,
            'clock_in_lng' => $att->clock_in_lng,
            'clock_out_lat' => $att->clock_out_lat,
            'clock_out_lng' => $att->clock_out_lng,
            'status' => $att->status,
            'is_late' => $att->is_late,
            'status_label' => AttendanceLateExcuse::manualPermissionStatusLabel($att->status)
                ?? ($att->is_late && $lateExcuse
                    ? AttendanceLateExcuse::STATUS_LABEL
                    : ($att->is_late ? 'Terlambat' : ($earlyDeparture ? AttendanceLateExcuse::EARLY_DEPARTURE_STATUS_LABEL : 'Hadir'))),
            'is_remote' => $att->is_remote,
            'remote_notes' => $att->remote_notes,
        ];

        if ($includeSensitive) {
            return $payload;
        }

        return Arr::except($payload, [
            'clock_in_photo', 'clock_out_photo',
            'clock_in_lat', 'clock_in_lng', 'clock_out_lat', 'clock_out_lng',
        ]);
    }
}
