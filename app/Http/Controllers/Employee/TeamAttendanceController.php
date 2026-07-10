<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Support\DepartmentTree;
use App\Support\MonthlyAttendance;
use App\Support\TodayTeamStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presensi tim untuk role MANAGER di portal employee.
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

    /** Hanya manager, dan hanya yang punya departemen. */
    private function authorizeManager(Request $request): Employee
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        abort_unless($employee->role === 'manager', 403, 'Halaman ini hanya untuk manager.');
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
     * Anggota tim: karyawan aktif di departemen manager dan seluruh turunannya, kecuali
     * manager itu sendiri.
     *
     * @return Collection<int, Employee>
     */
    private function teamMembers(Employee $manager): Collection
    {
        return Employee::query()
            ->whereIn('department_id', $this->teamDepartmentIds($manager))
            ->where('company_id', $manager->company_id)
            ->where('is_active', true)
            ->where('id', '!=', $manager->id)
            ->with(['department:id,name', ...Employee::scheduleTemplateEagerLoads()])
            ->orderBy('department_id')
            ->orderBy('full_name')
            ->get();
    }

    /** Nama departemen yang tercakup, untuk ditampilkan sebagai konteks. */
    private function departmentNames(Employee $manager): Collection
    {
        return Department::whereIn('id', $this->teamDepartmentIds($manager))
            ->orderBy('parent_id')
            ->pluck('name');
    }
}
