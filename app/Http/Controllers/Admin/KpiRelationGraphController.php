<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\KpiAssessment;
use App\Models\KpiCrossAssessor;
use App\Models\KpiCrossResult;
use App\Models\KpiDivisionRelation;
use App\Models\KpiPeriod;
use App\Models\KpiWorkRelation;
use App\Support\DepartmentTree;
use App\Support\KpiAssessorMap;
use App\Support\KpiCrossTargets;
use Illuminate\Http\Request;

/**
 * Graf relasi kerja KPI: satu simpul satu karyawan, warna mengikuti divisi.
 *
 * Simpulnya sengaja karyawan, bukan divisi. Graf lima simpul divisi tidak memberi tahu apa pun
 * yang tidak sudah terbaca di tabel Matriks Relasi Kerja — yang tidak terlihat di tabel justru
 * sebaran orangnya: siapa yang menanggung banyak garis penilaian sekaligus, divisi mana yang
 * seluruh hubungan luarnya bertumpu pada satu orang, dan siapa yang sama sekali tidak
 * tersambung ke divisi lain. Semuanya baru muncul kalau orangnya sendiri yang jadi simpul.
 *
 * Halaman ini hanya membaca. Penyuntingan mitra tetap di Matriks Relasi Kerja supaya aturan
 * Bab 7.3 hidup di satu tempat saja.
 */
class KpiRelationGraphController extends Controller
{
    /** Warna per divisi. Jenuh dan berjarak jauh di roda warna supaya terbaca di kanvas gelap. */
    private const PALETTE = ['#f59e0b', '#f87171', '#60a5fa', '#f472b6', '#34d399', '#c084fc', '#facc15'];

    public const EDGE_COMMAND = 'komando';

    public const EDGE_CROSS = 'silang';

    public const EDGE_WORK = 'kerja';

    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $companyId = $admin->company_id;

        $periods = KpiPeriod::where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get();

        $period = $periods->firstWhere('id', (int) $request->query('period')) ?? $periods->first();

        $divisions = Department::where('company_id', $companyId)
            ->where('is_division', true)
            ->orderBy('name')
            ->get();

        $employees = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->with(['kpiLevel:id,code,is_assessed', 'manager.manager.kpiLevel', 'manager.kpiLevel'])
            ->orderBy('full_name')
            ->get();

        $divisionOf = $this->divisionIndex($divisions);
        $assessors = $this->assessorLookup($period);

        $nodes = $this->nodes($employees, $divisionOf, $assessors);
        $edges = $this->edges($employees, $divisionOf, $assessors, $companyId);

        $this->applyDegree($nodes, $edges);
        $this->applyWorkLabels($nodes, $edges);

        // Simpul tanpa satu pun relasi dibuang: komisaris dan akun sistem tidak menilai,
        // tidak dinilai, dan tidak masuk penilaian silang, jadi di graf relasi mereka hanya
        // titik menggantung tanpa arti. Karyawan yang seharusnya punya penilai tetapi belum
        // dapat TIDAK hilang diam-diam karena hal itu — daftar "belum bisa dinilai" di halaman
        // periode tempatnya, dan di sanalah admin bisa menindaklanjutinya.
        $nodes = array_filter($nodes, fn ($node) => $node['degree'] > 0);

