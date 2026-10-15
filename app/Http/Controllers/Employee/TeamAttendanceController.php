<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Support\DepartmentTree;
use App\Support\OrgLevel;
use App\Support\MonthlyAttendance;
use App\Support\TodayTeamStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presensi tim di portal employee untuk level 1-3 (Direksi, Manajer, Leader/SPV).
 *
 * Yang terlihat hanya anggota yang levelnya DI BAWAH pelihat: L2 melihat L3 dan L4, L3 hanya
 * L4. Level diambil dari Level KPI, atau job_level bila Level KPI kosong (App\Support\OrgLevel).
 * Pemegang role hr_admin melihat SEMUA karyawan aktif L2-L4 se-perusahaan, lintas departemen.
 *
 * Cakupan: departemen manager BESERTA seluruh turunannya. Perbandingan `department_id` yang
 * persis tidak cukup — manager menempel di simpul induk sementara anak buahnya tersebar di
 * simpul anak, sehingga sebagian manager tidak akan melihat siapa pun.
 *
 * Foto selfie dan koordinat GPS sengaja TIDAK ditampilkan: manager butuh kehadiran, bukan
 * wajah dan lokasi anggotanya.
 */
class TeamAttendanceController extends Controller
{
    /**
     * Daftar tim = potret HARI INI saja. Rekap bulanan sengaja tidak di sini: ia menuntut
     * satu MonthlyAttendance::build() per anggota (±5 query per orang), sementara pertanyaan
     * yang dijawab halaman ini cuma "siapa yang sudah masuk dan siapa yang belum".
     * Rekap lengkap ada di halaman per karyawan.
     */
    public function index(Request $request)
    {
        $manager = $this->authorizeManager($request);
        $members = $this->teamMembers($manager);

        $today = TodayTeamStatus::for($members);

        $rows = $members->map(fn (Employee $member) => [
            'employee' => $member,
            'today' => $today->get($member->id),
        ]);

        return view('employee.team-attendance.index', [
            'employee' => $manager,
            'rows' => $rows,
            'departments' => $this->departmentNames($manager),
            'todaySummary' => $this->summarize($today),
        ]);
    }

    /**
     * Ringkasan hari ini: sudah masuk, belum absen padahal jam masuk lewat, dan yang memang
     * tidak dijadwalkan. Sengaja tidak menghitung "alpha" — harinya belum selesai.
     *
     * @param  Collection<int, array{tone:string}>  $today
     * @return array{hadir:int, telat:int, belum:int, terlewat:int, tak_masuk:int}
     */
    private function summarize(Collection $today): array
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

    public function show(Request $request, int $employeeId)
    {
        $manager = $this->authorizeManager($request);
        $period = $this->period($request);

        $member = $this->teamMembers($manager)->firstWhere('id', $employeeId);

        abort_if(! $member, 403, 'Karyawan ini bukan anggota tim Anda.');

        $data = MonthlyAttendance::build($member, $period, includeSensitive: false);

        return view('employee.team-attendance.show', [
            'employee' => $manager,
            'member' => $member,
            'period' => $period,
            'stats' => $data['stats'],
            'days' => $data['days'],
        ]);
    }

    /** Hanya level 1-3 yang punya departemen (atau manager tanpa level), atau pemegang hr_admin. */
    private function authorizeManager(Request $request): Employee
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $employee->loadMissing('kpiLevel');

        if (OrgLevel::seesWholeCompany($employee)) {
            return $employee;
        }

        abort_unless(OrgLevel::viewerLevel($employee) !== null, 403, 'Halaman ini hanya untuk level 3 ke atas.');
        abort_unless($employee->department_id, 403, 'Anda belum ditetapkan ke departemen mana pun.');

        return $employee;
    }

    private function period(Request $request): Carbon
    {
        $request->validate(['period' => 'nullable|date_format:Y-m']);

        return $request->query('period')
            ? Carbon::parse($request->query('period').'-01')->startOfMonth()
            : Carbon::today()->startOfMonth();
    }

    /** @var array<int, int>|null Sub-pohon departemen manager, dihitung sekali per request. */
    private ?array $deptIds = null;

    /** @return array<int, int> */
    private function teamDepartmentIds(Employee $manager): array
    {
        return $this->deptIds ??= DepartmentTree::withDescendants($manager->department_id);
    }

    /**
     * Anggota tim: karyawan aktif di departemen pelihat dan seluruh turunannya, kecuali
     * dirinya sendiri, dan hanya yang levelnya di bawah pelihat. Level yang tidak diketahui
     * dianggap staf. Pemegang hr_admin melihat seluruh perusahaan, L2 sampai L4.
     *
     * @return Collection<int, Employee>
     */
    private function teamMembers(Employee $manager): Collection
    {
        $wholeCompany = OrgLevel::seesWholeCompany($manager);
        $viewerLevel = $wholeCompany ? 1 : OrgLevel::viewerLevel($manager);

        return Employee::query()
            ->when(! $wholeCompany, fn ($q) => $q->whereIn('department_id', $this->teamDepartmentIds($manager)))
            ->where('company_id', $manager->company_id)
            ->where('is_active', true)
            ->where('id', '!=', $manager->id)
            ->with(['department:id,name', 'kpiLevel:id,code', ...Employee::scheduleTemplateEagerLoads()])
            ->orderBy('department_id')
            ->orderBy('full_name')
            ->get()
            ->filter(fn (Employee $member) => (OrgLevel::of($member) ?? OrgLevel::UNKNOWN_AS) > $viewerLevel)
            ->values();
    }

    /** Nama departemen yang tercakup, untuk ditampilkan sebagai konteks. */
    private function departmentNames(Employee $manager): Collection
    {
        if (OrgLevel::seesWholeCompany($manager)) {
            return collect(['Semua departemen']);
        }

        return Department::whereIn('id', $this->teamDepartmentIds($manager))
            ->orderBy('parent_id')
            ->pluck('name');
    }
}
