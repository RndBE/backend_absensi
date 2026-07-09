<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Notification;
use App\Models\ScheduleAssignment;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Pengingat clock-in — SHIFT-AWARE & PRA-SHIFT: karyawan diingatkan beberapa menit
 * SEBELUM jam masuknya sendiri (setting `clockin_reminder_before`) bila belum clock-in.
 * Jadi tiap orang diingatkan menjelang jam masuknya (jam 8, jam 9, security malam, dst),
 * bukan satu jam global untuk semua — agar absen tepat waktu.
 *
 * Jadwal seseorang diresolusi dengan tangga yang SAMA seperti App\Support\ScheduledWorkingDays:
 *   1. Override `schedule_assignments` pada tanggal itu — paling menang (juga menang atas libur,
 *      mis. security yang tetap masuk saat tanggal merah).
 *   2. Hari libur perusahaan — membatalkan jalur template.
 *   3. Template mingguan `employees.schedule_template_id` → `schedule_template_days`.
 * Karyawan yang hanya punya `work_schedule_id` belum tercakup: work schedule tidak menyimpan
 * jam mulai per hari, jadi waktu ingatkan tak bisa dihitung.
 *
 * Dua jalur berbagi kandidat & logika yang sama:
 * - dueForDate(): read-only, berbasis jendela `since` — untuk kanal luar (Tessa poll).
 * - remindForNow(): mengirim (WhatsApp + in-app + FCM) dengan dedup harian — untuk scheduler backend.
 */
class ClockinReminderService
{
    /** Default menit sebelum jam masuk untuk mengingatkan. */
    public const BEFORE_MINUTES = 15;

    /** Toleransi menit SETELAH jam masuk agar yang terlambat tetap dapat satu nudge. */
    public const LATE_GRACE_MINUTES = 30;

    public static function isEnabled(): bool
    {
        return Setting::getValue('clockin_reminder_enabled', '0') === '1';
    }

    /** Menit sebelum jam masuk, dari Pengaturan Presensi (fallback ke default). */
    public static function beforeMinutes(): int
    {
        return max(0, (int) Setting::getValue('clockin_reminder_before', self::BEFORE_MINUTES));
    }

    /**
     * Daftar penerima reminder clock-in yang JATUH TEMPO pada jendela (since, now] (read-only,
     * tanpa mengirim). Dipakai kanal luar (Tessa/WhatsApp) yang men-dedup lewat `since`.
     * $since kosong → lookback 30 menit.
     *
     * @return Collection<int, array{employee_id:int, name:string, phone:?string, shift_start:string, title:string, message:string}>
     */
    public static function dueForDate(Carbon $date, ?int $companyId = null, ?Carbon $since = null): Collection
    {
        if (! self::isEnabled()) {
            return collect();
        }

        $now = now();
        $since = $since ?: $now->copy()->subMinutes(30);
        $dateStr = $date->toDateString();
        $before = self::beforeMinutes();

        return self::candidates($date, $companyId)
            ->filter(function (array $c) use ($dateStr, $now, $since, $before) {
                $remindAt = self::remindAt($c['shift_start'], $dateStr, $before);

                return $remindAt->gt($since) && $remindAt->lte($now);
            })
            ->map(fn (array $c) => self::payload($c))
            ->values();
    }

