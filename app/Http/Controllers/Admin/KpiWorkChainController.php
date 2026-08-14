<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiWorkRelation;
use App\Models\KpiWorkChainEditLog;
use App\Support\KpiWorkChainOverseers;
use App\Support\KpiWorkChainReviewLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Peta rantai kerja antar orang: siapa menyerahkan atau mengoordinasikan apa kepada siapa.
 *
 * Halaman ini TIDAK menyentuh penilaian KPI. Yang menentukan siapa menilai siapa adalah Matriks
 * Relasi Kerja (divisi, Bab 7.3) dan daftar penilai silang (Bab 7.4) — keduanya halaman terpisah.
 * Peta di sini murni catatan alur kerja untuk ditinjau manusia, jadi mengubahnya di tengah periode
 * aman: tidak ada angka yang bergeser.
 *
 * ══ Kenapa pasangan dibuat dari perkalian dua sisi ══
 *
 * Satu rantai bukan satu baris, melainkan seluruh baris yang berbagi `label`. Sisi "dari" dan "ke"
 * masing-masing boleh berisi beberapa orang, dan pasangannya adalah perkalian keduanya. Bentuk itu
 * mengikuti cara manajemen bercerita ("FAT berkoordinasi dengan Marketing soal faktur"), tapi
 * membawa satu jebakan: menambah satu nama ke sisi "dari" ikut membuat pasangan ke SELURUH sisi
 * "ke". Karena itu penambahan selalu meminta kedua sisi sekaligus dan jumlah pasangan yang akan
 * lahir ditampilkan sebelum disimpan — bukan menambah orang ke satu sisi saja.
 */
class KpiWorkChainController extends Controller
{
    /** Batas jumlah pasangan sekali simpan. Menahan salah pilih yang melahirkan ratusan baris. */
    private const MAX_PAIRS_PER_SUBMIT = 60;

    public function index(Request $request, KpiWorkChainOverseers $overseers)
    {
        $admin = Employee::find(session('admin_id'));
        $companyId = $admin->company_id;

        $relations = KpiWorkRelation::where('company_id', $companyId)
            ->with([
                'from:id,full_name,position,department_id,kpi_level_id,manager_id',
                'from.department:id,name',
                'from.kpiLevel:id,code',
                'from.manager.manager.kpiLevel',
                'from.manager.kpiLevel',
                'to:id,full_name,position,department_id,kpi_level_id,manager_id',
                'to.department:id,name',
                'to.kpiLevel:id,code',
                'to.manager.manager.kpiLevel',
                'to.manager.kpiLevel',
            ])
            ->get()
            ->sortBy(fn ($r) => ($r->from->full_name ?? '').'|'.($r->to->full_name ?? ''));

        // Rantai diurutkan dari yang terpadat: yang paling banyak pasangannya paling butuh ditinjau.
        $chains = $relations
            ->groupBy('label')
            ->map(fn ($rows, $label) => [
                'label' => $label,
                'slug' => Str::slug($label) ?: md5($label),
                'pairs' => $rows->values(),
                'overseers' => $overseers->for($rows),
                'from_seeder' => $rows->contains(fn ($r) => $r->isFromSeeder()),
            ])
            ->sortByDesc(fn ($chain) => count($chain['pairs']))
            ->values();

        $candidates = Employee::where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->with(['department:id,name', 'kpiLevel:id,code'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'position', 'department_id', 'kpi_level_id'])
            ->groupBy(fn ($e) => $e->department->name ?? 'Tanpa departemen');

        $totals = [
            'chains' => $chains->count(),
            'pairs' => $relations->count(),
            'people' => $relations
                ->flatMap(fn ($r) => [$r->from_employee_id, $r->to_employee_id])
                ->unique()
                ->count(),
            'manual' => $relations->reject(fn ($r) => $r->isFromSeeder())->count(),
        ];

        $focus = $request->query('chain');

        return view('admin.kpi.work-chains.index', compact('chains', 'candidates', 'totals', 'focus'));
    }

