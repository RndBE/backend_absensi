<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\KpiPeriod;
use App\Models\KpiPeriodIndicatorSnapshot;
use Illuminate\Support\Collection;

/**
 * Indikator mana yang berlaku untuk seorang karyawan pada satu periode.
 *
 * ══ Satu aturan, satu tempat ══
 *
 * Indikator dibaca di empat tempat berbeda — formulir pengisian, penjagaan saat submit, penyimpanan
 * draf, dan perhitungan skor. Kalau aturannya disalin ke empat tempat, cukup satu yang tertinggal
 * saat aturan berubah dan seseorang dinilai memakai indikator yang bukan miliknya, atau skornya
 * dihitung dari set yang berbeda dari yang dia isi. Persoalan yang sama pernah terjadi pada sasaran
 * Lapis B sebelum App\Support\KpiCrossTargets dibuat.
 *
 * ══ Aturannya ══
 *
 * Kalau seorang karyawan punya indikator sendiri di sebuah kategori, indikator itu MENGGANTI seluruh
 * set kategori tersebut dari levelnya — bukan menambah. Menambah membuat jumlah bobot kategori lewat
 * dari 100 dan menggeser arti skornya tanpa ada yang menyadari.
 *
 * Kategori yang tidak punya indikator pribadi tetap memakai bawaan level. Jadi seorang welder bisa
 * punya Excellence sendiri sambil tetap dinilai Contribution dan Leadership yang sama dengan staf
 * lain — memang bagian itu yang seragam.
 */
class KpiIndicatorSet
{
    /**
     * @return Collection<int, KpiPeriodIndicatorSnapshot>  urut kategori lalu sort_order
     */
    public function forEmployee(KpiPeriod $period, Employee $employee): Collection
    {
        $snapshots = KpiPeriodIndicatorSnapshot::query()
            ->where('kpi_period_id', $period->id)
            ->whereHas('levelSnapshot', fn ($q) => $q->where('kpi_level_id', $employee->kpi_level_id))
            ->where(function ($query) use ($employee) {
                $query->whereNull('employee_id')->orWhere('employee_id', $employee->id);
            })
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return $this->resolve($snapshots, $employee->id);
    }

    /**
     * Menyaring set yang sudah dimuat, tanpa kueri baru. Dipakai saat snapshot periode sudah ada
     * di tangan — mis. halaman pengisian yang memuatnya lewat relasi.
     *
     * @param  Collection<int, KpiPeriodIndicatorSnapshot>  $snapshots
     * @return Collection<int, KpiPeriodIndicatorSnapshot>
     */
    public function resolve(Collection $snapshots, int $employeeId): Collection
    {
        // Kategori yang punya indikator pribadi: bawaan levelnya dibuang seluruhnya untuk kategori itu.
        $overridden = $snapshots
            ->filter(fn ($s) => $s->employee_id === $employeeId)
            ->pluck('category')
            ->unique()
            ->all();

        return $snapshots
            ->reject(fn ($s) => $s->employee_id !== null && $s->employee_id !== $employeeId)
            ->reject(fn ($s) => $s->employee_id === null && in_array($s->category, $overridden, true))
            ->sortBy([['category', 'asc'], ['sort_order', 'asc']])
            ->values();
    }

    /**
     * Kategori mana saja yang dipakaikan indikator pribadi untuk orang ini. Untuk ditampilkan admin
     * supaya jelas bagian mana yang menyimpang dari bawaan level.
     *
     * @return array<int, string>
     */
    public function overriddenCategories(KpiPeriod $period, Employee $employee): array
    {
        return KpiPeriodIndicatorSnapshot::query()
            ->where('kpi_period_id', $period->id)
            ->where('employee_id', $employee->id)
            ->pluck('category')
            ->unique()
            ->values()
            ->all();
    }
}
