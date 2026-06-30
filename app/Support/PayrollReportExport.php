<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export laporan payroll (flat) dengan tampilan rapi + baris GRAND TOTAL.
 *
 * @param  array  $headers  Judul kolom (1 baris).
 * @param  array  $rows     Baris data (array of array, urutan sesuai $headers).
 * @param  int    $numericFrom  Indeks kolom 1-based pertama yang berisi angka (untuk format & total).
 */
class PayrollReportExport
{
    public static function build(array $headers, array $rows, string $companyName, string $period, int $numericFrom = 4): string
    {
        $colCount = max(count($headers), 1);
        $lastCol = Coordinate::stringFromColumnIndex($colCount);

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Laporan Payroll');

        // Judul.
        $dt = Carbon::parse($period.'-01');
        $sheet->setCellValue('A1', $companyName);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A2', 'Laporan Payroll - '.$dt->locale('id')->translatedFormat('F Y'));
        $sheet->mergeCells("A2:{$lastCol}2");

        // Header (baris 4).
        $headerRow = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$headerRow, $h);
        }

        // Data.
        $dataStart = $headerRow + 1;
        $r = $dataStart;
        foreach ($rows as $row) {
            $ci = 1;
            foreach ($row as $val) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($ci).$r, $val);
                $ci++;
            }
            $r++;
        }
        $dataEnd = $r - 1;
        $hasData = $dataEnd >= $dataStart;
        $totalRow = $r;

        // Grand total.
        $sheet->setCellValue("A{$totalRow}", 'GRAND TOTAL');
        if ($numericFrom > 1) {
            $sheet->mergeCells('A'.$totalRow.':'.Coordinate::stringFromColumnIndex($numericFrom - 1).$totalRow);
        }
        for ($ci = $numericFrom; $ci <= $colCount; $ci++) {
            $colL = Coordinate::stringFromColumnIndex($ci);
            $sheet->setCellValue(
                "{$colL}{$totalRow}",
                $hasData ? "=SUM({$colL}{$dataStart}:{$colL}{$dataEnd})" : 0
            );
        }

        self::style($sheet, $colCount, $lastCol, $headerRow, $dataStart, $totalRow, $numericFrom);

        $writer = new Xlsx($ss);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    private static function style($sheet, int $colCount, string $lastCol, int $headerRow, int $dataStart, int $totalRow, int $numericFrom): void
    {
        // Judul.
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A2:{$lastCol}2")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('555555');
        $sheet->getStyle("A1:{$lastCol}2")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        // Header: biru tua, teks putih tebal.
        $hRange = "A{$headerRow}:{$lastCol}{$headerRow}";
        $sheet->getStyle($hRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($hRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('305496');
        $sheet->getStyle($hRange)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        // Border seluruh tabel (header s/d total).
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$totalRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');

        // Grand total: tebal + arsir.
        $tRange = "A{$totalRow}:{$lastCol}{$totalRow}";
        $sheet->getStyle($tRange)->getFont()->setBold(true);
        $sheet->getStyle($tRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FCE4D6');

        // Format angka kolom numerik (data + total).
        $firstNum = Coordinate::stringFromColumnIndex($numericFrom);
        $sheet->getStyle("{$firstNum}{$dataStart}:{$lastCol}{$totalRow}")
            ->getNumberFormat()->setFormatCode('#,##0');

        // Lebar kolom: kolom teks (sebelum numericFrom) lebih lebar.
        for ($ci = 1; $ci < $numericFrom; $ci++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setWidth($ci === 2 ? 28 : 20);
        }
        for ($ci = $numericFrom; $ci <= $colCount; $ci++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setWidth(18);
        }

        // Bekukan judul + header.
        $sheet->freezePane('A'.$dataStart);
    }
}
