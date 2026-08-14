<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\KpiWorkChainEditLog;
use App\Models\KpiWorkChainReviewer;
use App\Models\KpiWorkRelation;
use App\Support\KpiWorkChainOverseers;
use App\Support\KpiWorkChainReviewLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Halaman tinjauan peta rantai kerja untuk para Manajer — tanpa login, dibuka lewat tautan bertoken.
 *
 * ══ Kenapa kewenangannya lebih kecil dari halaman admin ══
 *
 * Tautan tanpa login bisa diteruskan ke grup obrolan tanpa disadari, jadi kewenangannya sengaja
 * dipangkas ke yang benar-benar dibutuhkan untuk memeriksa peta:
 *
 *   boleh  : menambah pasangan ke rantai yang ada, menghapus satu pasangan
 *   tidak  : membuat rantai baru, menghapus seluruh rantai
 *
 * Membuat dan menghapus rantai adalah tindakan struktural — satu klik bisa melenyapkan 15 baris —
 * dan itu tetap di halaman admin yang berlogin. Manajer yang merasa sebuah rantai harus dihapus
 * bisa menghapus pasangannya satu per satu; jejaknya lengkap di catatan perubahan.
 *
 * Setiap perubahan dicatat beserta pelaku, sumber, IP, dan peramban. Halaman ini juga menampilkan
 * catatan itu apa adanya kepada manajernya — kalau ada yang salah ubah, dia yang paling cepat tahu.
 */
class KpiWorkChainReviewController extends Controller
{
    /** Sama seperti halaman admin: menahan salah pilih yang melahirkan puluhan baris. */
    private const MAX_PAIRS_PER_SUBMIT = 60;

    public function show(
        Request $request,
        string $token,
        KpiWorkChainReviewLinks $links,
        KpiWorkChainOverseers $overseers,
    ) {
        $reviewer = $links->resolve($token);

        if (! $reviewer) {
            return response()->view('review.work-chains-invalid', [
                'reason' => 'Tautan tidak dikenali. Mungkin salah salin, atau sudah diganti yang baru.',
            ], 404);
        }

        if ($reason = $reviewer->blockingReason()) {
            return response()->view('review.work-chains-invalid', ['reason' => $reason], 410);
        }

        $links->markUsed($reviewer, $request);

        return view('review.work-chains', $this->pageData($reviewer, $token, $overseers));
    }

    public function addPairs(Request $request, string $token, KpiWorkChainReviewLinks $links)
    {
        $reviewer = $this->usableOrFail($token, $links);

        $data = $this->validatePayload($request, $reviewer->company_id);

        if (is_string($data)) {
            return back()->with('error', $data)->withInput();
        }

        $exists = KpiWorkRelation::where('company_id', $reviewer->company_id)
            ->where('label', $data['label'])
            ->exists();

        if (! $exists) {
            return back()->with('error', 'Rantai tidak ditemukan. Halaman ini tidak bisa membuat rantai baru.');
        }

        $created = $this->writePairs($reviewer->company_id, $data['label'], $data['from'], $data['to']);

        if ($created > 0) {
            $links->log(
                $reviewer->company_id,
                KpiWorkChainEditLog::SOURCE_REVIEW,
                KpiWorkChainEditLog::ACTION_ADD,
                $data['label'],
                ['count' => $created, 'from' => count($data['from']), 'to' => count($data['to'])],
                $request,
                $reviewer->employee_id,
                $reviewer->id,
            );
        }

        return redirect()
            ->route('kpi-review.show', ['token' => $token, 'chain' => Str::slug($data['label'])])
            ->with(
                $created > 0 ? 'success' : 'error',
                $created > 0
                    ? "{$created} pasangan ditambahkan ke \"{$data['label']}\"."
                    : 'Semua pasangan yang dipilih sudah ada di rantai ini.'
            );
    }

    public function destroyPair(Request $request, string $token, KpiWorkRelation $kpiWorkRelation, KpiWorkChainReviewLinks $links)
    {
        $reviewer = $this->usableOrFail($token, $links);
        abort_if($kpiWorkRelation->company_id !== $reviewer->company_id, 403);

        $label = $kpiWorkRelation->label;
        $detail = [
            'from' => $kpiWorkRelation->from?->full_name,
            'to' => $kpiWorkRelation->to?->full_name,
            'was_from_seeder' => $kpiWorkRelation->isFromSeeder(),
        ];

        $kpiWorkRelation->delete();

        $links->log(
            $reviewer->company_id,
            KpiWorkChainEditLog::SOURCE_REVIEW,
            KpiWorkChainEditLog::ACTION_DELETE_PAIR,
            $label,
            $detail,
            $request,
            $reviewer->employee_id,
            $reviewer->id,
        );

        return redirect()
            ->route('kpi-review.show', ['token' => $token, 'chain' => Str::slug($label)])
            ->with('success', "Pasangan {$detail['from']} → {$detail['to']} dihapus.");
    }

