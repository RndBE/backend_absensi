<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KpiIndicator;
use App\Models\KpiIndicatorRubric;
use App\Models\KpiLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KpiIndicatorController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $levels = KpiLevel::where('company_id', $admin->company_id)
            ->orderBy('sort_order')
            ->get();

        $selectedLevel = $levels->firstWhere('code', $request->query('level')) ?? $levels->firstWhere('is_assessed', true) ?? $levels->first();

        /*
         * Halaman ini punya dua mode. Tanpa ?employee= ia menampilkan indikator BAWAAN level;
         * dengan ?employee= ia menampilkan indikator milik orang itu.
         *
         * Keduanya sengaja tidak dicampur dalam satu tabel. Bobot tiap set harus berjumlah 100
         * sendiri-sendiri, dan menampilkannya berdampingan membuat admin menjumlahkan dua set
         * berbeda lalu mengira bobotnya lewat dari 100.
         */
        $selectedEmployee = $request->filled('employee')
            ? Employee::where('company_id', $admin->company_id)->find((int) $request->query('employee'))
            : null;

        if ($selectedEmployee && $selectedEmployee->kpi_level_id) {
            $selectedLevel = $levels->firstWhere('id', $selectedEmployee->kpi_level_id) ?? $selectedLevel;
        }

        $indicators = $selectedLevel
            ? KpiIndicator::where('kpi_level_id', $selectedLevel->id)
                ->when($selectedEmployee, fn ($q) => $q->ownedBy($selectedEmployee->id), fn ($q) => $q->levelDefault())
                ->withCount('rubrics')
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get()
                ->groupBy('category')
            : collect();

        // Daftar karyawan yang sudah punya indikator sendiri, supaya admin tahu siapa saja yang
        // menyimpang dari bawaan level tanpa harus membuka satu per satu.
        $withOwnIndicators = Employee::where('company_id', $admin->company_id)
            ->whereHas('kpiIndicators')
            ->withCount('kpiIndicators')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'position', 'kpi_level_id']);

        $candidates = Employee::where('company_id', $admin->company_id)
            ->where('is_active', true)
            ->where('is_kpi_excluded', false)
            ->whereNotNull('kpi_level_id')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'position', 'kpi_level_id']);

        // Jumlah bobot per kategori ditampilkan langsung di header tabel — kesalahan bobot
        // paling mudah terlihat saat admin sedang mengedit, bukan saat periode gagal dibuka.
        $weightTotals = $indicators->map(fn ($rows) => (float) $rows->where('is_active', true)->sum('weight'));

        return view('admin.kpi.indicators.index', compact('levels', 'selectedLevel', 'selectedEmployee', 'indicators', 'weightTotals', 'withOwnIndicators', 'candidates'));
    }

    public function store(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $data = $this->validated($request, $admin->company_id);

        $level = KpiLevel::where('company_id', $admin->company_id)->findOrFail($data['kpi_level_id']);

        $owner = $request->filled('employee_id')
            ? Employee::where('company_id', $admin->company_id)->findOrFail((int) $request->input('employee_id'))
            : null;

        // Indikator pribadi hanya untuk General Excellence — itu satu-satunya kategori yang isinya
        // "tugas inti jabatan" (Bab 1.1), yang memang berbeda tiap orang. Kolomnya sendiri tidak
        // dibatasi di basis data; yang dibatasi di sini, supaya perluasan nanti tidak perlu migrasi.
        if ($owner && $data['category'] !== KpiLevel::CATEGORY_EXCELLENCE) {
            return back()->with('error', 'Indikator per orang hanya untuk General Excellence. Kategori lain tetap seragam per level.');
        }

        $indicator = KpiIndicator::create([
            'company_id' => $admin->company_id,
            'kpi_level_id' => $level->id,
            'employee_id' => $owner?->id,
            'category' => $data['category'],
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'weight' => $data['weight'],
            'is_core' => $request->boolean('is_core'),
            'is_auto_filled' => $request->filled('auto_source'),
            'auto_source' => $request->input('auto_source') ?: null,
            'sort_order' => (int) KpiIndicator::where('kpi_level_id', $level->id)
                ->when($owner, fn ($q) => $q->ownedBy($owner->id), fn ($q) => $q->levelDefault())
                ->where('category', $data['category'])->max('sort_order') + 1,
            'is_active' => true,
        ]);

        $this->seedGenericRubric($indicator);

        return back()->with('success', $owner
            ? "Indikator {$indicator->code} ditambahkan untuk {$owner->full_name}."
            : "Indikator {$indicator->code} berhasil ditambahkan.");
    }

    public function update(Request $request, KpiIndicator $kpiIndicator)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiIndicator->company_id !== $admin->company_id, 403);

        $data = $this->validated($request, $admin->company_id, $kpiIndicator->id);

        $kpiIndicator->update([
            'kpi_level_id' => $data['kpi_level_id'],
            'category' => $data['category'],
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'weight' => $data['weight'],
            'is_core' => $request->boolean('is_core'),
            'is_auto_filled' => $request->filled('auto_source'),
            'auto_source' => $request->input('auto_source') ?: null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', "Indikator {$kpiIndicator->code} berhasil diperbarui.");
    }

    public function destroy(KpiIndicator $kpiIndicator)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiIndicator->company_id !== $admin->company_id, 403);

        // Snapshot periode menyimpan salinannya sendiri, jadi menghapus master tidak merusak
        // hasil periode lama — relasi snapshot memang sengaja nullOnDelete.
        $code = $kpiIndicator->code;
        $kpiIndicator->delete();

        return back()->with('success', "Indikator {$code} berhasil dihapus.");
    }

    public function rubrics(KpiIndicator $kpiIndicator)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiIndicator->company_id !== $admin->company_id, 403);

        $kpiIndicator->load('level');
        $rubrics = $kpiIndicator->rubrics->keyBy('score');

        return view('admin.kpi.indicators.rubrics', compact('kpiIndicator', 'rubrics'));
    }

    public function updateRubrics(Request $request, KpiIndicator $kpiIndicator)
    {
        $admin = Employee::find(session('admin_id'));
        abort_if($kpiIndicator->company_id !== $admin->company_id, 403);

        $data = $request->validate([
            'rubrics' => 'required|array',
            'rubrics.*' => 'required|string|max:500',
        ]);

        foreach ($data['rubrics'] as $score => $description) {
            $score = (int) $score;

            if ($score < 1 || $score > 5) {
                continue;
            }

            KpiIndicatorRubric::updateOrCreate(
                ['kpi_indicator_id' => $kpiIndicator->id, 'score' => $score],
                ['description' => $description]
            );
        }

        return back()->with('success', 'Rubrik berhasil disimpan.');
    }

    private function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'kpi_level_id' => [
                'required',
                Rule::exists('kpi_levels', 'id')->where('company_id', $companyId),
            ],
            'category' => ['required', Rule::in(array_keys(KpiLevel::CATEGORIES))],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('kpi_indicators', 'code')->where('company_id', $companyId)->ignore($ignoreId),
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'weight' => 'required|numeric|min:0|max:100',
            'auto_source' => ['nullable', Rule::in([KpiIndicator::SOURCE_CROSS_ASSESSMENT, KpiIndicator::SOURCE_ATTENDANCE])],
        ]);
    }

    /** Indikator baru selalu punya rubrik lengkap 1–5 supaya form penilaian tidak kosong. */
    private function seedGenericRubric(KpiIndicator $indicator): void
    {
        foreach (KpiIndicatorRubric::PREDICATES as $score => $predicate) {
            KpiIndicatorRubric::firstOrCreate(
                ['kpi_indicator_id' => $indicator->id, 'score' => $score],
                ['description' => $predicate.' — rubrik belum disusun, mohon dilengkapi.']
            );
        }
    }
}
