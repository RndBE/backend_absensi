<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Lpj;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LpjController extends Controller
{
    public function index(Request $request)
    {
        $query = Lpj::with([
            'employee:id,full_name,photo,department_id',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount',
        ]);

        $status = $request->get('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nomor_lpj', 'like', "%{$search}%")
                    ->orWhereHas('employee', fn ($eq) => $eq->where('full_name', 'like', "%{$search}%"))
                    ->orWhereHas('budgetRequest', fn ($bq) => $bq->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('month')) {
            $date = \Carbon\Carbon::parse($request->month . '-01');
            $query->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month);
        }

        $lpjs = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.lpj.index', compact('lpjs', 'status'));
    }

    public function show($id)
    {
        $lpj = Lpj::with([
            'employee:id,full_name,photo,department_id,position,job_level',
            'employee.department:id,name',
            'budgetRequest:id,title,total_amount,surat_tugas_no,surat_tugas_date',
            'travelReport:id,destination_city,departure_date,return_date',
            'items.budgetRequestItem',
            'approvalLogs.approver:id,full_name,photo',
        ])->findOrFail($id);

        return view('admin.lpj.show', compact('lpj'));
    }

    public function destroy($id)
    {
        $admin = Employee::find(session('admin_id'));
        if ($admin->role !== 'superadmin') {
            return back()->with('error', 'Hanya superadmin yang dapat menghapus LPJ.');
        }

        $lpj = Lpj::findOrFail($id);

        foreach ($lpj->items as $item) {
            if ($item->bukti_file) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($item->bukti_file);
            }
        }

        $lpj->items()->delete();
        $lpj->approvalLogs()->delete();
        $lpj->delete();

        return redirect()->route('admin.lpj.index')
            ->with('success', 'LPJ berhasil dihapus.');
    }

    public function exportExcel($id)
    {
        $lpj = Lpj::with([
            'employee',
            'employee.department',
            'budgetRequest',
            'travelReport',
            'items',
            'approvalLogs.approver',
        ])->findOrFail($id);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('LPJ');

        // ── Column widths ─────────────────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(30);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(14);
        $sheet->getColumnDimension('G')->setWidth(24);
        $sheet->getColumnDimension('H')->setWidth(16);

        // ── Header perusahaan ─────────────────────────────────────────────────
        $company = \App\Models\Company::first();
        $br = $lpj->budgetRequest;
        $tr = $lpj->travelReport;
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', strtoupper($company?->name ?? 'LAPORAN PERTANGGUNGJAWABAN'));
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'LAPORAN PERTANGGUNGJAWABAN (LPJ)');
        $this->styleTitle($sheet, 'A1:H1', 14);
        $this->styleTitle($sheet, 'A2:H2', 12);

        // ── Info LPJ ──────────────────────────────────────────────────────────
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
            $sheet->setCellValueExplicit("C{$row}", ': ' . $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
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

        // Judul blok
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

        // Header kolom
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
            $sheet->setCellValue("H{$row}", $item->bukti_file ? 'Ada' : '');

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
            $sheet->setCellValue("H{$row}", $item->bukti_file ? 'Ada' : '');
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode($moneyFmt);
            $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                'font'      => ['size' => 9],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                'borders'   => $thin,
            ]);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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

        $filename = 'LPJ_' . ($lpj->nomor_lpj ?? $lpj->id) . '_' . now()->format('Ymd') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    private function styleTitle($sheet, string $range, int $size): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'size' => $size],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }
}