    /** Rantai baru: label belum dipakai, lalu pasangan dari perkalian kedua sisi. */
    public function store(Request $request, KpiWorkChainReviewLinks $links)
    {
        $admin = Employee::find(session('admin_id'));

        $data = $this->validatePayload($request, $admin->company_id, requireNewLabel: true);

        if (is_string($data)) {
            return back()->with('error', $data)->withInput();
        }

        $created = $this->writePairs($admin->company_id, $data['label'], $data['from'], $data['to']);

        if ($created === 0) {
            return back()->with('error', 'Tidak ada pasangan yang bisa dibuat — periksa pilihan kedua sisi.')->withInput();
        }

        $this->record($links, $request, $admin, KpiWorkChainEditLog::ACTION_CREATE_CHAIN, $data['label'], ['count' => $created]);

        return redirect()
            ->route('admin.kpi-work-chains.index', ['chain' => Str::slug($data['label'])])
            ->with('success', "Rantai \"{$data['label']}\" dibuat dengan {$created} pasangan.");
    }

    /** Tambah pasangan ke rantai yang sudah ada. Label diambil dari rantai, bukan dari input. */
    public function addPairs(Request $request, KpiWorkChainReviewLinks $links)
    {
        $admin = Employee::find(session('admin_id'));

        $data = $this->validatePayload($request, $admin->company_id, requireNewLabel: false);

        if (is_string($data)) {
            return back()->with('error', $data)->withInput();
        }

        $exists = KpiWorkRelation::where('company_id', $admin->company_id)
            ->where('label', $data['label'])
            ->exists();

        if (! $exists) {
            return back()->with('error', 'Rantai tidak ditemukan.');
        }

        $created = $this->writePairs($admin->company_id, $data['label'], $data['from'], $data['to']);

        if ($created > 0) {
            $this->record($links, $request, $admin, KpiWorkChainEditLog::ACTION_ADD, $data['label'], ['count' => $created]);
        }

        $message = $created > 0
            ? "{$created} pasangan ditambahkan ke \"{$data['label']}\"."
            : 'Semua pasangan yang dipilih sudah ada di rantai ini.';

        return redirect()
            ->route('admin.kpi-work-chains.index', ['chain' => Str::slug($data['label'])])
            ->with($created > 0 ? 'success' : 'error', $message);
    }

