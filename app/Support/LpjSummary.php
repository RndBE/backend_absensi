<?php

namespace App\Support;

use App\Models\Lpj;
use App\Models\LpjItem;
use Illuminate\Support\Collection;

/**
 * Ringkasan LPJ dengan model PEMASUKAN vs PENGELUARAN yang terpisah:
 * - PEMASUKAN  = item Pengajuan Anggaran (anggaran disetujui).
 * - PENGELUARAN = item realisasi LPJ (berkategori), bebas/tidak terikat 1:1.
 * - Perbandingan & over-budget dihitung PER KATEGORI.
 *
 * Dipakai bersama oleh tampilan detail (Fase 2) dan exporter Excel (Fase 3)
 * agar angkanya selalu konsisten.
 */
class LpjSummary
{
    public static function for(Lpj $lpj): array
    {
        $lpj->loadMissing(['items', 'budgetRequest.items']);

        $budgetItems = $lpj->budgetRequest?->items ?? collect();

        // ── PEMASUKAN (dari item anggaran) ──
        $pemasukan = $budgetItems->map(fn ($bi) => [
            'uraian'         => $bi->description,
            'kategori'       => $bi->type,
            'kategori_label' => $bi->type_label,
            'jumlah'         => (float) $bi->amount,
        ])->values();

        $totalPemasukan = (float) ($lpj->total_anggaran ?: $pemasukan->sum('jumlah'));

        // ── PENGELUARAN (item realisasi LPJ) ──
        $pengeluaran = $lpj->items;
        $totalPengeluaran = (float) $pengeluaran->sum('realisasi');

        // ── Perbandingan per kategori ──
        $budgetByCat = $budgetItems
            ->groupBy(fn ($bi) => self::normalizeCategory($bi->type))
            ->map(fn (Collection $g) => (float) $g->sum('amount'));

        $realByCat = $pengeluaran
            ->groupBy(fn ($it) => self::normalizeCategory($it->kategori))
            ->map(fn (Collection $g) => (float) $g->sum('realisasi'));

        // Deskripsi item anggaran per kategori (untuk kolom PERIHAL di tabel ringkasan).
        $perihalByCat = $budgetItems
            ->groupBy(fn ($bi) => self::normalizeCategory($bi->type))
            ->map(fn (Collection $g) => $g->pluck('description')->filter()->implode(', '));

        // Catatan per kategori (mis. "Reimbursement") yang diisi/diimpor.
        $kategoriNotes = $lpj->kategori_notes ?? [];

        $perKategori = collect();
        foreach (LpjItem::CATEGORIES as $key => $label) {
            $anggaran = (float) ($budgetByCat[$key] ?? 0);
            $realisasi = (float) ($realByCat[$key] ?? 0);

            if ($anggaran == 0.0 && $realisasi == 0.0) {
                continue;
            }

            $perihal = (string) ($perihalByCat[$key] ?? '');

            $perKategori->push([
                'kategori'  => $key,
                'label'     => $label,
                'perihal'   => $perihal !== '' ? $perihal : $label,
                'keterangan' => (string) ($kategoriNotes[$key] ?? ''),
                'anggaran'  => $anggaran,
                'realisasi' => $realisasi,
                'selisih'   => $anggaran - $realisasi,
                // Over budget hanya untuk kategori yang PUNYA anggaran & realisasinya melebihi.
                'over'      => $anggaran > 0 ? max(0.0, $realisasi - $anggaran) : 0.0,
            ]);
        }

        // Over budget = jumlah kelebihan realisasi atas pengajuan, hanya untuk
        // kategori yang punya pengajuan (anggaran > 0).
        $totalOver = (float) $perKategori->sum('over');

        // Perihal/kategori yang over budget (untuk label "OVER BUDGET (…)").
        $overCategories = $perKategori->where('over', '>', 0)->pluck('perihal')->values();

        // Saldo = pemasukan − pengeluaran + over-budget (porsi over di-reimburse terpisah).
        $saldo = $totalPemasukan - $totalPengeluaran + $totalOver;

        return [
            'pemasukan'         => $pemasukan,
            'total_pemasukan'   => $totalPemasukan,
            'pengeluaran'       => $pengeluaran,
            'total_pengeluaran' => $totalPengeluaran,
            'per_kategori'      => $perKategori,
            'total_over_budget' => $totalOver,
            'over_categories'   => $overCategories,
            'saldo'             => $saldo,
        ];
    }

    /** Kategori tak dikenal / kosong (data lama) dikelompokkan ke 'lainnya'. */
    private static function normalizeCategory(?string $kategori): string
    {
        return array_key_exists((string) $kategori, LpjItem::CATEGORIES) ? (string) $kategori : 'lainnya';
    }
}
