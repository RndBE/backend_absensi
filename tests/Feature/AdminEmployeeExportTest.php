<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\EmployeeController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

class AdminEmployeeExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['employees', 'departments', 'work_schedules'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('work_schedule_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('employee_code')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->integer('job_level')->nullable();
            $table->string('employment_status')->default('employee');
            $table->string('role')->default('employee');
            $table->date('join_date')->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->string('nik')->nullable();
            $table->string('gender')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('religion')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('blood_type')->nullable();
            $table->text('ktp_address')->nullable();
            $table->text('residential_address')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('ptkp')->nullable();
            $table->string('npwp_15')->nullable();
            $table->string('npwp_16')->nullable();
            $table->string('bpjs_tk')->nullable();
            $table->string('bpjs_kesehatan')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('internship_institution')->nullable();
            $table->string('internship_supervisor')->nullable();
            $table->string('internship_field_supervisor')->nullable();
            $table->text('internship_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        DB::table('departments')->insert([
            ['id' => 10, 'company_id' => 1, 'name' => 'Software Division', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'company_id' => 1, 'name' => 'Finance Division', 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('work_schedules')->insert([
            'id' => 5,
            'company_id' => 1,
            'name' => 'Office Regular',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([
            [
                'id' => 1,
                'company_id' => 1,
                'employee_code' => 'ADM001',
                'email' => 'admin@example.test',
                'password' => 'secret',
                'full_name' => 'Admin User',
                'role' => 'admin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'department_id' => 10,
                'work_schedule_id' => 5,
                'manager_id' => 3,
                'employee_code' => '004/SOFTW/XII/2025',
                'email' => 'shandy@example.test',
                'password' => 'secret',
                'full_name' => 'Shandy Bagus Ferdiansyah',
                'phone' => '081215661025',
                'position' => 'Software Division',
                'job_level' => 4,
                'employment_status' => 'contract',
                'role' => 'employee',
                'join_date' => '2025-12-22',
                'contract_end_date' => '2026-09-23',
                'nik' => '3308102606030006',
                'gender' => 'male',
                'birth_place' => 'Karawang',
                'birth_date' => '2003-06-26',
                'religion' => 'Islam',
                'marital_status' => 'single',
                'ktp_address' => 'Klarisan RT 001/RW 004',
                'residential_address' => 'Juwangen RT 007/RW 002',
                'ptkp' => 'TK/0',
                'npwp_16' => '3308102606030006',
                'bpjs_tk' => '26021816447',
                'bpjs_kesehatan' => '0001601571611',
                'bank_name' => 'BCA',
                'bank_account' => '1222340823',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'company_id' => 1,
                'department_id' => 20,
                'employee_code' => 'MGR001',
                'email' => 'manager@example.test',
                'password' => 'secret',
                'full_name' => 'Nofiyanto',
                'position' => 'Manager',
                'role' => 'manager',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ] as $employee) {
            DB::table('employees')->insert($employee);
        }

        session(['admin_id' => 1]);
    }

    public function test_admin_can_export_employee_detail_data_to_excel(): void
    {
        $response = (new EmployeeController())->export(Request::create('/admin/employees/export', 'GET', [
            'department_id' => 10,
        ]));

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString('filename=karyawan_', $response->headers->get('Content-Disposition') ?? '');

        $sheet = $this->worksheetXml($response);

        $this->assertStringContainsString('Kode', $sheet);
        $this->assertStringContainsString('Nama', $sheet);
        $this->assertStringContainsString('NIK KTP', $sheet);
        $this->assertStringContainsString('BPJS Kesehatan', $sheet);
        $this->assertStringContainsString('No. Rekening', $sheet);
        $this->assertStringContainsString('Shandy Bagus Ferdiansyah', $sheet);
        $this->assertStringContainsString('004/SOFTW/XII/2025', $sheet);
        $this->assertStringContainsString('Software Division', $sheet);
        $this->assertStringContainsString('Kontrak', $sheet);
        $this->assertStringContainsString('Laki-laki', $sheet);
        $this->assertStringContainsString('Nofiyanto', $sheet);
        $this->assertStringNotContainsString('Admin User', $sheet);
    }

    private function worksheetXml(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'employee_export_');
        file_put_contents($path, $content);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($sheet);

        return $sheet;
    }
}
