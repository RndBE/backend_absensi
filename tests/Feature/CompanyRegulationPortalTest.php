<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyRegulationPortalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Schema::dropIfExists('company_regulation_attachments');
        Schema::dropIfExists('company_regulations');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('npwp')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('employee_code')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('company_regulations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('title');
            $table->string('category')->nullable();
            $table->text('content')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->timestamps();
        });

        Schema::create('company_regulation_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_regulation_id');
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_mime')->nullable();
            $table->timestamps();
        });

        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'PT Beacon Satu', 'address' => 'Batam', 'phone' => '0778', 'email' => 'hr@example.test', 'npwp' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'PT Beacon Dua', 'address' => null, 'phone' => null, 'email' => null, 'npwp' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'employee_code' => 'ADM001', 'email' => 'admin@example.test', 'password' => Hash::make('password'), 'full_name' => 'Admin HR', 'role' => 'admin', 'position' => 'HR', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'company_id' => 1, 'employee_code' => 'EMP001', 'email' => 'employee@example.test', 'password' => Hash::make('password'), 'full_name' => 'Employee One', 'role' => 'employee', 'position' => 'Staff', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_admin_can_create_company_regulation_from_company_info(): void
    {
        $this->withSession(['admin_id' => 1])
            ->post(route('admin.company.regulations.store'), [
                'title' => 'Tata Tertib Absensi',
                'category' => 'Absensi',
                'content' => 'Karyawan wajib melakukan clock in sebelum mulai bekerja.',
                'effective_date' => '2026-08-01',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.company.index'));

        $this->assertDatabaseHas('company_regulations', [
            'company_id' => 1,
            'title' => 'Tata Tertib Absensi',
            'category' => null,
            'is_active' => true,
        ]);

        $this->withSession(['admin_id' => 1])
            ->get(route('admin.company.index'))
            ->assertOk()
            ->assertSee('Peraturan Perusahaan')
            ->assertSee('Tata Tertib Absensi');
    }

    public function test_admin_can_import_company_regulations_from_csv(): void
    {
        $csv = implode("\n", [
            'judul,isi,tanggal_berlaku,status',
            'Aturan Absensi,Clock in tepat waktu,2026-08-01,Aktif',
            'Draft Benefit,Masih disiapkan,,Draft',
        ]);

        $file = UploadedFile::fake()->createWithContent('peraturan.csv', $csv);

        $this->withSession(['admin_id' => 1])
            ->post(route('admin.company.regulations.import'), [
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.company.index'));

        $this->assertDatabaseHas('company_regulations', [
            'company_id' => 1,
            'title' => 'Aturan Absensi',
            'category' => null,
            'content' => 'Clock in tepat waktu',
            'effective_date' => '2026-08-01 00:00:00',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('company_regulations', [
            'company_id' => 1,
            'title' => 'Draft Benefit',
            'category' => null,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_import_pdf_as_active_company_regulation_attachment(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('PERATURAN PERUSAHAAN 2025.pdf', 100, 'application/pdf');

        $this->withSession(['admin_id' => 1])
            ->post(route('admin.company.regulations.import'), [
                'import_file' => $file,
            ])
            ->assertRedirect(route('admin.company.index'));

        $regulation = DB::table('company_regulations')
            ->where('title', 'PERATURAN PERUSAHAAN 2025')
            ->first();

        $this->assertNotNull($regulation);
        $this->assertTrue((bool) $regulation->is_active);
        $this->assertNull($regulation->category);
        $this->assertNotNull($regulation->file_path);
        Storage::disk('local')->assertExists($regulation->file_path);
        $this->assertDatabaseHas('company_regulation_attachments', [
            'company_regulation_id' => $regulation->id,
            'file_name' => 'PERATURAN PERUSAHAAN 2025.pdf',
        ]);
    }

    public function test_admin_can_upload_multiple_company_regulation_attachments(): void
    {
        Storage::fake('local');

        $this->withSession(['admin_id' => 1])
            ->post(route('admin.company.regulations.store'), [
                'title' => 'Pedoman Karyawan',
                'content' => 'Baca semua lampiran.',
                'is_active' => '1',
                'attachments' => [
                    UploadedFile::fake()->create('bab-1.pdf', 100, 'application/pdf'),
                    UploadedFile::fake()->create('bab-2.docx', 120, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                ],
            ])
            ->assertRedirect(route('admin.company.index'));

        $regulation = DB::table('company_regulations')
            ->where('title', 'Pedoman Karyawan')
            ->first();

        $this->assertNotNull($regulation);
        $this->assertDatabaseHas('company_regulation_attachments', [
            'company_regulation_id' => $regulation->id,
            'file_name' => 'bab-1.pdf',
        ]);
        $this->assertDatabaseHas('company_regulation_attachments', [
            'company_regulation_id' => $regulation->id,
            'file_name' => 'bab-2.docx',
        ]);
        $this->assertSame(2, DB::table('company_regulation_attachments')->where('company_regulation_id', $regulation->id)->count());

        $this->withSession(['admin_id' => 1])
            ->get(route('admin.company.index'))
            ->assertOk()
            ->assertSee('2 lampiran')
            ->assertSee('bab-1.pdf')
            ->assertSee('bab-2.docx');
    }

    public function test_admin_can_delete_and_add_company_regulation_attachments_on_update(): void
    {
        Storage::fake('local');

        $regulationId = DB::table('company_regulations')->insertGetId([
            'company_id' => 1,
            'title' => 'Pedoman Update',
            'category' => null,
            'content' => 'Versi awal.',
            'effective_date' => null,
            'is_active' => true,
            'file_path' => 'company-regulations/lama-a.pdf',
            'file_name' => 'lama-a.pdf',
            'file_size' => 1024,
            'file_mime' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Storage::disk('local')->put('company-regulations/lama-a.pdf', 'a');
        Storage::disk('local')->put('company-regulations/lama-b.pdf', 'b');

        $deletedAttachmentId = DB::table('company_regulation_attachments')->insertGetId([
            'company_regulation_id' => $regulationId,
            'file_path' => 'company-regulations/lama-a.pdf',
            'file_name' => 'lama-a.pdf',
            'file_size' => 1024,
            'file_mime' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('company_regulation_attachments')->insert([
            'company_regulation_id' => $regulationId,
            'file_path' => 'company-regulations/lama-b.pdf',
            'file_name' => 'lama-b.pdf',
            'file_size' => 2048,
            'file_mime' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['admin_id' => 1])
            ->put(route('admin.company.regulations.update', $regulationId), [
                'title' => 'Pedoman Update',
                'content' => 'Versi baru.',
                'is_active' => '1',
                'delete_attachments' => [$deletedAttachmentId],
                'attachments' => [
                    UploadedFile::fake()->create('baru.pdf', 100, 'application/pdf'),
                ],
            ])
            ->assertRedirect(route('admin.company.index'));

        $this->assertDatabaseMissing('company_regulation_attachments', [
            'id' => $deletedAttachmentId,
            'file_name' => 'lama-a.pdf',
        ]);
        $this->assertDatabaseHas('company_regulation_attachments', [
            'company_regulation_id' => $regulationId,
            'file_name' => 'lama-b.pdf',
        ]);
        $this->assertDatabaseHas('company_regulation_attachments', [
            'company_regulation_id' => $regulationId,
            'file_name' => 'baru.pdf',
        ]);
        Storage::disk('local')->assertMissing('company-regulations/lama-a.pdf');
        Storage::disk('local')->assertExists('company-regulations/lama-b.pdf');
        $this->assertSame(2, DB::table('company_regulation_attachments')->where('company_regulation_id', $regulationId)->count());
    }

    public function test_employee_company_info_only_shows_active_company_regulations(): void
    {
        DB::table('company_regulations')->insert([
            ['company_id' => 1, 'title' => 'Jam Kerja', 'category' => 'Absensi', 'content' => 'Jam kerja dimulai pukul 08.00.', 'effective_date' => '2026-08-01', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 1, 'title' => 'Draft Rahasia', 'category' => 'Umum', 'content' => 'Belum boleh tampil.', 'effective_date' => null, 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
            ['company_id' => 2, 'title' => 'Aturan Company Lain', 'category' => 'Umum', 'content' => 'Tidak untuk company ini.', 'effective_date' => null, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $activeRegulationId = DB::table('company_regulations')->where('title', 'Jam Kerja')->value('id');
        DB::table('company_regulation_attachments')->insert([
            'company_regulation_id' => $activeRegulationId,
            'file_path' => 'company-regulations/jam-kerja.pdf',
            'file_name' => 'jam-kerja.pdf',
            'file_size' => 1024,
            'file_mime' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withSession(['employee_id' => 2])
            ->get(route('employee.company-info.index'))
            ->assertOk()
            ->assertSee('PT Beacon Satu')
            ->assertSee('Peraturan Perusahaan')
            ->assertSee('Jam Kerja')
            ->assertSee('jam-kerja.pdf')
            ->assertSee('Jam kerja dimulai pukul 08.00.')
            ->assertDontSee('Draft Rahasia')
            ->assertDontSee('Aturan Company Lain');
    }
}
