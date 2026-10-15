<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeApprover;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom paraf surat tugas mengikuti jenjang atasan peserta: level 3 (bila ada), level 2
 * (bila ada), lalu HRD dan GA HSE. Direktur (level 1) tidak diberi kolom karena ia yang
 * menandatangani surat.
 *
 * Sumber atasan:
 * 1. Rantai approval anggaran (employee_approvers, request_type 'budget'). Di data PT Arta
 *    rantai inilah yang memuat jenjang lengkap, mis. staf → Leader Production (L3) →
 *    Hardware Manager (L2).
 * 2. Bila karyawan belum punya rantai: telusuri manager_id ke atas. Kolom ini sering langsung
 *    menunjuk level 2 dan melewati level 3, makanya hanya jadi cadangan.
 *
 * Hanya atasan dengan level lebih tinggi dari peserta yang dihitung: peserta level 3 hanya
 * butuh paraf level 2, peserta level 2 tidak butuh paraf atasan sama sekali.
 */
final class AssignmentLetterInitialBoxes
{
    /** Urutan kolom: level 3 dulu, lalu level 2. */
    private const LEVELS = [3, 2];

    private const MAX_MANAGER_DEPTH = 6;

    /**
     * @param  array<int, int>  $employeeIds  urutan peserta di surat
     * @return array<int, array<int, array{id: int, level: int, label: string, name: string}>>
     */
    public static function superiorsFor(int $companyId, array $employeeIds): array
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        if (! $employeeIds) {
            return [];
        }

        $people = Employee::with('department:id,name')
            ->where('company_id', $companyId)
            ->get(['id', 'full_name', 'position', 'job_level', 'manager_id', 'department_id'])
            ->keyBy('id');

        $chains = Schema::hasTable('employee_approvers')
            ? EmployeeApprover::whereIn('employee_id', $employeeIds)
                ->where('request_type', 'budget')
                ->orderBy('step_order')
                ->get(['employee_id', 'approver_id'])
                ->groupBy('employee_id')
            : collect();

        $result = [];
        foreach ($employeeIds as $id) {
            $employee = $people->get($id);
            if (! $employee) {
                $result[$id] = [];

                continue;
            }

            $candidateIds = isset($chains[$id])
                ? $chains[$id]->pluck('approver_id')->all()
                : self::managerLine($employee, $people);
            $ownLevel = (int) $employee->job_level ?: PHP_INT_MAX;

            $result[$id] = collect($candidateIds)
                ->map(fn ($approverId) => $people->get((int) $approverId))
                ->filter(fn (?Employee $superior) => $superior
                    && in_array((int) $superior->job_level, self::LEVELS, true)
                    && (int) $superior->job_level < $ownLevel)
                ->unique('id')
                ->map(fn (Employee $superior) => [
                    'id' => $superior->id,
                    'level' => (int) $superior->job_level,
                    'label' => self::label($superior),
                    'name' => (string) $superior->full_name,
                ])
                ->values()
                ->all();
        }

        return $result;
    }

    /**
     * Label kolom paraf: semua atasan level 3, lalu level 2 (satu kolom per orang, walau jadi
     * atasan beberapa peserta), lalu kolom tetap HRD dan GA HSE.
     *
     * @param  array<int, array<int, array{id: int, level: int, label: string, name: string}>>  $superiorsByEmployee
     * @return array<int, string>
     */
    public static function boxes(array $superiorsByEmployee): array
    {
        $superiors = collect($superiorsByEmployee)->flatten(1)->unique('id');
        $labels = [];

        foreach (self::LEVELS as $level) {
            foreach ($superiors->where('level', $level) as $superior) {
                $label = $superior['label'];
                // Dua orang berbeda di unit yang sama: bedakan dengan namanya.
                if (in_array($label, $labels, true)) {
                    $label .= ' ('.$superior['name'].')';
                }
                $labels[] = $label;
            }
        }

        return array_merge($labels, config('assignment_letters.fixed_initial_boxes', ['HRD', 'GA HSE']));
    }

    /** "LV 3 PRODUCTION": jenjang + unit kerja atasan, seperti di surat Word. */
    private static function label(Employee $superior): string
    {
        $unit = $superior->department?->name ?: $superior->position ?: $superior->full_name;

        return 'LV '.$superior->job_level.' '.mb_strtoupper(trim((string) $unit));
    }

    /** @return array<int, int> id atasan berjenjang lewat manager_id */
    private static function managerLine(Employee $employee, $people): array
    {
        $ids = [];
        $cursor = $employee;

        for ($depth = 0; $cursor?->manager_id && $depth < self::MAX_MANAGER_DEPTH; $depth++) {
            if (in_array((int) $cursor->manager_id, $ids, true)) {
                break; // data melingkar
            }
            $ids[] = (int) $cursor->manager_id;
            $cursor = $people->get((int) $cursor->manager_id);
        }

        return $ids;
    }
}
