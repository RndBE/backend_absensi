<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\Lpj;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\TravelReport;
use Illuminate\Support\Carbon;

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
     *
     * @return array{sent: int, skipped: int}
     */
    public static function remindForDate(Carbon $date): array
    {
        if (! self::isEnabled()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $reminderDays = self::reminderDays();

        // Tanggal pulang yang memicu reminder hari ini.
        $targetReturnDate = $date->copy()->subDays($reminderDays)->toDateString();

        $reports = TravelReport::with(['budgetRequest', 'employee'])
            ->whereNotNull('return_date')
            ->whereDate('return_date', $targetReturnDate)
            ->whereHas('budgetRequest', fn ($q) => $q
                ->whereIn('status', ['approved', 'paid']))
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($reports as $report) {
            $budget = $report->budgetRequest;
            $employee = $report->employee;

            if (! $budget || ! $employee) {
                $skipped++;
                continue;
            }

            $lpjExists = Lpj::where('budget_request_id', $budget->id)
                ->where('employee_id', $employee->id)
                ->exists();
            if ($lpjExists) {
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
}
