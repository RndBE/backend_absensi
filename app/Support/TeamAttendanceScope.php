<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Collection;

/**
 * Siapa boleh melihat presensi siapa. Dipakai portal employee dan API mobile supaya aturannya
 * satu: level 1-3 yang punya departemen melihat anggota aktif di departemennya BESERTA seluruh
 * turunannya, hanya yang levelnya di bawah pelihat. Pemegang hr_admin melihat semua karyawan
 * aktif L2-L4 se-perusahaan. Aturan levelnya ada di OrgLevel.
 *
 * Perbandingan `department_id` yang persis tidak cukup: manager menempel di simpul induk
 * sementara anak buahnya tersebar di simpul anak.
 */
class TeamAttendanceScope
{
    /** @var array<int, int>|null Sub-pohon departemen pelihat, dihitung sekali per instance. */
    private ?array $deptIds = null;

    public function __construct(private readonly Employee $viewer)
    {
        $this->viewer->loadMissing('kpiLevel');
    }

    /** Level 1-3 yang punya departemen (atau manager tanpa level), atau pemegang hr_admin. */
    public function allowed(): bool
    {
        return OrgLevel::seesWholeCompany($this->viewer)
            || (OrgLevel::viewerLevel($this->viewer) !== null && $this->viewer->department_id);
    }

    /** Pesan penolakan bila bukan pelihat yang sah, atau null bila boleh. */
    public function denial(): ?string
    {
        if (OrgLevel::seesWholeCompany($this->viewer)) {
            return null;
        }

        if (OrgLevel::viewerLevel($this->viewer) === null) {
            return 'Halaman ini hanya untuk level 3 ke atas.';
        }

        return $this->viewer->department_id ? null : 'Anda belum ditetapkan ke departemen mana pun.';
    }

    /**
     * Anggota tim: karyawan aktif di departemen pelihat dan seluruh turunannya, kecuali dirinya
     * sendiri, dan hanya yang levelnya di bawah pelihat. Level yang tidak diketahui dianggap staf.
     *
     * @return Collection<int, Employee>
     */
    public function members(): Collection
    {
        $wholeCompany = OrgLevel::seesWholeCompany($this->viewer);
        $viewerLevel = $wholeCompany ? 1 : OrgLevel::viewerLevel($this->viewer);

        return Employee::query()
            ->when(! $wholeCompany, fn ($q) => $q->whereIn('department_id', $this->departmentIds()))
            ->where('company_id', $this->viewer->company_id)
            ->where('is_active', true)
            ->where('id', '!=', $this->viewer->id)
            ->whereNotIn('id', (array) config('services.team_attendance.hidden_employee_ids', []))
            ->with(['department:id,name', 'kpiLevel:id,code', ...Employee::scheduleTemplateEagerLoads()])
            ->orderBy('department_id')
            ->orderBy('full_name')
            ->get()
            ->filter(fn (Employee $member) => (OrgLevel::of($member) ?? OrgLevel::UNKNOWN_AS) > $viewerLevel)
            ->values();
    }

    /** Nama departemen yang tercakup, untuk ditampilkan sebagai konteks. */
    public function departmentNames(): Collection
    {
        if (OrgLevel::seesWholeCompany($this->viewer)) {
            return collect(['Semua departemen']);
        }

        return Department::whereIn('id', $this->departmentIds())
            ->orderBy('parent_id')
            ->pluck('name');
    }

    /**
     * Ringkasan hari ini: sudah masuk, belum absen padahal jam masuk lewat, dan yang memang
     * tidak dijadwalkan. Sengaja tidak menghitung "alpha" — harinya belum selesai.
     *
     * @param  Collection<int, array{tone:string}>  $today
     * @return array{hadir:int, telat:int, belum:int, terlewat:int, tak_masuk:int}
     */
    public static function summarize(Collection $today): array
    {
        $hitung = fn (array $tones) => $today->filter(fn ($s) => in_array($s['tone'], $tones, true))->count();

        return [
            'hadir' => $hitung(['hadir']),
            'telat' => $hitung(['telat']),
            'belum' => $hitung(['belum']),
            'terlewat' => $hitung(['terlewat']),
            'tak_masuk' => $hitung(['izin', 'off', 'libur', 'kosong']),
        ];
    }

    /** @return array<int, int> */
    private function departmentIds(): array
    {
        return $this->deptIds ??= DepartmentTree::withDescendants($this->viewer->department_id);
    }
}
