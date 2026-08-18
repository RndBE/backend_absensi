<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\KpiIndicatorSet;
use App\Models\KpiAssessment;
use App\Models\KpiAssessmentScore;
use App\Models\KpiPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KpiAssessmentController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->whereNotIn('status', [KpiPeriod::STATUS_DRAFT])
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        $assessments = $period
            ? KpiAssessment::where('kpi_period_id', $period->id)
                ->where('assessor_id', $admin->id)
                ->with(['employee:id,full_name,employee_code,position,department_id,kpi_level_id', 'employee.department:id,name', 'employee.kpiLevel:id,code'])
                ->withCount(['scores as filled_count' => fn ($q) => $q->whereNotNull('score_raw')])
                ->get()
                ->sortBy(fn ($a) => $a->employee->full_name)
            : collect();

        return view('admin.kpi.assessments.index', compact('periods', 'period', 'assessments'));
    }

    public function edit(KpiAssessment $kpiAssessment)
    {
        $admin = Employee::find(session('admin_id'));
        $this->authorizeAssessor($kpiAssessment, $admin);

        $kpiAssessment->load([
            'period',
            'employee:id,full_name,employee_code,position,department_id,kpi_level_id',
            'employee.department:id,name',
            'employee.kpiLevel:id,code,name',
        ]);

        $levelSnapshot = $kpiAssessment->period->levelSnapshots()
            ->where('kpi_level_id', $kpiAssessment->employee->kpi_level_id)
            ->first();

        abort_if(! $levelSnapshot, 404, 'Snapshot level untuk karyawan ini tidak ditemukan pada periode tersebut.');

        // Lewat KpiIndicatorSet, bukan langsung dari snapshot level: karyawan yang punya indikator
        // Excellence sendiri harus melihat miliknya, bukan bawaan levelnya.
        $indicators = app(KpiIndicatorSet::class)
            ->forEmployee($kpiAssessment->period, $kpiAssessment->employee)
            ->groupBy('category');
        $scores = $kpiAssessment->scores->keyBy('kpi_period_indicator_snapshot_id');

        return view('admin.kpi.assessments.edit', compact('kpiAssessment', 'levelSnapshot', 'indicators', 'scores'));
    }

    /** Simpan sebagai draft — boleh setengah terisi, tanpa pemeriksaan bukti. */
    public function update(Request $request, KpiAssessment $kpiAssessment)
    {
        $admin = Employee::find(session('admin_id'));
        $this->authorizeAssessor($kpiAssessment, $admin);

        if (! $kpiAssessment->isEditable()) {
            return back()->with('error', 'Penilaian sudah dikirim dan tidak bisa diubah lagi.');
        }

        $this->saveScores($request, $kpiAssessment);

        return back()->with('success', 'Draft penilaian tersimpan.');
    }

    /**
     * Kirim final. Di sinilah aturan bukti Bab 1.3 ditegakkan: skor ≥ 4 dan ≤ 2 wajib
     * disertai contoh kejadian konkret, skor 3 tidak. Tanpa bukti, sistem menolak simpan.
     */
    public function submit(Request $request, KpiAssessment $kpiAssessment)
    {
        $admin = Employee::find(session('admin_id'));
        $this->authorizeAssessor($kpiAssessment, $admin);

        if (! $kpiAssessment->isEditable()) {
            return back()->with('error', 'Penilaian sudah dikirim sebelumnya.');
        }

        $this->saveScores($request, $kpiAssessment);
        $kpiAssessment->refresh()->load('scores.indicatorSnapshot');

        $missingScore = [];
        $missingEvidence = [];

        $required = app(KpiIndicatorSet::class)
            ->forEmployee($kpiAssessment->period, $kpiAssessment->employee)
            ->where('is_auto_filled', false);

        $scores = $kpiAssessment->scores->keyBy('kpi_period_indicator_snapshot_id');

        foreach ($required as $indicator) {
            $score = $scores->get($indicator->id);

            if (! $score || $score->score_raw === null) {
                $missingScore[] = $indicator->code;

                continue;
            }

            if ($score->needsEvidence() && ! $score->hasEvidence()) {
                $missingEvidence[] = $indicator->code.' (skor '.$score->score_raw.')';
            }
        }

        if ($missingScore !== []) {
            return back()->with('error', 'Masih ada indikator yang belum diberi skor: '.implode(', ', $missingScore));
        }

        if ($missingEvidence !== []) {
            return back()->with('error', 'Skor 4–5 dan 1–2 wajib disertai contoh kejadian. Bukti belum diisi untuk: '.implode(', ', $missingEvidence));
        }

        $kpiAssessment->update([
            'status' => KpiAssessment::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('admin.kpi-assessments.index', ['period' => $kpiAssessment->kpi_period_id])
            ->with('success', 'Penilaian untuk '.$kpiAssessment->employee->full_name.' berhasil dikirim.');
    }

    /**
     * Indikator ber-`auto_source` sengaja dilewati: nilainya berasal dari data sistem dan
     * penilai tidak boleh menimpanya lewat form.
     */
    private function saveScores(Request $request, KpiAssessment $kpiAssessment): void
    {
        $data = $request->validate([
            'scores' => 'nullable|array',
            'scores.*' => 'nullable|integer|min:1|max:5',
            'evidence' => 'nullable|array',
            'evidence.*' => 'nullable|string|max:2000',
        ]);

        // Daftar yang boleh disimpan dibatasi set milik karyawan ini. Tanpa itu, id indikator milik
        // orang lain yang dikirim lewat form bisa ikut tersimpan.
        $editable = app(KpiIndicatorSet::class)
            ->forEmployee($kpiAssessment->period, $kpiAssessment->employee)
            ->where('is_auto_filled', false)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($data, $editable, $kpiAssessment) {
            foreach ($editable as $indicatorId) {
                $score = $data['scores'][$indicatorId] ?? null;
                $evidence = $data['evidence'][$indicatorId] ?? null;

                if ($score === null && ($evidence === null || trim($evidence) === '')) {
                    continue;
                }

                KpiAssessmentScore::updateOrCreate(
                    [
                        'kpi_assessment_id' => $kpiAssessment->id,
                        'kpi_period_indicator_snapshot_id' => $indicatorId,
                    ],
                    [
                        'score_raw' => $score,
                        'evidence_text' => $evidence,
                    ]
                );
            }
        });
    }

    private function authorizeAssessor(KpiAssessment $assessment, ?Employee $admin): void
    {
        abort_if(! $admin || $assessment->assessor_id !== $admin->id, 403, 'Anda bukan penilai untuk penilaian ini.');
    }
}
