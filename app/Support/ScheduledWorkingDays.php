<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleTemplate;
use Illuminate\Support\Carbon;

class ScheduledWorkingDays
{
    /**
     * Jumlah hari kerja terjadwal karyawan dalam rentang [start, end] (inklusif).
     *
     * Hari dianggap "kerja" jika dijadwalkan masuk:
     *   - Override jadwal (schedule_assignments) ada & shift-nya bukan OFF; ATAU
     *   - Bukan hari libur DAN template mingguan hari itu ada shift bukan OFF; ATAU
     *   - Karyawan memakai work schedule tetap.
     * Hari OFF, libur nasional, dan hari tanpa jadwal tidak dihitung.
     *
     * @param  array<int,string>|null  $holidayDates  Daftar tanggal libur (Y-m-d). Null = query sendiri.
     * @param  bool  $forPayroll  Tanggal sebelum riwayat template paling awal memakai template
     *                            baris itu (lihat templateForCounting). Hanya untuk hitungan payroll.
     */
    public static function count(Employee $employee, Carbon $start, Carbon $end, ?array $holidayDates = null, bool $forPayroll = false): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $overrides = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->date)->toDateString());

        if ($holidayDates === null) {
            $holidayDates = Holiday::where('company_id', $employee->company_id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all();
        }
        $holidaySet = array_flip($holidayDates);

        $employee->loadMissing([...Employee::scheduleTemplateEagerLoads(), 'workSchedule']);

        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        while ($cursor->lte($last)) {
            if (self::isWorkingDay($employee, $cursor, $overrides, $holidaySet, $forPayroll)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    /**
     * Total hari kerja "sebulan penuh" untuk dipakai sebagai PEMBAGI pro-rate.
     *
     * - Punya template mingguan / work schedule → hitung langsung (pola sudah mencakup sebulan).
     * - Hanya assignment per-hari (bisa terpotong karena resign/join) → simpulkan pola hari kerja
     *   mingguan dari assignment yang ada (mis. off tiap Rabu), lalu terapkan ke sebulan penuh.
     * - Tidak ada jadwal sama sekali → 0 (pemanggil fallback ke hari kalender).
     *
     * @param  array<int,string>|null  $holidayDates
     */
    public static function monthlyWorkingDays(Employee $employee, Carbon $start, Carbon $end, ?array $holidayDates = null): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        $employee->loadMissing([...Employee::scheduleTemplateEagerLoads(), 'workSchedule']);

        // Pola berulang sudah mencakup sebulan penuh → hitung apa adanya.
        // `schedule_template_id` tetap menjadi penunjuk "template yang berlaku sekarang",
        // jadi cukup diperiksa itu — riwayat selalu punya penunjuk yang bersesuaian.
        if ($employee->schedule_template_id || $employee->work_schedule_id) {
            return self::count($employee, $start, $end, $holidayDates, forPayroll: true);
        }

        $assignments = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        if ($assignments->isEmpty()) {
            return 0;
        }

        // Simpulkan hari kerja mingguan (ISO: 1=Sen..7=Min): hari yang muncul sebagai
        // shift kerja dianggap hari kerja; hari yang muncul hanya sebagai OFF dianggap libur mingguan.
        $working = [];
        $offOnly = [];
        foreach ($assignments as $a) {
            $dow = Carbon::parse($a->date)->dayOfWeekIso;
            if ($a->shift && ! $a->shift->is_off) {
                $working[$dow] = true;
            } elseif ($a->shift && $a->shift->is_off) {
                $offOnly[$dow] = ($offOnly[$dow] ?? true);
            }
        }
        // Hari yang pernah kerja pasti dihitung kerja (buang dari daftar off).
        foreach (array_keys($working) as $dow) {
            unset($offOnly[$dow]);
        }

        // Bila tak terlihat kerja di akhir pekan & tak ada info, pakai standar Senin–Jumat.
        if ($working === []) {
            return 0;
        }
        if (! isset($working[6]) && ! isset($working[7]) && count($working) < 5) {
            $working = [1 => true, 2 => true, 3 => true, 4 => true, 5 => true];
            foreach (array_keys($working) as $dow) {
                unset($offOnly[$dow]);
            }
        }

        if ($holidayDates === null) {
            $holidayDates = Holiday::where('company_id', $employee->company_id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->pluck('date')
                ->map(fn ($d) => Carbon::parse($d)->toDateString())
                ->all();
        }
        $holidaySet = array_flip($holidayDates);

        // Deteksi apakah karyawan tetap bekerja saat libur: jika ada shift kerja pada
        // tanggal yang berstatus libur di jadwalnya, maka libur dihitung sebagai hari kerja
        // (mis. security). Kalau tidak, libur tetap dikurangi dari pembagi.
        $worksOnHolidays = false;
        foreach ($assignments as $a) {
            if ($a->shift && ! $a->shift->is_off && isset($holidaySet[Carbon::parse($a->date)->toDateString()])) {
                $worksOnHolidays = true;
                break;
            }
        }

        $count = 0;
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();
        while ($cursor->lte($last)) {
            $dow = $cursor->dayOfWeekIso;
            $isHoliday = isset($holidaySet[$cursor->toDateString()]);
            // Hari kerja bila: dow termasuk hari kerja mingguan, bukan off mingguan, DAN
            // (bukan libur ATAU karyawan memang bekerja saat libur).
            if (isset($working[$dow]) && ! isset($offOnly[$dow]) && (! $isHoliday || $worksOnHolidays)) {
                $count++;
            }
            $cursor->addDay();
        }

        return $count;
    }

    /**
     * Apakah satu $date adalah hari KERJA terjadwal bagi $employee (bukan off/libur).
     * Dipakai mis. auto-deteksi tipe lembur (hari kerja vs libur) di form pengajuan.
     */
    public static function isWorkingDate(Employee $employee, Carbon $date): bool
    {
        $dateStr = $date->toDateString();

        $overrides = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', $dateStr)
            ->get()
            ->keyBy(fn ($a) => Carbon::parse($a->date)->toDateString());

        $holidaySet = Holiday::where('company_id', $employee->company_id)
            ->whereDate('date', $dateStr)
            ->exists() ? [$dateStr => true] : [];

        // Relasi template/workSchedule di-load lazy di dalam isWorkingDay hanya bila FK terisi
        // (akses ter-guard), jadi tak perlu preload untuk satu tanggal.
        return self::isWorkingDay($employee, $date->copy()->startOfDay(), $overrides, $holidaySet);
    }

    /**
     * Template untuk menghitung hari kerja pada $date.
     *
     * Riwayat template karyawan baru biasanya baru dimulai di tanggal join, sehingga
     * scheduleTemplateOn() bernilai null untuk hari-hari sebelumnya. Kalau dibiarkan, pembagi
     * pro-rate ikut terpotong ke tanggal join (rasio jadi 1, gaji tidak terprorata). Tanggal
     * sebelum baris riwayat paling awal memakai template baris itu. Baris dengan template_id
     * NULL ("sejak tanggal ini tanpa template") tetap dihormati.
     */
    private static function templateForCounting(Employee $employee, Carbon $date): ?ScheduleTemplate
    {
        $template = $employee->scheduleTemplateOn($date);
        if ($template || ! Employee::hasScheduleTemplateHistory()) {
            return $template;
        }

        $earliest = $employee->scheduleTemplateHistory->sortBy('effective_from')->first();
        if (! $earliest || ! $earliest->effective_from->copy()->startOfDay()->gt($date)) {
            return $template;
        }

        return $earliest->template;
    }

    private static function isWorkingDay(Employee $employee, Carbon $date, $overrides, array $holidaySet, bool $forPayroll = false): bool
    {
        $dateStr = $date->toDateString();

        // 1. Override jadwal per tanggal (paling menang).
        $override = $overrides->get($dateStr);
        if ($override) {
            return $override->shift && ! $override->shift->is_off;
        }

        // 2. Libur nasional.
        if (isset($holidaySet[$dateStr])) {
            return false;
        }

        // 3. Template mingguan YANG BERLAKU pada tanggal itu (bukan yang terpasang sekarang).
        //    Sengaja TIDAK memakai isEmployedOn(): pembagi pro-rate payroll harus tetap
        //    "hari kerja sebulan penuh", termasuk hari sebelum karyawan join.
        $template = $forPayroll ? self::templateForCounting($employee, $date) : $employee->scheduleTemplateOn($date);
        if ($shift = $template?->getShiftForDay($date->dayOfWeekIso)) {
            return ! $shift->is_off;
        }

        // 4. Work schedule tetap (tanpa info hari off → dianggap kerja).
        if ($employee->work_schedule_id && $employee->workSchedule) {
            return true;
        }

        return false;
    }
}
