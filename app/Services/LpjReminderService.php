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
        $targetReturnDate = $date->copy()->subDays($reminderDays)->toDateString();

        $reports = TravelReport::with(['budgetRequest', 'employee'])
            ->whereNotNull('return_date')
            ->whereDate('return_date', $targetReturnDate)
            ->whereHas('budgetRequest', fn ($q) => $q->whereIn('status', ['approved', 'paid']))
            ->when($companyId, fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('company_id', $companyId)))
            ->get();

        $items = collect();

        foreach ($reports as $report) {
            $budget = $report->budgetRequest;
            $employee = $report->employee;
            if (! $budget || ! $employee) {
                continue;
            }
            if (Lpj::where('budget_request_id', $budget->id)->where('employee_id', $employee->id)->exists()) {
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
}
