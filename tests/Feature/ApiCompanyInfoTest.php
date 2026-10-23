<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Info Perusahaan di app: identitas perusahaan dan peraturan yang berlaku, seperti halaman portal. */
class ApiCompanyInfoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        foreach (['company_regulation_attachments', 'company_regulations', 'employees', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
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

        $now = now();
        DB::table('companies')->insert([
            ['id' => 1, 'name' => 'PT Beacon Satu', 'logo' => 'companies/logo.png', 'address' => 'Jl. Kaliurang', 'phone' => '0274 111', 'email' => 'hr@beacon.test', 'npwp' => '01.234', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'PT Beacon Dua', 'logo' => null, 'address' => null, 'phone' => null, 'email' => null, 'npwp' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('employees')->insert([
            ['id' => 1, 'company_id' => 1, 'employee_code' => 'EMP1', 'full_name' => 'Budi', 'email' => 'budi@example.test', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'company_id' => 2, 'employee_code' => 'EMP2', 'full_name' => 'Sinta', 'email' => 'sinta@example.test', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'company_id' => 9, 'employee_code' => 'EMP3', 'full_name' => 'Tanpa Perusahaan', 'email' => 'tanpa@example.test', 'password' => 'x', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function actAs(int $employeeId = 1): void
    {
        Sanctum::actingAs(Employee::findOrFail($employeeId));
    }

    private function regulation(array $attributes = []): int
    {
        return DB::table('company_regulations')->insertGetId(array_merge([
            'company_id' => 1,
            'title' => 'Tata Tertib',
            'content' => null,
            'effective_date' => '2026-08-01',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    private function attachment(int $regulationId, string $name = 'tata-tertib.pdf', string $body = 'isi pdf', bool $stored = true): int
    {
        $path = 'company-regulations/'.$name;
        if ($stored) {
            Storage::disk('local')->put($path, $body);
        }

        return DB::table('company_regulation_attachments')->insertGetId([
            'company_regulation_id' => $regulationId,
            'file_path' => $path,
            'file_name' => $name,
            'file_size' => strlen($body),
            'file_mime' => 'application/pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function relative(string $url): string
    {
        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    public function test_requires_login(): void
    {
        $this->getJson('/api/company-info')->assertUnauthorized();
        $this->getJson('/api/company-info/regulations/1/attachments/1/link')->assertUnauthorized();
    }

    public function test_returns_company_identity(): void
    {
        $this->actAs();

        $this->getJson('/api/company-info')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company.id', 1)
            ->assertJsonPath('data.company.name', 'PT Beacon Satu')
            ->assertJsonPath('data.company.address', 'Jl. Kaliurang')
            ->assertJsonPath('data.company.phone', '0274 111')
            ->assertJsonPath('data.company.email', 'hr@beacon.test')
            ->assertJsonPath('data.company.logo', asset('storage/companies/logo.png'))
            ->assertJsonMissingPath('data.company.npwp');
    }

    public function test_company_without_logo_or_details_returns_nulls(): void
    {
        $this->actAs(2);

        $this->getJson('/api/company-info')
            ->assertOk()
            ->assertJsonPath('data.company.name', 'PT Beacon Dua')
            ->assertJsonPath('data.company.logo', null)
            ->assertJsonPath('data.company.address', null)
            ->assertJsonPath('data.company.phone', null)
            ->assertJsonPath('data.company.email', null);
    }

    public function test_employee_whose_company_is_missing_gets_empty_info(): void
    {
        $this->actAs(3);
        $this->regulation(['company_id' => 1]);

        $this->getJson('/api/company-info')
            ->assertOk()
            ->assertJsonPath('data.company', null)
            ->assertJsonPath('data.regulations', []);
    }

    public function test_lists_only_active_regulations_of_own_company_newest_first_then_by_title(): void
    {
        $this->actAs();
        $this->regulation(['title' => 'Lama', 'effective_date' => '2025-01-01']);
        $this->regulation(['title' => 'Baru B', 'effective_date' => '2026-09-01']);
        $this->regulation(['title' => 'Baru A', 'effective_date' => '2026-09-01']);
        $this->regulation(['title' => 'Tanpa tanggal', 'effective_date' => null]);
        $this->regulation(['title' => 'Nonaktif', 'is_active' => false]);
        $this->regulation(['title' => 'Milik perusahaan lain', 'company_id' => 2]);

        $titles = $this->getJson('/api/company-info')->assertOk()->json('data.regulations.*.title');

        $this->assertSame(['Baru A', 'Baru B', 'Lama', 'Tanpa tanggal'], $titles);
    }

    public function test_regulation_content_becomes_one_point_per_line(): void
    {
        $this->actAs();
        $this->regulation([
            'title' => 'Absensi',
            'effective_date' => '2026-08-01',
            'content' => "  Clock in sebelum jam kerja  \r\n\r\nClock out setelah selesai\n   \nLapor bila terlambat\r",
        ]);
        $this->regulation(['title' => 'Kosong', 'effective_date' => '2026-07-01', 'content' => null]);
        $this->regulation(['title' => 'Spasi', 'effective_date' => '2026-06-01', 'content' => " \n \r\n "]);

        $response = $this->getJson('/api/company-info')->assertOk();

        $this->assertSame(['Clock in sebelum jam kerja', 'Clock out setelah selesai', 'Lapor bila terlambat'], $response->json('data.regulations.0.points'));
        $this->assertSame([], $response->json('data.regulations.1.points'));
        $this->assertSame([], $response->json('data.regulations.2.points'));
        $response->assertJsonPath('data.regulations.0.effective_date', '2026-08-01');
    }

    public function test_effective_date_is_plain_and_null_when_absent(): void
    {
        $this->actAs();
        $this->regulation(['title' => 'Bertanggal', 'effective_date' => '2026-10-08']);
        $this->regulation(['title' => 'Tanpa tanggal', 'effective_date' => null]);

        $this->getJson('/api/company-info')
            ->assertJsonPath('data.regulations.0.effective_date', '2026-10-08')
            ->assertJsonPath('data.regulations.1.effective_date', null);
    }

    public function test_attachments_are_listed_with_metadata_but_without_links(): void
    {
        $this->actAs();
        $id = $this->regulation();
        $first = $this->attachment($id, 'bab-1.pdf', 'satu');
        $second = $this->attachment($id, 'bab-2.pdf', 'dua dua');
        $this->regulation(['title' => 'Tanpa lampiran', 'effective_date' => '2025-01-01']);

        $response = $this->getJson('/api/company-info')->assertOk();

        $response->assertJsonPath('data.regulations.0.attachments.0.id', $first)
            ->assertJsonPath('data.regulations.0.attachments.0.file_name', 'bab-1.pdf')
            ->assertJsonPath('data.regulations.0.attachments.0.file_size', 4)
            ->assertJsonPath('data.regulations.0.attachments.0.file_mime', 'application/pdf')
            ->assertJsonPath('data.regulations.0.attachments.1.id', $second)
            ->assertJsonPath('data.regulations.1.attachments', []);
        $this->assertStringNotContainsString('signature', $response->getContent(), 'tautan dibuat saat lampiran diketuk, bukan di daftar');
    }

    public function test_link_opens_the_file_until_it_expires(): void
    {
        $this->actAs();
        $regulation = $this->regulation();
        $attachment = $this->attachment($regulation, 'tata-tertib.pdf', 'isi pdf rahasia');

        $data = $this->getJson("/api/company-info/regulations/$regulation/attachments/$attachment/link")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.file_name', 'tata-tertib.pdf')
            ->assertJsonPath('data.expires_in_minutes', 30)
            ->json('data');
        $signed = $this->relative($data['url']);

        $this->assertStringStartsWith(url("/files/company-regulations/$regulation/attachments/$attachment?"), $data['url']);
        $response = $this->get($signed)->assertOk();
        $this->assertSame('isi pdf rahasia', $response->streamedContent());
        $this->assertStringContainsString('tata-tertib.pdf', $response->headers->get('content-disposition'));

        // Tanpa tanda tangan atau dengan tanda tangan diubah: ditolak.
        $this->get("/files/company-regulations/$regulation/attachments/$attachment")->assertForbidden();
        $this->get($signed.'x')->assertForbidden();

        Carbon::setTestNow(now()->addMinutes(31));
        try {
            $this->get($signed)->assertForbidden();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_link_stops_working_when_the_regulation_is_switched_off_after_it_was_issued(): void
    {
        $this->actAs();
        $regulation = $this->regulation();
        $attachment = $this->attachment($regulation);
        $signed = $this->relative($this->getJson("/api/company-info/regulations/$regulation/attachments/$attachment/link")->json('data.url'));

        DB::table('company_regulations')->where('id', $regulation)->update(['is_active' => false]);

        $this->get($signed)->assertNotFound();
    }

    public function test_link_is_refused_for_other_companies_inactive_or_mismatched_files(): void
    {
        $this->actAs();
        $mine = $this->regulation();
        $mineFile = $this->attachment($mine, 'mine.pdf');
        $other = $this->regulation(['company_id' => 2, 'title' => 'Milik lain']);
        $otherFile = $this->attachment($other, 'other.pdf');
        $off = $this->regulation(['is_active' => false, 'title' => 'Nonaktif']);
        $offFile = $this->attachment($off, 'off.pdf');
        $missingFile = $this->attachment($mine, 'hilang.pdf', 'x', stored: false);

        foreach ([
            "$other/attachments/$otherFile" => 'perusahaan lain',
            "$off/attachments/$offFile" => 'nonaktif',
            "$mine/attachments/$otherFile" => 'lampiran milik peraturan lain',
            "$mine/attachments/$missingFile" => 'berkas hilang dari penyimpanan',
            "$mine/attachments/9999" => 'tidak ada',
            '9999/attachments/'.$mineFile => 'peraturan tidak ada',
        ] as $path => $why) {
            $this->getJson("/api/company-info/regulations/$path/link")
                ->assertNotFound()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Lampiran tidak ditemukan.');
            $this->assertTrue(true, $why);
        }

        $this->getJson("/api/company-info/regulations/$mine/attachments/$mineFile/link")->assertOk();
    }
}
