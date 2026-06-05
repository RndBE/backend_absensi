<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendancePhotoArchive;
use App\Models\Employee;
use App\Services\AttendancePhotoArchiveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class AttendancePhotoArchiveServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('PHP ZipArchive extension is required.');
        }

        Schema::dropIfExists('attendance_photo_archives');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('employee_code')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
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
            $table->string('clock_in_photo')->nullable();
            $table->string('clock_out_photo')->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->timestamps();
        });

        Schema::create('attendance_photo_archives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('period', 7);
            $table->string('status')->default('pending');
            $table->string('zip_file_name')->nullable();
            $table->string('zip_file_path')->nullable();
            $table->unsignedInteger('photo_count')->default(0);
            $table->json('photo_paths')->nullable();
            $table->text('drive_link')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('local_photos_deleted_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'period']);
        });

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_generate_zip_archives_monthly_attendance_photos_for_one_company(): void
    {
        Storage::disk('public')->put('attendance/clock-in/in-june.jpg', 'clock in june');
        Storage::disk('public')->put('attendance/clock-out/out-june.jpg', 'clock out june');
        Storage::disk('public')->put('attendance/clock-in/in-may.jpg', 'clock in may');
        Storage::disk('public')->put('attendance/clock-in/other-company.jpg', 'other company');

        $admin = $this->employee(1, 'HRD Admin', 'admin@example.test');
        $employee = $this->employee(1, 'Budi Santoso', 'budi@example.test', 'EMP/001');
        $otherCompanyEmployee = $this->employee(2, 'Sari Wibowo', 'sari@example.test', 'EMP/002');

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-06-03',
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
            'clock_in_photo' => 'attendance/clock-in/in-june.jpg',
            'clock_out_photo' => 'attendance/clock-out/out-june.jpg',
        ]);
        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-05-31',
            'clock_in_photo' => 'attendance/clock-in/in-may.jpg',
        ]);
        Attendance::create([
            'employee_id' => $otherCompanyEmployee->id,
            'date' => '2026-06-03',
            'clock_in_photo' => 'attendance/clock-in/other-company.jpg',
        ]);

        $archive = app(AttendancePhotoArchiveService::class)->generate(1, '2026-06', $admin->id);

        $this->assertSame('ready', $archive->status);
        $this->assertSame(2, $archive->photo_count);
        $this->assertSame(['attendance/clock-in/in-june.jpg', 'attendance/clock-out/out-june.jpg'], $archive->photo_paths);
        Storage::disk('local')->assertExists($archive->zip_file_path);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($archive->zip_file_path)));
        $this->assertNotFalse($zip->locateName('foto_absensi_2026-06/EMP_001_Budi_Santoso/2026-06-03_clock-in.jpg'));
        $this->assertNotFalse($zip->locateName('foto_absensi_2026-06/EMP_001_Budi_Santoso/2026-06-03_clock-out.jpg'));
        $this->assertFalse($zip->locateName('foto_absensi_2026-06/EMP_002_Sari_Wibowo/2026-06-03_clock-in.jpg'));
        $zip->close();
    }

    public function test_mark_uploaded_deletes_only_photos_recorded_in_archive_manifest(): void
    {
        Storage::disk('public')->put('attendance/clock-in/in-june.jpg', 'clock in june');
        Storage::disk('public')->put('attendance/clock-out/out-june.jpg', 'clock out june');
        Storage::disk('public')->put('attendance/clock-in/not-in-archive.jpg', 'not in archive');

        $admin = $this->employee(1, 'HRD Admin', 'admin@example.test');

        $archive = AttendancePhotoArchive::create([
            'company_id' => 1,
            'period' => '2026-06',
            'status' => 'ready',
            'zip_file_name' => 'foto_absensi_2026-06.zip',
            'zip_file_path' => 'attendance-photo-archives/foto_absensi_2026-06.zip',
            'photo_count' => 2,
            'photo_paths' => ['attendance/clock-in/in-june.jpg', 'attendance/clock-out/out-june.jpg'],
            'generated_by' => $admin->id,
            'generated_at' => Carbon::parse('2026-07-01 09:00:00'),
        ]);

        $updated = app(AttendancePhotoArchiveService::class)->markUploaded(
            $archive,
            'https://drive.google.com/file/d/example/view',
            $admin->id
        );

        $this->assertSame('archived', $updated->status);
        $this->assertSame('https://drive.google.com/file/d/example/view', $updated->drive_link);
        $this->assertNotNull($updated->archived_at);
        $this->assertNotNull($updated->local_photos_deleted_at);
        Storage::disk('public')->assertMissing('attendance/clock-in/in-june.jpg');
        Storage::disk('public')->assertMissing('attendance/clock-out/out-june.jpg');
        Storage::disk('public')->assertExists('attendance/clock-in/not-in-archive.jpg');
    }

    public function test_mark_uploaded_requires_google_drive_link_before_deleting_local_photos(): void
    {
        Storage::disk('public')->put('attendance/clock-in/in-june.jpg', 'clock in june');

        $archive = AttendancePhotoArchive::create([
            'company_id' => 1,
            'period' => '2026-06',
            'status' => 'ready',
            'photo_count' => 1,
            'photo_paths' => ['attendance/clock-in/in-june.jpg'],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Link Google Drive wajib diisi.');

        app(AttendancePhotoArchiveService::class)->markUploaded($archive, '', 1);

        Storage::disk('public')->assertExists('attendance/clock-in/in-june.jpg');
    }

    private function employee(int $companyId, string $name, string $email, ?string $code = null): Employee
    {
        return Employee::create([
            'company_id' => $companyId,
            'employee_code' => $code,
            'email' => $email,
            'password' => 'secret',
            'full_name' => $name,
            'role' => 'hr_admin',
            'is_active' => true,
        ]);
    }
}
