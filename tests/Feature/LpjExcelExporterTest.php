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
        $summaryRow = null;

        for ($row = 1; $row <= $sheet->getHighestRow(); $row++) {
            if ($sheet->getCell("C{$row}")->getValue() === 'OVER BUDGET') {
                $summaryRow = $row;
                break;
            }
        }

        $this->assertNotNull($summaryRow);
        $this->assertSame(-180000.0, (float) $sheet->getCell("E{$summaryRow}")->getValue());
        $this->assertSame('SALDO', $sheet->getCell('C' . ($summaryRow + 1))->getValue());
        $this->assertSame(-180000.0, (float) $sheet->getCell('E' . ($summaryRow + 1))->getValue());
    }

    public function test_export_separates_income_budget_and_realization_expense(): void
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

        $tolIncomeRow = $this->findRowByColumnValue($sheet, 'B', 'Tol Jogja-Jakarta');
        $mealIncomeRow = $this->findRowByColumnValue($sheet, 'B', 'Uang makan');

        $this->assertNotNull($tolIncomeRow);
        $this->assertNotNull($mealIncomeRow);
        $this->assertSame('Transportasi', $sheet->getCell("D{$tolIncomeRow}")->getValue());
        $this->assertSame(150000.0, (float) $sheet->getCell("E{$tolIncomeRow}")->getValue());
        $this->assertSame('Makan', $sheet->getCell("D{$mealIncomeRow}")->getValue());
        $this->assertSame(50000.0, (float) $sheet->getCell("E{$mealIncomeRow}")->getValue());

        $tolExpenseRow = $this->findRowByColumnValue($sheet, 'D', 'Tol Jogja-Jakarta');
        $mealExpenseRow = $this->findRowByColumnValue($sheet, 'D', 'Uang makan');

        $this->assertNotNull($tolExpenseRow);
        $this->assertNotNull($mealExpenseRow);
        $this->assertSame('Transportasi', $sheet->getCell("C{$tolExpenseRow}")->getValue());
        $this->assertSame(300000.0, (float) $sheet->getCell("E{$tolExpenseRow}")->getValue());
        $this->assertSame('Makan', $sheet->getCell("C{$mealExpenseRow}")->getValue());
        $this->assertSame(80000.0, (float) $sheet->getCell("E{$mealExpenseRow}")->getValue());

        $totalRow = $this->findRowByColumnValue($sheet, 'A', 'TOTAL PEMASUKAN');
        $this->assertNotNull($totalRow);
        $this->assertSame(200000.0, (float) $sheet->getCell("E{$totalRow}")->getValue());

        $summaryRow = $this->findRowByColumnValue($sheet, 'C', 'OVER BUDGET');
        $this->assertNotNull($summaryRow);
        $this->assertSame(-180000.0, (float) $sheet->getCell("E{$summaryRow}")->getValue());
        $this->assertSame(-180000.0, (float) $sheet->getCell('E' . ($summaryRow + 1))->getValue());
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
}
