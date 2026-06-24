<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lpj;
use App\Support\LpjSummary;
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
    public static function download(Lpj $lpj): StreamedResponse
    {
        $spreadsheet = self::build($lpj);
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
            'employee', 'employee.department', 'budgetRequest', 'budgetRequest.items',
            'travelReport', 'items', 'approvalLogs.approver',
        ]);

        $sum = LpjSummary::for($lpj);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('LPJ');

        foreach (['A' => 5, 'B' => 14, 'C' => 26, 'D' => 16, 'E' => 16, 'F' => 14, 'G' => 22, 'H' => 18] as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $thin = ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]];
        $moneyFmt = '#,##0;(#,##0)';

        $company = Company::first();
        $br = $lpj->budgetRequest;
        $tr = $lpj->travelReport;
        $itemDate = $tr?->departure_date?->format('d/m/Y') ?? ($br?->surat_tugas_date?->format('d/m/Y') ?? '');

        // ── Judul ──
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', strtoupper($company?->name ?? 'LAPORAN PERTANGGUNGJAWABAN'));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'LAPORAN PERTANGGUNGJAWABAN (LPJ)');
        self::styleTitle($sheet, 'A1:H1', 14);
        self::styleTitle($sheet, 'A2:H2', 12);

        // ── Info kegiatan ──
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
            $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle("A{$row}:H{$row}")->getFont()->setSize(10);
            $sheet->getStyle("A{$row}:H{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $row++;
        }
        $sheet->getStyle("A4:H" . ($row - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FDEFD2');
        $row += 1;

        // ════════════ PEMASUKAN | REALISASI PENGELUARAN (per kategori) ════════════
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", 'PEMASUKAN');
        $sheet->mergeCells("E{$row}:H{$row}");
        $sheet->setCellValue("E{$row}", 'REALISASI PENGELUARAN');
        self::sectionHeader($sheet, "A{$row}:H{$row}", '00FFFF', $thin);
        $row++;

        foreach (['NO', 'TANGGAL', 'PERIHAL', 'JUMLAH PENGAJUAN', 'JUMLAH REALISASI', 'SELISIH', 'KETERANGAN', 'FOTO NOTA'] as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . $row, $h);
        }
        self::columnHeader($sheet, "A{$row}:H{$row}", $thin);
        $sheet->getRowDimension($row)->setRowHeight(26);
        $row++;

        $cats = $sum['per_kategori']->values();
        $rowsToShow = max(5, $cats->count());
        for ($k = 0; $k < $rowsToShow; $k++) {
            $cat = $cats->get($k);
            $sheet->setCellValue("A{$row}", $k + 1);
            if ($cat) {
                $sheet->setCellValue("B{$row}", $itemDate);
                $sheet->setCellValue("C{$row}", $cat['perihal']);
                if ($cat['anggaran'] > 0) {
                    $sheet->setCellValue("D{$row}", $cat['anggaran']);
                }
                $sheet->setCellValue("E{$row}", $cat['realisasi']);
                $sheet->setCellValue("F{$row}", $cat['selisih']);
                $sheet->setCellValue("G{$row}", $cat['keterangan']);
            } else {
                $sheet->setCellValue("E{$row}", 0);
                $sheet->setCellValue("F{$row}", 0);
            }
            foreach (['D', 'E', 'F'] as $c) {
                $sheet->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            }
            $sheet->getStyle("F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => $thin,
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL PEMASUKAN');
        $sheet->setCellValue("D{$row}", $sum['total_pemasukan']);
        $sheet->setCellValue("E{$row}", $sum['total_pengeluaran']);
        $sheet->setCellValue("F{$row}", $sum['total_pemasukan'] - $sum['total_pengeluaran']);
        foreach (['D', 'E', 'F'] as $c) {
            $sheet->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
        }
        self::totalRow($sheet, "A{$row}:H{$row}", $thin);
        $row += 2;

        // ════════════ KOTAK RINGKASAN ════════════
        $overLabel = 'OVER BUDGET';
        if ($sum['over_categories']->isNotEmpty()) {
            $overLabel .= ' (' . strtoupper($sum['over_categories']->implode(', ')) . ')';
        }
        $summary = [
            ['TOTAL PEMASUKAN',   $sum['total_pemasukan'],    'FFFFFF', '000000'],
            ['TOTAL PENGELUARAN', $sum['total_pengeluaran'],  'FF0000', 'FFFFFF'],
            [$overLabel,          -$sum['total_over_budget'], 'F2DCDB', '000000'],
            ['SALDO',             $sum['saldo'],              'E4DFEC', '000000'],
        ];
        foreach ($summary as [$label, $value, $bg, $fg]) {
            $sheet->mergeCells("C{$row}:D{$row}");
            $sheet->setCellValue("C{$row}", $label);
            $sheet->mergeCells("E{$row}:F{$row}");
            $sheet->setCellValue("E{$row}", $value);
            $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle("C{$row}:F{$row}")->applyFromArray([
                'font'    => ['bold' => true, 'size' => 10, 'color' => ['rgb' => $fg]],
                'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
                'borders' => $thin,
            ]);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $row++;
        }
        $row += 2;

        // ════════════ PENGELUARAN (rincian detail per item) ════════════
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'PENGELUARAN');
        self::sectionHeader($sheet, "A{$row}:H{$row}", '00FFFF', $thin);
        $row++;

        $sheet->setCellValue("A{$row}", 'NO');
        $sheet->setCellValue("B{$row}", 'TANGGAL');
        $sheet->setCellValue("C{$row}", 'PERIHAL');
        $sheet->setCellValue("D{$row}", 'JUMLAH');
        $sheet->mergeCells("E{$row}:G{$row}");
        $sheet->setCellValue("E{$row}", 'KETERANGAN');
        $sheet->setCellValue("H{$row}", 'SCREENSHOOT');
        self::columnHeader($sheet, "A{$row}:H{$row}", $thin);
        $sheet->getRowDimension($row)->setRowHeight(26);
        $row++;

        $no = 1;
        foreach ($sum['pengeluaran'] as $item) {
            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValue("B{$row}", $itemDate);
            $sheet->setCellValue("C{$row}", $item->kategori_label);
            $sheet->setCellValue("D{$row}", (float) $item->realisasi);
            $sheet->mergeCells("E{$row}:G{$row}");
            $sheet->setCellValue("E{$row}", $item->uraian);
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font' => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders' => $thin,
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            self::placeBukti($sheet, $item->bukti_file, "H{$row}", $row);
            $row++;
        }
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("A{$row}", 'Total Pengeluaran');
        $sheet->setCellValue("D{$row}", $sum['total_pengeluaran']);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
        self::totalRow($sheet, "A{$row}:H{$row}", $thin);
        $row += 2;

        // ── Tanda tangan ──
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

    private static function sectionHeader($sheet, string $range, string $rgb, array $thin): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => $thin,
        ]);
    }

    private static function columnHeader($sheet, string $range, array $thin): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => $thin,
        ]);
    }

    private static function totalRow($sheet, string $range, array $thin): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => $thin,
        ]);
        $first = explode(':', $range)[0];
        $sheet->getStyle($first)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

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
