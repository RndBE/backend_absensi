<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiImprovementPlan;
use App\Models\KpiPeriod;
use App\Support\AdminDataScope;
use App\Support\DepartmentTree;
use App\Support\KpiFollowUp;
use Illuminate\Http\Request;

/**
 * Tindak lanjut wajib atas hasil KPI (Bab 10.1).
 */
class KpiImprovementPlanController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        $plans = $period
            ? $this->scopedPlans($period, $admin)->with('creator:id,full_name')->orderBy('due_date')->get()
            : collect();

        if ($plans->isNotEmpty()) {
            KpiImprovementPlan::preloadSubjects($plans);
        }

        $summary = [
            'total' => $plans->count(),
            'overdue' => $plans->filter->isOverdue()->count(),
            'done' => $plans->where('status', KpiImprovementPlan::STATUS_DONE)->count(),
        ];

        return view('admin.kpi.improvement-plans.index', compact('periods', 'period', 'plans', 'summary'));
    }

    /** Jalankan tabel pemicu Bab 10.1 atas hasil periode ini. */
    public function generate(KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        $notComputed = [KpiPeriod::STATUS_DRAFT, KpiPeriod::STATUS_OPEN, KpiPeriod::STATUS_FILLING];

        if (in_array($kpiPeriod->status, $notComputed, true)) {
            return back()->with('error', 'Nilai akhir belum dihitung. Masukkan periode ke tahap Pemrosesan lebih dulu.');
        }

        $created = app(KpiFollowUp::class)->generateForPeriod($kpiPeriod, $admin->id);

        if ($created === 0) {
            return back()->with('success', 'Tidak ada pemicu tindak lanjut pada periode ini.');
        }

        return back()->with('success', "{$created} rencana perbaikan tercatat sesuai tabel pemicu.");
    }

    public function update(Request $request, KpiImprovementPlan $kpiImprovementPlan)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiImprovementPlan->period->company_id !== $admin->company_id, 403);

        $data = $request->validate([
            'plan_text' => 'nullable|string|max:5000',
            'due_date' => 'nullable|date',
            'review_date' => 'nullable|date|after_or_equal:due_date',
            'status' => 'required|in:'.implode(',', array_keys(KpiImprovementPlan::STATUS_LABELS)),
        ]);

        // Rencana yang ditandai selesai tanpa isi tidak bisa ditinjau periode berikutnya —
        // itu persis cara tindak lanjut wajib berubah jadi formalitas.
        if ($data['status'] === KpiImprovementPlan::STATUS_DONE && trim((string) $data['plan_text']) === '') {
            return back()->with('error', 'Isi rencana perbaikan dulu sebelum menandainya selesai.');
        }

        $kpiImprovementPlan->update($data);

        return back()->with('success', 'Rencana perbaikan diperbarui.');
    }

    /** Manager hanya melihat divisinya sendiri beserta turunannya dan karyawan di dalamnya. */
    private function scopedPlans(KpiPeriod $period, ?Employee $admin)
    {
        $query = KpiImprovementPlan::where('kpi_period_id', $period->id);

        if ($departmentId = AdminDataScope::departmentId($admin)) {
            $allowed = DepartmentTree::withDescendants($departmentId);
            $employeeIds = Employee::whereIn('department_id', $allowed)->pluck('id')->all();

            $query->where(function ($q) use ($allowed, $employeeIds) {
                $q->where(fn ($sub) => $sub->where('subject_type', KpiImprovementPlan::SUBJECT_DIVISION)
                    ->whereIn('subject_id', $allowed))
                    ->orWhere(fn ($sub) => $sub->where('subject_type', KpiImprovementPlan::SUBJECT_EMPLOYEE)
                        ->whereIn('subject_id', $employeeIds));
            });
        }

        return $query;
    }
}
