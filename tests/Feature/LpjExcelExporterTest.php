<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestItem;
use App\Models\Lpj;
use App\Models\LpjDisbursement;
use App\Models\LpjItem;
use App\Services\LpjExcelExporter;
use App\Support\BudgetSignatureSlots;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use Tests\TestCase;

class LpjExcelExporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('companies');
        Schema::dropIfExists('budget_request_participants');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->timestamps();
        });

        Schema::create('budget_request_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();
        });

        Schema::dropIfExists('lpj_disbursements');
        Schema::create('lpj_disbursements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lpj_id')->nullable();
            $table->date('transferred_at');
            $table->decimal('amount', 15, 2);
            $table->string('notes')->nullable();
            $table->string('proof_file')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        DB::table('companies')->insert([
            'name' => 'PT Beacon',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_over_budget_summary_counts_category_overage(): void
    {
        $budgetRequest = new BudgetRequest([
            'title' => 'Perjalanan Jakarta',
            'total_amount' => 200000,
        ]);
        $budgetRequest->setRelation('items', new Collection([
            new BudgetRequestItem([
                'description' => 'Tol Jogja-Jakarta',
                'type' => 'transport',
                'amount' => 150000,
            ]),
            new BudgetRequestItem([
                'description' => 'Uang makan',
                'type' => 'meal',
                'amount' => 50000,
            ]),
        ]));

        $lpj = new Lpj([
            'total_anggaran' => 200000,
            'total_realisasi' => 380000,
        ]);

        $employee = new Employee(['full_name' => 'Zaeni']);
        $employee->setRelation('department', null);

        $lpj->setRelation('employee', $employee);
        $lpj->setRelation('budgetRequest', $budgetRequest);
        $lpj->setRelation('travelReport', null);
        $lpj->setRelation('approvalLogs', new Collection());
        $lpj->setRelation('items', new Collection([
            new LpjItem([
                'uraian' => 'Tol Jogja-Jakarta',
                'kategori' => 'transport',
                'anggaran' => 0,
                'realisasi' => 300000,
                'keterangan' => 'test1',
            ]),
            new LpjItem([
                'uraian' => 'Uang makan',
                'kategori' => 'meal',
                'anggaran' => 0,
                'realisasi' => 80000,
                'keterangan' => 'Reimbursement',
            ]),
        ]));

        $sheet = LpjExcelExporter::build($lpj)->getActiveSheet();

        // Label OVER BUDGET kini diberi keterangan kategori, jadi dicari via prefix.
        $summaryRow = $this->findRowByColumnPrefix($sheet, 'C', 'OVER BUDGET');

        $this->assertNotNull($summaryRow);
        $this->assertSame(-180000.0, (float) $sheet->getCell("E{$summaryRow}")->getValue());
        // Belum ada dana transfer: saldo memakai anggaran sebagai dasar dan labelnya mengatakannya.
        $this->assertSame('SALDO (dari anggaran)', $sheet->getCell('C' . ($summaryRow + 1))->getValue());
        // Saldo = pemasukan 200k - pengeluaran 380k + over 180k = 0 (over di-reimburse terpisah).
        $this->assertSame(0.0, (float) $sheet->getCell('E' . ($summaryRow + 1))->getValue());
    }

    public function test_header_shows_transfer_date_filled_by_finance(): void
    {
        $build = function (?string $transferDate) {
            $lpj = new Lpj(['total_anggaran' => 0, 'total_realisasi' => 0, 'transfer_date' => $transferDate]);
            $lpj->setRelation('employee', new Employee(['full_name' => 'Afif']));
            $lpj->setRelation('budgetRequest', null);
            $lpj->setRelation('travelReport', null);
            $lpj->setRelation('approvalLogs', new Collection());
            $lpj->setRelation('items', new Collection());

            $sheet = LpjExcelExporter::build($lpj)->getActiveSheet();
            $row = $this->findRowByColumnPrefix($sheet, 'A', 'Tanggal Transfer');
            $this->assertNotNull($row);

            return $sheet->getCell("C{$row}")->getValue();
        };

        $this->assertSame(': '.Carbon::parse('2026-10-21')->format('d F Y'), $build('2026-10-21'));
        $this->assertSame(': -', $build(null));
    }

    public function test_only_finance_or_accounting_position_counts_as_finance(): void
    {
        $this->assertTrue(BudgetSignatureSlots::isFinance(new Employee(['position' => 'Finance Staff'])));
        $this->assertTrue(BudgetSignatureSlots::isFinance(new Employee(['position' => 'Accounting & Finance Manager'])));
        $this->assertFalse(BudgetSignatureSlots::isFinance(new Employee(['position' => 'Hardware Manager'])));
    }

    public function test_export_shows_summary_box_and_expense_detail(): void
    {
        $budgetRequest = new BudgetRequest([
            'title' => 'Perjalanan Jakarta',
            'total_amount' => 200000,
        ]);
        $budgetRequest->setRelation('items', new Collection([
            new BudgetRequestItem([
                'id' => 10,
                'description' => 'Tol Jogja-Jakarta',
                'type' => 'transport',
                'amount' => 150000,
            ]),
            new BudgetRequestItem([
                'id' => 11,
                'description' => 'Uang makan',
                'type' => 'meal',
                'amount' => 50000,
            ]),
        ]));

        $lpj = new Lpj([
            'total_anggaran' => 200000,
            'total_realisasi' => 380000,
        ]);

        $employee = new Employee(['full_name' => 'Zaeni']);
        $employee->setRelation('department', null);

        $lpj->setRelation('employee', $employee);
        $lpj->setRelation('budgetRequest', $budgetRequest);
        $lpj->setRelation('travelReport', null);
        $lpj->setRelation('approvalLogs', new Collection());
        $lpj->setRelation('items', new Collection([
            new LpjItem([
                'uraian' => 'Tol Jogja-Jakarta',
                'kategori' => 'transport',
                'anggaran' => 0,
                'realisasi' => 300000,
                'keterangan' => 'test1',
            ]),
            new LpjItem([
                'uraian' => 'Uang makan',
                'kategori' => 'meal',
                'anggaran' => 0,
                'realisasi' => 80000,
                'keterangan' => 'Reimbursement',
            ]),
        ]));

        $sheet = LpjExcelExporter::build($lpj)->getActiveSheet();

        // ── Kotak ringkasan (label di kolom C, nilai di kolom E) ──
        $pemasukanRow = $this->findRowByColumnValue($sheet, 'C', 'TOTAL PEMASUKAN');
        $this->assertNotNull($pemasukanRow);
        $this->assertSame(200000.0, (float) $sheet->getCell("E{$pemasukanRow}")->getValue());

        $pengeluaranRow = $this->findRowByColumnValue($sheet, 'C', 'TOTAL PENGELUARAN');
        $this->assertNotNull($pengeluaranRow);
        $this->assertSame(380000.0, (float) $sheet->getCell("E{$pengeluaranRow}")->getValue());

        $overRow = $this->findRowByColumnPrefix($sheet, 'C', 'OVER BUDGET');
        $this->assertNotNull($overRow);
        $this->assertSame(-180000.0, (float) $sheet->getCell("E{$overRow}")->getValue());

        // ── Rincian PENGELUARAN: uraian item di kolom E, kategori di C, realisasi di D ──
        $tolRow = $this->findRowByColumnValue($sheet, 'E', 'Tol Jogja-Jakarta');
        $mealRow = $this->findRowByColumnValue($sheet, 'E', 'Uang makan');
        $this->assertNotNull($tolRow);
        $this->assertNotNull($mealRow);
        $this->assertSame('Transportasi', $sheet->getCell("C{$tolRow}")->getValue());
        $this->assertSame(300000.0, (float) $sheet->getCell("D{$tolRow}")->getValue());
        $this->assertSame('Makan', $sheet->getCell("C{$mealRow}")->getValue());
        $this->assertSame(80000.0, (float) $sheet->getCell("D{$mealRow}")->getValue());
    }

    public function test_export_lists_finance_transfers_in_their_own_table_without_touching_realisation(): void
    {
        $lpj = new Lpj(['total_anggaran' => 500000, 'total_realisasi' => 120000]);
        $lpj->setRelation('employee', new Employee(['full_name' => 'Afif']));
        $lpj->setRelation('budgetRequest', null);
        $lpj->setRelation('travelReport', null);
        $lpj->setRelation('approvalLogs', new Collection());
        $lpj->setRelation('items', new Collection([
            new LpjItem(['uraian' => 'Bensin', 'kategori' => 'transport', 'anggaran' => 0, 'realisasi' => 120000]),
        ]));

        $maritza = new Employee(['full_name' => 'Maritza']);
        $first = new LpjDisbursement(['transferred_at' => '2026-11-10', 'amount' => 300000, 'notes' => 'Transfer awal']);
        $first->setRelation('recorder', $maritza);
        $second = new LpjDisbursement(['transferred_at' => '2026-11-12', 'amount' => 200000, 'notes' => 'Tambahan']);
        $second->setRelation('recorder', $maritza);
        $lpj->setRelation('disbursements', new Collection([$first, $second]));

        $sheet = LpjExcelExporter::build($lpj)->getActiveSheet();

        $this->assertNotNull($this->findRowByColumnValue($sheet, 'A', 'DANA DITRANSFER FINANCE'));
        $this->assertNotNull($this->findRowByColumnValue($sheet, 'H', 'BUKTI TRANSFER'));

        $awal = $this->findRowByColumnValue($sheet, 'E', 'Transfer awal');
        $tambahan = $this->findRowByColumnValue($sheet, 'E', 'Tambahan');
        $this->assertNotNull($awal);
        $this->assertNotNull($tambahan);
        $this->assertSame('10/11/2026', $sheet->getCell("B{$awal}")->getValue());
        $this->assertSame('Maritza', $sheet->getCell("C{$awal}")->getValue());
        $this->assertSame(300000.0, (float) $sheet->getCell("D{$awal}")->getValue());
        $this->assertSame(200000.0, (float) $sheet->getCell("D{$tambahan}")->getValue());

        $totalRow = $this->findRowByColumnValue($sheet, 'A', 'Total Ditransfer');
        $this->assertNotNull($totalRow);
        $this->assertSame(500000.0, (float) $sheet->getCell("D{$totalRow}")->getValue());

        // Realisasi pengaju tetap 120.000: dana transfer tidak ikut terhitung sebagai pengeluaran.
        $pengeluaranRow = $this->findRowByColumnValue($sheet, 'C', 'TOTAL PENGELUARAN');
        $this->assertSame(120000.0, (float) $sheet->getCell("E{$pengeluaranRow}")->getValue());
    }

    public function test_export_without_transfers_still_renders_an_empty_table(): void
    {
        $lpj = new Lpj(['total_anggaran' => 0, 'total_realisasi' => 0]);
        $lpj->setRelation('employee', new Employee(['full_name' => 'Afif']));
        $lpj->setRelation('budgetRequest', null);
        $lpj->setRelation('travelReport', null);
        $lpj->setRelation('approvalLogs', new Collection());
        $lpj->setRelation('items', new Collection());
        $lpj->setRelation('disbursements', new Collection());

        $sheet = LpjExcelExporter::build($lpj)->getActiveSheet();

        $totalRow = $this->findRowByColumnValue($sheet, 'A', 'Total Ditransfer');
        $this->assertNotNull($totalRow);
        $this->assertSame(0.0, (float) $sheet->getCell("D{$totalRow}")->getValue());
    }

    public function test_transfer_proof_is_embedded_for_images_and_linked_for_other_files(): void
    {
        if (! function_exists('imagecreatefromwebp') || ! function_exists('imagewebp')) {
            $this->markTestSkipped('GD tanpa dukungan WEBP.');
        }

        Storage::fake('public');
        $image = imagecreatetruecolor(40, 30);
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put('lpj-transfer-bukti/a.jpg', ob_get_clean());
        ob_start();
        imagewebp($image);
        Storage::disk('public')->put('lpj-transfer-bukti/b.webp', ob_get_clean());
        Storage::disk('public')->put('lpj-transfer-bukti/c.pdf', '%PDF-1.4 contoh');

        $lpj = new Lpj(['total_anggaran' => 0, 'total_realisasi' => 0]);
        $lpj->setRelation('employee', new Employee(['full_name' => 'Afif']));
        $lpj->setRelation('budgetRequest', null);
        $lpj->setRelation('travelReport', null);
        $lpj->setRelation('approvalLogs', new Collection());
        $lpj->setRelation('items', new Collection());
        $rows = [];
        foreach (['a.jpg', 'b.webp', 'c.pdf', 'hilang.heic'] as $i => $name) {
            $d = new LpjDisbursement(['transferred_at' => '2026-11-1'.$i, 'amount' => 1000, 'notes' => "baris {$name}", 'proof_file' => 'lpj-transfer-bukti/'.$name]);
            $d->setRelation('recorder', new Employee(['full_name' => 'Maritza']));
            $rows[] = $d;
        }
        $lpj->setRelation('disbursements', new Collection($rows));

        $spreadsheet = LpjExcelExporter::build($lpj);
        $sheet = $spreadsheet->getActiveSheet();
        $rowOf = fn (string $name) => $this->findRowByColumnValue($sheet, 'E', "baris {$name}");

        $drawings = collect($sheet->getDrawingCollection());
        $at = fn (string $name) => $drawings->first(fn ($d) => $d->getCoordinates() === 'H'.$rowOf($name));

        $this->assertInstanceOf(Drawing::class, $at('a.jpg'));
        $this->assertInstanceOf(MemoryDrawing::class, $at('b.webp'));
        $this->assertCount(2, $drawings);

        // File xlsx hasil simpan memuat dua gambar: JPG asli dan WEBP yang sudah menjadi PNG.
        $path = tempnam(sys_get_temp_dir(), 'lpj').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);
        $zip = new \ZipArchive();
        $zip->open($path);
        $media = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'xl/media/')) {
                $media[] = strtolower(pathinfo($zip->getNameIndex($i), PATHINFO_EXTENSION));
            }
        }
        $zip->close();
        @unlink($path);
        sort($media);
        $this->assertSame(['jpg', 'png'], $media);

        foreach (['c.pdf' => 'PDF', 'hilang.heic' => 'HEIC'] as $name => $label) {
            $cell = 'H'.$rowOf($name);
            $this->assertSame("Lihat bukti ({$label})", $sheet->getCell($cell)->getValue());
            $this->assertStringEndsWith('/storage/lpj-transfer-bukti/'.$name, $sheet->getCell($cell)->getHyperlink()->getUrl());
        }
    }

    public function test_saldo_uses_money_transferred_by_finance_and_still_adds_back_over_budget(): void
    {
        $build = function (array $transfers) {
            $budgetRequest = new BudgetRequest(['title' => 'Perjalanan', 'total_amount' => 200000]);
            $budgetRequest->setRelation('items', new Collection([
                new BudgetRequestItem(['id' => 10, 'description' => 'Tol', 'type' => 'transport', 'amount' => 150000]),
                new BudgetRequestItem(['id' => 11, 'description' => 'Makan', 'type' => 'meal', 'amount' => 50000]),
            ]));

            $lpj = new Lpj(['total_anggaran' => 200000, 'total_realisasi' => 380000]);
            $lpj->setRelation('employee', new Employee(['full_name' => 'Zaeni']));
            $lpj->setRelation('budgetRequest', $budgetRequest);
            $lpj->setRelation('travelReport', null);
            $lpj->setRelation('approvalLogs', new Collection());
            // Tol 300k (over 150k dari anggaran 150k), Makan 80k (over 30k): over 180k, pengeluaran 380k.
            $lpj->setRelation('items', new Collection([
                new LpjItem(['uraian' => 'Tol', 'kategori' => 'transport', 'anggaran' => 0, 'realisasi' => 300000]),
                new LpjItem(['uraian' => 'Makan', 'kategori' => 'meal', 'anggaran' => 0, 'realisasi' => 80000]),
            ]));
            $lpj->setRelation('disbursements', new Collection(array_map(
                fn ($amount) => new LpjDisbursement(['transferred_at' => '2026-11-10', 'amount' => $amount]),
                $transfers
            )));

            return $lpj;
        };

        // Anggaran 200k, tapi finance hanya mentransfer 120k + 100k = 220k.
        $lpj = $build([120000, 100000]);
        $sum = \App\Support\LpjSummary::for($lpj);
        $this->assertTrue($sum['has_transfer']);
        $this->assertSame(220000.0, $sum['total_ditransfer']);
        $this->assertSame(200000.0, $sum['total_pemasukan']);
        // Saldo = ditransfer 220k - pengeluaran 380k + over 180k = 20k.
        $this->assertSame(20000.0, $sum['saldo']);

        $sheet = LpjExcelExporter::build($lpj)->getActiveSheet();
        $ditransfer = $this->findRowByColumnValue($sheet, 'C', 'TOTAL DITRANSFER');
        $this->assertNotNull($ditransfer);
        $this->assertSame(220000.0, (float) $sheet->getCell("E{$ditransfer}")->getValue());
        $this->assertSame('TOTAL PENGELUARAN', $sheet->getCell('C'.($ditransfer + 1))->getValue());
        $saldoRow = $this->findRowByColumnValue($sheet, 'C', 'SALDO');
        $this->assertNotNull($saldoRow);
        $this->assertSame(20000.0, (float) $sheet->getCell("E{$saldoRow}")->getValue());

        // Tanpa dana transfer: baris menulis "Belum diisi" dan saldo jatuh ke anggaran (200k - 380k + 180k = 0).
        $sheet = LpjExcelExporter::build($build([]))->getActiveSheet();
        $ditransfer = $this->findRowByColumnValue($sheet, 'C', 'TOTAL DITRANSFER');
        $this->assertSame('Belum diisi', $sheet->getCell("E{$ditransfer}")->getValue());
        $saldoRow = $this->findRowByColumnValue($sheet, 'C', 'SALDO (dari anggaran)');
        $this->assertNotNull($saldoRow);
        $this->assertSame(0.0, (float) $sheet->getCell("E{$saldoRow}")->getValue());
    }

    private function findRowByColumnValue($sheet, string $column, string $value): ?int
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($sheet->getCell("{$column}{$row}")->getValue() === $value) {
                return $row;
            }
        }

        return null;
    }

    private function findRowByColumnPrefix($sheet, string $column, string $prefix): ?int
    {
        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            $cell = $sheet->getCell("{$column}{$row}")->getValue();
            if (is_string($cell) && str_starts_with($cell, $prefix)) {
                return $row;
            }
        }

        return null;
    }
}
