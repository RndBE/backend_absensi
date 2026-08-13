<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection;

/**
 * Siapa yang menjadi sasaran Lapis B (Bab 7.2), dipakai bersama halaman pengisian dan graf
 * relasi.
 *
 * Diangkat jadi kelas sendiri karena aturannya menentukan siapa yang benar-benar dinilai
 * personal. Ketika halaman pengisian dan graf masing-masing menyimpan salinan aturan ini,
 * keduanya pasti melenceng cepat atau lambat — dan graf yang melenceng lebih buruk daripada
 * tidak ada graf, sebab ia terlihat seperti bukti.
 */
class KpiCrossTargets
{
    /**
     * Sasaran Lapis B: L2 dan L3 divisi mitra (mereka wajah divisinya), ditambah L4 yang
     * ditandai lintas fungsi. Penandaan L4 harus sudah ada sejak awal periode.
     *
     * @param  array<int, int>  $partnerDepartmentIds
     * @return Collection<int, Employee>
     */
    public function individuals(int $companyId, array $partnerDepartmentIds): Collection
    {
        return Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->whereIn('department_id', $this->expandDivisions($partnerDepartmentIds))
            // Dibungkus supaya OR-nya tidak keluar dari batas company/aktif/departemen.
            ->where(function ($query) {
                $query->whereHas('kpiLevel', fn ($level) => $level->whereIn('code', ['L2', 'L3']))
                    ->orWhere('is_cross_functional', true);
            })
            ->with(['department:id,name', 'kpiLevel:id,code'])
            ->orderBy('full_name')
            ->get();
    }

    /**
     * Divisi beserta seluruh sub-departemennya. Karyawan jarang duduk persis di simpul
     * divisi — di data nyata mereka tersebar di simpul anak (lihat App\Support\DepartmentTree),
     * jadi mencocokkan department_id ke id divisi saja akan melewatkan hampir semua orang.
     *
     * @param  array<int, int>  $divisionIds
     * @return array<int, int>
     */
    public function expandDivisions(array $divisionIds): array
    {
        $ids = [];

        foreach ($divisionIds as $divisionId) {
            $ids = array_merge($ids, DepartmentTree::withDescendants((int) $divisionId));
        }

        return array_values(array_unique($ids));
    }
}
