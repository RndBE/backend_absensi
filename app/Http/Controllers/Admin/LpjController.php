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
        $sheet->getColumnDimension('B')->setWidth(32);
        $sheet->getColumnDimension('C')->setWidth(10);
        $sheet->getColumnDimension('D')->setWidth(8);
        $sheet->getColumnDimension('E')->setWidth(16);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('I')->setWidth(20);

        // ── Header perusahaan ─────────────────────────────────────────────────
        $company = \App\Models\Company::first();
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', strtoupper($company?->name ?? 'LAPORAN PERTANGGUNGJAWABAN'));
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', 'LAPORAN PERTANGGUNGJAWABAN (LPJ)');
        $this->styleTitle($sheet, 'A1:I1', 14);
        $this->styleTitle($sheet, 'A2:I2', 12);

        // ── Info LPJ ──────────────────────────────────────────────────────────
        $row = 4;
        $infoFields = [
            ['Nomor LPJ',       $lpj->nomor_lpj ?? '-'],
            ['Nama Karyawan',   $lpj->employee->full_name ?? '-'],
            ['Jabatan',         $lpj->employee->position ?? '-'],
            ['Departemen',      $lpj->employee->department?->name ?? '-'],
            ['Nomor Surat Tugas', $lpj->budgetRequest?->surat_tugas_no ?? '-'],
            ['Tgl Surat Tugas', $lpj->budgetRequest?->surat_tugas_date?->format('d/m/Y') ?? '-'],
            ['Kegiatan',        $lpj->budgetRequest?->title ?? '-'],
            ['Tujuan',          $lpj->travelReport?->destination_city ?? '-'],
        ];

        foreach ($infoFields as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", ':');
            $sheet->mergeCells("C{$row}:I{$row}");
            $sheet->setCellValue("C{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setSize(10);
            $row++;
        }

        $row++;

        // ── Tabel item ────────────────────────────────────────────────────────
        $headers = ['No', 'Uraian', 'Satuan', 'Vol', 'Harga Satuan', 'Anggaran', 'Realisasi', 'Selisih', 'Keterangan'];
        foreach ($headers as $i => $h) {
            $col = chr(65 + $i);
            $sheet->setCellValue("{$col}{$row}", $h);
        }

        $headerStyle = [
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3730A3']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C7D2FE']]],
        ];
        $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;

        $no = 1;
        foreach ($lpj->items as $item) {
            $selisih = (float) $item->anggaran - (float) $item->realisasi;

            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValue("B{$row}", $item->uraian);
            $sheet->setCellValue("C{$row}", $item->satuan ?? '-');
            $sheet->setCellValue("D{$row}", (float) $item->volume);
            $sheet->setCellValue("E{$row}", (float) $item->harga_satuan);
            $sheet->setCellValue("F{$row}", (float) $item->anggaran);
            $sheet->setCellValue("G{$row}", (float) $item->realisasi);
            $sheet->setCellValue("H{$row}", $selisih);
            $sheet->setCellValue("I{$row}", $item->keterangan ?? '-');

            foreach (['E', 'F', 'G', 'H'] as $col) {
                $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }

            $bg = $selisih < 0 ? 'FEE2E2' : ($selisih === 0.0 ? 'F0FDF4' : 'F0FDF4');
            $sheet->getStyle("H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($selisih < 0 ? 'FCA5A5' : 'BBF7D0');
            $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
                'font'      => ['size' => 10],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // ── Baris total ───────────────────────────────────────────────────────
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->setCellValue("F{$row}", (float) $lpj->total_anggaran);
        $sheet->setCellValue("G{$row}", (float) $lpj->total_realisasi);
        $sheet->setCellValue("H{$row}", (float) $lpj->sisa);

        foreach (['F', 'G', 'H'] as $col) {
            $sheet->getStyle("{$col}{$row}")->getNumberFormat()->setFormatCode('#,##0');
        }

        $totalStyle = [
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '3730A3']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C7D2FE']]],
        ];
        $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($totalStyle);
        $row += 2;

        // ── Tanda tangan ──────────────────────────────────────────────────────
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", 'Yang Membuat,');
        $sheet->mergeCells("F{$row}:I{$row}");
        $sheet->setCellValue("F{$row}", 'Mengetahui,');
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setSize(10);
        $row += 3;

        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", '('. ($lpj->employee->full_name ?? '') .')');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);

        $approver = $lpj->approvalLogs->where('action', 'approved')->last()?->approver;
        $sheet->mergeCells("F{$row}:I{$row}");
        $sheet->setCellValue("F{$row}", '('. ($approver?->full_name ?? '_______________') .')');
        $sheet->getStyle("F{$row}")->getFont()->setBold(true)->setSize(10);

        // ── Freeze pane & output ──────────────────────────────────────────────
        $sheet->freezePane('A' . ($row - count($lpj->items) - 3));

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
