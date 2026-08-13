<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiAssessmentScore;
use App\Models\KpiIndicator;
use App\Models\KpiPeriod;
use App\Support\KpiAssessorMap;
use App\Support\KpiAttendanceScore;
use App\Support\KpiCrossAbuseDetector;
use App\Support\KpiCrossScoreCalculator;
use App\Support\KpiPeriodSnapshot;
use App\Support\KpiScoreCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;

class KpiPeriodController extends Controller
{
    public function index()
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->withCount('indicatorSnapshots')
            ->orderByDesc('start_date')
            ->get();

        $problems = app(KpiPeriodSnapshot::class)->weightProblems($admin->company_id);

        return view('admin.kpi.periods.index', compact('periods', 'problems'));
    }

    public function show(KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        $assessments = KpiAssessment::where('kpi_period_id', $kpiPeriod->id)
            ->with(['employee:id,full_name,employee_code,department_id', 'employee.department:id,name', 'assessor:id,full_name'])
            ->get();

        $progress = [
            'total' => $assessments->count(),
            'submitted' => $assessments->where('status', KpiAssessment::STATUS_SUBMITTED)->count(),
        ];

        $byAssessor = $assessments->groupBy('assessor_id')->map(fn ($rows) => [
            'assessor' => $rows->first()->assessor,
            'total' => $rows->count(),
            'submitted' => $rows->where('status', KpiAssessment::STATUS_SUBMITTED)->count(),
        ])->sortByDesc('total');

        $unassigned = $this->unassignedEmployees($admin->company_id);

        return view('admin.kpi.periods.show', compact('kpiPeriod', 'progress', 'byAssessor', 'unassigned'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $data = $this->validated($request);

        KpiPeriod::create($data + [
            'company_id' => $admin->company_id,
            'status' => KpiPeriod::STATUS_DRAFT,
            'is_trial' => $request->boolean('is_trial'),
            'created_by' => $admin->id,
        ]);

        return back()->with('success', 'Periode berhasil dibuat. Buka periode untuk membekukan bobot dan indikator.');
    }

    public function update(Request $request, KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        if ($kpiPeriod->isFinal()) {
            return back()->with('error', 'Periode sudah final dan tidak bisa diubah.');
        }

        $kpiPeriod->update($this->validated($request) + ['is_trial' => $request->boolean('is_trial')]);

        return back()->with('success', 'Periode berhasil diperbarui.');
    }

    /** Draft → dibuka: bekukan bobot, indikator, dan rubrik ke dalam periode. */
    public function open(KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        if (! $kpiPeriod->isDraft()) {
            return back()->with('error', 'Hanya periode berstatus draft yang bisa dibuka.');
        }

        try {
            app(KpiPeriodSnapshot::class)->build($kpiPeriod);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Periode tidak bisa dibuka: '.$e->getMessage());
        }

        $kpiPeriod->update([
            'status' => KpiPeriod::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        return back()->with('success', 'Periode dibuka. Bobot dan indikator sudah dibekukan.');
    }

    public function advance(Request $request, KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        $target = $request->input('status');

        if (! $kpiPeriod->canTransitionTo($target)) {
            return back()->with('error', 'Perpindahan status tidak sah. Status hanya boleh maju satu langkah.');
        }

        if ($target === KpiPeriod::STATUS_PROCESSING) {
            return $this->runProcessing($kpiPeriod);
        }

        // Rencana perbaikan baru dibuat setelah kalibrasi selesai — sebelum itu angkanya
        // masih bisa bergeser, dan menerbitkan PIP dari angka yang belum final tidak adil.
        if ($target === KpiPeriod::STATUS_FINAL && class_exists(\App\Support\KpiFollowUp::class)) {
            $plans = app(\App\Support\KpiFollowUp::class)->generateForPeriod($kpiPeriod, $admin->id);

            $kpiPeriod->update(['status' => $target, 'finalized_at' => now()]);

            return back()->with('success', "Periode difinalkan. {$plans} rencana perbaikan diterbitkan.");
        }

        $kpiPeriod->update([
            'status' => $target,
            'finalized_at' => $target === KpiPeriod::STATUS_FINAL ? now() : $kpiPeriod->finalized_at,
        ]);

        return back()->with('success', 'Status periode diperbarui menjadi '.$kpiPeriod->statusLabel().'.');
    }

    /**
     * Tahap pemrosesan (Bab 9.3, minggu ke-1 bulan berikutnya).
     *
     * Urutannya mengikat: koreksi anti-penyalahgunaan harus jalan SEBELUM skor silang
     * dihitung, dan skor silang harus masuk ke indikator SEBELUM nilai akhir dihitung —
     * kalau tidak, indikator CO yang diisi otomatis masih kosong saat perhitungan berjalan.
     */
    private function runProcessing(KpiPeriod $kpiPeriod)
    {
        $abuse = app(KpiCrossAbuseDetector::class)->process($kpiPeriod);
        $cross = app(KpiCrossScoreCalculator::class)->computeForPeriod($kpiPeriod);
        $applied = app(KpiCrossScoreCalculator::class)->applyToIndicators($kpiPeriod);
        $saved = app(KpiScoreCalculator::class)->computeForPeriod($kpiPeriod);

        $kpiPeriod->update(['status' => KpiPeriod::STATUS_PROCESSING]);

        $message = sprintf(
            'Pemrosesan selesai: %d hasil karyawan dihitung, %d skor divisi dan %d skor kolaborasi individu, %d indikator terisi otomatis.',
            $saved,
            $cross['divisions'],
            $cross['employees'],
            $applied
        );

        return back()
            ->with('success', $message)
            ->with('kpi_abuse', $abuse);
    }

    /**
     * Bikin baris penilaian untuk seluruh karyawan yang layak dinilai.
     *
     * Assessment yang sudah di-submit tidak disentuh — menjalankan ulang setelah ada
     * karyawan baru masuk tidak boleh menghapus pekerjaan penilai yang sudah selesai.
     */
    public function generate(KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        if ($kpiPeriod->isDraft()) {
            return back()->with('error', 'Buka periode dulu sebelum membuat daftar penilaian.');
        }

        $map = app(KpiAssessorMap::class);
        $created = 0;
        $skipped = [];

        Employee::query()
            ->where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->whereNotNull('kpi_level_id')
            // Level atasan dua tingkat ikut dibaca KpiAssessorMap untuk memeriksa Bab 2.1.
            ->with(['kpiLevel', 'manager.manager.kpiLevel'])
            ->chunkById(100, function ($employees) use ($kpiPeriod, $map, &$created, &$skipped) {
                foreach ($employees as $employee) {
                    $reason = $map->blockingReason($employee);

                    if ($reason) {
                        $skipped[] = $employee->full_name.' — '.$reason;

                        continue;
                    }

                    foreach ($map->for($employee) as $row) {
                        $assessment = KpiAssessment::firstOrNew([
                            'kpi_period_id' => $kpiPeriod->id,
                            'employee_id' => $employee->id,
                            'assessor_id' => $row['assessor_id'],
                        ]);

                        if ($assessment->exists && $assessment->isSubmitted()) {
                            continue;
                        }

                        $assessment->fill([
                            'assessor_role' => $row['assessor_role'],
                            'weight' => $row['weight'],
                            'status' => KpiAssessment::STATUS_DRAFT,
                        ])->save();

                        if ($row['assessor_role'] === KpiAssessment::ROLE_PRIMARY) {
                            $this->fillAutoScores($kpiPeriod, $employee, $assessment);
                        }

                        $created++;
                    }
                }
            });

        $message = "{$created} baris penilaian disiapkan.";

        if ($skipped !== []) {
            $message .= ' '.count($skipped).' karyawan dilewati.';
        }

        return back()->with('success', $message)->with('kpi_skipped', $skipped);
    }

    public function destroy(KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        if (! $kpiPeriod->isDraft()) {
            return back()->with('error', 'Hanya periode draft yang boleh dihapus. Periode berjalan menyimpan hasil penilaian.');
        }

        $kpiPeriod->delete();

        return redirect()->route('admin.kpi-periods.index')->with('success', 'Periode berhasil dihapus.');
    }

    /**
     * Indikator ber-`auto_source` diisi sistem dan hanya ditulis ke assessment penilai utama.
     * Kalau ditulis ke keduanya, nilainya tidak berubah (rata-rata tertimbang dari angka yang
     * sama), tetapi tampilannya menyesatkan — seolah penilai pendukung ikut menilainya.
     */
    private function fillAutoScores(KpiPeriod $period, Employee $employee, KpiAssessment $assessment): void
    {
        $indicators = $period->indicatorSnapshots()
            ->where('is_auto_filled', true)
            ->where('auto_source', KpiIndicator::SOURCE_ATTENDANCE)
            ->whereHas('levelSnapshot', fn ($q) => $q->where('kpi_level_id', $employee->kpi_level_id))
            ->get();

        if ($indicators->isEmpty()) {
            return;
        }

        $result = app(KpiAttendanceScore::class)->for(
            $employee,
            Carbon::parse($period->start_date),
            Carbon::parse($period->end_date)
        );

        foreach ($indicators as $indicator) {
            KpiAssessmentScore::updateOrCreate(
                [
                    'kpi_assessment_id' => $assessment->id,
                    'kpi_period_indicator_snapshot_id' => $indicator->id,
                ],
                [
                    'score_raw' => $result['score'] ?? (int) KpiScoreCalculator::DEFAULT_SCORE,
                    'evidence_text' => $result['evidence'],
                ]
            );
        }
    }

    /** Karyawan aktif yang belum bisa dinilai — ditampilkan agar admin bisa membereskannya. */
    private function unassignedEmployees(int $companyId): array
    {
        $map = app(KpiAssessorMap::class);
        $rows = [];

        Employee::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->with('kpiLevel')
            ->orderBy('full_name')
            ->chunkById(200, function ($employees) use ($map, &$rows) {
                foreach ($employees as $employee) {
                    if ($reason = $map->blockingReason($employee)) {
                        $rows[] = ['employee' => $employee, 'reason' => $reason];
                    }
                }
            });

        return $rows;
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'cross_fill_start' => 'nullable|date',
            'cross_fill_end' => 'nullable|date|after_or_equal:cross_fill_start',
            'fill_start' => 'nullable|date',
            'fill_end' => 'nullable|date|after_or_equal:fill_start',
        ]);
    }
}
