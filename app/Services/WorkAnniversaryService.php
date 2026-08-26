<?php

namespace App\Services;

use App\Mail\WorkAnniversaryMail;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Apresiasi hari jadi kerja: ucapan terima kasih untuk karyawan yang hari ini genap
 * sekian tahun bergabung.
 *
 * Kanal utamanya EMAIL, dengan notifikasi in-app + FCM sebagai pendamping. Berbeda dari
 * ClockinReminderService yang menahan penanda dedup sampai WhatsApp berhasil: di sini
 * penanda tetap dibuat walau email gagal, karena hari jadi cuma lewat sekali setahun —
 * kalau dedup ditahan, percobaan berikutnya (besok) sudah jatuh di tanggal yang tidak
 * cocok lagi, jadi ucapannya hilang sama sekali. Yang dikorbankan hanya emailnya,
 * in-app tetap sampai, dan kegagalannya tercatat di log serta di ringkasan command.
 */
class WorkAnniversaryService
{
    /** Kelipatan tahun yang dirayakan bila admin belum mengubah pengaturan. */
    public const DEFAULT_MILESTONES = '1,3,5,10,15,20,25';

    /** Nilai pengaturan yang berarti "rayakan setiap tahun, tanpa kelipatan". */
    public const EVERY_YEAR = '*';

    /** Jenis notifikasi in-app; sekaligus kunci dedup. */
    public const NOTIFICATION_TYPE = 'work_anniversary';

    /** Jarak hari minimum antar-ucapan untuk satu karyawan. Lihat alreadyGreeted(). */
    private const DEDUP_DAYS = 300;

    public static function isEnabled(): bool
    {
        return Setting::getValue('anniversary_greeting_enabled', '1') === '1';
    }

