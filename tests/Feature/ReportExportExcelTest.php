<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ReportController;
use App\Support\SimpleXlsxExporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

class ReportExportExcelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'payroll_run_details',
            'payroll_runs',
            'overtime_requests',
            'leave_requests',
            'leave_types',
            'attendances',
            'employees',
            'departments',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('position')->nullable();
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2)->default(0);
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('overtime_type')->default('workday');
            $table->integer('pre_shift_duration')->default(0);
            $table->integer('post_shift_duration')->default(0);
            $table->integer('total_duration')->default(0);
            $table->integer('break_duration')->default(0);
            $table->integer('approved_break')->nullable();
            $table->integer('actual_duration')->nullable();
            $table->time('actual_clock_out')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->string('status')->default('draft');
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
            $table->timestamps();
        });

        \DB::table('employees')->insert([
            'id' => 1,
            'company_id' => 1,
            'employee_code' => 'ADM',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'full_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['admin_id' => 1]);
    }

    public function test_report_exports_download_excel_files(): void
    {
        foreach ($this->exportRequests() as $method => [$path, $params, $filename]) {
            $response = (new ReportController())->{$method}(Request::create($path, 'GET', $params));

            $this->assertInstanceOf(StreamedResponse::class, $response, "{$method} should stream a download.");
            $this->assertSame(
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                $response->headers->get('Content-Type'),
                "{$method} should use the XLSX content type."
            );
            $this->assertStringContainsString(
                "filename={$filename}",
                $response->headers->get('Content-Disposition') ?? '',
                "{$method} should download {$filename}."
            );
        }
    }

    public function test_report_export_buttons_say_excel_not_csv(): void
    {
        foreach (['attendance', 'leave', 'overtime', 'payroll'] as $view) {
            $contents = file_get_contents(resource_path("views/admin/reports/{$view}.blade.php"));

            $this->assertStringContainsString('Export Excel', $contents);
            $this->assertStringNotContainsString('Export CSV', $contents);
        }
    }

    public function test_xlsx_exporter_generates_a_valid_excel_package(): void
    {
        $xlsx = SimpleXlsxExporter::make(['Kode', 'Nama'], [['EMP001', 'Budi']], 'Laporan');
        $path = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($path, $xlsx);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        $this->assertNotFalse($zip->locateName('xl/workbook.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('EMP001', $sheet);
        $this->assertStringContainsString('Budi', $sheet);
    }

    private function exportRequests(): array
    {
        return [
            'exportAttendance' => ['/admin/reports/attendance/export', ['month' => '2026-06'], 'attendance_2026-06.xlsx'],
            'exportLeave' => ['/admin/reports/leave/export', ['year' => '2026'], 'leave_2026.xlsx'],
            'exportOvertime' => ['/admin/reports/overtime/export', ['month' => '2026-06'], 'overtime_2026-06.xlsx'],
            'exportPayroll' => ['/admin/reports/payroll/export', ['period' => '2026-06'], 'payroll_2026-06.xlsx'],
        ];
    }
}
