<?php

namespace App\Services;

use App\Models\Lpj;
use App\Models\LpjItem;
use App\Support\LpjSummary;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Impor kembali file Excel hasil ekspor LPJ.
 *
 * Yang dibaca adalah tabel "PENGELUARAN" (rincian detail per transaksi):
 *   NO | TANGGAL | PERIHAL(kategori) | JUMLAH | KETERANGAN(uraian) | SCREENSHOOT
 * Tiap baris dicocokkan ke item LPJ berdasarkan URUTAN (sort_order). Nilai yang
 * diperbarui: realisasi (JUMLAH), kategori (PERIHAL), dan uraian (KETERANGAN).
 *
 * Tabel ringkasan atas (PEMASUKAN | REALISASI per kategori) tidak diimpor —
 * itu dihitung otomatis.
 */
class LpjExcelImporter
{
    /**
     * @return array{updated:int,total:int,warnings:string[]}
     */
    public static function import(Lpj $lpj, string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

        [$headerIdx, $col] = self::locateDetailHeader($rows);
        if ($headerIdx === null) {
            throw new \RuntimeException('Format Excel tidak dikenali. Gunakan file hasil "Export Excel" dari LPJ ini (tabel PENGELUARAN).');
        }

        $dataRows = self::collectDataRows($rows, $headerIdx);
        $items = $lpj->items; // urutan sort_order — sama dengan urutan saat ekspor

        if (count($dataRows) !== $items->count()) {
            throw new \RuntimeException(
                'Jumlah baris pengeluaran di Excel ('.count($dataRows).') tidak sama dengan jumlah item LPJ ('.$items->count().'). '
                .'Jangan menambah atau menghapus baris item — cukup ubah nilainya.'
            );
        }

        // Baca catatan per kategori dari tabel atas SEBELUM item diubah,
        // agar urutan kategori cocok dengan file yang diekspor.
        $kategoriNotes = self::readKategoriNotes($lpj, $rows);

        $warnings = [];
        $updated = 0;

        foreach ($items as $idx => $item) {
            $row = $dataRows[$idx];

            $realisasi = self::parseMoney($row[$col['jumlah']] ?? null);
            if ($realisasi === null) {
                $warnings[] = 'Baris '.($idx + 1)." ({$item->uraian}): nilai jumlah kosong/tidak valid — dilewati.";
                continue;
            }

            $payload = ['realisasi' => $realisasi];

            // PERIHAL = kategori
            if ($col['perihal'] !== null) {
                $kategori = self::resolveCategory($row[$col['perihal']] ?? null);
                if ($kategori !== null) {
                    $payload['kategori'] = $kategori;
                }
            }
            // KETERANGAN = uraian/deskripsi
            if ($col['keterangan'] !== null) {
                $uraian = trim((string) ($row[$col['keterangan']] ?? ''));
                if ($uraian !== '') {
                    $payload['uraian'] = $uraian;
                }
            }

            $item->update($payload);
            $updated++;
        }

        if ($kategoriNotes !== null) {
            $lpj->update(['kategori_notes' => $kategoriNotes]);
        }

        $lpj->recalculate();

        return ['updated' => $updated, 'total' => $items->count(), 'warnings' => $warnings];
    }

