<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\AttendancePhotoArchive;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class AttendancePhotoArchiveService
{
    public function countPhotos(int $companyId, string $period): int
    {
        return count($this->photoEntries($companyId, $period));
    }

    public function generate(int $companyId, string $period, int $adminId): AttendancePhotoArchive
    {
        $month = $this->month($period);
        $period = $month->format('Y-m');
        $entries = $this->photoEntries($companyId, $period);
        $photoPaths = array_values(array_unique(array_column($entries, 'source')));
        $zipFileName = "foto_absensi_{$period}.zip";
        $zipFilePath = "attendance-photo-archives/{$zipFileName}";

        $archive = AttendancePhotoArchive::updateOrCreate(
            ['company_id' => $companyId, 'period' => $period],
            [
                'status' => 'processing',
                'zip_file_name' => $zipFileName,
                'zip_file_path' => $zipFilePath,
                'photo_count' => count($entries),
                'photo_paths' => $photoPaths,
                'generated_by' => $adminId,
                'generated_at' => now(),
                'drive_link' => null,
                'archived_by' => null,
                'archived_at' => null,
                'local_photos_deleted_at' => null,
                'error_message' => null,
            ]
        );

        try {
            Storage::disk('local')->makeDirectory('attendance-photo-archives');

            if (Storage::disk('local')->exists($zipFilePath)) {
                Storage::disk('local')->delete($zipFilePath);
            }

            $zipPath = Storage::disk('local')->path($zipFilePath);
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Gagal membuat file ZIP arsip foto absensi.');
            }

            if (!$entries) {
                $zip->addFromString(
                    "foto_absensi_{$period}/README.txt",
                    "Tidak ada foto clock-in atau clock-out untuk periode {$period}."
                );
            }

            foreach ($entries as $entry) {
                $zip->addFile(Storage::disk('public')->path($entry['source']), $entry['target']);
            }

            $zip->close();

            $archive->update([
                'status' => 'ready',
                'photo_count' => count($entries),
                'photo_paths' => $photoPaths,
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            $archive->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $archive->fresh();
    }

    public function markUploaded(AttendancePhotoArchive $archive, string $driveLink, int $adminId): AttendancePhotoArchive
    {
        $driveLink = trim($driveLink);
        if ($driveLink === '') {
            throw new InvalidArgumentException('Link Google Drive wajib diisi.');
        }

        foreach (array_unique($archive->photo_paths ?? []) as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }

        if ($archive->zip_file_path && Storage::disk('local')->exists($archive->zip_file_path)) {
            Storage::disk('local')->delete($archive->zip_file_path);
        }

        $archive->update([
            'status' => 'archived',
            'drive_link' => $driveLink,
            'archived_by' => $adminId,
            'archived_at' => now(),
            'local_photos_deleted_at' => now(),
            'error_message' => null,
        ]);

        return $archive->fresh();
    }

    private function photoEntries(int $companyId, string $period): array
    {
        $month = $this->month($period);
        $attendances = Attendance::query()
            ->with('employee:id,company_id,employee_code,full_name')
            ->whereHas('employee', fn ($query) => $query->where('company_id', $companyId))
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('date')
            ->orderBy('employee_id')
            ->get();

        $entries = [];
        foreach ($attendances as $attendance) {
            foreach (['clock_in_photo' => 'clock-in', 'clock_out_photo' => 'clock-out'] as $column => $label) {
                $source = $attendance->{$column};
                if (!$source || !Storage::disk('public')->exists($source)) {
                    continue;
                }

                $employee = $attendance->employee;
                $employeeFolder = $this->safeSegment(($employee->employee_code ?: 'EMP-' . $employee->id) . '_' . $employee->full_name);
                $date = Carbon::parse($attendance->date)->format('Y-m-d');
                $extension = pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg';

                $entries[] = [
                    'source' => $source,
                    'target' => "foto_absensi_{$month->format('Y-m')}/{$employeeFolder}/{$date}_{$label}.{$extension}",
                ];
            }
        }

        return $entries;
    }

    private function month(string $period): Carbon
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            throw new InvalidArgumentException('Periode arsip harus berformat YYYY-MM.');
        }

        return Carbon::createFromFormat('Y-m-d', "{$period}-01")->startOfMonth();
    }

    private function safeSegment(string $value): string
    {
        $value = Str::ascii($value);
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_') ?: 'tanpa_nama';
    }
}
