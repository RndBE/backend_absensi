<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sumber tunggal pemetaan jenis izin/cuti -> kategori, dipakai bersama oleh rekap
 * presensi, API, dan sinkronisasi ke DailyCloseApp.
 *
 * Pembedaan yang menentukan: hanya CUTI dan SAKIT membuat karyawan tidak bekerja
 * sehari penuh. WFH dan izin parsial (datang terlambat / pulang cepat) tetap hari
 * kerja — orangnya tetap masuk, jadi kewajiban laporan harian tetap berlaku dan
 * hari itu tidak boleh dikecualikan dari perhitungan apa pun yang berbasis "kerja".
 *
 * Menyamakan keempat kategori adalah salah hitung yang paling mudah terjadi:
 * orang yang cuma izin terlambat sejam ikut hilang dari daftar "belum lapor".
 */
class LeaveDayCategory
{
    public const CUTI = 'cuti';
    public const SAKIT = 'sakit';
    public const WFH = 'wfh';
    public const IZIN_PARSIAL = 'izin_parsial';

    /**
     * Kategori sebuah pengajuan, atau null kalau tidak ada pengajuan.
     *
     * Urutan pemeriksaan mengikuti rekap presensi: izin parsial diperiksa lebih
     * dulu supaya nama tipe yang mengandung "sakit" sekalipun tidak menutupi fakta
     * bahwa izinnya cuma sebagian hari.
     */
    public static function for(?LeaveRequest $leave): ?string
    {
        if (! $leave) {
            return null;
        }

        if (AttendanceLateExcuse::isPartialDayLeave($leave)) {
            return self::IZIN_PARSIAL;
        }

        if (self::isSick($leave)) {
            return self::SAKIT;
        }

        if (AttendanceLateExcuse::isWfhLeave($leave)) {
            return self::WFH;
        }

        return self::CUTI;
    }

    public static function isSick(?LeaveRequest $leave): bool
    {
        $name = Str::lower((string) ($leave?->leaveType?->name ?? ''));

        return Str::contains($name, ['sakit', 'sick', 'medical']);
    }

    /**
     * Benar hanya kalau karyawan tidak bekerja sehari penuh (cuti atau sakit).
     * WFH dan izin parsial sengaja dijawab false — lihat catatan kelas.
     */
    public static function isFullDayAway(?LeaveRequest $leave): bool
    {
        return in_array(self::for($leave), [self::CUTI, self::SAKIT], true);
    }

    public static function label(?string $category): ?string
    {
        return match ($category) {
            self::CUTI => 'Cuti',
            self::SAKIT => 'Sakit',
            self::WFH => 'WFH',
            self::IZIN_PARSIAL => 'Izin Parsial',
            default => null,
        };
    }

    /**
     * Tanggal (Y-m-d) dalam rentang saat karyawan tidak bekerja sehari penuh karena
     * cuti/sakit yang sudah di-ACC. Hasilnya unik dan terurut.
     *
     * @return list<string>
     */
    public static function fullDayAwayDates(
        Employee|int $employee,
        CarbonInterface|string $start,
        CarbonInterface|string $end
    ): array {
        $employeeId = $employee instanceof Employee ? $employee->id : (int) $employee;
        $periodStart = self::startOfDay($start);
        $periodEnd = self::startOfDay($end);

        if ($periodStart->gt($periodEnd)) {
            return [];
        }

        $leaves = LeaveRequest::with('leaveType')
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $periodEnd->toDateString())
            ->where('end_date', '>=', $periodStart->toDateString())
            ->get()
            ->filter(fn (LeaveRequest $leave) => self::isFullDayAway($leave));

        $dates = [];

        foreach ($leaves as $leave) {
            // Jangan pakai max()/min() Carbon di sini: keduanya bisa mengembalikan
            // instance $periodStart/$periodEnd itu sendiri, dan addDay() di bawah
            // akan menggeser tanggal periode milik pemanggil.
            $cursor = self::startOfDay($leave->start_date);
            if ($cursor->lt($periodStart)) {
                $cursor = $periodStart->copy();
            }

            $until = self::startOfDay($leave->end_date);
            if ($until->gt($periodEnd)) {
                $until = $periodEnd->copy();
            }

            while ($cursor->lte($until)) {
                $dates[$cursor->toDateString()] = true;
                $cursor->addDay();
            }
        }

        $dates = array_keys($dates);
        sort($dates);

        return $dates;
    }

    private static function startOfDay(CarbonInterface|string $date): Carbon
    {
        $carbon = $date instanceof CarbonInterface
            ? Carbon::instance($date)
            : Carbon::parse($date);

        return $carbon->copy()->startOfDay();
    }
}
