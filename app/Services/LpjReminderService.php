<?php

namespace App\Services;

use App\Models\BudgetRequest;
use App\Models\Employee;
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
     * Yang diingatkan: pemilik anggaran (biaya bersama dan miliknya) dan tiap peserta yang punya
     * biaya pribadi, masing-masing sekali per anggaran selama LPJ-nya belum dibuat.
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

        foreach (self::authorsDueOn($date, $reminderDays) as ['budget' => $budget, 'employee' => $employee, 'done' => $done]) {
            if ($done) {
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

        foreach (self::authorsDueOn($date, $reminderDays) as ['budget' => $budget, 'employee' => $employee, 'done' => $done]) {
            if ($done || ($companyId && (int) $employee->company_id !== $companyId)) {
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
     * Pasangan (anggaran, pembuat LPJ) untuk anggaran yang tanggal pulangnya jatuh tempo.
     * `done` true bila pembuat itu sudah membuat LPJ-nya.
     *
     * @return Collection<int, array{budget: BudgetRequest, employee: Employee, done: bool}>
     */
    private static function authorsDueOn(Carbon $date, int $reminderDays): Collection
    {
        $pairs = collect();

        foreach (self::budgetsReturningOn($date, $reminderDays) as $budget) {
            $authors = Employee::whereIn('id', $budget->lpjAuthorIds())->get()->keyBy('id');
            $doneIds = $budget->lpjs()->pluck('employee_id')->map(fn ($id) => (int) $id);

            foreach ($authors as $employee) {
                $pairs->push([
                    'budget' => $budget,
                    'employee' => $employee,
                    'done' => $doneIds->contains($employee->id),
                ]);
            }
        }

        return $pairs;
    }

    /**
     * Anggaran disetujui/cair yang punya LHP dengan tanggal pulang tepat $reminderDays hari
     * sebelum $date.
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

        return BudgetRequest::with('items')
            ->whereIn('id', $budgetIds)
            ->whereIn('status', ['approved', 'paid'])
            ->get();
    }
}
