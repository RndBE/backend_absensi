<?php

namespace App\Support;

use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
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

        if ($letter->revises_letter_id) {
            $old = $letter->revises()->value('number');

            self::send($letter, 'Surat Tugas Perbaikan',
                "Surat tugas {$letter->number} memperbaiki surat tugas {$old}: {$activity} ({$range}). "
                .'Gunakan surat yang baru ini.');

            return;
        }

        self::send($letter, 'Surat Tugas Baru',
            "Anda ditugaskan lewat surat tugas {$letter->number}: {$activity} ({$range}). "
            .'Untuk pengajuan anggaran, pilih surat tugas ini di form Pengajuan Anggaran.');
    }

    /** Isi surat terbit diubah sebelum ditandatangani Direktur (nomor tetap). */
    public static function updated(AssignmentLetter $letter): void
    {
        $letter->loadMissing(['employees', 'destinations']);

        self::send($letter, 'Surat Tugas Diperbarui',
            "Isi surat tugas {$letter->number} diperbarui: ".self::shortActivity($letter).' ('.self::range($letter).'). '
            .'Periksa kembali tanggal, tujuan, dan fasilitasnya.');
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

    /**
     * Surat menunggu keputusan pemberi persetujuan di $approvals (atasan yang ditunjuk, atau
     * pemegang izin HRD/GA HSE). Push memakai reference_type 'assignment_letter_approval';
     * aplikasi mobile membukanya di detail surat tugas, yang memuat tombol setuju/tolak bila
     * penerimanya memang pemberi paraf (versi aplikasi lama hanya menampilkan pesannya).
     * Keputusan diambil di aplikasi, portal karyawan (Persetujuan), atau admin panel (HRD/GA HSE).
     *
     * @param  \Illuminate\Support\Collection<int, AssignmentLetterApproval>  $approvals
     */
    public static function approvalRequested(AssignmentLetter $letter, $approvals): void
    {
        $letter->loadMissing(['employees', 'destinations']);
        $names = self::participantNames($letter);
        $activity = self::shortActivity($letter);

        foreach ($approvals as $approval) {
            $message = "Surat tugas {$letter->number} untuk {$names} ({$activity}) menunggu paraf Anda sebagai {$approval->label}.";

            foreach (AssignmentLetterApprovalFlow::approversFor($approval, (int) $letter->company_id) as $approver) {
                self::notify($letter, $approver, 'Surat Tugas Menunggu Persetujuan', $message, 'assignment_letter_approval');
            }
        }
    }

    public static function approvalCompleted(AssignmentLetter $letter): void
    {
        $letter->loadMissing('employees');
        $names = self::participantNames($letter);

        self::notifyStaff($letter, 'Paraf Surat Tugas Lengkap',
            "Semua paraf surat tugas {$letter->number} untuk {$names} sudah terisi. Siap dicetak dan ditandatangani Direktur.");
    }

    public static function approvalRejected(AssignmentLetter $letter, Employee $rejecter): void
    {
        $letter->loadMissing('employees');
        $names = self::participantNames($letter);

        self::notifyStaff($letter, 'Paraf Surat Tugas Ditolak',
            "Surat tugas {$letter->number} untuk {$names} ditolak oleh {$rejecter->full_name}. Alasan: {$letter->rejection_reason}. "
            .'Ubah suratnya lalu klik Ajukan Ulang.');
    }

    /** Pembuat dan penerbit surat (HRD/GA HSE), tanpa duplikat. */
    private static function notifyStaff(AssignmentLetter $letter, string $title, string $message): void
    {
        $ids = collect([$letter->created_by, $letter->submitted_by])->filter()->unique();

        foreach (Employee::whereIn('id', $ids)->where('is_active', true)->get() as $employee) {
            self::notify($letter, $employee, $title, $message, 'assignment_letter_approval');
        }
    }

    private static function participantNames(AssignmentLetter $letter): string
    {
        return mb_strimwidth($letter->employees->pluck('name')->implode(', '), 0, 120, '…');
    }

    private static function notify(AssignmentLetter $letter, Employee $employee, string $title, string $message, string $fcmReference): void
    {
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
                'reference_type' => $fcmReference,
                'reference_id' => (string) $letter->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Notifikasi approval surat tugas gagal dikirim', [
                'assignment_letter_id' => $letter->id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }
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
