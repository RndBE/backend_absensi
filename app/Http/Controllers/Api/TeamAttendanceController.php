<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\MonthlyAttendance;
use App\Support\OrgLevel;
use App\Support\TeamAttendanceScope;
use App\Support\TodayTeamStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Presensi tim untuk app mobile. Aturan siapa melihat siapa sama dengan portal employee
 * (App\Support\TeamAttendanceScope): level 1-3 melihat level di bawahnya di departemen sendiri
 * beserta turunannya, hr_admin melihat se-perusahaan.
 *
 * Foto selfie dan koordinat GPS sengaja tidak dikirim: manager butuh kehadiran, bukan wajah
 * dan lokasi anggotanya.
 */
class TeamAttendanceController extends Controller
{
    /** Potret HARI INI: siapa sudah masuk dan siapa belum. Rekap bulanan ada di show(). */
    public function index(Request $request): JsonResponse
    {
        $scope = new TeamAttendanceScope($request->user());

        if ($denied = $this->denied($scope)) {
            return $denied;
        }

        $members = $scope->members();
        $today = TodayTeamStatus::for($members);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => Carbon::today()->toDateString(),
                'departments' => $scope->departmentNames()->values(),
                'summary' => TeamAttendanceScope::summarize($today),
                'members' => $members->map(fn (Employee $member) => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'position' => $member->position,
                    'department' => $member->department?->name,
                    'level' => OrgLevel::of($member),
                    'photo' => $member->photo ? asset('storage/'.$member->photo) : null,
                    'today' => $today->get($member->id),
                ])->values(),
            ],
        ]);
    }

    /** Rekap satu anggota untuk satu bulan (`period=YYYY-MM`, default bulan ini). */
    public function show(Request $request, int $id): JsonResponse
    {
        $request->validate(['period' => 'nullable|date_format:Y-m']);

        $scope = new TeamAttendanceScope($request->user());

        if ($denied = $this->denied($scope)) {
            return $denied;
        }

        $member = $scope->members()->firstWhere('id', $id);

        if (! $member) {
            return response()->json(['success' => false, 'message' => 'Karyawan ini bukan anggota tim Anda.'], 403);
        }

        $period = $request->query('period')
            ? Carbon::parse($request->query('period').'-01')->startOfMonth()
            : Carbon::today()->startOfMonth();

        $data = MonthlyAttendance::build($member, $period, includeSensitive: false);

        return response()->json([
            'success' => true,
            'data' => [
                'member' => [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'position' => $member->position,
                    'department' => $member->department?->name,
                    'level' => OrgLevel::of($member),
                    'photo' => $member->photo ? asset('storage/'.$member->photo) : null,
                ],
                'period' => $period->format('Y-m'),
                'stats' => $data['stats'],
                'days' => $data['days'],
            ],
        ]);
    }

    private function denied(TeamAttendanceScope $scope): ?JsonResponse
    {
        $message = $scope->denial();

        return $message === null
            ? null
            : response()->json(['success' => false, 'message' => $message], 403);
    }
}
