<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiCrossAssessor;
use App\Models\KpiCrossItem;
use App\Models\KpiDivisionRelation;
use App\Models\KpiPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Matriks relasi kerja antar divisi (Bab 7.3) dan penetapan penilai resmi (Bab 7.4).
 *
 * Keduanya harus beres SEBELUM periode masuk tahap pengisian — Bab 7.2 menegaskan
 * penandaan dilakukan di awal periode, tidak boleh ditentukan setelah hasil keluar.
 */
class KpiCrossMatrixController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));

        $allDepartments = Department::where('company_id', $admin->company_id)
            ->withCount('employees')
            ->orderBy('name')
            ->get();

        $divisions = $allDepartments->where('is_division', true)->values();

        $relations = KpiDivisionRelation::where('company_id', $admin->company_id)
            ->get()
            ->groupBy('department_id');

        $items = KpiCrossItem::where('company_id', $admin->company_id)
            ->orderBy('layer')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('layer');

        $problems = $this->matrixProblems($divisions, $relations);

        return view('admin.kpi.cross.matrix', compact('allDepartments', 'divisions', 'relations', 'items', 'problems'));
    }

    /**
     * Simpan mitra untuk satu divisi. Relasi dibuat DUA ARAH: divisi layanan umum
     * (HRD, IT, Keuangan) dinilai semua divisi, tetapi juga berhak menilai balik —
     * relasi satu arah akan dianggap tidak adil dan merusak kepercayaan pada sistem.
     */
    public function update(Request $request, Department $department)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($department->company_id !== $admin->company_id, 403);

        $data = $request->validate([
            'partners' => 'nullable|array',
            'partners.*' => [
                'integer',
                Rule::exists('departments', 'id')->where('company_id', $admin->company_id),
            ],
        ]);

        $partners = collect($data['partners'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $department->id) // tidak boleh menilai divisi sendiri
            ->unique()
            ->values();

        if ($partners->count() > KpiDivisionRelation::MAX_PARTNERS) {
            return back()->with('error', sprintf(
                'Maksimal %d divisi mitra. Lebih dari itu membuat kualitas pengisian anjlok.',
                KpiDivisionRelation::MAX_PARTNERS
            ));
        }

        DB::transaction(function () use ($department, $partners, $admin) {
            KpiDivisionRelation::where('department_id', $department->id)->delete();
            KpiDivisionRelation::where('partner_department_id', $department->id)->delete();

            foreach ($partners as $partnerId) {
                foreach ([[$department->id, $partnerId], [$partnerId, $department->id]] as [$from, $to]) {
                    KpiDivisionRelation::updateOrCreate(
                        ['department_id' => $from, 'partner_department_id' => $to],
                        ['company_id' => $admin->company_id, 'is_active' => true]
                    );
                }
            }
        });

        return back()->with('success', "Mitra penilaian {$department->name} diperbarui (relasi dibuat dua arah).");
    }

    public function updateFlags(Request $request, Department $department)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($department->company_id !== $admin->company_id, 403);

        $department->update([
            'is_division' => $request->boolean('is_division'),
            'is_shared_service' => $request->boolean('is_shared_service'),
            'kpi_code' => $request->input('kpi_code') ?: null,
        ]);

        return back()->with('success', "Penandaan {$department->name} diperbarui.");
    }

    /** Daftar penilai resmi per periode. */
    public function assessors(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->where('status', '!=', KpiPeriod::STATUS_FINAL)
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        $divisions = Department::where('company_id', $admin->company_id)
            ->where('is_division', true)
            ->orderBy('name')
            ->get();

        $assessors = $period
            ? KpiCrossAssessor::where('kpi_period_id', $period->id)
                ->with('employee:id,full_name,position,department_id,kpi_level_id', 'employee.kpiLevel:id,code')
                ->get()
                ->groupBy('department_id')
            : collect();

        $candidates = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->with('kpiLevel:id,code')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'position', 'department_id', 'kpi_level_id', 'is_cross_functional']);

        return view('admin.kpi.cross.assessors', compact('periods', 'period', 'divisions', 'assessors', 'candidates'));
    }

    public function storeAssessor(Request $request, KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        if ($kpiPeriod->isFinal()) {
            return back()->with('error', 'Periode sudah final.');
        }

        $data = $request->validate([
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where('company_id', $admin->company_id),
            ],
            'employee_id' => [
                'required',
                Rule::exists('employees', 'id')->where('company_id', $admin->company_id),
            ],
            'can_assess_individual' => 'nullable|boolean',
        ]);

        $existing = KpiCrossAssessor::where('kpi_period_id', $kpiPeriod->id)
            ->where('department_id', $data['department_id'])
            ->count();

        if ($existing >= KpiCrossAssessor::MAX_PER_DIVISION) {
            return back()->with('error', sprintf(
                'Sudah %d penilai untuk divisi ini. Bab 7.4 membatasi %d penilai per divisi.',
                $existing,
                KpiCrossAssessor::MAX_PER_DIVISION
            ));
        }

        KpiCrossAssessor::updateOrCreate(
            ['kpi_period_id' => $kpiPeriod->id, 'employee_id' => $data['employee_id']],
            [
                'department_id' => $data['department_id'],
                'can_assess_individual' => $request->boolean('can_assess_individual'),
            ]
        );

        return back()->with('success', 'Penilai silang ditambahkan.');
    }

    public function destroyAssessor(KpiCrossAssessor $kpiCrossAssessor)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiCrossAssessor->period->company_id !== $admin->company_id, 403);

        if ($kpiCrossAssessor->period->isFinal()) {
            return back()->with('error', 'Periode sudah final.');
        }

        $kpiCrossAssessor->delete();

        return back()->with('success', 'Penilai silang dihapus.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Department>  $divisions
     * @return array<int, string>
     */
    private function matrixProblems($divisions, $relations): array
    {
        $problems = [];

        if ($divisions->isEmpty()) {
            return ['Belum ada departemen yang ditandai sebagai divisi. Tandai dulu di tabel di bawah.'];
        }

        foreach ($divisions as $division) {
            $count = ($relations[$division->id] ?? collect())->count();

            if ($count < KpiDivisionRelation::MIN_PARTNERS) {
                $problems[] = sprintf(
                    '%s baru punya %d divisi mitra — minimal %d.',
                    $division->name,
                    $count,
                    KpiDivisionRelation::MIN_PARTNERS
                );
            }
        }

        return $problems;
    }
}
