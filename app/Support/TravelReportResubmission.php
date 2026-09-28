<?php

namespace App\Support;

use App\Models\Lpj;
use App\Models\TravelReport;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pengajuan ulang LHP: LHP baru yang merujuk LHP yang ditolak.
 *
 * LHP lama tidak diubah — riwayat approval dan alasan penolakannya tetap utuh sebagai
 * arsip — dan LHP baru menjalani approval dari step 1 dengan riwayatnya sendiri.
 */
class TravelReportResubmission
{
    /**
     * LHP ditolak yang digantikan LHP baru. Rujukan eksplisit datang dari tombol "Ajukan ulang";
     * tanpa itu, LHP baru untuk anggaran yang LHP-nya ditolak otomatis dirujukkan ke sana,
     * jadi pengajuan ulang dari app mobile yang belum mengenal fitur ini tetap tercatat.
     */
    public static function resolveOriginal(int $employeeId, ?int $budgetRequestId, ?int $explicitId = null): ?TravelReport
    {
        if ($explicitId) {
            $original = TravelReport::where('employee_id', $employeeId)->find($explicitId);

            if (! $original || ! $original->canBeResubmitted()) {
                throw ValidationException::withMessages([
                    'resubmission_of_id' => 'LHP ini tidak bisa diajukan ulang. Hanya LHP yang ditolak dan belum pernah diajukan ulang.',
                ]);
            }

            return $original;
        }

        return TravelReport::rejectedAwaitingResubmission($employeeId, $budgetRequestId);
    }

    /**
     * Atribut tambahan untuk LHP pengganti. Batas & status telat diwarisi dari LHP yang
     * ditolak, supaya penolakan tidak membuat karyawan jadi terlambat.
     */
    public static function attributes(?TravelReport $original): array
    {
        if (! $original) {
            return [];
        }

        $attributes = ['resubmission_of_id' => $original->id];

        if ($original->submission_deadline) {
            $attributes['submission_deadline'] = $original->submission_deadline;
            $attributes['is_late'] = $original->is_late;
        }

        return $attributes;
    }

    /** Pindahkan LPJ yang masih menunjuk LHP lama ke LHP penggantinya. */
    public static function relinkLpj(TravelReport $original, TravelReport $replacement): void
    {
        Lpj::where('travel_report_id', $original->id)->update(['travel_report_id' => $replacement->id]);
    }

    /**
     * Salin foto dokumentasi LHP lama ke penggantinya, dipakai saat form ajukan ulang diisi
     * dari LHP lama. File disalin, bukan dipakai bersama, karena edit/hapus LHP menghapus
     * file miliknya dari disk.
     *
     * Tanpa $keptByRow semua foto disalin: foto aktivitas dipasangkan ke aktivitas dengan
     * urutan yang sama, dan bila urutan itu tidak ada lagi foto masuk dokumentasi umum.
     * Dengan $keptByRow (dari form yang menampilkan foto lama per aktivitas) hanya foto yang
     * dipertahankan di tiap baris yang disalin, ke aktivitas baris itu. Dokumentasi umum
     * (tanpa aktivitas) tidak tampil di form, jadi selalu ikut disalin.
     *
     * @param  array<int|string, int[]>|null  $keptByRow  index baris aktivitas => ID dokumen LHP lama
     */
    public static function copyDocuments(TravelReport $original, TravelReport $replacement, ?array $keptByRow = null): void
    {
        $documents = $original->documents()->get();
        $replacementActivityIds = $replacement->activities()->pluck('id', 'sort_order');

        // Kelompokkan per aktivitas tujuan; kunci '' = dokumentasi umum.
        $targets = [];

        if ($keptByRow === null) {
            $originalOrder = $original->activities()->pluck('sort_order', 'id');

            foreach ($documents as $document) {
                $sortOrder = $originalOrder[$document->travel_report_activity_id] ?? null;
                $targets[$sortOrder !== null ? ($replacementActivityIds[$sortOrder] ?? '') : ''][] = $document;
            }
        } else {
            // Hanya foto aktivitas milik LHP lama yang boleh dipilih; ID lain dari request diabaikan.
            $selectable = $documents->whereNotNull('travel_report_activity_id')->keyBy('id');

            foreach ($keptByRow as $row => $documentIds) {
                $activityId = $replacementActivityIds[$row] ?? null;

                foreach ($documentIds as $documentId) {
                    $document = $selectable->pull((int) $documentId);

                    if ($document && $activityId) {
                        $targets[$activityId][] = $document;
                    }
                }
            }

            foreach ($documents->whereNull('travel_report_activity_id') as $document) {
                $targets[''][] = $document;
            }
        }

        $disk = Storage::disk('public');

        foreach ($targets as $activityId => $group) {
            $group = array_values(array_filter($group, fn ($document) => $disk->exists($document->file_path)));

            if ($group === []) {
                continue;
            }

            // Foto lama tampil lebih dulu, foto yang baru diunggah di form menyusul.
            $replacement->documents()
                ->where('travel_report_activity_id', $activityId === '' ? null : $activityId)
                ->increment('sort_order', count($group));

            foreach ($group as $position => $document) {
                $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
                $path = 'travel-report-docs/'.Str::random(40).($extension !== '' ? '.'.$extension : '');
                $disk->copy($document->file_path, $path);

                $replacement->documents()->create([
                    'travel_report_activity_id' => $activityId === '' ? null : $activityId,
                    'file_path' => $path,
                    'caption' => $document->caption,
                    'activity_date' => $document->activity_date,
                    'sort_order' => $position,
                ]);
            }
        }
    }

    /**
     * Foto lama yang dipertahankan per baris aktivitas di form ajukan ulang: yang tercantum
     * di existing_documents dan tidak dicentang Hapus (remove_documents). Kontraknya sama
     * dengan form edit LHP, jadi baris aktivitas yang dibuang ikut membuang fotonya.
     *
     * @return array<int|string, int[]>
     */
    public static function keptDocumentsByRow(array $activities): array
    {
        $kept = [];

        foreach ($activities as $row => $activity) {
            $existing = array_map('intval', (array) ($activity['existing_documents'] ?? []));
            $removed = array_map('intval', (array) ($activity['remove_documents'] ?? []));
            $kept[$row] = array_values(array_diff($existing, $removed));
        }

        return $kept;
    }

    /** Judul dan pesan notifikasi untuk approver pertama. */
    public static function approverNotification(TravelReport $report, string $employeeName): array
    {
        return $report->resubmission_of_id
            ? ['Pengajuan Ulang LHP', "{$employeeName} mengajukan ulang LHP ke {$report->destination_city} yang sebelumnya ditolak"]
            : ['Pengajuan LHP Baru', "{$employeeName} mengajukan LHP ke {$report->destination_city}"];
    }
}
