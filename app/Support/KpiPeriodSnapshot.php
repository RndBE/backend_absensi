<?php

namespace App\Support;

use App\Models\KpiCrossItem;
use App\Models\KpiIndicator;
use App\Models\KpiLevel;
use App\Models\KpiPeriod;
use App\Models\KpiPeriodCrossItemSnapshot;
use App\Models\KpiPeriodIndicatorSnapshot;
use App\Models\KpiPeriodLevelSnapshot;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membekukan master KPI ke dalam sebuah periode.
 *
 * Setelah periode dibuka, seluruh perhitungan membaca snapshot — bukan master. Tanpa ini,
 * admin yang mengubah bobot di tengah jalan akan menggeser skor yang sudah diisi penilai,
 * dan hasil periode lama tidak lagi bisa direproduksi (Bab 11.4).
 */
class KpiPeriodSnapshot
{
    /** Toleransi pembulatan saat memeriksa jumlah bobot. */
    private const EPSILON = 0.01;

    /**
     * Bangun snapshot untuk satu periode. Idempoten: snapshot lama dibuang lebih dulu,
     * jadi memanggil ulang pada periode yang masih draft aman.
     *
     * @throws RuntimeException bila ada bobot yang tidak berjumlah 100
     */
    public function build(KpiPeriod $period): void
    {
        if ($period->isFinal()) {
            throw new RuntimeException('Periode sudah final, snapshot tidak boleh dibangun ulang.');
        }

        $levels = KpiLevel::query()
            ->where('company_id', $period->company_id)
            ->where('is_active', true)
            ->where('is_assessed', true)
            // levelDefault() wajib: tanpa itu relasi `indicators` ikut menarik indikator milik
            // orang, dan penjaga bobot akan menjumlahkan dua set berbeda jadi satu lalu melaporkan
            // "bobot lewat dari 100" yang tidak bisa dijelaskan admin.
            ->with(['indicators' => fn ($q) => $q->levelDefault()->active()->orderBy('sort_order'), 'indicators.rubrics'])
            ->orderBy('sort_order')
            ->get();

        if ($levels->isEmpty()) {
            throw new RuntimeException('Belum ada level KPI aktif yang dinilai untuk perusahaan ini.');
        }

        $owned = KpiIndicator::query()
            ->where('company_id', $period->company_id)
            ->whereNotNull('employee_id')
            ->active()
            ->with(['rubrics', 'employee:id,full_name,kpi_level_id,is_active,is_kpi_excluded'])
            ->orderBy('sort_order')
            ->get()
            // Indikator milik orang yang sudah tidak aktif atau dikecualikan tidak perlu dibekukan;
            // dia tidak akan dinilai periode ini.
            ->filter(fn (KpiIndicator $i) => $i->employee && $i->employee->is_active && ! $i->employee->is_kpi_excluded);

        $this->guardWeights($levels, $owned);

        $crossItems = KpiCrossItem::query()
            ->where('company_id', $period->company_id)
            ->active()
            ->orderBy('layer')
            ->orderBy('sort_order')
            ->get();

        DB::transaction(function () use ($period, $levels, $crossItems, $owned) {
            // Urutan penting: indikator menunjuk ke snapshot level.
            KpiPeriodIndicatorSnapshot::where('kpi_period_id', $period->id)->delete();
            KpiPeriodLevelSnapshot::where('kpi_period_id', $period->id)->delete();
            KpiPeriodCrossItemSnapshot::where('kpi_period_id', $period->id)->delete();

            foreach ($crossItems as $item) {
                KpiPeriodCrossItemSnapshot::create([
                    'kpi_period_id' => $period->id,
                    'kpi_cross_item_id' => $item->id,
                    'layer' => $item->layer,
                    'code' => $item->code,
                    'name' => $item->name,
                    'question' => $item->question,
                    'weight' => $item->weight,
                    'sort_order' => $item->sort_order,
                ]);
            }

            foreach ($levels as $level) {
                $levelSnapshot = KpiPeriodLevelSnapshot::create([
                    'kpi_period_id' => $period->id,
                    'kpi_level_id' => $level->id,
                    'code' => $level->code,
                    'name' => $level->name,
                    'is_assessed' => $level->is_assessed,
                    'weight_excellence' => $level->weight_excellence,
                    'weight_contribution' => $level->weight_contribution,
                    'weight_leadership' => $level->weight_leadership,
                    'sort_order' => $level->sort_order,
                ]);

                foreach ($level->indicators as $indicator) {
                    KpiPeriodIndicatorSnapshot::create([
                        'kpi_period_id' => $period->id,
                        'kpi_indicator_id' => $indicator->id,
                        'kpi_period_level_snapshot_id' => $levelSnapshot->id,
                        'category' => $indicator->category,
                        'code' => $indicator->code,
                        'name' => $indicator->name,
                        'description' => $indicator->description,
                        'weight' => $indicator->weight,
                        'is_core' => $indicator->is_core,
                        'is_auto_filled' => $indicator->is_auto_filled,
                        'auto_source' => $indicator->auto_source,
                        'rubrics' => $indicator->rubricMap(),
                        'sort_order' => $indicator->sort_order,
                    ]);
                }
            }

            /*
             * Indikator pribadi menempel pada snapshot level pemiliknya, supaya bobot kategori
             * (70/25/5 untuk L4) tetap terbaca dari tempat yang sama. Yang membedakannya hanya
             * `employee_id`; App\Support\KpiIndicatorSet yang memutuskan set mana yang dipakai.
             */
            $levelSnapshots = KpiPeriodLevelSnapshot::where('kpi_period_id', $period->id)
                ->get()
                ->keyBy('kpi_level_id');

            foreach ($owned as $indicator) {
                $levelSnapshot = $levelSnapshots[$indicator->employee->kpi_level_id] ?? null;

                if (! $levelSnapshot) {
                    continue; // levelnya tidak dinilai periode ini
                }

                KpiPeriodIndicatorSnapshot::create([
                    'kpi_period_id' => $period->id,
                    'kpi_indicator_id' => $indicator->id,
                    'employee_id' => $indicator->employee_id,
                    'kpi_period_level_snapshot_id' => $levelSnapshot->id,
                    'category' => $indicator->category,
                    'code' => $indicator->code,
                    'name' => $indicator->name,
                    'description' => $indicator->description,
                    'weight' => $indicator->weight,
                    'is_core' => $indicator->is_core,
                    'is_auto_filled' => $indicator->is_auto_filled,
                    'auto_source' => $indicator->auto_source,
                    'rubrics' => $indicator->rubricMap(),
                    'sort_order' => $indicator->sort_order,
                ]);
            }
        });
    }