    /** @return array<string, mixed> */
    private function pageData(KpiWorkChainReviewer $reviewer, string $token, KpiWorkChainOverseers $overseers): array
    {
        $companyId = $reviewer->company_id;

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

        $chains = $relations
            ->groupBy('label')
            ->map(fn ($rows, $label) => [
                'label' => $label,
                'slug' => Str::slug($label) ?: md5($label),
                'pairs' => $rows->values(),
                'overseers' => $overseers->for($rows),
                'mine' => $rows->contains(fn ($r) => $this->touchesDivision($r, $reviewer)),
            ])
            ->sortByDesc(fn ($chain) => [$chain['mine'], count($chain['pairs'])])
            ->values();

        return [
            'token' => $token,
            'reviewer' => $reviewer,
            'chains' => $chains,
            'candidates' => Employee::where('company_id', $companyId)
                ->where('is_active', true)
                ->where('is_kpi_excluded', false)
                ->with(['department:id,name', 'kpiLevel:id,code'])
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'position', 'department_id', 'kpi_level_id'])
                ->groupBy(fn ($e) => $e->department->name ?? 'Tanpa departemen'),
            'totals' => [
                'chains' => $chains->count(),
                'pairs' => $relations->count(),
                'people' => $relations->flatMap(fn ($r) => [$r->from_employee_id, $r->to_employee_id])->unique()->count(),
            ],
            // Catatan diperlihatkan ke manajernya sendiri: kalau ada yang salah ubah, dia yang
            // paling cepat menyadarinya.
            'logs' => KpiWorkChainEditLog::where('company_id', $companyId)
                ->with('actor:id,full_name')
                ->latest()
                ->limit(40)
                ->get(),
            'focus' => request()->query('chain'),
        ];
    }

    /** Rantai yang menyentuh divisi si peninjau ditaruh di atas — itu yang paling dia kenal. */
    private function touchesDivision(KpiWorkRelation $relation, KpiWorkChainReviewer $reviewer): bool
    {
        $division = $reviewer->employee?->department_id;

        return $division !== null
            && ($relation->from?->department_id === $division || $relation->to?->department_id === $division);
    }

    private function usableOrFail(string $token, KpiWorkChainReviewLinks $links): KpiWorkChainReviewer
    {
        $reviewer = $links->resolve($token);

        abort_if(! $reviewer || ! $reviewer->isUsable(), 403, 'Tautan tinjauan tidak berlaku.');

        return $reviewer;
    }

    /**
     * @return array{label:string, from:array<int,int>, to:array<int,int>}|string
     */
    private function validatePayload(Request $request, int $companyId): array|string
    {
        $employeeExists = Rule::exists('employees', 'id')->where('company_id', $companyId);

        $data = $request->validate([
            'label' => 'required|string|max:80',
            'from' => 'required|array|min:1',
            'from.*' => ['integer', $employeeExists],
            'to' => 'required|array|min:1',
            'to.*' => ['integer', $employeeExists],
        ]);

        $from = collect($data['from'])->map(fn ($id) => (int) $id)->unique()->values();
        $to = collect($data['to'])->map(fn ($id) => (int) $id)->unique()->values();

        $pairs = $from->crossJoin($to)->reject(fn ($pair) => $pair[0] === $pair[1])->count();

        if ($pairs === 0) {
            return 'Kedua sisi berisi orang yang sama, jadi tidak ada pasangan yang bisa dibuat.';
        }

        if ($pairs > self::MAX_PAIRS_PER_SUBMIT) {
            return sprintf(
                'Pilihan itu menghasilkan %d pasangan (%d × %d), melewati batas %d sekali simpan.',
                $pairs,
                $from->count(),
                $to->count(),
                self::MAX_PAIRS_PER_SUBMIT
            );
        }

        return ['label' => trim($data['label']), 'from' => $from->all(), 'to' => $to->all()];
    }

    /**
     * @param  array<int,int>  $from
     * @param  array<int,int>  $to
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
