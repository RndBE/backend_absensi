<?php

namespace App\Support;

use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
use App\Models\AssignmentLetterDestination;
use App\Models\AssignmentLetterEmployee;
use App\Models\AssignmentLetterFacility;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Bentuk JSON surat tugas untuk app mobile, dipakai bersama oleh surat milik peserta
 * (Api\AssignmentLetterController) dan persetujuan paraf (Api\AssignmentLetterApprovalController).
 *
 * Aplikasi membuka surat & hasil pindai TTD lewat browser, yang tidak membawa token API. Karena
 * itu API memberi tautan bertanda tangan yang berlaku FILE_LINK_MINUTES menit; aplikasi meminta
 * ulang detail surat setiap kali tombol ditekan supaya tautannya selalu baru.
 */
class AssignmentLetterPresenter
{
    public const FILE_LINK_MINUTES = 30;

    /** Relasi yang harus dimuat sebelum memanggil payload(). */
    public const RELATIONS = ['employees', 'destinations', 'facilities'];

    public static function payload(AssignmentLetter $letter): array
    {
        $date = fn ($value) => $value?->toDateString();

        return [
            'id' => $letter->id,
            'number' => $letter->number,
            'status' => $letter->status,
            'status_label' => $letter->statusLabel(),
            'letter_date' => $date($letter->letter_date),
            'city' => $letter->city,
            'signer_name' => $letter->signer_name,
            'signer_position' => $letter->signer_position,
            'title' => (string) $letter->destinations->first()?->activity,
            'purpose_text' => trim(implode("\n", array_filter([$letter->purposeHeading(), ...$letter->destinationSentences()]))),
            'departure_date' => $date($letter->departure_date),
            'work_start_date' => $date($letter->work_start_date),
            'return_date' => $date($letter->return_date),
            'working_days' => $letter->workingDays(),
            'schedule_text' => $letter->scheduleSentence(),
            'destinations' => $letter->destinations->map(fn (AssignmentLetterDestination $d) => [
                'activity' => $d->activity,
                'address' => $d->address,
                'distance_km' => $d->distance_km !== null ? (float) $d->distance_km : null,
            ])->values()->all(),
            'employees' => $letter->employees->map(fn (AssignmentLetterEmployee $e) => [
                'employee_id' => $e->employee_id,
                'name' => $e->name,
                'employee_code' => $e->employee_code,
                'position' => $e->position,
            ])->values()->all(),
            'facilities' => $letter->facilities->map(fn (AssignmentLetterFacility $f) => [
                'type' => $f->type,
                'type_label' => $f->type === AssignmentLetterFacility::TYPE_DEVICE ? 'Perangkat Komunikasi' : 'Transportasi',
                'description' => $f->description,
                'inspection' => $f->inspectionLabel(),
            ])->values()->all(),
            'cancelled_at' => $letter->cancelled_at?->toIso8601String(),
            'cancel_reason' => $letter->cancel_reason,
            'print_url' => self::link('assignment-letters.link.print', $letter),
            'signed_file' => $letter->hasSignedFile() ? [
                'file_name' => $letter->signed_file_name,
                'is_pdf' => str_ends_with(strtolower((string) $letter->signed_file_name), '.pdf'),
                'uploaded_at' => $letter->signed_uploaded_at?->toIso8601String(),
                'url' => self::signedFileUrl($letter),
            ] : null,
        ];
    }

    public static function signedFileUrl(AssignmentLetter $letter): ?string
    {
        return $letter->hasSignedFile() ? self::link('assignment-letters.link.signed', $letter) : null;
    }

    /**
     * Surat tugas beserta kotak parafnya, untuk pemberi persetujuan: payload surat, daftar
     * paraf (siapa sudah memutuskan, catatannya, tanda tangannya), dan paraf yang boleh
     * diputuskan $viewer sekarang. Relasi RELATIONS dan approvals.approver/actor harus dimuat.
     */
    public static function approvalDetail(AssignmentLetter $letter, Employee $viewer, Collection $mine): array
    {
        return [
            ...self::payload($letter),
            'participant_names' => self::participantNames($letter),
            'approvals' => $letter->approvals->sortBy('sort_order')->map(fn (AssignmentLetterApproval $a) => [
                'id' => $a->id,
                'role' => $a->role,
                'label' => $a->label,
                'step_group' => $a->step_group,
                'status' => $a->status,
                'status_label' => $a->statusLabel(),
                'approver_name' => $a->approver?->full_name,
                'acted_by_name' => $a->actor?->full_name,
                'acted_at' => $a->acted_at?->toIso8601String(),
                'notes' => $a->notes,
                'signature_url' => $a->signatureUrl(),
            ])->values()->all(),
            // Paraf yang menunggu keputusan $viewer; kosong bila ia hanya melihat.
            'my_approvals' => $mine->map(fn (AssignmentLetterApproval $a) => ['id' => $a->id, 'label' => $a->label])->values()->all(),
            // Tanpa tanda tangan digital, kotak parafnya hanya memuat nama.
            'has_signature' => filled($viewer->signature),
            'rejection_reason' => $letter->rejection_reason,
            'rejected_by' => $letter->rejecter?->full_name,
        ];
    }

    /** Satu baris antrean persetujuan; `id` adalah id surat, `approval_id` kotak paraf yang menunggu. */
    public static function queueRow(AssignmentLetterApproval $approval): array
    {
        $letter = $approval->letter;
        $names = self::participantNames($letter);

        return [
            'id' => $letter->id,
            'approval_id' => $approval->id,
            'label' => $approval->label,
            'number' => $letter->number,
            'title' => (string) $letter->destinations->first()?->activity,
            'participant_names' => $names,
            // Bentuk yang sama dengan jenis pengajuan lain, supaya daftar Inbox tak perlu cabang khusus.
            'employee' => ['full_name' => $names],
            'departure_date' => $letter->departure_date?->toDateString(),
            'return_date' => $letter->return_date?->toDateString(),
            'created_at' => ($letter->submitted_at ?? $letter->created_at)?->toIso8601String(),
        ];
    }

    private static function participantNames(AssignmentLetter $letter): string
    {
        return $letter->employees->pluck('name')->implode(', ');
    }

    /**
     * Tanda tangan dihitung dari path saja (relative) supaya tetap sah walau server berada di
     * balik proxy yang mengubah skema/host; host diambil dari request yang sedang berjalan.
     */
    private static function link(string $route, AssignmentLetter $letter): string
    {
        return url(URL::temporarySignedRoute($route, now()->addMinutes(self::FILE_LINK_MINUTES), ['id' => $letter->id], false));
    }
}
