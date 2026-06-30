<?php

namespace App\Services;

use App\Mail\LeaveDelegationMail;
use App\Models\LeaveRequest;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Memberi tahu karyawan yang ditunjuk sebagai delegasi saat pengajuan cuti/izin
 * DISETUJUI FINAL. Channel: in-app Notification + push FCM + email.
 */
class LeaveDelegateNotifier
{
    public static function notifyApproved(LeaveRequest $leave): void
    {
        if (! $leave->delegate_to) {
            return;
        }

        $leave->loadMissing(['delegate', 'employee', 'leaveType']);
        $delegate = $leave->delegate;
        if (! $delegate) {
            return;
        }

        $employeeName = $leave->employee->full_name ?? 'Rekan Anda';
        $type = $leave->leaveType->name ?? 'cuti/izin';
        $start = $leave->start_date?->format('d/m/Y');
        $end = $leave->end_date?->format('d/m/Y');
        $period = $start
            ? ($end && $end !== $start ? "{$start} - {$end}" : $start)
            : '-';

        $title = 'Anda Ditunjuk Sebagai Delegasi';
        $message = "{$employeeName} menunjuk Anda menggantikan tugasnya selama {$type} ({$period}).";

        // In-app Notification (menjangkau portal web tanpa fcm_token).
        Notification::create([
            'employee_id' => $delegate->id,
            'title' => $title,
            'message' => $message,
            'type' => 'delegation',
            'reference_type' => LeaveRequest::class,
            'reference_id' => $leave->id,
        ]);

        // Push FCM (mobile).
        FcmService::sendToEmployee($delegate, $title, $message, [
            'type' => 'delegation',
            'reference_type' => 'leave',
            'reference_id' => (string) $leave->id,
        ]);

        // Email — jangan sampai kegagalan email mengganggu proses approval.
        if ($delegate->email) {
            try {
                Mail::to($delegate->email)->send(new LeaveDelegationMail($leave, $delegate));
            } catch (\Throwable $e) {
                Log::warning('Gagal mengirim email delegasi untuk leave #'.$leave->id.': '.$e->getMessage());
            }
        }
    }
}
