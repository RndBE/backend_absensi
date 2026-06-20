<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\OvertimeRequest;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttendanceRecapImportTest extends TestCase
{
    private string $csvPath;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-18 09:00:00');

        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('schedule_assignments');
        Schema::dropIfExists('schedule_template_days');
        Schema::dropIfExists('schedule_templates');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('shifts');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('employees');

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('schedule_template_id')->nullable();
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->string('photo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('color')->default('#2563eb');
            $table->boolean('is_off')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->date('date');
            $table->string('name');
            $table->boolean('is_national')->default(true);
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present');
            $table->string('review_status')->nullable();
            $table->boolean('is_late')->default(false);
            $table->boolean('is_remote')->default(false);
            $table->text('remote_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->string('overtime_type')->default('workday');
            $table->time('planned_start')->nullable();
            $table->time('planned_end')->nullable();
            $table->integer('pre_shift_duration')->default(0);
            $table->integer('pre_shift_break')->default(0);
            $table->integer('post_shift_duration')->default(0);
            $table->integer('post_shift_break')->default(0);
            $table->integer('break_duration')->default(0);
            $table->integer('total_duration')->default(0);
            $table->integer('approved_duration')->nullable();
            $table->integer('approved_break')->nullable();
            $table->integer('actual_duration')->nullable();
            $table->time('shift_end_time')->nullable();
            $table->time('actual_clock_in')->nullable();
            $table->time('actual_clock_out')->nullable();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->unsignedInteger('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('schedule_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('schedule_template_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedBigInteger('shift_id');
            $table->timestamps();
        });

        Schema::create('schedule_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shift_id');
            $table->date('date');
            $table->text('notes')->nullable();
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
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $this->seedAttendanceImportFixtures();
    }

    protected function tearDown(): void
    {
        if (isset($this->csvPath) && is_file($this->csvPath)) {
            unlink($this->csvPath);
        }

        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_import_manual_excel_csv_into_attendance_recap(): void
    {
        $file = $this->uploadedCsv(implode("\n", [
            'employee_code,date,clock_in,clock_out',
            'EMP001,2026-06-18,08:15,17:05',
            'EMP002,18/06/2026,07:55,17:00',
        ]));

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->post(route('admin.attendance-recap.import'), [
                'attendance_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import presensi selesai: 2 berhasil, 0 dilewati.');

        $lateAttendance = Attendance::where('employee_id', 1)->whereDate('date', '2026-06-18')->first();
        $onTimeAttendance = Attendance::where('employee_id', 2)->whereDate('date', '2026-06-18')->first();

        $this->assertSame('08:15:00', $lateAttendance->clock_in);
        $this->assertSame('17:05:00', $lateAttendance->clock_out);
        $this->assertSame('present', $lateAttendance->status);
        $this->assertTrue((bool) $lateAttendance->is_late);

        $this->assertSame('07:55:00', $onTimeAttendance->clock_in);
        $this->assertSame('17:00:00', $onTimeAttendance->clock_out);
        $this->assertFalse((bool) $onTimeAttendance->is_late);
    }

    public function test_import_skips_unknown_employee_codes_and_reports_warning(): void
    {
        $file = $this->uploadedCsv(implode("\n", [
            'employee_code,date,clock_in,clock_out',
            'UNKNOWN,2026-06-18,08:00,17:00',
        ]));

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->post(route('admin.attendance-recap.import'), [
                'attendance_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Import presensi selesai: 0 berhasil, 1 dilewati. Baris 2: kode karyawan UNKNOWN tidak ditemukan.');
        $this->assertSame(0, Attendance::count());
    }

    public function test_admin_can_import_attendence_sheet_from_salary_report_xlsx(): void
    {
        $file = $this->uploadedAttendanceReportXlsx();

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->post(route('admin.attendance-recap.import'), [
                'attendance_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import presensi selesai: 2 berhasil, 1 dilewati. Baris 4: clock in/out kosong atau tidak valid.');

        $firstAttendance = Attendance::where('employee_id', 1)->whereDate('date', '2026-06-18')->first();
        $secondAttendance = Attendance::where('employee_id', 2)->whereDate('date', '2026-06-19')->first();

        $this->assertSame('08:30:00', $firstAttendance->clock_in);
        $this->assertSame('17:45:00', $firstAttendance->clock_out);
        $this->assertSame('07:55:00', $secondAttendance->clock_in);
        $this->assertSame('17:00:00', $secondAttendance->clock_out);
    }

    public function test_imported_national_holiday_attendance_is_not_marked_late(): void
    {
        DB::table('holidays')->insert([
            'company_id' => 1,
            'date' => '2026-06-18',
            'name' => 'Libur Nasional Test',
            'is_national' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = $this->uploadedCsv(implode("\n", [
            'employee_code,date,clock_in,clock_out',
            'EMP001,2026-06-18,09:30,12:00',
        ]));

        $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->post(route('admin.attendance-recap.import'), [
                'attendance_file' => $file,
            ])
            ->assertRedirect();

        $attendance = Attendance::where('employee_id', 1)->whereDate('date', '2026-06-18')->first();

        $this->assertSame('09:30:00', $attendance->clock_in);
        $this->assertFalse((bool) $attendance->is_late);
    }

    public function test_imported_off_shift_attendance_is_not_marked_late(): void
    {
        $file = $this->uploadedCsv(implode("\n", [
            'employee_code,date,clock_in,clock_out,shift',
            'EMP001,2026-06-18,09:30,12:00,dayoff',
        ]));

        $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->post(route('admin.attendance-recap.import'), [
                'attendance_file' => $file,
            ])
            ->assertRedirect();

        $attendance = Attendance::where('employee_id', 1)->whereDate('date', '2026-06-18')->first();

        $this->assertSame('09:30:00', $attendance->clock_in);
        $this->assertFalse((bool) $attendance->is_late);
    }

    public function test_admin_can_import_overtime_columns_from_attendence_sheet(): void
    {
        $file = $this->uploadedAttendanceReportXlsx(withOvertime: true);

        $response = $this->withoutMiddleware()
            ->withSession(['admin_id' => 99])
            ->post(route('admin.attendance-recap.import'), [
                'attendance_file' => $file,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Import presensi selesai: 3 berhasil, 0 dilewati.');

        $workdayOvertime = OvertimeRequest::where('employee_id', 1)->whereDate('date', '2026-06-18')->first();
        $holidayOvertime = OvertimeRequest::where('employee_id', 2)->whereDate('date', '2026-06-20')->first();

        $this->assertNotNull($workdayOvertime);
        $this->assertSame('workday', $workdayOvertime->overtime_type);
        $this->assertSame('18:00:00', $workdayOvertime->actual_clock_in);
        $this->assertSame('20:30:00', $workdayOvertime->actual_clock_out);
        $this->assertSame(150, $workdayOvertime->total_duration);
        $this->assertSame(30, $workdayOvertime->break_duration);
        $this->assertSame(120, $workdayOvertime->actual_duration);
        $this->assertSame(150, $workdayOvertime->post_shift_duration);
        $this->assertSame('approved', $workdayOvertime->status);

        $this->assertNotNull($holidayOvertime);
        $this->assertSame('holiday', $holidayOvertime->overtime_type);
        $this->assertSame('09:00:00', $holidayOvertime->planned_start);
        $this->assertSame('12:00:00', $holidayOvertime->planned_end);
        $this->assertSame(180, $holidayOvertime->total_duration);
        $this->assertSame(180, $holidayOvertime->actual_duration);
        $this->assertSame('approved', $holidayOvertime->status);
    }

    public function test_attendance_recap_view_exposes_manual_excel_import_form(): void
    {
        $view = file_get_contents(resource_path('views/admin/attendance-recap/index.blade.php'));

        $this->assertStringContainsString("route('admin.attendance-recap.import')", $view);
        $this->assertStringContainsString('enctype="multipart/form-data"', $view);
        $this->assertStringContainsString('name="attendance_file"', $view);
        $this->assertStringContainsString('employee_code,date,clock_in,clock_out', $view);
    }

    private function uploadedCsv(string $contents): UploadedFile
    {
        $this->csvPath = tempnam(sys_get_temp_dir(), 'attendance-import-');
        file_put_contents($this->csvPath, $contents);

        return new UploadedFile($this->csvPath, 'attendance-import.csv', 'text/csv', null, true);
    }

    private function uploadedAttendanceReportXlsx(bool $withOvertime = false): UploadedFile
    {
        $this->csvPath = tempnam(sys_get_temp_dir(), 'attendance-report-');

        $zip = new \ZipArchive();
        $zip->open($this->csvPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="PAYSLIP" sheetId="1" r:id="rId1"/><sheet name="ATTENDENCE" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml([
            ['Employee ID', 'Basic Salary'],
            ['EMP001', 5000000],
        ]));
        $attendenceRows = [
            ['Employee ID*', 'Full Name', 'Date*', 'Shift', 'Schedule In', 'Schedule Out', 'Attendance Code', 'Check In', 'Check Out'],
            ['EMP001', 'Employee One', '2026-06-18', 'Office', '08:00', '17:00', 'H', '08:30', '17:45'],
            ['EMP002', 'Employee Two', '2026-06-19', 'Office', '08:00', '17:00', 'H', '07:55', '17:00'],
            ['EMP001', 'Employee One', '2026-06-20', 'dayoff', '00:00', '00:00', '', '', ''],
        ];

        if ($withOvertime) {
            $attendenceRows = [
                ['Employee ID*', 'Full Name', 'Date*', 'Shift', 'Schedule In', 'Schedule Out', 'Attendance Code', 'Check In', 'Check Out', 'Overtime Check In', 'Overtime Check Out', 'Overtime Break'],
                ['EMP001', 'Employee One', '2026-06-18', 'Office', '08:00', '17:00', 'H', '08:30', '17:45', '18:00', '20:30', '00:30'],
                ['EMP002', 'Employee Two', '2026-06-19', 'Office', '08:00', '17:00', 'H', '07:55', '17:00', '', '', ''],
                ['EMP002', 'Employee Two', '2026-06-20', 'dayoff', '00:00', '00:00', '', '', '', '09:00', '12:00', '0'],
            ];
        }

        $zip->addFromString('xl/worksheets/sheet2.xml', $this->worksheetXml($attendenceRows));
        $zip->close();

        return new UploadedFile($this->csvPath, 'report-attendence.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
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

    private function seedAttendanceImportFixtures(): void
    {
        DB::table('departments')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Operations',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedule_templates')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Office',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('shifts')->insert([
            'id' => 1,
            'company_id' => 1,
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'color' => '#2563eb',
            'is_off' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedule_template_days')->insert([
            'template_id' => 1,
            'day_of_week' => 4,
            'shift_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('employees')->insert([
            [
                'id' => 1,
                'company_id' => 1,
                'department_id' => 1,
                'schedule_template_id' => 1,
                'employee_code' => 'EMP001',
                'full_name' => 'Employee One',
                'email' => 'employee-one@example.test',
                'password' => 'password',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'company_id' => 1,
                'department_id' => 1,
                'schedule_template_id' => 1,
                'employee_code' => 'EMP002',
                'full_name' => 'Employee Two',
                'email' => 'employee-two@example.test',
                'password' => 'password',
                'role' => 'employee',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 99,
                'company_id' => 1,
                'department_id' => 1,
                'schedule_template_id' => 1,
                'employee_code' => 'ADM099',
                'full_name' => 'HR Admin',
                'email' => 'admin@example.test',
                'password' => 'password',
                'role' => 'hr_admin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
