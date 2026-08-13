<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiCrossResult;
use App\Models\KpiFinalResult;
use App\Models\KpiPeriod;
use Illuminate\Http\Request;

/**
 * Sesi kalibrasi antar penilai (Bab 9.2) dan penyesuaian atasan atas skor kolaborasi
 * (Bab 7.9 Langkah 4).
 *
 * Tujuan kalibrasi bukan memaksa distribusi normal, melainkan menyamakan standar: ada manajer
 * yang murah nilai dan ada yang pelit, dan tanpa pembanding, angka antar divisi tidak bisa
 * disandingkan. Karena itu halaman ini menyandingkan rata-rata tiap divisi dan tiap penilai
 * dengan rata-rata perusahaan, lalu menandai yang menyimpang jauh untuk dijelaskan di forum.
 */
class KpiCalibrationController extends Controller
{
    /**
     * Batas perubahan kalibrasi terhadap nilai perhitungan. Disamakan dengan batas penyesuaian
     * atasan (Bab 7.9 Langkah 4): kalibrasi meluruskan standar penilai, bukan menulis ulang
     * hasil penilaian. Perbedaan yang lebih besar dari ini berarti penilaiannya yang harus
     * diperbaiki, bukan angkanya yang digeser.
     */
    public const MAX_CALIBRATION = 1.0;

    /**
     * Selisih rata-rata divisi terhadap rata-rata perusahaan yang mewajibkan penjelasan.
     * Memakai ambang yang sama dengan koreksi kemurahan hati (Bab 7.8d) supaya HRD tidak
     * memegang dua ukuran "menyimpang" yang berbeda dalam satu siklus.
     */
    public const DEVIATION_THRESHOLD = 0.5;

    /** Toleransi pembanding angka desimal — nilai disimpan 4 desimal, jangan tertolak karena pembulatan. */
    private const EPSILON = 0.0001;

    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        if (! $period) {
            return view('admin.kpi.calibration.index', [
                'periods' => $periods,
                'period' => null,
                'companyAverage' => null,
                'departments' => collect(),
                'assessors' => collect(),
                'results' => collect(),
                'crossResults' => collect(),
                'pendingApproval' => collect(),
            ]);
        }

        $results = KpiFinalResult::where('kpi_period_id', $period->id)
            ->with([
                'employee:id,full_name,employee_code,department_id',
                'employee.department:id,name',
                'levelSnapshot:id,code',
            ])
            ->get()
            ->filter(fn ($result) => $result->employee !== null);

        $scores = $results->map->effectiveScore()->filter(fn ($score) => $score !== null);
        $companyAverage = $scores->isEmpty() ? null : (float) $scores->avg();