    /**
     * Kirim reminder clock-in untuk semua yang jatuh tempo pada $now — WhatsApp (gateway
     * backend) + notifikasi in-app + push FCM. Dedup: satu reminder per karyawan per hari,
     * sehingga aman dipanggil tiap menit oleh scheduler tanpa dobel.
     *
     * @return array{sent:int, skipped:int, wa_failed:int}
     */
    public static function remindForNow(Carbon $now, ?int $companyId = null): array
    {
        if (! self::isEnabled()) {
            return ['sent' => 0, 'skipped' => 0, 'wa_failed' => 0];
        }

        $date = $now->copy()->startOfDay();
        $dateStr = $date->toDateString();
        $before = self::beforeMinutes();

        $sent = 0;
        $skipped = 0;
        $waFailed = 0;

        foreach (self::candidates($date, $companyId) as $c) {
            $employee = $c['employee'];
            $remindAt = self::remindAt($c['shift_start'], $dateStr, $before);

            // Jendela kirim: dari waktu ingatkan sampai sedikit setelah jam masuk (nudge terlambat).
            $windowEnd = Carbon::parse($dateStr.' '.$c['shift_start'])->addMinutes(self::LATE_GRACE_MINUTES);
            if ($now->lt($remindAt) || $now->gt($windowEnd)) {
                $skipped++;
                continue;
            }

            // Dedup harian: sudah pernah diingatkan hari ini → lewati.
            if (self::alreadyReminded($employee->id, $date)) {
                $skipped++;
                continue;
            }

            $payload = self::payload($c);

            // Notifikasi in-app (sekaligus penanda dedup). Jalur template tak punya assignment
            // untuk dirujuk, jadi referensinya dikosongkan.
            $notif = Notification::create([
                'employee_id' => $employee->id,
                'title' => $payload['title'],
                'message' => $payload['message'],
                'type' => 'clockin_reminder',
                'reference_type' => $c['assignment_id'] ? ScheduleAssignment::class : null,
                'reference_id' => $c['assignment_id'],
            ]);

            // Push FCM ke aplikasi mobile (lewati diam-diam bila tak ada token).
            FcmService::sendToEmployee($employee, $notif->title, $notif->message, [
                'type' => 'clockin_reminder',
                'reference_type' => 'attendance',
                'reference_id' => (string) ($c['assignment_id'] ?? $employee->id),
            ]);

            // WhatsApp (best-effort — kegagalan satu orang tak menggagalkan batch).
            if (filled($employee->phone)) {
                try {
                    app(WhatsAppGatewayService::class)->sendText($employee->phone, $payload['message']);
                } catch (\Throwable $e) {
                    $waFailed++;
                    Log::warning('Clock-in reminder WhatsApp gagal terkirim', [
                        'employee_id' => $employee->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'wa_failed' => $waFailed];
    }

    /**
     * Karyawan aktif (dalam scope perusahaan) yang terjadwal MASUK pada $date, punya jam mulai
     * shift, dan BELUM clock-in. Jadwal diambil dari override per tanggal lebih dulu; sisanya
     * dari template mingguan (kecuali hari libur).
     *
     * @return Collection<int, array{employee: Employee, shift_start: string, assignment_id: ?int}>
     */
    private static function candidates(Carbon $date, ?int $companyId): Collection
    {
        $dateStr = $date->toDateString();

        $clockedIn = Attendance::whereDate('date', $dateStr)
            ->whereNotNull('clock_in')
            ->pluck('employee_id')
            ->flip();

        $out = collect();

        // 1. Override per tanggal — menang atas template DAN atas hari libur.
        $assignments = ScheduleAssignment::query()
            ->whereDate('date', $dateStr)
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)
                ->when($companyId, fn ($e) => $e->where('company_id', $companyId)))
            ->with(['employee', 'shift:id,start_time,is_off'])
            ->get();

        $overridden = [];

        foreach ($assignments as $a) {
            if (! $a->employee) {
                continue;
            }

            // Ditandai "sudah diputuskan lewat override" walau hasilnya libur / sudah clock-in,
            // supaya template tidak menimpanya di bawah.
            $overridden[$a->employee_id] = true;

            if ($clockedIn->has($a->employee_id) || ! $a->shift || $a->shift->is_off || ! $a->shift->start_time) {
                continue;
            }

            $out->push([
                'employee' => $a->employee,
                'shift_start' => (string) $a->shift->start_time,
                'assignment_id' => $a->id,
            ]);
        }

        // 2. Template mingguan, untuk yang tak punya override pada tanggal itu.
        $dayOfWeek = $date->dayOfWeekIso;

        $templated = Employee::query()
            ->where('is_active', true)
            ->whereNotNull('schedule_template_id')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->when($overridden !== [], fn ($q) => $q->whereNotIn('id', array_keys($overridden)))
            ->with('scheduleTemplate.days.shift')
            ->get();

        // Libur perusahaan pada tanggal itu — hanya membatalkan jalur template.
        $holidayCompanies = Holiday::whereDate('date', $dateStr)->pluck('company_id')->flip();

        foreach ($templated as $employee) {
            if ($clockedIn->has($employee->id) || $holidayCompanies->has($employee->company_id)) {
                continue;
            }

            $shift = $employee->scheduleTemplate?->getShiftForDay($dayOfWeek);
            if (! $shift || $shift->is_off || ! $shift->start_time) {
                continue;
            }

            $out->push([
                'employee' => $employee,
                'shift_start' => (string) $shift->start_time,
                'assignment_id' => null,
            ]);
        }

        return $out->unique(fn (array $c) => $c['employee']->id)->values();
    }

    /** Waktu ingatkan = jam masuk shift DIKURANGI jeda (menit). */
    private static function remindAt(string $shiftStart, string $dateStr, int $before): Carbon
    {
        return Carbon::parse($dateStr.' '.$shiftStart)->subMinutes($before);
    }

    /** Satu reminder per karyawan per hari — berlaku untuk jalur override maupun template. */
    private static function alreadyReminded(int $employeeId, Carbon $date): bool
    {
        return Notification::where('employee_id', $employeeId)
            ->where('type', 'clockin_reminder')
            ->whereDate('created_at', $date->toDateString())
            ->exists();
    }

    /** Baris siap-kirim (wire-safe: tanpa model Employee). */
    private static function payload(array $c): array
    {
        $start = substr($c['shift_start'], 0, 5);
        $name = $c['employee']->full_name;

        return [
            'employee_id' => $c['employee']->id,
            'name' => $name,
            'phone' => $c['employee']->phone,
            'shift_start' => $start,
            'title' => 'Pengingat Clock-In',
            'message' => "Halo {$name}, shift Anda mulai pukul {$start}. Jangan lupa clock-in ya.",
        ];
    }
}