    /**
     * Tahun-tahun masa kerja yang dirayakan. Array kosong berarti SETIAP tahun —
     * bukan berarti tidak ada yang dirayakan.
     *
     * @return array<int, int>
     */
    public static function milestones(): array
    {
        $raw = trim((string) Setting::getValue('anniversary_milestones', self::DEFAULT_MILESTONES));

        if ($raw === '' || $raw === self::EVERY_YEAR) {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn ($tahun) => (int) trim((string) $tahun))
            ->filter(fn (int $tahun) => $tahun >= 1)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Siapa saja yang hari jadinya JATUH pada $date dan masuk daftar kelipatan
     * (read-only, tanpa mengirim apa pun). Dipisah dari pengiriman supaya bisa dipakai
     * mode --dry-run dan kanal luar (Tessa/WhatsApp), sama seperti service pengingat lain.
     *
     * @return Collection<int, array{employee: Employee, years: int}>
     */
    public static function celebrantsForDate(Carbon $date): Collection
    {
        $today = $date->copy()->startOfDay();
        $milestones = self::milestones();

        $celebrants = Employee::query()
            ->select('id', 'full_name', 'email', 'company_id', 'department_id', 'position', 'join_date', 'fcm_token')
            ->where('is_active', true)
            ->whereNotNull('join_date')
            // Tanggal masuk hari ini atau di masa depan tidak pernah jadi hari jadi.
            ->whereDate('join_date', '<', $today->toDateString())
            ->with('department:id,name')
            ->get()
            ->map(function (Employee $employee) use ($today) {
                $join = $employee->join_date->copy()->startOfDay();

                if (! self::occurrenceIn($join, (int) $today->year)->isSameDay($today)) {
                    return null;
                }

                $years = (int) $today->year - (int) $join->year;

                return $years >= 1 ? ['employee' => $employee, 'years' => $years] : null;
            })
            ->filter();

        if ($milestones !== []) {
            $celebrants = $celebrants->filter(fn (array $row) => in_array($row['years'], $milestones, true));
        }

        return $celebrants
            ->sortBy(fn (array $row) => (string) $row['employee']->full_name)
            ->values();
    }

    /**
     * Kirim apresiasi untuk semua yang hari jadinya jatuh pada $date.
     *
     * @return array{sent: int, skipped: int, email_failed: int}
     */
    public static function greetForDate(Carbon $date): array
    {
        if (! self::isEnabled()) {
            return ['sent' => 0, 'skipped' => 0, 'email_failed' => 0];
        }

        $sent = 0;
        $skipped = 0;
        $emailFailed = 0;

        foreach (self::celebrantsForDate($date) as $row) {
            /** @var Employee $employee */
            $employee = $row['employee'];
            $years = (int) $row['years'];

            if (self::alreadyGreeted($employee->id)) {
                $skipped++;
                continue;
            }

            if (! self::sendEmail($employee, $years)) {
                $emailFailed++;
            }

            self::createNotification($employee, $years);
            $sent++;
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'email_failed' => $emailFailed];
    }

    /**
     * Perayaan hari jadi pada $year dari tanggal masuk $join.
     *
     * 29 Februari ditangani khusus dengan alasan yang sama seperti di DashboardTimeline:
     * memasang tahun non-kabisat pada tanggal itu membuat Carbon melimpah ke 1 Maret,
     * sehingga orang yang masuk 29 Februari diucapkan pada tanggal yang salah tiga dari
     * empat tahun.
     */
    public static function occurrenceIn(Carbon $join, int $year): Carbon
    {
        $month = (int) $join->month;
        $day = (int) $join->day;

        if ($month === 2 && $day === 29 && ! Carbon::create($year, 1, 1)->isLeapYear()) {
            $day = 28;
        }

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    /**
     * Sudah pernah diucapkan dalam siklus tahun ini?
     *
     * Patokannya JARAK dari ucapan terakhir, bukan tahun kalender `created_at`. Dengan
     * tahun kalender, menjalankan command memakai argumen tanggal di tahun lain (uji coba,
     * atau susulan saat scheduler mati) tidak pernah cocok dengan tahun pembuatan
     * notifikasi, sehingga orang yang sama diucapkan berkali-kali.
     *
     * Jendelanya lebih pendek dari setahun supaya ucapan tahun depan tidak ikut tertahan,
     * dan cukup lebar untuk menutup pergeseran 29 Februari (366 hari) maupun susulan yang
     * terlambat beberapa pekan.
     */
    private static function alreadyGreeted(int $employeeId): bool
    {
        return Notification::where('employee_id', $employeeId)
            ->where('type', self::NOTIFICATION_TYPE)
            ->where('created_at', '>=', Carbon::now()->subDays(self::DEDUP_DAYS))
            ->exists();
    }

    private static function sendEmail(Employee $employee, int $years): bool
    {
        if (! filled($employee->email)) {
            return false;
        }

        try {
            $company = Company::find($employee->company_id);

            Mail::to($employee->email)->send(new WorkAnniversaryMail($employee, $company, $years));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Email apresiasi hari jadi gagal terkirim', [
                'employee_id' => $employee->id,
                'years' => $years,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function createNotification(Employee $employee, int $years): void
    {
        $notif = Notification::create([
            'employee_id' => $employee->id,
            'title' => 'Selamat Hari Jadi Kerja!',
            'message' => "Hari ini genap {$years} tahun Anda bergabung. Terima kasih atas dedikasinya!",
            'type' => self::NOTIFICATION_TYPE,
            'reference_type' => Employee::class,
            'reference_id' => $employee->id,
        ]);

        // Tanpa deep-link: aplikasi belum punya tujuan untuk reference_type 'employee',
        // dan menaruh tipe yang tidak dikenali membuat tap notifikasi berakhir di layar
        // kosong. Ucapan ini memang cukup dibaca di daftar notifikasi.
        FcmService::sendToEmployee($employee, $notif->title, $notif->message, [
            'type' => self::NOTIFICATION_TYPE,
        ]);
    }
}
