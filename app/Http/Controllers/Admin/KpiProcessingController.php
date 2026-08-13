<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiCrossLayerA;
use App\Models\KpiCrossLayerAScore;
use App\Models\KpiCrossLayerB;
use App\Models\KpiCrossLayerBScore;
use App\Models\KpiLeniencyCorrection;
use App\Models\KpiPeriod;
use App\Support\KpiCrossAbuseDetector;
use Illuminate\Http\Request;

/**
 * Tinjauan pemrosesan HRD (Bab 9.3 minggu ke-1 bulan berikutnya): koreksi anti-penyalahgunaan
 * (Bab 7.8) dan penyaringan komentar (Bab 7.7), dijalankan sebelum sesi kalibrasi.
 *
 * Halaman ini memang menampilkan data mentah — Bab 7.7 menempatkan HRD sebagai satu-satunya
 * pihak yang boleh melihatnya. Yang tetap tidak ditampilkan adalah identitas penilai di balik
 * tiap komentar: penyaringan komentar tidak butuh tahu siapa penulisnya, dan begitu identitas
 * itu muncul di layar, ia akan ikut bocor ke ruangan.
 */
class KpiProcessingController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $periods = KpiPeriod::where('company_id', $admin->company_id)
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        if (! $period) {
            return view('admin.kpi.processing.index', [
                'periods' => $periods,
                'period' => null,
                'summary' => $this->emptySummary(),
                'leniency' => collect(),
                'retaliation' => [],
                'commentsA' => collect(),
                'commentsB' => collect(),
            ]);
        }

        return view('admin.kpi.processing.index', [
            'periods' => $periods,
            'period' => $period,
            'summary' => $this->summary($period),
            'leniency' => $this->leniencyRows($period),
            'retaliation' => $this->retaliationRows($period),
            'commentsA' => $this->layerAComments($period),
            'commentsB' => $this->layerBComments($period),
        ]);
    }

    /**
     * Jalankan ulang seluruh pemeriksaan Bab 7.8. Aman diulang: detektor menghitung ulang dari
     * skor mentah dan hanya mencabut penanda yang dipasang sistem, jadi pembuangan manual HRD
     * dan komentar yang sudah disaring tidak ikut hilang.
     */
    public function run(KpiPeriod $kpiPeriod)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiPeriod->company_id !== $admin->company_id, 403);

        if ($kpiPeriod->isDraft()) {
            return back()->with('error', 'Periode masih draft — belum ada isian penilaian silang yang bisa diproses.');
        }

        $result = app(KpiCrossAbuseDetector::class)->process($kpiPeriod);

        $message = sprintf(
            'Pemrosesan selesai. %d kuesioner dibuang karena pengisian asal, %d baris skor dipangkas karena persekongkolan, %d penilai kena koreksi kemurahan hati, %d pasangan divisi ditandai untuk peninjauan pembalasan.',
            $result['straight_lining'],
            $result['collusion'],
            $result['leniency'],
            count($result['retaliation'])
        );

        if ($result['retaliation'] !== []) {
            $message .= ' Pasangan divisi yang ditandai tidak diubah otomatis — panggil kedua kepala divisi.';
        }

        return back()->with('success', $message);
    }

    /**
     * Sembunyikan atau tampilkan kembali satu komentar (Bab 7.7).
     *
     * Sengaja dua arah. Batas antara "menyerang pribadi" dan "kritik keras yang faktual" tipis,
     * dan Bab 7.7 memperingatkan bahwa HRD yang menghaluskan kritik benar akan mematikan sistem
     * ini dalam satu periode — keputusan menyembunyikan harus bisa ditarik, bukan sekali jalan.
     */
    public function hideComment(Request $request, string $layer, int $id)
    {
        $admin = Employee::find(session('admin_id'));

        abort_unless(in_array($layer, ['a', 'b'], true), 404);

        $data = $request->validate([
            'action' => 'required|in:hide,restore',
            'hidden_reason' => 'required_if:action,hide|nullable|string|min:5|max:100',
        ]);

        $model = $layer === 'a' ? KpiCrossLayerA::class : KpiCrossLayerB::class;

        // anonymous(): identitas penilai tidak pernah ikut termuat, bahkan di jalur tulis.
        $submission = $model::anonymous()->findOrFail($id);

        $period = KpiPeriod::find($submission->kpi_period_id);
        abort_if($period === null || $period->company_id !== $admin->company_id, 403);

        if ($data['action'] === 'restore') {
            $submission->update(['comment_hidden' => false, 'hidden_reason' => null]);

            return back()->with('success', 'Komentar ditampilkan kembali kepada pihak yang dinilai.');
        }

        $submission->update([
            'comment_hidden' => true,
            'hidden_reason' => $data['hidden_reason'],
        ]);

        return back()->with('success', 'Komentar disembunyikan. Kritik keras yang faktual tidak boleh ikut dibuang — tinjau ulang bila ragu.');
    }

    /** @return array<string, int> */
    private function summary(KpiPeriod $period): array
    {
        $layerAIds = KpiCrossLayerA::where('kpi_period_id', $period->id)->select('id');
        $layerBIds = KpiCrossLayerB::where('kpi_period_id', $period->id)->select('id');

        $straightA = KpiCrossLayerA::where('kpi_period_id', $period->id)
            ->where('invalid_reason', KpiCrossLayerA::INVALID_STRAIGHT_LINING)
            ->count();

        $straightB = KpiCrossLayerB::where('kpi_period_id', $period->id)
            ->where('invalid_reason', KpiCrossLayerA::INVALID_STRAIGHT_LINING)
            ->count();

        $trimmed = KpiCrossLayerAScore::whereIn('kpi_cross_layer_a_id', $layerAIds)
            ->where('correction_reason', 'collusion')
            ->count()
            + KpiCrossLayerBScore::whereIn('kpi_cross_layer_b_id', $layerBIds)
                ->where('correction_reason', 'collusion')
                ->count();

        return [
            'submitted' => KpiCrossLayerA::where('kpi_period_id', $period->id)->submitted()->count()
                + KpiCrossLayerB::where('kpi_period_id', $period->id)->submitted()->count(),
            'straight_lining' => $straightA + $straightB,
            'collusion' => $trimmed,
            'leniency' => KpiLeniencyCorrection::where('kpi_period_id', $period->id)->count(),
            'hidden' => KpiCrossLayerA::where('kpi_period_id', $period->id)->where('comment_hidden', true)->count()
                + KpiCrossLayerB::where('kpi_period_id', $period->id)->where('comment_hidden', true)->count(),
        ];
    }

    /** @return array<string, int> */
    private function emptySummary(): array
    {
        return [
            'submitted' => 0,
            'straight_lining' => 0,
            'collusion' => 0,
            'leniency' => 0,
            'hidden' => 0,
        ];
    }

    /** Penilai yang skornya dikoreksi karena terlalu murah atau terlalu pelit (Bab 7.8d). */
    private function leniencyRows(KpiPeriod $period)
    {
        return KpiLeniencyCorrection::where('kpi_period_id', $period->id)
            ->with(['assessor:id,full_name,department_id', 'assessor.department:id,name'])
            ->get()
            ->sortByDesc(fn ($row) => abs((float) $row->correction_value))
            ->values();
    }

    /**
     * Pasangan divisi yang saling memberi skor rendah. Dihitung ulang saat halaman dibuka,
     * bukan disimpan: hasilnya memang tidak mengubah data apa pun, hanya daftar panggilan
     * untuk HRD (Bab 7.8a).
     *
     * @return array<int, array<string, mixed>>
     */
    private function retaliationRows(KpiPeriod $period): array
    {
        $pairs = app(KpiCrossAbuseDetector::class)->detectRetaliation($period);

        if ($pairs === []) {
            return [];
        }

        $names = Department::whereIn('id', collect($pairs)->flatMap(fn ($p) => [$p['department_id'], $p['partner_id']])->unique())
            ->pluck('name', 'id');

        return collect($pairs)->map(fn ($pair) => $pair + [
            'department_name' => $names[$pair['department_id']] ?? 'Divisi #'.$pair['department_id'],
            'partner_name' => $names[$pair['partner_id']] ?? 'Divisi #'.$pair['partner_id'],
        ])->all();
    }

    /** Komentar Lapis A tanpa identitas penilai — hanya divisi yang dinilai yang disebut. */
    private function layerAComments(KpiPeriod $period)
    {
        return KpiCrossLayerA::anonymous()
            ->where('kpi_period_id', $period->id)
            ->submitted()
            ->with('targetDepartment:id,name')
            ->orderByDesc('submitted_at')
            ->get()
            ->filter(fn ($row) => trim((string) $row->comment_positive) !== '' || trim((string) $row->comment_improvement) !== '')
            ->values();
    }

    private function layerBComments(KpiPeriod $period)
    {
        return KpiCrossLayerB::anonymous()
            ->where('kpi_period_id', $period->id)
            ->submitted()
            ->with('targetEmployee:id,full_name,employee_code')
            ->orderByDesc('submitted_at')
            ->get()
            ->filter(fn ($row) => trim((string) $row->comment) !== '')
            ->values();
    }
}
