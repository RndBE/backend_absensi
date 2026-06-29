<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\BudgetRequest;
use App\Models\BudgetRequestItem;
use App\Models\Lpj;
use App\Models\LpjItem;
use App\Services\LpjExcelExporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $this->assertSame('SALDO', $sheet->getCell('C' . ($summaryRow + 1))->getValue());
        // Saldo = pemasukan 200k - pengeluaran 380k + over 180k = 0 (over di-reimburse terpisah).
        $this->assertSame(0.0, (float) $sheet->getCell('E' . ($summaryRow + 1))->getValue());
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