    /**
     * Daftar masalah bobot tanpa melempar exception — dipakai halaman admin untuk
     * menampilkan peringatan sebelum tombol "Buka Periode" ditekan.
     *
     * @return array<int, string>
     */
    public function weightProblems(int $companyId): array
    {
        $levels = KpiLevel::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->where('is_assessed', true)
            ->with(['indicators' => fn ($q) => $q->levelDefault()->active()])
            ->get();

        $owned = KpiIndicator::query()
            ->where('company_id', $companyId)
            ->whereNotNull('employee_id')
            ->active()
            ->with('employee:id,full_name,kpi_level_id,is_active,is_kpi_excluded')
            ->get()
            ->filter(fn (KpiIndicator $i) => $i->employee && $i->employee->is_active && ! $i->employee->is_kpi_excluded);

        try {
            $this->guardWeights($levels, $owned);
        } catch (RuntimeException $e) {
            return explode("\n", $e->getMessage());
        }

        return [];
    }

    /**
     * Jumlah bobot tiga kategori harus 100, begitu pula jumlah bobot indikator dalam tiap
     * kategori. Diperiksa sekaligus supaya admin melihat SELURUH kesalahan, bukan satu per
     * satu tiap kali menekan tombol.
     *
     * Set indikator pribadi diperiksa dengan aturan yang sama: ia MENGGANTI set kategori milik
     * level, jadi jumlahnya pun harus 100. Kalau tidak diperiksa, seseorang bisa dinilai dengan
     * kategori berbobot 80% sementara rekan selevelnya 100% — dan selisihnya tidak akan terlihat
     * di mana pun kecuali pada skor akhirnya.
     *
     * @param  \Illuminate\Support\Collection<int, KpiLevel>  $levels
     * @param  \Illuminate\Support\Collection<int, KpiIndicator>  $owned
     */
    private function guardWeights($levels, $owned = null): void
    {
        $problems = [];

        foreach ($levels as $level) {
            if (! $level->hasValidCategoryWeight()) {
                $problems[] = sprintf(
                    'Bobot kategori %s berjumlah %s%%, seharusnya 100%%.',
                    $level->code,
                    $this->format($level->totalCategoryWeight())
                );
            }

            foreach (array_keys(KpiLevel::CATEGORIES) as $category) {
                $rows = $level->indicators->where('category', $category);

                if ($rows->isEmpty()) {
                    // Kategori berbobot 0 memang boleh kosong; selain itu skornya tak terhitung.
                    if ($level->categoryWeights()[$category] > 0) {
                        $problems[] = sprintf('Kategori %s pada %s belum punya indikator aktif.', $category, $level->code);
                    }

                    continue;
                }

                $total = (float) $rows->sum('weight');

                if (abs($total - 100.0) >= self::EPSILON) {
                    $problems[] = sprintf(
                        'Bobot indikator %s/%s berjumlah %s%%, seharusnya 100%%.',
                        $level->code,
                        $category,
                        $this->format($total)
                    );
                }
            }
        }

        foreach (($owned ?? collect())->groupBy('employee_id') as $rows) {
            $employee = $rows->first()->employee;

            foreach ($rows->groupBy('category') as $category => $categoryRows) {
                $total = (float) $categoryRows->sum('weight');

                if (abs($total - 100.0) >= self::EPSILON) {
                    $problems[] = sprintf(
                        'Bobot indikator %s milik %s berjumlah %s%%, seharusnya 100%%.',
                        $category,
                        $employee?->full_name ?? 'karyawan',
                        $this->format($total)
                    );
                }
            }
        }

        if ($problems !== []) {
            throw new RuntimeException(implode("\n", $problems));
        }
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }
}
