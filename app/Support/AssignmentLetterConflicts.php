<?php

namespace App\Support;

use App\Models\AssignmentLetter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Peringatan jadwal bentrok untuk surat tugas: peserta yang sedang cuti, peserta atau kendaraan
 * yang sudah tercantum di surat tugas lain pada tanggal yang beririsan.
 *
 * Hanya peringatan. Surat tetap boleh disimpan dan diterbitkan, karena HRD kadang memang
 * sengaja menerbitkan dua surat (mis. tugas lanjutan di kota lain pada hari yang sama).
 */
class AssignmentLetterConflicts
{
    private const ACTIVE_LEAVE_STATUSES = ['pending', 'in_review', 'approved'];

    /** Kalimat lengkap untuk halaman detail surat. @return array<int, string> */
    public static function for(AssignmentLetter $letter): array
    {
        if ($letter->isCancelled() || ! $letter->departure_date || ! $letter->return_date) {
            return [];
        }

        $letter->loadMissing(['employees', 'facilities.vehicle']);
        $from = $letter->departure_date->toDateString();
        $to = $letter->return_date->toDateString();
        $employeeIds = $letter->employees->pluck('employee_id')->all();
        $names = $letter->employees->pluck('name', 'employee_id');
        $excludeId = $letter->exists ? $letter->id : null;

        $leaves = self::leaveRows($employeeIds, $from, $to)
            ->map(fn ($leave) => ($names[$leave->employee_id] ?? 'Karyawan')
                .' punya cuti '.self::range($leave->start_date, $leave->end_date).' ('.self::leaveStatus($leave->status).').');

        $letters = self::letterRows($letter->company_id, $employeeIds, $from, $to, $excludeId)
            ->map(fn ($row) => ($names[$row->employee_id] ?? 'Karyawan')
                .' juga tercantum di surat tugas '.self::letterLabel($row)
                .' ('.self::range($row->departure_date, $row->return_date).').');

        return array_merge($leaves->all(), $letters->all(), self::vehicleConflicts($letter, $from, $to, $excludeId));
    }

    /**
     * Peringatan ringkas per karyawan untuk rentang tanggal, dipakai form saat memilih peserta.
     *
     * @param  array<int, int>  $employeeIds
     * @return array<int, array<int, string>> employee_id => daftar peringatan
     */
    public static function byEmployee(int $companyId, array $employeeIds, string $from, string $to, ?int $excludeLetterId = null): array
    {
        $result = [];

        foreach (self::leaveRows($employeeIds, $from, $to) as $leave) {
            $result[$leave->employee_id][] = 'Cuti '.self::shortRange($leave->start_date, $leave->end_date)
                .' ('.self::leaveStatus($leave->status).')';
        }

        foreach (self::letterRows($companyId, $employeeIds, $from, $to, $excludeLetterId) as $row) {
            $result[$row->employee_id][] = 'Sudah di surat tugas '.self::letterLabel($row)
                .' ('.self::shortRange($row->departure_date, $row->return_date).')';
        }

        return $result;
    }

    private static function leaveRows(array $employeeIds, string $from, string $to): Collection
    {
        if (! $employeeIds || ! Schema::hasTable('leave_requests')) {
            return collect();
        }

        return DB::table('leave_requests')
            ->whereIn('employee_id', $employeeIds)
            ->whereIn('status', self::ACTIVE_LEAVE_STATUSES)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->orderBy('start_date')
            ->get(['employee_id', 'start_date', 'end_date', 'status']);
    }

    private static function letterRows(int $companyId, array $employeeIds, string $from, string $to, ?int $excludeLetterId): Collection
    {
        if (! $employeeIds) {
            return collect();
        }

        return DB::table('assignment_letter_employees as ale')
            ->join('assignment_letters as al', 'al.id', '=', 'ale.assignment_letter_id')
            ->where('al.company_id', $companyId)
            ->when($excludeLetterId, fn ($q) => $q->where('al.id', '!=', $excludeLetterId))
            ->where('al.status', '!=', AssignmentLetter::STATUS_CANCELLED)
            ->whereIn('ale.employee_id', $employeeIds)
            ->whereDate('al.departure_date', '<=', $to)
            ->whereDate('al.return_date', '>=', $from)
            ->orderBy('al.departure_date')
            ->get(['ale.employee_id', 'al.id', 'al.number', 'al.departure_date', 'al.return_date']);
    }

    private static function vehicleConflicts(AssignmentLetter $letter, string $from, string $to, ?int $excludeLetterId): array
    {
        $vehicles = $letter->facilities->whereNotNull('vehicle_id')->keyBy('vehicle_id');
        if ($vehicles->isEmpty()) {
            return [];
        }

        return DB::table('assignment_letter_facilities as alf')
            ->join('assignment_letters as al', 'al.id', '=', 'alf.assignment_letter_id')
            ->where('al.company_id', $letter->company_id)
            ->when($excludeLetterId, fn ($q) => $q->where('al.id', '!=', $excludeLetterId))
            ->where('al.status', '!=', AssignmentLetter::STATUS_CANCELLED)
            ->whereIn('alf.vehicle_id', $vehicles->keys()->all())
            ->whereDate('al.departure_date', '<=', $to)
            ->whereDate('al.return_date', '>=', $from)
            ->orderBy('al.departure_date')
            ->get(['alf.vehicle_id', 'al.id', 'al.number', 'al.departure_date', 'al.return_date'])
            ->map(function ($row) use ($vehicles) {
                $vehicle = $vehicles[$row->vehicle_id]->vehicle;
                $label = $vehicle ? trim($vehicle->name.' '.$vehicle->plate_number) : 'Kendaraan';

                return "Kendaraan {$label} juga dipakai surat tugas ".self::letterLabel($row)
                    .' ('.self::range($row->departure_date, $row->return_date).').';
            })
            ->all();
    }

    private static function leaveStatus(string $status): string
    {
        return $status === 'approved' ? 'sudah disetujui' : 'masih diajukan';
    }

    private static function letterLabel(object $row): string
    {
        // ID draft tidak ditampilkan: angkanya bukan nomor surat dan membingungkan.
        return $row->number ?: 'yang masih draft';
    }

    private static function range(string $from, string $to): string
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);

        return $start->isSameDay($end)
            ? AssignmentLetter::formatDate($start)
            : AssignmentLetter::formatDate($start).' – '.AssignmentLetter::formatDate($end);
    }

    /** "25 Sep 2026" atau "25 Sep – 27 Sep 2026". */
    private static function shortRange(string $from, string $to): string
    {
        $start = Carbon::parse($from)->locale('id');
        $end = Carbon::parse($to)->locale('id');

        if ($start->isSameDay($end)) {
            return $start->translatedFormat('d M Y');
        }

        return $start->translatedFormat($start->year === $end->year ? 'd M' : 'd M Y').' – '.$end->translatedFormat('d M Y');
    }
}
