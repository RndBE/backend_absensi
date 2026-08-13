<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiAppeal;
use App\Models\KpiFinalResult;
use App\Models\KpiPeriod;
use App\Support\AdminDataScope;
use App\Support\DepartmentTree;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Hak sanggah hasil KPI (Bab 9.4).
 */
class KpiAppealController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        $appeals = $period ? $this->scopedAppeals($period, $admin)
            ->with([
                'employee:id,full_name,employee_code,department_id',
                'employee.department:id,name',
                'decider:id,full_name',
            ])
            ->orderByDesc('submitted_at')
            ->get()
            : collect();

        $appeals->each(fn ($appeal) => $appeal->setRelation('period', $period));

        $deadline = $period ? $this->deadlineFor($period) : null;

        return view('admin.kpi.appeals.index', [
            'periods' => $periods,
            'period' => $period,
            'appeals' => $appeals,
            'deadline' => $deadline,
            'isOpen' => $deadline !== null && ! Carbon::today()->gt($deadline),
            'candidates' => $period ? $this->candidates($period, $admin, $appeals->pluck('employee_id')->all()) : collect(),
        ]);
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $data = $request->validate([
            'kpi_period_id' => 'required|integer|exists:kpi_periods,id',
            'employee_id' => 'required|integer|exists:employees,id',
            'reason' => 'required|string|min:10|max:2000',
        ]);

        $period = KpiPeriod::findOrFail($data['kpi_period_id']);
        abort_if($period->company_id !== $admin->company_id, 403);

        $employee = Employee::findOrFail($data['employee_id']);
        abort_if($employee->company_id !== $admin->company_id, 403);

        $hasResult = KpiFinalResult::where('kpi_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->exists();

        if (! $hasResult) {
            return back()->with('error', 'Karyawan ini belum punya hasil akhir pada periode tersebut.');
        }

        $deadline = $this->deadlineFor($period);

        if ($deadline === null) {
            return back()->with('error', 'Periode belum difinalkan — hasil belum disampaikan sehingga tenggat sanggah belum berjalan.');
        }

        // Bab 9.4: keputusan sanggahan bersifat final untuk periode tersebut, jadi tenggat
        // yang lewat tidak bisa dibuka lagi lewat pengajuan susulan.
        if (Carbon::today()->gt($deadline)) {
            return back()->with('error', 'Tenggat sanggah sudah lewat ('.$deadline->format('d/m/Y').'). Sanggahan tidak bisa diajukan lagi.');
        }

        $exists = KpiAppeal::where('kpi_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'Karyawan ini sudah mengajukan sanggahan untuk periode tersebut.');
        }

        KpiAppeal::create([
            'kpi_period_id' => $period->id,
            'employee_id' => $employee->id,
            'reason' => $data['reason'],
            'submitted_at' => now(),
            'deadline_at' => $deadline,
            'status' => KpiAppeal::STATUS_SUBMITTED,
        ]);

        return back()->with('success', 'Sanggahan tercatat. Keputusan diambil atasan dua tingkat, paling lama 14 hari kerja.');
    }

    public function decide(Request $request, KpiAppeal $kpiAppeal)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiAppeal->period->company_id !== $admin->company_id, 403);

        $data = $request->validate([
            'status' => 'required|in:'.KpiAppeal::STATUS_ACCEPTED.','.KpiAppeal::STATUS_REJECTED,
            'decision_note' => 'required|string|min:10|max:2000',
        ]);

        if ($kpiAppeal->isDecided()) {
            return back()->with('error', 'Sanggahan sudah diputus dan keputusannya final untuk periode ini.');
        }

        if (! $this->mayDecide($admin, $kpiAppeal)) {
            return back()->with('error', 'Keputusan sanggahan hanya boleh diambil atasan dua tingkat di atas karyawan yang bersangkutan.');
        }

        $kpiAppeal->update([
            'status' => $data['status'],
            'decision_note' => $data['decision_note'],
            'decided_by' => $admin->id,
            'decided_at' => now(),
        ]);

        return back()->with('success', 'Keputusan sanggahan tercatat dan bersifat final untuk periode ini.');
    }

    /**
     * Bab 9.4 menempatkan kewenangan memutus pada atasan dua tingkat, bukan HRD — HRD hanya
     * menerima dan menyalurkan. Superadmin tetap dibolehkan sebagai jalan keluar saat posisi
     * atasan dua tingkat kosong atau karyawannya sudah tidak aktif.
     */
    private function mayDecide(?Employee $admin, KpiAppeal $appeal): bool
    {
        if (! $admin) {
            return false;
        }

        if ($admin->role === 'superadmin') {
            return true;
        }

        return $admin->id === $appeal->employee?->manager?->manager_id;
    }

    /**
     * Hasil dianggap diterima karyawan saat periode difinalkan (Bab 9.3, minggu ke-4).
     * Satu tanggal untuk seluruh karyawan agar tenggatnya sama rata dan tidak bergantung pada
     * urutan persetujuan tiap baris hasil.
     */
    private function deadlineFor(KpiPeriod $period): ?Carbon
    {
        if (! $period->finalized_at) {
            return null;
        }

        return KpiAppeal::deadlineFor($period->finalized_at, $period->company_id);
    }

    /** Karyawan berhasil final yang belum mengajukan sanggahan pada periode ini. */
    private function candidates(KpiPeriod $period, ?Employee $admin, array $alreadyAppealed)
    {
        $query = KpiFinalResult::where('kpi_period_id', $period->id)
            ->whereNotIn('employee_id', $alreadyAppealed)
            ->with('employee:id,full_name,employee_code');

        if ($departmentId = AdminDataScope::departmentId($admin)) {
            $allowed = DepartmentTree::withDescendants($departmentId);
            $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $allowed));
        }

        return $query->get()
            ->filter(fn ($result) => $result->employee !== null)
            ->sortBy(fn ($result) => $result->employee->full_name);
    }

    /** Manager hanya melihat sanggahan dari departemennya sendiri beserta turunannya. */
    private function scopedAppeals(KpiPeriod $period, ?Employee $admin)
    {
        $query = KpiAppeal::where('kpi_period_id', $period->id);

        if ($departmentId = AdminDataScope::departmentId($admin)) {
            $allowed = DepartmentTree::withDescendants($departmentId);
            $query->whereHas('employee', fn ($q) => $q->whereIn('department_id', $allowed));
        }

        return $query;
    }
}