    /**
     * Baca kolom KETERANGAN tabel ringkasan atas (PEMASUKAN | REALISASI per kategori)
     * dan petakan ke key kategori (berdasarkan urutan kategori saat ini).
     *
     * @param array<int,array<int,mixed>> $rows
     * @return array<string,string>|null  null bila tabel atas tidak ditemukan
     */
    private static function readKategoriNotes(Lpj $lpj, array $rows): ?array
    {
        $headerIdx = null;
        $ketCol = null;
        foreach ($rows as $i => $row) {
            $labels = array_map(fn ($c) => strtoupper(trim((string) $c)), $row);
            if (in_array('JUMLAH PENGAJUAN', $labels, true) && in_array('JUMLAH REALISASI', $labels, true)) {
                $k = array_search('KETERANGAN', $labels, true);
                if ($k !== false) {
                    $headerIdx = $i;
                    $ketCol = $k;
                    break;
                }
            }
        }

        if ($headerIdx === null) {
            return null;
        }

        $cats = LpjSummary::for($lpj)['per_kategori']->values();
        $notes = [];
        $catIdx = 0;

        for ($i = $headerIdx + 1, $n = count($rows); $i < $n && $catIdx < $cats->count(); $i++) {
            $no = trim((string) ($rows[$i][0] ?? ''));
            if ($no === '' || ! is_numeric($no)) {
                break; // baris TOTAL atau kosong
            }

            $note = trim((string) ($rows[$i][$ketCol] ?? ''));
            $key = $cats->get($catIdx)['kategori'];
            if ($note !== '') {
                $notes[$key] = $note;
            }
            $catIdx++;
        }

        return $notes;
    }

    /**
     * Cari baris header tabel PENGELUARAN detail (punya kolom SCREENSHOOT).
     *
     * @param array<int,array<int,mixed>> $rows
     * @return array{0:?int,1:array<string,?int>}
     */
    private static function locateDetailHeader(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $labels = array_map(fn ($c) => strtoupper(trim((string) $c)), $row);

            $perihal = array_search('PERIHAL', $labels, true);
            $jumlah = array_search('JUMLAH', $labels, true);
            $screenshoot = array_search('SCREENSHOOT', $labels, true);

            // Tabel detail dikenali dari kolom PERIHAL + JUMLAH + SCREENSHOOT.
            if ($perihal !== false && $jumlah !== false && $screenshoot !== false) {
                $keterangan = array_search('KETERANGAN', $labels, true);
                $kategori = array_search('KATEGORI', $labels, true);

                return [$i, [
                    'perihal'    => $perihal,
                    'jumlah'     => $jumlah,
                    'keterangan' => $keterangan === false ? null : $keterangan,
                    'kategori'   => $kategori === false ? null : $kategori,
                ]];
            }
        }

        return [null, []];
    }

    /**
     * Ambil baris data setelah header sampai sebelum baris "Total" (kolom NO tidak numerik).
     * Baris benar-benar kosong (tanpa jumlah & tanpa perihal) dilewati.
     *
     * @param array<int,array<int,mixed>> $rows
     * @return array<int,array<int,mixed>>
     */
    private static function collectDataRows(array $rows, int $headerIdx): array
    {
        $data = [];
        for ($i = $headerIdx + 1, $n = count($rows); $i < $n; $i++) {
            $no = trim((string) ($rows[$i][0] ?? ''));
            if ($no === '' || ! is_numeric($no)) {
                break; // baris "Total Pengeluaran" atau baris kosong
            }
            $data[] = $rows[$i];
        }

        return $data;
    }

    /**
     * Petakan teks kategori (key atau label) ke key kategori yang valid.
     * Mengembalikan null bila tidak dikenali (kategori lama dipertahankan).
     */
    private static function resolveCategory(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (array_key_exists($raw, LpjItem::CATEGORIES)) {
            return $raw;
        }

        foreach (LpjItem::CATEGORIES as $key => $label) {
            if (mb_strtolower($label) === mb_strtolower($raw)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Ubah nilai sel menjadi angka. Mendukung angka asli Excel maupun format
     * teks Indonesia ("1.000.000" / "1.500,50").
     */
    private static function parseMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        // Angka asli dari Excel (bukan teks) langsung dipakai.
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = preg_replace('/[^0-9,.\-]/', '', (string) $value);
        if ($s === '' || $s === '-') {
            return null;
        }

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        } else {
            $parts = explode('.', $s);
            if (count($parts) === 2 && strlen($parts[1]) === 3) {
                $s = str_replace('.', '', $s);
            }
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
