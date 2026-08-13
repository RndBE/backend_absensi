<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiCrossAssessor;
use App\Models\KpiCrossItem;
use App\Models\KpiCrossLayerA;
use App\Models\KpiCrossLayerAScore;
use App\Models\KpiCrossLayerB;
use App\Models\KpiCrossLayerBScore;
use App\Models\KpiDivisionRelation;
use App\Models\KpiPeriod;
use App\Support\DepartmentTree;
use App\Support\KpiCrossTargets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pengisian penilaian silang oleh penilai resmi (Bab 7.5 dan 7.6).
 *
 * Anonimitas: identitas penilai TETAP disimpan di database dan dapat diakses HRD, tetapi
 * tidak pernah dikirim ke halaman pihak yang dinilai. Aturannya disampaikan terang-terangan
 * di layar pengisian — orang lebih hati-hati menulis kalau tahu ada jejaknya, tetapi tetap
 * merasa aman dari konfrontasi langsung.
 */
class KpiCrossAssessmentController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->whereNotIn('status', [KpiPeriod::STATUS_DRAFT])
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        $assignment = $period
            ? KpiCrossAssessor::where('kpi_period_id', $period->id)->where('employee_id', $admin->id)->first()
            : null;

        $partners = collect();
        $individuals = collect();
        $doneA = collect();
        $doneB = collect();

        if ($period && $assignment) {
            $partnerIds = KpiDivisionRelation::where('department_id', $assignment->department_id)
                ->where('is_active', true)
                ->pluck('partner_department_id');

            $partners = Department::whereIn('id', $partnerIds)->orderBy('name')->get();

            $doneA = KpiCrossLayerA::where('kpi_period_id', $period->id)
                ->where('assessor_id', $admin->id)
                ->get()
                ->keyBy('target_department_id');

            if ($assignment->can_assess_individual) {
                $individuals = $this->individualTargets($period, $partnerIds->all());

                $doneB = KpiCrossLayerB::where('kpi_period_id', $period->id)
                    ->where('assessor_id', $admin->id)
                    ->get()
                    ->keyBy('target_employee_id');
            }
        }

        return view('admin.kpi.cross.index', compact(
            'periods', 'period', 'assignment', 'partners', 'individuals', 'doneA', 'doneB'
        ));
    }

    /** Form Lapis A — menilai satu divisi mitra. */
    public function editDivision(KpiPeriod $kpiPeriod, Department $department)
    {
        $admin = Employee::find(session('admin_id'));
        $assignment = $this->authorizeAssessor($kpiPeriod, $admin);
        $this->authorizePartner($assignment, $department->id);

        $items = $kpiPeriod->crossItemSnapshots()
            ->layer(KpiCrossItem::LAYER_DIVISION)
            ->orderBy('sort_order')
            ->get();

        $submission = KpiCrossLayerA::where('kpi_period_id', $kpiPeriod->id)
            ->where('assessor_id', $admin->id)
            ->where('target_department_id', $department->id)
            ->with('scores')
            ->first();

        $scores = $submission ? $submission->scores->keyBy('item_code') : collect();

        return view('admin.kpi.cross.division-form', compact('kpiPeriod', 'department', 'items', 'submission', 'scores'));
    }

    public function storeDivision(Request $request, KpiPeriod $kpiPeriod, Department $department)
    {
        $admin = Employee::find(session('admin_id'));
        $assignment = $this->authorizeAssessor($kpiPeriod, $admin);
        $this->authorizePartner($assignment, $department->id);

        $items = $kpiPeriod->crossItemSnapshots()->layer(KpiCrossItem::LAYER_DIVISION)->get();

        $data = $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'required|integer|min:1|max:5',
            // Kedua kolom isian bebas WAJIB (Bab 7.5). Kolom "perlu diperbaiki" biasanya
            // jauh lebih berguna daripada seluruh angkanya — di situ masalah alur kerja
            // yang sesungguhnya terungkap.
            'comment_positive' => 'required|string|max:2000',
            'comment_improvement' => 'required|string|max:2000',
        ]);

        $missing = $items->pluck('code')->diff(array_keys($data['scores']));

        if ($missing->isNotEmpty()) {
            return back()->with('error', 'Masih ada butir yang belum diisi: '.$missing->implode(', '))->withInput();
        }

        $submission = DB::transaction(function () use ($kpiPeriod, $admin, $assignment, $department, $data, $items) {
            $submission = KpiCrossLayerA::updateOrCreate(
                [
                    'kpi_period_id' => $kpiPeriod->id,
                    'assessor_id' => $admin->id,
                    'target_department_id' => $department->id,
                ],
                [
                    'assessor_department_id' => $assignment->department_id,
                    'comment_positive' => $data['comment_positive'],
                    'comment_improvement' => $data['comment_improvement'],
                    'submitted_at' => now(),
                ]
            );

            foreach ($items as $item) {
                KpiCrossLayerAScore::updateOrCreate(
                    ['kpi_cross_layer_a_id' => $submission->id, 'item_code' => $item->code],
                    ['score' => $data['scores'][$item->code]]
                );
            }

            return $submission;
        });

        return redirect()
            ->route('admin.kpi-cross.index', ['period' => $kpiPeriod->id])
            ->with('success', "Penilaian untuk divisi {$department->name} tersimpan.");
    }

    /** Form Lapis B — menilai satu individu lintas fungsi. */
    public function editIndividual(KpiPeriod $kpiPeriod, Employee $employee)
    {
        $admin = Employee::find(session('admin_id'));
        $assignment = $this->authorizeAssessor($kpiPeriod, $admin, true);
        $this->authorizeIndividualTarget($kpiPeriod, $assignment, $employee);

        $items = $kpiPeriod->crossItemSnapshots()
            ->layer(KpiCrossItem::LAYER_INDIVIDUAL)
            ->orderBy('sort_order')
            ->get();

        $submission = KpiCrossLayerB::where('kpi_period_id', $kpiPeriod->id)
            ->where('assessor_id', $admin->id)
            ->where('target_employee_id', $employee->id)
            ->with('scores')
            ->first();

        $scores = $submission ? $submission->scores->keyBy('item_code') : collect();

        return view('admin.kpi.cross.individual-form', compact('kpiPeriod', 'employee', 'items', 'submission', 'scores'));
    }

    public function storeIndividual(Request $request, KpiPeriod $kpiPeriod, Employee $employee)
    {
        $admin = Employee::find(session('admin_id'));
        $assignment = $this->authorizeAssessor($kpiPeriod, $admin, true);
        $this->authorizeIndividualTarget($kpiPeriod, $assignment, $employee);

        $items = $kpiPeriod->crossItemSnapshots()->layer(KpiCrossItem::LAYER_INDIVIDUAL)->get();

        $data = $request->validate([
            'scores' => 'required|array',
            'scores.*' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ]);

        $missing = $items->pluck('code')->diff(array_keys($data['scores']));

        if ($missing->isNotEmpty()) {
            return back()->with('error', 'Masih ada butir yang belum diisi: '.$missing->implode(', '))->withInput();
        }

        DB::transaction(function () use ($kpiPeriod, $admin, $assignment, $employee, $data, $items) {
            $submission = KpiCrossLayerB::updateOrCreate(
                [
                    'kpi_period_id' => $kpiPeriod->id,
                    'assessor_id' => $admin->id,
                    'target_employee_id' => $employee->id,
                ],
                [
                    'assessor_department_id' => $assignment->department_id,
                    'comment' => $data['comment'] ?? null,
                    'submitted_at' => now(),
                ]
            );

            foreach ($items as $item) {
                KpiCrossLayerBScore::updateOrCreate(
                    ['kpi_cross_layer_b_id' => $submission->id, 'item_code' => $item->code],
                    ['score' => $data['scores'][$item->code]]
                );
            }
        });

        return redirect()
            ->route('admin.kpi-cross.index', ['period' => $kpiPeriod->id])
            ->with('success', "Penilaian untuk {$employee->full_name} tersimpan.");
    }

    /** Sasaran Lapis B — aturannya di App\Support\KpiCrossTargets, dipakai bersama graf relasi. */
    private function individualTargets(KpiPeriod $period, array $partnerDepartmentIds)
    {
        return app(KpiCrossTargets::class)->individuals($period->company_id, $partnerDepartmentIds);
    }

    private function expandDivisions(array $divisionIds): array
    {
        return app(KpiCrossTargets::class)->expandDivisions($divisionIds);
    }

    private function authorizeAssessor(KpiPeriod $period, ?Employee $admin, bool $needsIndividual = false): KpiCrossAssessor
    {
        abort_if(! $admin || $period->company_id !== $admin->company_id, 403);

        $assignment = KpiCrossAssessor::where('kpi_period_id', $period->id)
            ->where('employee_id', $admin->id)
            ->first();

        abort_if(! $assignment, 403, 'Anda bukan penilai silang resmi pada periode ini.');
        abort_if($needsIndividual && ! $assignment->can_assess_individual, 403, 'Anda hanya ditetapkan menilai divisi (Lapis A).');
        abort_if($period->isFinal(), 403, 'Periode sudah final.');

        return $assignment;
    }

    /** Tidak boleh menilai divisi sendiri, dan hanya divisi yang benar-benar jadi mitra kerja. */
    private function authorizePartner(KpiCrossAssessor $assignment, int $targetDepartmentId): void
    {
        abort_if($assignment->department_id === $targetDepartmentId, 403, 'Tidak boleh menilai divisi sendiri.');

        $isPartner = KpiDivisionRelation::where('department_id', $assignment->department_id)
            ->where('partner_department_id', $targetDepartmentId)
            ->where('is_active', true)
            ->exists();

        abort_if(! $isPartner, 403, 'Divisi ini bukan mitra kerja divisi Anda pada matriks relasi.');
    }

    /**
     * Sasaran Lapis B sah bila departemennya berada di bawah salah satu divisi mitra —
     * bukan harus persis simpul divisinya, karena karyawan tersebar di sub-departemen.
     */
    private function authorizeIndividualTarget(KpiPeriod $period, KpiCrossAssessor $assignment, Employee $target): void
    {
        abort_if($target->company_id !== $period->company_id, 403);
        abort_if($target->id === $assignment->employee_id, 403, 'Tidak boleh menilai diri sendiri.');

        $ownDivision = DepartmentTree::withDescendants($assignment->department_id);
        abort_if(
            in_array((int) $target->department_id, $ownDivision, true),
            403,
            'Tidak boleh menilai orang dari divisi sendiri.'
        );

        $partnerIds = KpiDivisionRelation::where('department_id', $assignment->department_id)
            ->where('is_active', true)
            ->pluck('partner_department_id')
            ->all();

        abort_if(
            ! in_array((int) $target->department_id, $this->expandDivisions($partnerIds), true),
            403,
            'Orang ini bukan dari divisi mitra kerja Anda.'
        );
    }
}
