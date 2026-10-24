<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\Lpj;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\TravelReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class LpjReminderService
{
    /** Default hari setelah tanggal pulang untuk mengingatkan pembuatan LPJ. */
    public const REMINDER_DAYS = 3;

    /** Apakah reminder LPJ diaktifkan (dari Pengaturan Presensi). */
    public static function isEnabled(): bool
    {
        return Setting::getValue('lpj_reminder_enabled', '1') === '1';
    }

    /** Jumlah hari setelah pulang, dari pengaturan (fallback ke default). */
    public static function reminderDays(): int
    {
        return max(1, (int) Setting::getValue('lpj_reminder_days', self::REMINDER_DAYS));
    }

    /**
     * Kirim pengingat LPJ untuk perjalanan yang tanggal pulangnya tepat
     * sejumlah hari yang dikonfigurasi sebelum $date, dan LPJ-nya belum dibuat.
     * LPJ satu per anggaran dan dibuat pemilik anggaran, jadi hanya pemilik yang diingatkan,
     * sekali per anggaran, walau LHP-nya dibuat beberapa peserta.
     *
     * @return array{sent: int, skipped: int}
     */
    public static function remindForDate(Carbon $date): array
    {
        if (! self::isEnabled()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $reminderDays = self::reminderDays();

        $sent = 0;
        $skipped = 0;

        foreach (self::budgetsReturningOn($date, $reminderDays) as $budget) {
            $employee = $budget->employee;

            if (! $employee) {
                $skipped++;
                continue;
            }

            if (Lpj::where('budget_request_id', $budget->id)->exists()) {
                $skipped++;
                continue;
            }

            // Hindari kirim ganda untuk anggaran yang sama.
            $alreadySent = Notification::where('employee_id', $employee->id)
                ->where('type', 'lpj_reminder')
                ->where('reference_type', BudgetRequest::class)
                ->where('reference_id', $budget->id)
                ->exists();

            if ($alreadySent) {
                $skipped++;
                continue;
            }

            $notif = Notification::create([
                'employee_id'    => $employee->id,
                'title'          => 'Pengingat LPJ',
                'message'        => "Jangan lupa membuat LPJ untuk \"{$budget->title}\". Sudah {$reminderDays} hari sejak tanggal pulang.",
                'type'           => 'lpj_reminder',
                'reference_type' => BudgetRequest::class,
                'reference_id'   => $budget->id,
            ]);

            FcmService::sendToEmployee($employee, $notif->title, $notif->message, [
                'type'           => 'lpj_reminder',
                'reference_type' => 'lpj',
                'reference_id'   => (string) $budget->id,
            ]);

            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /**
     * Daftar penerima yang JATUH TEMPO diingatkan LPJ pada $date (read-only, tanpa
     * mengirim). Dipakai kanal luar (mis. Tessa/WhatsApp). Berbasis state: hanya yang
     * LPJ-nya belum dibuat. Dedup antar-panggilan diatur oleh kadens pemanggil.
     *
     * @return Collection<int, array{employee: \App\Models\Employee, title: string, message: string, reference_id: int}>
     */
    public static function dueForDate(Carbon $date, ?int $companyId = null): Collection
    {
        if (! self::isEnabled()) {
            return collect();
        }

        $reminderDays = self::reminderDays();

        $items = collect();

        foreach (self::budgetsReturningOn($date, $reminderDays) as $budget) {
            $employee = $budget->employee;
            if (! $employee || ($companyId && (int) $employee->company_id !== $companyId)) {
                continue;
            }
            if (Lpj::where('budget_request_id', $budget->id)->exists()) {
                continue;
            }

            $items->push([
                'employee' => $employee,
                'title' => 'Pengingat LPJ',
                'message' => "Halo {$employee->full_name}, jangan lupa membuat LPJ untuk \"{$budget->title}\". Sudah {$reminderDays} hari sejak tanggal pulang.",
                'reference_id' => $budget->id,
            ]);
        }

        return $items;
    }

    /**
     * Anggaran disetujui/cair yang punya LHP dengan tanggal pulang tepat $reminderDays hari
     * sebelum $date, sekali per anggaran beserta pemiliknya.
     *
     * @return Collection<int, BudgetRequest>
     */
    private static function budgetsReturningOn(Carbon $date, int $reminderDays): Collection
    {
        $targetReturnDate = $date->copy()->subDays($reminderDays)->toDateString();

        $budgetIds = TravelReport::whereNotNull('return_date')
            ->whereDate('return_date', $targetReturnDate)
            ->whereNotNull('budget_request_id')
            ->distinct()
            ->pluck('budget_request_id');

        return BudgetRequest::with('employee')
            ->whereIn('id', $budgetIds)
            ->whereIn('status', ['approved', 'paid'])
            ->get();
    }
}
