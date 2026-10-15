<?php

namespace App\Support;

use App\Models\AssignmentLetter;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi in-app + push ke karyawan yang tercantum di surat tugas.
 *
 * Push memakai reference_type 'assignment_letter'; aplikasi mobile membuka detail surat tugas
 * saat notifikasi ditekan (versi aplikasi lama mengabaikannya, pesannya tetap tampil).
 * Dipanggil setelah transaksi selesai; kegagalan kirim tidak membatalkan penerbitan.
 */
class AssignmentLetterNotifier
{
    public static function issued(AssignmentLetter $letter): void
    {
        $letter->loadMissing(['employees', 'destinations']);
        $range = self::range($letter);
        $activity = self::shortActivity($letter);

        self::send($letter, 'Surat Tugas Baru',
            "Anda ditugaskan lewat surat tugas {$letter->number}: {$activity} ({$range}). "
            .'Untuk pengajuan anggaran, pilih surat tugas ini di form Pengajuan Anggaran.');
    }

    public static function cancelled(AssignmentLetter $letter): void
    {
        $letter->loadMissing('employees');

        self::send($letter, 'Surat Tugas Dibatalkan',
            "Surat tugas {$letter->number} ({$letter->departure_date->format('d/m/Y')}) dibatalkan. "
            ."Alasan: {$letter->cancel_reason}");
    }

    /** Hanya unggahan pertama; mengganti file (mis. pindaian buram) tidak mengirim ulang. */
    public static function signedUploaded(AssignmentLetter $letter): void
    {
        $letter->loadMissing('employees');

        self::send($letter, 'Surat Tugas Sudah Ditandatangani',
            "Surat tugas {$letter->number} sudah ditandatangani. Lihat atau unduh di menu Surat Tugas.");
    }

    private static function send(AssignmentLetter $letter, string $title, string $message): void
    {
        $employees = Employee::whereIn('id', $letter->employees->pluck('employee_id'))
            ->where('is_active', true)
            ->get();

        foreach ($employees as $employee) {
            try {
                Notification::create([
                    'employee_id' => $employee->id,
                    'title' => $title,
                    'message' => $message,
                    'type' => 'info',
                    'reference_type' => AssignmentLetter::class,
                    'reference_id' => $letter->id,
                ]);

                FcmService::sendToEmployee($employee, $title, $message, [
                    'type' => 'info',
                    'reference_type' => 'assignment_letter',
                    'reference_id' => (string) $letter->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Notifikasi surat tugas gagal dikirim', [
                    'assignment_letter_id' => $letter->id,
                    'employee_id' => $employee->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private static function range(AssignmentLetter $letter): string
    {
        $from = $letter->departure_date->format('d/m/Y');
        $to = $letter->return_date->format('d/m/Y');

        return $from === $to ? $from : "{$from} – {$to}";
    }

    private static function shortActivity(AssignmentLetter $letter): string
    {
        return mb_strimwidth((string) $letter->destinations->first()?->activity, 0, 80, '…');
    }
}
