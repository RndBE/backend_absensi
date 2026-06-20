<?php

namespace Tests\Feature;

use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayslipImportTest extends TestCase
{
    private array $tempPaths = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'payroll_run_details',
            'payroll_runs',
            'payroll_components',
            'employees',
            'companies',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('category')->default('fixed');
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_auto')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->string('status')->default('draft');
            $table->decimal('total_earning', 18, 2)->default(0);
            $table->decimal('total_deduction', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_run_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payroll_run_id');
            $table->unsignedBigInteger('employee_id');
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_earning', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->json('components')->nullable();
            $table->boolean('is_manual_edited')->default(false);
            $table->timestamps();
        });

        $this->seedPayslipImportFixtures();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        parent::tearDown();
    }

    public function test_admin_can_import_monthly_payslips_with_registered_components(): void
    {
        $file = $this->uploadedCsv(implode("\n", [
            'employee_code,basic_salary,Tunjangan Makan,Lembur,BPJS Kesehatan,PPh 21,Pinjaman',
            'EMP001,5000000,300000,,50000,250000,500000',
            'EMP002,4500000,,100000,45000,0,',
        ]));

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.payslips.import'), [
                'period' => '2026-06',
                'payslip_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import payslip selesai: 2 berhasil, 0 dilewati.');

        $run = PayrollRun::where('period', '2026-06')->first();
        $this->assertSame('published', $run->status);
        $this->assertEquals(9900000.0, (float) $run->total_earning);
        $this->assertEquals(845000.0, (float) $run->total_deduction);
        $this->assertEquals(9055000.0, (float) $run->total_net);

        $firstDetail = PayrollRunDetail::where('employee_id', 2)->first();
        $secondDetail = PayrollRunDetail::where('employee_id', 3)->first();

        $this->assertEquals(5300000.0, (float) $firstDetail->total_earning);
        $this->assertEquals(800000.0, (float) $firstDetail->total_deduction);
        $this->assertEquals(4500000.0, (float) $firstDetail->net_salary);
        $this->assertTrue((bool) $firstDetail->is_manual_edited);

        $firstComponents = collect($firstDetail->components);
        $this->assertSame(5, $firstComponents->count());
        $this->assertEquals(0.0, (float) $firstComponents->firstWhere('name', 'Lembur')['amount']);
        $this->assertSame('deduction', $firstComponents->firstWhere('name', 'Pinjaman')['type']);

        $secondComponents = collect($secondDetail->components);
        $this->assertEquals(0.0, (float) $secondComponents->firstWhere('name', 'Tunjangan Makan')['amount']);
        $this->assertEquals(0.0, (float) $secondComponents->firstWhere('name', 'Pinjaman')['amount']);
    }

    public function test_payslip_import_keeps_unregistered_component_columns_as_manual_components(): void
    {
        $file = $this->uploadedCsv(implode("\n", [
            'employee_code,basic_salary,Tunjangan Tidak Ada',
            'EMP001,5000000,100000',
        ]));

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.payslips.import'), [
                'period' => '2026-06',
                'payslip_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import payslip selesai: 1 berhasil, 0 dilewati.');

        $detail = PayrollRunDetail::where('employee_id', 2)->first();
        $manualComponent = collect($detail->components)->firstWhere('name', 'Tunjangan Tidak Ada');

        $this->assertNull($manualComponent['id']);
        $this->assertSame('earning', $manualComponent['type']);
        $this->assertSame('manual_import', $manualComponent['category']);
        $this->assertEquals(100000.0, (float) $manualComponent['amount']);
        $this->assertEquals(5100000.0, (float) $detail->total_earning);
        $this->assertEquals(0.0, (float) $detail->total_deduction);
    }

    public function test_admin_can_import_salary_report_xlsx_with_stacked_headers(): void
    {
        $file = $this->uploadedXlsx([
            ['PT Arta Teknologi Comunindo'],
            ['Salary Report 6 2026'],
            ['Employee ID', 'Full Name', 'Job Position', 'Organization', 'Basic Salary', 'Allowance', '', '', 'Total Allowance', 'Deduction', '', '', 'Total Deduction', 'Benefit', 'Take Home Pay'],
            ['', '', '', '', '', 'Tunjangan Makan', 'Overtime', 'Tunjangan Profesi', '', 'BPJS K Employee', 'Pinjaman', 'Denda LHP', '', 'Rate BPJS Ketenagakerjaan', ''],
            ['EMP001', 'Employee One', 'Software', 'Software', 5000000, 300000, 100000, 200000, 600000, 50000, 250000, 15000, 315000, 370000, 5285000],
            ['EMP002', 'Employee Two', 'Hardware', 'Hardware', 4500000, '', 100000, 0, 100000, 45000, '', 0, 45000, 350000, 4555000],
            ['GRAND TOTAL', '', '', '', 9500000, 300000, 200000, 200000, 700000, 95000, 250000, 15000, 360000, 720000, 9840000],
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 1])
            ->post(route('admin.payslips.import'), [
                'period' => '2026-06',
                'payslip_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import payslip selesai: 2 berhasil, 0 dilewati.');

        $run = PayrollRun::where('period', '2026-06')->first();
        $this->assertSame('published', $run->status);
        $this->assertEquals(10200000.0, (float) $run->total_earning);
        $this->assertEquals(360000.0, (float) $run->total_deduction);
        $this->assertEquals(9840000.0, (float) $run->total_net);

        $firstDetail = PayrollRunDetail::where('employee_id', 2)->first();
        $firstComponents = collect($firstDetail->components);

        $this->assertEquals(5600000.0, (float) $firstDetail->total_earning);
        $this->assertEquals(315000.0, (float) $firstDetail->total_deduction);
        $this->assertEquals(100000.0, (float) $firstComponents->firstWhere('name', 'Lembur')['amount']);
        $this->assertEquals(50000.0, (float) $firstComponents->firstWhere('name', 'BPJS Kesehatan')['amount']);
        $this->assertEquals(250000.0, (float) $firstComponents->firstWhere('name', 'Pinjaman')['amount']);

        $manualEarning = $firstComponents->firstWhere('name', 'Tunjangan Profesi');
        $manualDeduction = $firstComponents->firstWhere('name', 'Denda LHP');
        $manualInfo = $firstComponents->firstWhere('name', 'Rate BPJS Ketenagakerjaan');

        $this->assertNull($manualEarning['id']);
        $this->assertSame('earning', $manualEarning['type']);
        $this->assertEquals(200000.0, (float) $manualEarning['amount']);
        $this->assertNull($manualDeduction['id']);
        $this->assertSame('deduction', $manualDeduction['type']);
        $this->assertEquals(15000.0, (float) $manualDeduction['amount']);
        $this->assertNull($manualInfo['id']);
        $this->assertSame('info', $manualInfo['type']);
        $this->assertEquals(370000.0, (float) $manualInfo['amount']);
    }

    public function test_payslip_index_exposes_component_import_form(): void
    {
        $view = file_get_contents(resource_path('views/admin/payslips/index.blade.php'));

        $this->assertStringContainsString("route('admin.payslips.import')", $view);
        $this->assertStringContainsString('name="period"', $view);
        $this->assertStringContainsString('name="payslip_file"', $view);
        $this->assertStringContainsString('employee_code,basic_salary,Tunjangan Makan,Lembur,BPJS Kesehatan', $view);
    }

    private function uploadedCsv(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'payslip-import-');
        file_put_contents($path, $contents);
        $this->tempPaths[] = $path;

        return new UploadedFile($path, 'payslip-import.csv', 'text/csv', null, true);
    }

    private function uploadedXlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'payslip-import-');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="PAYSLIP" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($rows));
        $zip->close();
        $this->tempPaths[] = $path;

        return new UploadedFile($path, 'salary-report.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function worksheetXml(array $rows): string
    {
        $xmlRows = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];
            foreach ($row as $columnIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $cellRef = $this->excelColumnName($columnIndex + 1).($rowIndex + 1);
                if (is_numeric($value)) {
                    $cells[] = '<c r="'.$cellRef.'"><v>'.$value.'</v></c>';
                    continue;
                }

                $cells[] = '<c r="'.$cellRef.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
            }
            $xmlRows[] = '<row r="'.($rowIndex + 1).'">'.implode('', $cells).'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';
    }

    private function excelColumnName(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function seedPayslipImportFixtures(): void
    {
        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Import Payslip',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 1,
                'employee_code' => 'ADM001',
                'company_id' => 1,
                'full_name' => 'Admin Payroll',
                'email' => 'admin@example.test',
                'password' => 'secret',
                'role' => 'payroll_admin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'employee_code' => 'EMP001',
                'company_id' => 1,
                'full_name' => 'Employee One',
                'email' => 'employee-one@example.test',
                'password' => 'secret',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'employee_code' => 'EMP002',
                'company_id' => 1,
                'full_name' => 'Employee Two',
                'email' => 'employee-two@example.test',
                'password' => 'secret',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        foreach ([
            ['id' => 1, 'name' => 'Tunjangan Makan', 'type' => 'earning'],
            ['id' => 2, 'name' => 'Lembur', 'type' => 'earning'],
            ['id' => 3, 'name' => 'BPJS Kesehatan', 'type' => 'deduction'],
            ['id' => 4, 'name' => 'PPh 21', 'type' => 'deduction'],
            ['id' => 5, 'name' => 'Pinjaman', 'type' => 'deduction'],
        ] as $component) {
            DB::table('payroll_components')->insert([
                'id' => $component['id'],
                'name' => $component['name'],
                'type' => $component['type'],
                'category' => 'recurring',
                'default_amount' => 0,
                'is_taxable' => $component['type'] === 'earning',
                'is_auto' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
