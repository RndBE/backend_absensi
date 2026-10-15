<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Surat tugas di aplikasi mobile: daftar & detail lewat API, surat dan hasil pindai TTD dibuka
 * lewat tautan bertanda tangan yang berbatas waktu.
 */
class ApiAssignmentLetterTest extends AssignmentLetterTestCase
{
    private AssignmentLetter $letter;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Surat terbit untuk Fadel (PERMANENT) & Prastowo (CONTRACT).
        $this->letter = $this->issue($this->storeDraft());
    }

    private function asEmployee(int $id): static
    {
        Sanctum::actingAs(Employee::findOrFail($id));

        return $this;
    }

    private function uploadSigned(string $name = 'scan.pdf'): void
    {
        $file = str_ends_with($name, '.pdf')
            ? UploadedFile::fake()->create($name, 100, 'application/pdf')
            : UploadedFile::fake()->image($name);

        $this->asRole('hr_admin')
            ->post(route('admin.assignment-letters.signed.store', $this->letter->id), ['signed_file' => $file])
            ->assertSessionHas('success');
    }

    public function test_list_and_detail_only_show_letters_of_the_employee(): void
    {
        $this->storeDraft(); // draft untuk Fadel juga: tidak boleh muncul

        $data = $this->asEmployee(self::PERMANENT)->getJson('/api/assignment-letters')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('001/ST-ATC/I/2026', $data[0]['number']);
        $this->assertSame('issued', $data[0]['status']);
        $this->assertSame('Riset dan update firmware di Bendungan Ciawi', $data[0]['title']);
        $this->assertSame(3, $data[0]['working_days']);
        $this->assertSame('Fadel Irsyad', $data[0]['employees'][0]['name']);
        $this->assertSame('Kereta Api', collect($data[0]['facilities'])->firstWhere('type', 'transport')['description']);
        $this->assertStringContainsString('/files/assignment-letters/'.$this->letter->id.'/print?', $data[0]['print_url']);
        $this->assertNull($data[0]['signed_file']);

        $this->asEmployee(self::PERMANENT)->getJson('/api/assignment-letters/'.$this->letter->id)
            ->assertOk()
            ->assertJsonPath('data.number', '001/ST-ATC/I/2026');

        $this->asEmployee(self::DIRECTOR)->getJson('/api/assignment-letters')->assertOk()->assertJsonCount(0, 'data');
        $this->asEmployee(self::DIRECTOR)->getJson('/api/assignment-letters/'.$this->letter->id)->assertNotFound();
    }

    public function test_links_open_the_letter_and_signed_scan_until_they_expire(): void
    {
        $this->uploadSigned();

        $data = $this->asEmployee(self::PERMANENT)->getJson('/api/assignment-letters/'.$this->letter->id)->json('data');
        $this->assertSame('Surat Tugas 001-ST-ATC-I-2026 (TTD).pdf', $data['signed_file']['file_name']);
        $this->assertTrue($data['signed_file']['is_pdf']);

        $print = parse_url($data['print_url'], PHP_URL_PATH).'?'.parse_url($data['print_url'], PHP_URL_QUERY);
        $signed = parse_url($data['signed_file']['url'], PHP_URL_PATH).'?'.parse_url($data['signed_file']['url'], PHP_URL_QUERY);

        $this->get($print)
            ->assertOk()
            ->assertSee('001/ST-ATC/I/2026')
            ->assertSee('Lihat yang sudah TTD')
            ->assertDontSee('class="back-btn', false);
        $this->get($signed)->assertOk();

        // Tautan yang diubah atau tanpa tanda tangan ditolak.
        $this->get(str_replace('/'.$this->letter->id.'/', '/999/', $print))->assertForbidden();
        $this->get('/files/assignment-letters/'.$this->letter->id.'/signed')->assertForbidden();

        $this->travel(31)->minutes();
        $this->get($print)->assertForbidden();
        $this->get($signed)->assertForbidden();
    }

    public function test_first_signed_upload_notifies_participants_once(): void
    {
        $count = fn () => DB::table('notifications')
            ->where('reference_id', $this->letter->id)
            ->where('title', 'Surat Tugas Sudah Ditandatangani')
            ->count();

        $this->uploadSigned();
        $this->assertSame(2, $count()); // satu per peserta

        $this->uploadSigned('scan-ulang.jpg');
        $this->assertSame(2, $count()); // mengganti file tidak mengirim ulang
    }
}
