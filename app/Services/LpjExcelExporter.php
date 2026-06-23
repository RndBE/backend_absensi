<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lpj;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LpjExcelExporter
{
    /**
     * Bangun + kirim file Excel LPJ sebagai unduhan.
     */
    public static function download(Lpj $lpj): StreamedResponse
    {
        $spreadsheet = self::build($lpj);
        // Nomor LPJ bisa memuat "/" (mis. LPJ/2026/06/23/001) yang dilarang di nama file.
        $safeNomor = str_replace(['/', '\\'], '-', (string) ($lpj->nomor_lpj ?? $lpj->id));
        $filename = 'LPJ_' . $safeNomor . '_' . now()->format('Ymd') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public static function build(Lpj $lpj): Spreadsheet
    {
        $lpj->loadMissing([
            'employee', 'employee.department', 'budgetRequest', 'travelReport',
            'items', 'approvalLogs.approver',
        ]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('LPJ');

        // ── Lebar kolom ───────────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(14);
        $sheet->getColumnDimension('G')->setWidth(24);
        $sheet->getColumnDimension('H')->setWidth(18);

        // ── Judul ─────────────────────────────────────────────────────────────
        $company = Company::first();
        $br = $lpj->budgetRequest;
        $tr = $lpj->travelReport;
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', strtoupper($company?->name ?? 'LAPORAN PERTANGGUNGJAWABAN'));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'LAPORAN PERTANGGUNGJAWABAN (LPJ)');
        self::styleTitle($sheet, 'A1:H1', 14);
        self::styleTitle($sheet, 'A2:H2', 12);

        // ── Info kegiatan ─────────────────────────────────────────────────────
        $jumlahOrang = $br ? max(1, $br->participants()->count()) : 1;
        $row = 4;
        $infoFields = [
            ['Project',             $br?->title ?? '-'],
            ['Tanggal Pelaksanaan', $tr?->departure_date?->format('d F Y') ?? '-'],
            ['Tanggal Selesai',     $tr?->return_date?->format('d F Y') ?? '-'],
            ['Jumlah hari Tugas',   $tr ? (string) $tr->duration_days : '-'],
            ['Nomor Surat Tugas',   $br?->surat_tugas_no ?? '-'],
            ['PIC',                 $lpj->employee->full_name ?? '-'],
            ['Jumlah Orang',        (string) $jumlahOrang],
        ];

        foreach ($infoFields as [$label, $value]) {
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("A{$row}", $label);
            $sheet->mergeCells("C{$row}:H{$row}");
            $sheet->setCellValueExplicit("C{$row}", ': ' . $value, DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("C{$row}:H{$row}")->applyFromArray([
                'font'      => ['size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $row++;
        }

        $sheet->getStyle("A4:H" . ($row - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDEFD2');

        $row++;

        // ════════════ TABEL PEMASUKAN vs REALISASI ════════════
        $thin = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]];
        $moneyFmt = '#,##0;(#,##0)';
        $itemDate = $tr?->departure_date?->format('d/m/Y') ?? ($br?->surat_tugas_date?->format('d/m/Y') ?? '');

        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", 'PEMASUKAN');
        $sheet->mergeCells("E{$row}:H{$row}");
        $sheet->setCellValue("E{$row}", 'REALISASI PENGELUARAN');
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00FFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => $thin,
        ]);
        $row++;

        foreach (['NO', 'TANGGAL', 'PERIHAL', 'JUMLAH PENGAJUAN', 'JUMLAH REALISASI', 'SELISIH', 'KETERANGAN', 'FOTO NOTA'] as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
        }
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => $thin,
        ]);
        $sheet->getRowDimension($row)->setRowHeight(26);
        $row++;

        $overBudget = 0.0;
        $no = 1;
        foreach ($lpj->items as $item) {
            $anggaran  = (float) $item->anggaran;
            $realisasi = (float) $item->realisasi;
            $selisih   = $anggaran - $realisasi;
            if ($anggaran > 0 && $selisih < 0) {
                $overBudget += abs($selisih);
            }

            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValue("B{$row}", $itemDate);
            $sheet->setCellValue("C{$row}", $item->uraian);
            if ($anggaran > 0) {
                $sheet->setCellValue("D{$row}", $anggaran);
            }
            $sheet->setCellValue("E{$row}", $realisasi);
            $sheet->setCellValue("F{$row}", $selisih);
            $sheet->setCellValue("G{$row}", $item->keterangan);

            foreach (['D', 'E', 'F'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            }
            $sheet->getStyle("F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font'      => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => $thin,
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            self::placeBukti($sheet, $item->bukti_file, "H{$row}", $row);
            $row++;
        }

        // Total pemasukan
        $totalAnggaran  = (float) $lpj->total_anggaran;
        $totalRealisasi = (float) $lpj->total_realisasi;
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL PEMASUKAN');
        $sheet->setCellValue("D{$row}", $totalAnggaran);
        $sheet->setCellValue("E{$row}", $totalRealisasi);
        $sheet->setCellValue("F{$row}", $totalAnggaran - $totalRealisasi);
        foreach (['D', 'E', 'F'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
        }
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => $thin,
        ]);
        $row += 2;

        // ════════════ KOTAK RINGKASAN ════════════
        $saldo = ($totalAnggaran - $totalRealisasi) + $overBudget;
        $summary = [
            ['TOTAL PEMASUKAN',          $totalAnggaran,  'FFFFFF', '000000'],
            ['TOTAL PENGELUARAN',        $totalRealisasi, 'FF0000', 'FFFFFF'],
            ['OVER BUDGET (UANG MAKAN)', -$overBudget,    'F2DCDB', '000000'],
            ['SALDO',                    $saldo,          'E4DFEC', '000000'],
        ];
        foreach ($summary as [$label, $value, $bg, $fg]) {
            $sheet->mergeCells("C{$row}:E{$row}");
            $sheet->setCellValue("C{$row}", $label);
            $sheet->mergeCells("F{$row}:G{$row}");
            $sheet->setCellValue("F{$row}", $value);
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle("C{$row}:G{$row}")->applyFromArray([
                'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $fg]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => $thin,
            ]);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }
        $row += 1;

        // ════════════ TABEL PENGELUARAN (rincian per kategori) ════════════
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'PENGELUARAN');
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '00FFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => $thin,
        ]);
        $row++;

        $sheet->setCellValue("A{$row}", 'NO');
        $sheet->setCellValue("B{$row}", 'TANGGAL');
        $sheet->setCellValue("C{$row}", 'PERIHAL');
        $sheet->setCellValue("D{$row}", 'JUMLAH');
        $sheet->mergeCells("E{$row}:G{$row}");
        $sheet->setCellValue("E{$row}", 'KETERANGAN');
        $sheet->setCellValue("H{$row}", 'Screenshoot');
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => $thin,
        ]);
        $row++;

        $no = 1;
        foreach ($lpj->items as $item) {
            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValue("B{$row}", $itemDate);
            $sheet->setCellValue("C{$row}", $item->uraian);
            $sheet->setCellValue("D{$row}", (float) $item->realisasi);
            $sheet->mergeCells("E{$row}:G{$row}");
            $sheet->setCellValue("E{$row}", $item->keterangan);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font'      => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => $thin,
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            self::placeBukti($sheet, $item->bukti_file, "H{$row}", $row);
            $row++;
        }

        // Total pengeluaran
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'Total Pengeluaran');
        $sheet->setCellValue("D{$row}", $totalRealisasi);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => $thin,
        ]);
        $row += 2;

        // Tanda tangan
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'Yang Membuat,');
        $sheet->mergeCells("F{$row}:H{$row}");
        $sheet->setCellValue("F{$row}", 'Mengetahui,');
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setSize(10);
        $row += 3;

        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", '(' . ($lpj->employee->full_name ?? '') . ')');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);

        $approver = $lpj->approvalLogs->where('action', 'approved')->last()?->approver;
        $sheet->mergeCells("F{$row}:H{$row}");
        $sheet->setCellValue("F{$row}", '(' . ($approver?->full_name ?? '_______________') . ')');
        $sheet->getStyle("F{$row}")->getFont()->setBold(true)->setSize(10);

        return $spreadsheet;
    }

    /**
     * Tempel gambar bukti ke sel. Jika bukan gambar (mis. PDF), tulis teks.
     */
    private static function placeBukti($sheet, ?string $buktiFile, string $cell, int $row): void
    {
        if (! $buktiFile) {
            return;
        }

        $ext = strtolower(pathinfo($buktiFile, PATHINFO_EXTENSION));
        $abs = Storage::disk('public')->path($buktiFile);

        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true) && is_file($abs)) {
            $drawing = new Drawing();
            $drawing->setPath($abs);
            $drawing->setHeight(46);
            $drawing->setOffsetX(4);
            $drawing->setOffsetY(4);
            $drawing->setCoordinates($cell);
            $drawing->setWorksheet($sheet);
            $sheet->getRowDimension($row)->setRowHeight(50);
        } else {
            // PDF atau format lain yang tidak bisa ditempel sebagai gambar.
            $sheet->setCellValue($cell, strtoupper($ext ?: 'FILE') . ' (lihat sistem)');
        }
    }

    private static function styleTitle($sheet, string $range, int $size): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => $size],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }
}