    /**
     * Pasangan dihapus, tidak ditandai nonaktif. Riwayat peta versi seeder ada di `$workChains`
     * lewat git; tabel ini tidak dibaca perhitungan KPI mana pun, jadi tidak ada angka yang
     * bergantung pada baris yang hilang. Lihat migration 2026_08_12_000018.
     *
     * Untuk baris `source = seeder`, penghapusan hanya bertahan sampai `db:seed` berikutnya kalau
     * pasangannya masih tercantum di `$workChains` — di situ berkas seeder yang berkuasa. Pesan
     * baliknya menyebut itu supaya admin tidak menebak-nebak kenapa pasangannya muncul lagi.
     */
    public function destroy(Request $request, KpiWorkRelation $kpiWorkRelation, KpiWorkChainReviewLinks $links)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiWorkRelation->company_id !== $admin->company_id, 403);

        $fromSeeder = $kpiWorkRelation->isFromSeeder();
        $detail = [
            'from' => $kpiWorkRelation->from?->full_name,
            'to' => $kpiWorkRelation->to?->full_name,
            'was_from_seeder' => $fromSeeder,
        ];
        $label = $kpiWorkRelation->label;

        $kpiWorkRelation->delete();

        $this->record($links, $request, $admin, KpiWorkChainEditLog::ACTION_DELETE_PAIR, $label, $detail);

        $message = 'Pasangan dihapus.';

        if ($fromSeeder) {
            $message .= ' Pasangan ini berasal dari seeder — akan kembali saat db:seed berikutnya kecuali dicabut juga dari $workChains di KpiOrgWiringSeeder.';
        }

        return back()->with('success', $message);
    }

    /** Hapus seluruh pasangan satu rantai sekaligus. */
    public function destroyChain(Request $request, KpiWorkChainReviewLinks $links)
    {
        $admin = Employee::find(session('admin_id'));

        $label = (string) $request->input('label');

        $rows = KpiWorkRelation::where('company_id', $admin->company_id)
            ->where('label', $label)
            ->get();

        if ($rows->isEmpty()) {
            return back()->with('error', 'Rantai itu tidak ditemukan.');
        }

        $fromSeeder = $rows->contains(fn ($row) => $row->isFromSeeder());

        KpiWorkRelation::whereIn('id', $rows->pluck('id'))->delete();

        $this->record($links, $request, $admin, KpiWorkChainEditLog::ACTION_DELETE_CHAIN, $label, ['count' => $rows->count()]);

        $message = "Rantai \"{$label}\" dihapus — {$rows->count()} pasangan.";

        if ($fromSeeder) {
            $message .= ' Sebagian berasal dari seeder dan akan kembali saat db:seed berikutnya kecuali dicabut juga dari $workChains.';
        }

        return back()->with('success', $message);
    }

    /**
     * @return array{label:string, from:array<int,int>, to:array<int,int>}|string  Pesan galat kalau tidak lolos.
     */
    private function validatePayload(Request $request, int $companyId, bool $requireNewLabel): array|string
    {
        $employeeExists = Rule::exists('employees', 'id')->where('company_id', $companyId);

        $data = $request->validate([
            'label' => 'required|string|max:80',
            'from' => 'required|array|min:1',
            'from.*' => ['integer', $employeeExists],
            'to' => 'required|array|min:1',
            'to.*' => ['integer', $employeeExists],
        ], [], [
            'label' => 'nama rantai',
            'from' => 'sisi "dari"',
            'to' => 'sisi "ke"',
        ]);

        $label = trim($data['label']);

        if ($label === '') {
            return 'Nama rantai tidak boleh kosong.';
        }

        if ($requireNewLabel) {
            $taken = KpiWorkRelation::where('company_id', $companyId)
                ->where('label', $label)
                ->exists();

            if ($taken) {
                return "Rantai \"{$label}\" sudah ada. Tambahkan pasangannya dari kartu rantai itu.";
            }
        }

        $from = collect($data['from'])->map(fn ($id) => (int) $id)->unique()->values();
        $to = collect($data['to'])->map(fn ($id) => (int) $id)->unique()->values();

        // Perkalian dikurangi pasangan orang dengan dirinya sendiri, yang memang dilewati saat tulis.
        $pairs = $from->crossJoin($to)->reject(fn ($pair) => $pair[0] === $pair[1])->count();

        if ($pairs === 0) {
            return 'Kedua sisi berisi orang yang sama, jadi tidak ada pasangan yang bisa dibuat.';
        }

        if ($pairs > self::MAX_PAIRS_PER_SUBMIT) {
            return sprintf(
                'Pilihan itu menghasilkan %d pasangan (%d × %d), melewati batas %d sekali simpan. Pecah jadi beberapa kali penambahan supaya tetap bisa ditinjau.',
                $pairs,
                $from->count(),
                $to->count(),
                self::MAX_PAIRS_PER_SUBMIT
            );
        }

        return [
            'label' => $label,
            'from' => $from->all(),
            'to' => $to->all(),
        ];
    }

    /**
     * Catat perubahan ke riwayat yang sama dengan tautan tinjauan Manajer. Satu tabel untuk kedua
     * jalur: riwayat yang terpecah dua tempat tidak bisa dibaca sebagai satu urutan kejadian.
     *
     * @param  array<string, mixed>  $detail
     */
    private function record(
        KpiWorkChainReviewLinks $links,
        Request $request,
        Employee $admin,
        string $action,
        string $label,
        array $detail,
    ): void {
        $links->log(
            $admin->company_id,
            KpiWorkChainEditLog::SOURCE_ADMIN,
            $action,
            $label,
            $detail,
            $request,
            $admin->id,
        );
    }

    /**
     * Menulis perkalian `from` × `to`. Pasangan yang sudah ada dilewati — indeks unik
     * (from, to, label) sudah menjamin satu rantai tidak mencatat pasangan yang sama dua kali.
     *
     * @param  array<int,int>  $from
     * @param  array<int,int>  $to
     * @return int  Jumlah pasangan yang benar-benar baru.
     */
    private function writePairs(int $companyId, string $label, array $from, array $to): int
    {
        $created = 0;

        DB::transaction(function () use ($companyId, $label, $from, $to, &$created) {
            foreach ($from as $fromId) {
                foreach ($to as $toId) {
                    if ($fromId === $toId) {
                        continue;
                    }

                    $exists = KpiWorkRelation::where('from_employee_id', $fromId)
                        ->where('to_employee_id', $toId)
                        ->where('label', $label)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    KpiWorkRelation::create([
                        'company_id' => $companyId,
                        'from_employee_id' => $fromId,
                        'to_employee_id' => $toId,
                        'label' => $label,
                        'source' => KpiWorkRelation::SOURCE_MANUAL,
                    ]);

                    $created++;
                }
            }
        });

        return $created;
    }
}
