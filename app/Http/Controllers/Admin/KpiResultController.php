<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiFinalResult;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Support\AdminDataScope;
use App\Support\DepartmentTree;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KpiResultController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        $results = $period
            ? $this->scopedResults($period, $admin)
                ->with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name', 'levelSnapshot:id,code,name'])
                ->get()
                ->sortByDesc(fn ($r) => $r->effectiveScore())
            : collect();

        $distribution = $results->groupBy('grade')->map->count();

        return view('admin.kpi.results.index', compact('periods', 'period', 'results', 'distribution'));
    }

    public function show(KpiFinalResult $kpiResult)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiResult->period->company_id !== $admin->company_id, 403);

        if ($departmentId = AdminDataScope::departmentId($admin)) {
            $allowed = DepartmentTree::withDescendants($departmentId);
            abort_if(! in_array((int) $kpiResult->employee->department_id, $allowed, true), 403);
        }

        $kpiResult->load([
            'employee:id,full_name,employee_code,position,department_id',
            'employee.department:id,name',
            'levelSnapshot',
            'period',
        ]);

        $assessments = KpiAssessment::where('kpi_period_id', $kpiResult->kpi_period_id)
            ->where('employee_id', $kpiResult->employee_id)
            ->submitted()
            ->with(['assessor:id,full_name,position', 'scores.indicatorSnapshot'])
            ->get();

        // Skor per indikator = rata-rata tertimbang antar penilai, sama seperti yang dipakai
        // KpiScoreCalculator. Dihitung ulang di sini hanya untuk ditampilkan per baris.
        $perIndicator = [];

        foreach ($assessments as $assessment) {
            foreach ($assessment->scores as $score) {
                $value = $score->effectiveScore();

                if ($value === null) {
                    continue;
                }

                $id = $score->kpi_period_indicator_snapshot_id;
                $perIndicator[$id]['snapshot'] ??= $score->indicatorSnapshot;
                $perIndicator[$id]['sum'] = ($perIndicator[$id]['sum'] ?? 0) + $value * (float) $assessment->weight;
                $perIndicator[$id]['weight'] = ($perIndicator[$id]['weight'] ?? 0) + (float) $assessment->weight;
                $perIndicator[$id]['evidence'][] = [
                    'assessor' => $assessment->assessor?->full_name,
                    'score' => $value,
                    'text' => $score->evidence_text,
                ];
            }
        }

        $rows = collect($perIndicator)
            ->map(fn ($row) => [
                'snapshot' => $row['snapshot'],
                'score' => $row['weight'] > 0 ? $row['sum'] / $row['weight'] : null,
                'evidence' => $row['evidence'],
            ])
            ->filter(fn ($row) => $row['snapshot'] !== null)
            ->sortBy(fn ($row) => $row['snapshot']->sort_order)
            ->groupBy(fn ($row) => $row['snapshot']->category);

        $categories = KpiLevel::CATEGORIES;

        return view('admin.kpi.results.show', compact('kpiResult', 'assessments', 'rows', 'categories'));
    }

    /**
     * Lembar hasil individu untuk arsip dan tanda tangan (Bab 11.3).
     *
     * Identitas penilai silang tidak pernah muncul di sini: berkas ini diserahkan kepada
     * orang yang dinilai, sementara anonimitas penilai silang adalah syarat agar isian
     * berikutnya tetap jujur (Bab 7.8 & 11.4). Hanya atasan langsung yang disebut namanya
     * karena penilaiannya memang terbuka dan ditandatangani bersama.
     */
    public function pdf(KpiFinalResult $kpiResult)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiResult->period->company_id !== $admin->company_id, 403);

        if ($departmentId = AdminDataScope::departmentId($admin)) {
            $allowed = DepartmentTree::withDescendants($departmentId);
            abort_if(! in_array((int) $kpiResult->employee->department_id, $allowed, true), 403);
        }

        $kpiResult->load([
            'employee:id,full_name,employee_code,position,department_id',
            'employee.department:id,name',
            'levelSnapshot',
            'period',
        ]);

        $assessments = KpiAssessment::where('kpi_period_id', $kpiResult->kpi_period_id)
            ->where('employee_id', $kpiResult->employee_id)
            ->submitted()
            ->with(['assessor:id,full_name,position', 'scores.indicatorSnapshot'])
            ->get();

        $rows = $this->indicatorBreakdown($assessments);
        $categories = KpiLevel::CATEGORIES;
        $company = Company::find($kpiResult->period->company_id);

        // Hanya penilai utama (atasan langsung) yang boleh disebut namanya di lembar ini.
        $primaryAssessor = $assessments
            ->firstWhere('assessor_role', KpiAssessment::ROLE_PRIMARY)?->assessor;

        $logoBase64 = null;
        if ($company && $company->logo) {
            $logoPath = storage_path('app/public/'.$company->logo);
            if (file_exists($logoPath)) {
                $logoBase64 = 'data:'.mime_content_type($logoPath).';base64,'.base64_encode(file_get_contents($logoPath));
            }
        }

        $pdf = Pdf::loadView('admin.kpi.results.pdf', compact(
            'kpiResult', 'rows', 'categories', 'company', 'logoBase64', 'primaryAssessor'
        ))->setPaper('A4', 'portrait');

        $filename = 'kpi_'
            .($kpiResult->employee->employee_code ?: $kpiResult->employee_id).'_'
            .(Str::slug($kpiResult->period->name) ?: $kpiResult->kpi_period_id).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Riwayat skor antar periode (Bab 11.3) beserta rata-rata perusahaan per periode
     * (Bab 10.2). Rata-rata perusahaan sengaja dihitung se-perusahaan meski penggunanya
     * manager: angka agregat memang dibagikan terbuka, yang dilindungi adalah nilai
     * per individu.
     */
    public function trend(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $limit = min(max((int) $request->query('periods', 6), 2), 12);

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->orderByDesc('start_date')
            ->limit($limit)
            ->get()
            ->sortBy('start_date')
            ->values();

        if ($periods->isEmpty()) {
            return view('admin.kpi.results.trend', [
                'periods' => $periods,
                'rows' => collect(),
                'companyAverages' => collect(),
                'scopedAverages' => collect(),
                'isScoped' => AdminDataScope::isDepartmentScoped($admin),
                'limit' => $limit,
            ]);
        }

        $periodIds = $periods->pluck('id')->all();

        $visible = KpiFinalResult::whereIn('kpi_period_id', $periodIds)
            ->when(AdminDataScope::departmentId($admin), function ($query, $departmentId) {
                $allowed = DepartmentTree::withDescendants($departmentId);
                $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $allowed));
            })
            ->with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name'])
            ->get();

        $rows = $visible
            ->filter(fn ($result) => $result->employee !== null)
            ->groupBy('employee_id')
            ->map(function ($results) use ($periods) {
                $employee = $results->first()->employee;
                $byPeriod = $results->keyBy('kpi_period_id');
                $cells = [];
                $previous = null;
                $latestResult = null;

                foreach ($periods as $period) {
                    $result = $byPeriod->get($period->id);
                    $score = $result?->effectiveScore();

                    $cells[$period->id] = [
                        'result' => $result,
                        'score' => $score,
                        'grade' => $result?->grade,
                        // Selisih hanya terhadap periode berisi terakhir, bukan periode
                        // sebelumnya secara kalender — karyawan bisa absen satu periode.
                        'delta' => ($score !== null && $previous !== null) ? $score - $previous : null,
                    ];

                    if ($score !== null) {
                        $previous = $score;
                        $latestResult = $result;
                    }
                }

                $scores = collect($cells)->pluck('score')->filter(fn ($s) => $s !== null);

                return [
                    'employee' => $employee,
                    'cells' => $cells,
                    'latestResult' => $latestResult,
                    'average' => $scores->isEmpty() ? null : $scores->avg(),
                    'filled' => $scores->count(),
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower($row['employee']->full_name ?? ''))
            ->values();

        $companyAverages = KpiFinalResult::whereIn('kpi_period_id', $periodIds)
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->get(['kpi_period_id', 'final_score', 'calibrated_score'])
            ->groupBy('kpi_period_id')
            ->map(fn ($group) => [
                'average' => $group->map->effectiveScore()->filter(fn ($s) => $s !== null)->avg(),
                'count' => $group->count(),
            ]);

        $scopedAverages = $visible
            ->groupBy('kpi_period_id')
            ->map(fn ($group) => [
                'average' => $group->map->effectiveScore()->filter(fn ($s) => $s !== null)->avg(),
                'count' => $group->count(),
            ]);

        return view('admin.kpi.results.trend', [
            'periods' => $periods,
            'rows' => $rows,
            'companyAverages' => $companyAverages,
            'scopedAverages' => $scopedAverages,
            'isScoped' => AdminDataScope::isDepartmentScoped($admin),
            'limit' => $limit,
        ]);
    }

    /**
     * Skor per indikator = rata-rata tertimbang antar penilai, sama seperti show().
     * Nama penilai hanya ikut untuk penilai utama; sisanya cukup perannya saja.
     *
     * @param  \Illuminate\Support\Collection<int, KpiAssessment>  $assessments
     */
    private function indicatorBreakdown($assessments)
    {
        $perIndicator = [];

        foreach ($assessments as $assessment) {
            $isPrimary = $assessment->assessor_role === KpiAssessment::ROLE_PRIMARY;

            foreach ($assessment->scores as $score) {
                $value = $score->effectiveScore();

                if ($value === null) {
                    continue;
                }

                $id = $score->kpi_period_indicator_snapshot_id;
                $perIndicator[$id]['snapshot'] ??= $score->indicatorSnapshot;
                $perIndicator[$id]['sum'] = ($perIndicator[$id]['sum'] ?? 0) + $value * (float) $assessment->weight;
                $perIndicator[$id]['weight'] = ($perIndicator[$id]['weight'] ?? 0) + (float) $assessment->weight;
                $perIndicator[$id]['evidence'][] = [
                    'assessor' => $isPrimary ? $assessment->assessor?->full_name : null,
                    'role' => $assessment->roleLabel(),
                    'score' => $value,
                    'text' => $score->evidence_text,
                ];
            }
        }

        return collect($perIndicator)
            ->map(fn ($row) => [
                'snapshot' => $row['snapshot'],
                'score' => $row['weight'] > 0 ? $row['sum'] / $row['weight'] : null,
                'evidence' => $row['evidence'],
            ])
            ->filter(fn ($row) => $row['snapshot'] !== null)
            ->sortBy(fn ($row) => $row['snapshot']->sort_order)
            ->groupBy(fn ($row) => $row['snapshot']->category);
    }

    /** Manager hanya melihat departemennya sendiri beserta turunannya. */
    private function scopedResults(KpiPeriod $period, ?Employee $admin)
    {
        $query = KpiFinalResult::where('kpi_period_id', $period->id);

        if ($departmentId = AdminDataScope::departmentId($admin)) {
            $allowed = DepartmentTree::withDescendants($departmentId);
            $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $allowed));
        }

        return $query;
    }
}