        return view('admin.kpi.relation-graph.index', [
            'periods' => $periods,
            'period' => $period,
            'graph' => [
                'divisions' => $this->divisionLegend($divisions, $nodes, $period),
                'nodes' => array_values($nodes),
                'edges' => $edges,
            ],
        ]);
    }

    /**
     * Peta department_id mana pun (termasuk sub-departemen) ke id divisi induknya. Karyawan
     * jarang duduk persis di simpul divisi — lihat App\Support\DepartmentTree.
     *
     * @return array<int, int>
     */
    private function divisionIndex($divisions): array
    {
        $map = [];

        foreach ($divisions as $division) {
            foreach (DepartmentTree::withDescendants($division->id) as $descendantId) {
                $map[$descendantId] = $division->id;
            }
        }

        return $map;
    }

    /** @return array<int, KpiCrossAssessor> penilai silang per employee_id */
    private function assessorLookup(?KpiPeriod $period): array
    {
        if (! $period) {
            return [];
        }

        return KpiCrossAssessor::where('kpi_period_id', $period->id)
            ->get()
            ->keyBy('employee_id')
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function nodes($employees, array $divisionOf, array $assessors): array
    {
        $nodes = [];

        foreach ($employees as $employee) {
            $divisionId = $divisionOf[$employee->department_id] ?? null;
            $assessor = $assessors[$employee->id] ?? null;

            $nodes['e'.$employee->id] = [
                'id' => 'e'.$employee->id,
                'name' => $employee->full_name,
                'position' => $employee->position,
                'level' => $employee->kpiLevel?->code,
                'assessed' => (bool) $employee->kpiLevel?->is_assessed,
                'division' => $divisionId,
                'cross_functional' => (bool) $employee->is_cross_functional,
                'assessor' => $assessor !== null,
                'layer_b' => (bool) $assessor?->can_assess_individual,
                'degree' => 0,
            ];
        }

        return $nodes;
    }

    /**
     * Dua jenis sisi, keduanya dari data yang sudah dipakai perhitungan — bukan turunan baru:
     *
     *   komando  penilai → yang dinilai, dari App\Support\KpiAssessorMap. Sebagian besar berada
     *            di dalam satu divisi, dan itulah yang membuat gugusan per divisi terbentuk
     *            sendiri tanpa perlu ditata manual.
     *   silang   penilai Lapis B → orang yang dinilainya di divisi mitra.
     *
     * Sisi silang TIDAK boleh digambar antar penilai. Lapis A menilai divisi sebagai unit, bukan
     * orang, jadi dua penilai Lapis A tidak pernah saling menilai — menariknya jadi garis
     * orang-ke-orang memunculkan relasi yang tidak ada dan membuat staf yang sebenarnya hanya
     * berurusan dengan satu divisi tampak tersambung ke seluruh perusahaan. Satu-satunya relasi
     * orang-ke-orang di penilaian silang adalah Lapis B.
     *
     *   kerja    rantai kerja nyata dari App\Models\KpiWorkRelation. Tidak berpengaruh pada
     *            penilaian sama sekali; justru lapisan inilah yang memperlihatkan apakah peta
     *            penilaian sudah sejalan dengan pekerjaan yang benar-benar berjalan. Orang yang
     *            punya rantai kerja padat tetapi tidak punya sisi silang berarti dinilai oleh
     *            pihak yang tidak pernah bekerja dengannya.
     *
     * @return array<int, array<string, mixed>>
     */
    private function edges($employees, array $divisionOf, array $assessors, int $companyId): array
    {
        $map = app(KpiAssessorMap::class);
        $edges = [];
        $ids = $employees->keyBy('id');

        foreach ($employees as $employee) {
            foreach ($map->for($employee) as $row) {
                if (! $ids->has($row['assessor_id'])) {
                    continue;
                }

                $edges[] = [
                    'source' => 'e'.$row['assessor_id'],
                    'target' => 'e'.$employee->id,
                    'kind' => self::EDGE_COMMAND,
                    'primary' => $row['assessor_role'] === KpiAssessment::ROLE_PRIMARY,
                ];
            }
        }

        $partners = KpiDivisionRelation::where('company_id', $companyId)
            ->where('is_active', true)
            ->get()
            ->groupBy('department_id')
            ->map(fn ($rows) => $rows->pluck('partner_department_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $targets = app(KpiCrossTargets::class);
        $cache = [];

        foreach ($assessors as $employeeId => $assessor) {
            if (! $ids->has($employeeId) || ! $assessor->can_assess_individual) {
                continue;
            }

            $divisionId = (int) $assessor->department_id;

            // Sasaran hanya bergantung pada divisi penilainya, jadi dihitung sekali per divisi —
            // tanpa cache, satu kueri berjalan untuk setiap penilai di divisi yang sama.
            $cache[$divisionId] ??= $targets
                ->individuals($companyId, $partners[$divisionId] ?? [])
                ->pluck('id')
                ->all();

            foreach ($cache[$divisionId] as $targetId) {
                if (! $ids->has($targetId) || $targetId === $employeeId) {
                    continue;
                }

                $edges[] = [
                    'source' => 'e'.$employeeId,
                    'target' => 'e'.$targetId,
                    'kind' => self::EDGE_CROSS,
                    'primary' => false,
                ];
            }
        }

        foreach (KpiWorkRelation::where('company_id', $companyId)->get() as $relation) {
            if (! $ids->has($relation->from_employee_id) || ! $ids->has($relation->to_employee_id)) {
                continue;
            }

            $edges[] = [
                'source' => 'e'.$relation->from_employee_id,
                'target' => 'e'.$relation->to_employee_id,
                'kind' => self::EDGE_WORK,
                'primary' => false,
                'label' => $relation->label,
            ];
        }

        return $edges;
    }

    /**
     * Derajat dipakai untuk ukuran simpul — padanan "god node" Graphify. Yang terbesar adalah
     * orang dengan garis relasi terbanyak, dan di sistem ini itu biasanya penilai yang menanggung
     * beban paling berat: tepat orang yang perlu dilihat lebih dulu.
     *
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function applyDegree(array &$nodes, array $edges): void
    {
        foreach ($edges as $edge) {
            foreach ([$edge['source'], $edge['target']] as $id) {
                if (isset($nodes[$id])) {
                    $nodes[$id]['degree']++;
                }
            }
        }
    }

    /**
     * Rantai kerja yang dipegang tiap orang, untuk panel rincian. Menjawab pertanyaan yang
     * paling sering muncul saat meninjau peta: orang ini sebenarnya mengurus apa.
     *
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<int, array<string, mixed>>  $edges
     */
    private function applyWorkLabels(array &$nodes, array $edges): void
    {
        foreach ($nodes as $id => $node) {
            $nodes[$id]['work'] = [];
        }

        foreach ($edges as $edge) {
            if ($edge['kind'] !== self::EDGE_WORK) {
                continue;
            }

            foreach ([$edge['source'], $edge['target']] as $id) {
                if (isset($nodes[$id]) && ! in_array($edge['label'], $nodes[$id]['work'], true)) {
                    $nodes[$id]['work'][] = $edge['label'];
                }
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function divisionLegend($divisions, array $nodes, ?KpiPeriod $period): array
    {
        $legend = [];
        $index = 0;

        foreach ($divisions as $division) {
            $members = array_filter($nodes, fn ($node) => $node['division'] === $division->id);

            $legend[] = [
                'id' => $division->id,
                'code' => $division->kpi_code ?: mb_substr($division->name, 0, 4),
                'name' => $division->name,
                'color' => self::PALETTE[$index % count(self::PALETTE)],
                'members' => count($members),
                'assessors' => count(array_filter($members, fn ($node) => $node['assessor'])),
                'cross_functional' => count(array_filter($members, fn ($node) => $node['cross_functional'])),
                'shared_service' => (bool) $division->is_shared_service,
                'score' => $this->divisionScore($period, $division->id),
            ];

            $index++;
        }

        // Simpul tanpa divisi (Direksi, akun sistem) tetap perlu warna dan bisa dimatikan
        // seperti divisi lain — kalau tidak, mereka muncul kelabu tanpa keterangan apa pun.
        if (array_filter($nodes, fn ($node) => $node['division'] === null) !== []) {
            $legend[] = [
                'id' => null,
                'code' => 'LAIN',
                'name' => 'Di luar divisi',
                'color' => '#94a3b8',
                'members' => count(array_filter($nodes, fn ($node) => $node['division'] === null)),
                'assessors' => 0,
                'cross_functional' => 0,
                'shared_service' => false,
                'score' => null,
            ];
        }

        return $legend;
    }

    private function divisionScore(?KpiPeriod $period, int $departmentId): ?float
    {
        if (! $period) {
            return null;
        }

        $result = KpiCrossResult::where('kpi_period_id', $period->id)
            ->where('department_id', $departmentId)
            ->divisionLevel()
            ->first(['score_a_corrected', 'score_a_raw']);

        $score = $result?->score_a_corrected ?? $result?->score_a_raw;

        return $score === null ? null : round((float) $score, 2);
    }
}