        return view('admin.kpi.calibration.index', [
            'periods' => $periods,
            'period' => $period,
            'companyAverage' => $companyAverage,
            'departments' => $this->departmentDistribution($results, $companyAverage),
            'assessors' => $this->assessorDistribution($period),
            'results' => $results->sortByDesc(fn ($result) => $result->effectiveScore())->values(),
            'crossResults' => $this->crossResults($period),
            'pendingApproval' => $this->pendingApproval($period),
        ]);
    }

    /** Simpan hasil kalibrasi satu karyawan. Catatan wajib — angka tanpa alasan tidak bisa diaudit. */
    public function calibrate(Request $request, KpiFinalResult $kpiFinalResult)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiFinalResult->period->company_id !== $admin->company_id, 403);

        $data = $request->validate([
            'calibrated_score' => 'required|numeric|min:1|max:5',
            'calibration_note' => 'required|string|min:10|max:1000',
        ]);

        if ($kpiFinalResult->period->isFinal()) {
            return back()->with('error', 'Periode sudah final. Perubahan nilai hanya lewat jalur sanggah.');
        }

        if ($kpiFinalResult->final_score === null) {
            return back()->with('error', 'Nilai akhir belum dihitung. Jalankan tahap pemrosesan lebih dulu.');
        }

        $score = round((float) $data['calibrated_score'], 4);
        $difference = $score - (float) $kpiFinalResult->final_score;

        if (abs($difference) > self::MAX_CALIBRATION + self::EPSILON) {
            return back()->with('error', sprintf(
                'Kalibrasi maksimal ±%s poin dari nilai perhitungan (%s). Nilai yang diminta menggeser %s poin.',
                number_format(self::MAX_CALIBRATION, 1, ',', '.'),
                number_format((float) $kpiFinalResult->final_score, 2, ',', '.'),
                number_format(abs($difference), 2, ',', '.')
            ));
        }

        $kpiFinalResult->update([
            'calibrated_score' => $score,
            'calibration_note' => $data['calibration_note'],
            // Predikat ikut dihitung ulang dari angka 4 desimal; kalau tidak, kolom predikat
            // akan bertentangan dengan nilai yang berlaku (Bab 8.2 & 8.4).
            'grade' => KpiFinalResult::gradeFor($score),
            'status' => KpiFinalResult::STATUS_CALIBRATED,
        ]);

        return back()->with('success', sprintf(
            'Kalibrasi %s tersimpan: %s → %s (predikat %s).',
            $kpiFinalResult->employee?->full_name ?? 'karyawan',
            number_format((float) $kpiFinalResult->final_score, 2, ',', '.'),
            number_format($score, 2, ',', '.'),
            $kpiFinalResult->grade ?? '—'
        ));
    }

    /**
     * Penyesuaian atasan langsung atas skor kolaborasi (Bab 7.9 Langkah 4): maksimal ±1,0 poin
     * dan wajib beralasan tertulis. Tanpa alasan, sistem menolak.
     */
    public function adjustCross(Request $request, KpiCrossResult $kpiCrossResult)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiCrossResult->period->company_id !== $admin->company_id, 403);

        $max = KpiCrossResult::MAX_ADJUSTMENT;

        $data = $request->validate([
            'superior_adjustment' => 'required|numeric|min:-'.$max.'|max:'.$max,
            'adjustment_reason' => 'required|string|min:10|max:1000',
        ]);

        if ($kpiCrossResult->period->isFinal()) {
            return back()->with('error', 'Periode sudah final. Penyesuaian tidak bisa diubah lagi.');
        }

        if ($kpiCrossResult->isDivisionResult()) {
            return back()->with('error', 'Penyesuaian atasan hanya berlaku untuk skor kolaborasi individu, bukan hasil divisi.');
        }

        $adjustment = round((float) $data['superior_adjustment'], 2);
        $sameDirection = $this->sameDirectionCount($kpiCrossResult, $admin, $adjustment);
        $needsApproval = $sameDirection >= KpiCrossResult::APPROVAL_TRIGGER_COUNT;

        $kpiCrossResult->update([
            'superior_adjustment' => $adjustment,
            'adjustment_reason' => $data['adjustment_reason'],
            'adjusted_by' => $admin->id,
            'adjusted_at' => now(),
            'adjustment_needs_approval' => $needsApproval,
            // Persetujuan lama gugur: yang pernah disetujui adalah angka sebelumnya.
            'adjustment_approved_by' => null,
        ]);

        if ($needsApproval) {
            return back()->with('success', sprintf(
                'Penyesuaian tersimpan tetapi BELUM berlaku. Anda sudah menyesuaikan %d bawahan lain ke arah yang sama pada periode ini, jadi penyesuaian ini menunggu persetujuan atasan di atas Anda.',
                $sameDirection
            ));
        }

        return back()->with('success', 'Penyesuaian tersimpan dan langsung berlaku pada skor kolaborasi.');
    }

    /** Persetujuan atasan di atas untuk penyesuaian yang tertahan. */
    public function approveAdjustment(KpiCrossResult $kpiCrossResult)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiCrossResult->period->company_id !== $admin->company_id, 403);

        if (! $kpiCrossResult->adjustment_needs_approval) {
            return back()->with('error', 'Penyesuaian ini tidak memerlukan persetujuan.');
        }

        if ($kpiCrossResult->adjustment_approved_by !== null) {
            return back()->with('error', 'Penyesuaian ini sudah disetujui.');
        }

        // Aturan Bab 7.9 ada untuk mengerem atasan yang menaikkan seluruh timnya. Kalau ia boleh
        // menyetujui penyesuaiannya sendiri, remnya tidak berfungsi sama sekali.
        if ((int) $kpiCrossResult->adjusted_by === (int) $admin->id) {
            return back()->with('error', 'Penyesuaian harus disetujui atasan di atas, bukan oleh yang mengajukannya.');
        }

        $kpiCrossResult->update(['adjustment_approved_by' => $admin->id]);

        return back()->with('success', 'Penyesuaian disetujui dan kini berlaku pada skor kolaborasi.');
    }

    /**
     * Berapa banyak bawahan LAIN yang sudah disesuaikan atasan yang sama ke arah yang sama pada
     * periode ini. Penyesuaian nol tidak dihitung karena tidak punya arah.
     */
    private function sameDirectionCount(KpiCrossResult $result, Employee $admin, float $adjustment): int
    {
        if (abs($adjustment) < self::EPSILON) {
            return 0;
        }

        $query = KpiCrossResult::where('kpi_period_id', $result->kpi_period_id)
            ->where('adjusted_by', $admin->id)
            ->where('id', '!=', $result->id)
            ->whereNotNull('employee_id');

        return $adjustment > 0
            ? $query->where('superior_adjustment', '>', 0)->count()
            : $query->where('superior_adjustment', '<', 0)->count();
    }

    /**
     * Sebaran nilai akhir per divisi dibanding rata-rata perusahaan (Bab 9.2).
     *
     * @param  \Illuminate\Support\Collection<int, KpiFinalResult>  $results
     */
    private function departmentDistribution($results, ?float $companyAverage)
    {
        $grades = array_keys(KpiFinalResult::GRADE_LABELS);

        return $results
            ->groupBy(fn ($result) => $result->employee->department?->name ?? 'Tanpa departemen')
            ->map(function ($rows, $name) use ($companyAverage, $grades) {
                $values = $rows->map->effectiveScore()->filter(fn ($score) => $score !== null);
                $average = $values->isEmpty() ? null : (float) $values->avg();
                $deviation = ($average === null || $companyAverage === null) ? null : $average - $companyAverage;

                return [
                    'name' => $name,
                    'count' => $rows->count(),
                    'average' => $average,
                    'deviation' => $deviation,
                    'needs_explanation' => $deviation !== null && abs($deviation) > self::DEVIATION_THRESHOLD,
                    'grades' => collect($grades)->mapWithKeys(fn ($grade) => [$grade => $rows->where('grade', $grade)->count()]),
                ];
            })
            ->sortByDesc(fn ($row) => $row['deviation'] ?? 0)
            ->values();
    }

    /**
     * Sebaran skor per penilai. Dihitung dari skor butir yang benar-benar diberikan, bukan dari
     * nilai akhir karyawan — nilai akhir sudah tercampur bobot kategori dan skor silang, jadi
     * tidak menunjukkan kemurahan tangan si penilai.
     */
    private function assessorDistribution(KpiPeriod $period)
    {
        $assessments = KpiAssessment::where('kpi_period_id', $period->id)
            ->submitted()
            ->with(['assessor:id,full_name,department_id', 'assessor.department:id,name', 'scores'])
            ->get();

        $allScores = $assessments
            ->flatMap(fn ($assessment) => $assessment->scores->map(fn ($score) => $score->effectiveScore()))
            ->filter(fn ($score) => $score !== null);

        if ($allScores->isEmpty()) {
            return collect();
        }

        $companyItemAverage = (float) $allScores->avg();

        return $assessments
            ->groupBy('assessor_id')
            ->map(function ($rows) use ($companyItemAverage) {
                $values = $rows
                    ->flatMap(fn ($assessment) => $assessment->scores->map(fn ($score) => $score->effectiveScore()))
                    ->filter(fn ($score) => $score !== null);

                $average = $values->isEmpty() ? null : (float) $values->avg();
                $deviation = $average === null ? null : $average - $companyItemAverage;

                return [
                    'assessor' => $rows->first()->assessor,
                    'assessments' => $rows->count(),
                    'items' => $values->count(),
                    'average' => $average,
                    'company_average' => $companyItemAverage,
                    'deviation' => $deviation,
                    'needs_explanation' => $deviation !== null && abs($deviation) > self::DEVIATION_THRESHOLD,
                ];
            })
            ->sortByDesc(fn ($row) => $row['deviation'] === null ? -1 : abs($row['deviation']))
            ->values();
    }

    /** Skor kolaborasi individu beserta status penyesuaian atasannya. */
    private function crossResults(KpiPeriod $period)
    {
        return KpiCrossResult::where('kpi_period_id', $period->id)
            ->individual()
            ->with([
                'employee:id,full_name,employee_code,department_id',
                'employee.department:id,name',
                'adjuster:id,full_name',
            ])
            ->get()
            ->filter(fn ($row) => $row->employee !== null)
            ->sortBy(fn ($row) => mb_strtolower($row->employee->full_name))
            ->values();
    }

    private function pendingApproval(KpiPeriod $period)
    {
        return KpiCrossResult::where('kpi_period_id', $period->id)
            ->where('adjustment_needs_approval', true)
            ->whereNull('adjustment_approved_by')
            ->with(['employee:id,full_name', 'adjuster:id,full_name'])
            ->get();
    }
}
