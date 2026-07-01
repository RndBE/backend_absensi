<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\TravelReport;
use App\Support\WorkingDays;
use Illuminate\Support\Carbon;

class LhpReminderService
{
    /** Default hari (kalender) setelah pulang untuk pengingat pertama. */
    public const AFTER_DAYS = 1;

    /** Default H- (hari kerja) sebelum batas untuk pengingat kedua. */
    public const BEFORE_DAYS = 2;

    public static function isEnabled(): bool
    {
        return Setting::getValue('lhp_reminder_enabled', '1') === '1';
    }

    /** Jumlah hari setelah pulang untuk nudge pertama. */
    public static function afterDays(): int
    {
        return max(1, (int) Setting::getValue('lhp_reminder_after_days', self::AFTER_DAYS));
    }

    /** Jumlah hari kerja sebelum batas untuk pengingat kedua. */
    public static function beforeDays(): int
    {
        return max(1, (int) Setting::getValue('lhp_reminder_before_days', self::BEFORE_DAYS));
    }

    /**
     * Kirim pengingat LHP pada dua momen: (1) beberapa hari setelah pulang, dan
     * (2) beberapa hari kerja sebelum batas pengumpulan. Hanya untuk anggaran
     * perjalanan yang sudah cair, punya tanggal pulang, dan LHP-nya belum dibuat.
     *
     * @return array{sent: int, skipped: int}
     */
    public static function remindForDate(Carbon $date): array
    {
        if (! self::isEnabled()) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $today = $date->copy()->startOfDay();
        $afterDays = self::afterDays();
        $beforeDays = self::beforeDays();

        $budgets = BudgetRequest::with([
                'employee:id,full_name,company_id',
                'participants:id,full_name,company_id',
            ])
            ->whereIn('status', ['approved', 'paid'])
            ->whereNotNull('return_date')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($budgets as $budget) {
            $deadline = $budget->lhpDeadlineDate();

            $isAfterReturn = $budget->return_date
                && $today->isSameDay($budget->return_date->copy()->addDays($afterDays));

            $isBeforeDeadline = $deadline
                && WorkingDays::add($today, $beforeDays, $budget->employee?->company_id)->isSameDay($deadline);

            if (! $isAfterReturn && ! $isBeforeDeadline) {
                continue;
            }

            // Susun pesan per pemicu yang aktif hari ini.
            $targets = [];
            if ($isAfterReturn) {
                $targets[] = [
                    'type' => 'lhp_reminder_after',
                    'message' => "Anda sudah pulang dari perjalanan \"{$budget->title}\". Jangan lupa membuat LHP.",
                ];
            }
            if ($isBeforeDeadline) {
                $targets[] = [
                    'type' => 'lhp_reminder_deadline',
                    'message' => "Batas pengumpulan LHP \"{$budget->title}\" pada {$deadline->translatedFormat('d M Y')}. Segera buat LHP agar tidak terlambat.",
                ];
            }

            // Sasaran: pemilik anggaran + peserta yang di-tag, yang belum membuat LHP-nya.
            foreach (self::responsibleEmployees($budget) as $employee) {
                if (self::hasTravelReport($budget->id, $employee->id)) {
                    $skipped++;
                    continue;
                }

                foreach ($targets as $target) {
                    if (self::alreadySent($employee->id, $target['type'], $budget->id)) {
                        $skipped++;
                        continue;
                    }

                    self::dispatch($budget, $employee, $target['type'], $target['message']);
                    $sent++;
                }
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    /** Pemilik anggaran + peserta yang di-tag, unik per id. */
    private static function responsibleEmployees(BudgetRequest $budget)
    {
        return collect([$budget->employee])
            ->concat($budget->participants)
            ->filter()
            ->unique('id')
            ->values();
    }

    private static function hasTravelReport(int $budgetId, int $employeeId): bool
    {
        return TravelReport::where('budget_request_id', $budgetId)
            ->where('employee_id', $employeeId)
            ->exists();
    }

    private static function alreadySent(int $employeeId, string $type, int $budgetId): bool
    {
        return Notification::where('employee_id', $employeeId)
            ->where('type', $type)
            ->where('reference_type', BudgetRequest::class)
            ->where('reference_id', $budgetId)
            ->exists();
    }

    private static function dispatch(BudgetRequest $budget, Employee $employee, string $type, string $message): void
    {
        $notif = Notification::create([
            'employee_id'    => $employee->id,
            'title'          => 'Pengingat LHP',
            'message'        => $message,
            'type'           => $type,
            'reference_type' => BudgetRequest::class,
            'reference_id'   => $budget->id,
        ]);

        // reference_id menunjuk ke Pengajuan Anggaran; pakai deep-link 'budget'
        // (dikenali aplikasi) agar tap notifikasi membuka detail anggaran → buat LHP.
        FcmService::sendToEmployee($employee, $notif->title, $notif->message, [
            'type'           => $type,
            'reference_type' => 'budget',
            'reference_id'   => (string) $budget->id,
        ]);
    }
}
