<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\MonthlyAttendance;
use App\Support\TeamAttendanceScope;
use App\Support\TodayTeamStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Presensi tim di portal employee untuk level 1-3 (Direksi, Manajer, Leader/SPV).
 *
 * Yang terlihat hanya anggota yang levelnya DI BAWAH pelihat: L2 melihat L3 dan L4, L3 hanya
 * L4. Level diambil dari Level KPI, atau job_level bila Level KPI kosong (App\Support\OrgLevel).
 * Pemegang role hr_admin melihat SEMUA karyawan aktif L2-L4 se-perusahaan, lintas departemen.
 * Aturan cakupannya ada di App\Support\TeamAttendanceScope, dipakai bersama API mobile.
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
        $scope = $this->authorizeManager($request);
        $members = $scope->members();

        $today = TodayTeamStatus::for($members);

        $rows = $members->map(fn (Employee $member) => [
            'employee' => $member,
            'today' => $today->get($member->id),
        ]);

        return view('employee.team-attendance.index', [
            'employee' => $request->attributes->get('employee'),
            'rows' => $rows,
            'departments' => $scope->departmentNames(),
            'todaySummary' => TeamAttendanceScope::summarize($today),
        ]);
    }

    public function show(Request $request, int $employeeId)
    {
        $scope = $this->authorizeManager($request);
        $period = $this->period($request);

        $member = $scope->members()->firstWhere('id', $employeeId);

        abort_if(! $member, 403, 'Karyawan ini bukan anggota tim Anda.');

        $data = MonthlyAttendance::build($member, $period, includeSensitive: false);

        return view('employee.team-attendance.show', [
            'employee' => $request->attributes->get('employee'),
            'member' => $member,
            'period' => $period,
            'stats' => $data['stats'],
            'days' => $data['days'],
        ]);
    }

    /** Hanya level 1-3 yang punya departemen (atau manager tanpa level), atau pemegang hr_admin. */
    private function authorizeManager(Request $request): TeamAttendanceScope
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $scope = new TeamAttendanceScope($employee);

        abort_if($scope->denial() !== null, 403, (string) $scope->denial());

        return $scope;
    }

    private function period(Request $request): Carbon
    {
        $request->validate(['period' => 'nullable|date_format:Y-m']);

        return $request->query('period')
            ? Carbon::parse($request->query('period').'-01')->startOfMonth()
            : Carbon::today()->startOfMonth();
    }
}
