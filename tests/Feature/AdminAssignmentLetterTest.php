<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterDestination;
use App\Models\DocumentSequence;
use App\Models\Vehicle;
use App\Support\AssignmentLetterConflicts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminAssignmentLetterTest extends AssignmentLetterTestCase
{
    public function test_store_rejects_intern_and_outsourcing_employees(): void
    {
        foreach ([self::INTERN, self::OUTSOURCING, self::OTHER_COMPANY] as $id) {
            $this->asRole('hr_admin')
                ->post(route('admin.assignment-letters.store'), $this->payload([
                    'employees' => [['employee_id' => $id, 'position' => '']],
                ]))
                ->assertSessionHasErrors('employees');
        }

        $this->assertSame(0, AssignmentLetter::count());
    }

    public function test_create_form_only_offers_eligible_employees(): void
    {
        $response = $this->asRole('hr_admin')->get(route('admin.assignment-letters.create'))->assertOk();

        $response->assertSee('Fadel Irsyad')->assertSee('Prastowo Dian');
        $response->assertDontSee('Anak Magang')->assertDontSee('Petugas Outsourcing')->assertDontSee('Karyawan PT Lain');
    }

    public function test_transport_row_offers_personal_rental_and_public_choices_and_stores_the_composed_text(): void
    {
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.create'))
            ->assertOk()
            ->assertSee('Kendaraan pribadi…')
            ->assertSee('Kendaraan rental / sewa…')
            ->assertSee('Kereta Api')
            ->assertSee('milik pribadi')
            // Hasil pemeriksaan memakai dropdown bergaya sama (bukan datalist bawaan browser).
            ->assertSee('Lainnya, ketik sendiri…')
            ->assertSee('— Belum diperiksa —')
            ->assertDontSee('<datalist', false);

        // Kendaraan pribadi tidak punya id master: hanya teks gabungan yang tersimpan dan tercetak.
        $letter = $this->storeDraft(['facilities' => [
            ['type' => 'transport', 'vehicle_id' => '', 'description' => 'Vario / milik pribadi / AB 2779 NX', 'inspection_result' => '', 'inspected_on' => ''],
        ]]);

        $facility = $letter->facilities()->sole();
        $this->assertNull($facility->vehicle_id);
        $this->assertSame('Vario / milik pribadi / AB 2779 NX', $facility->description);

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.print', $letter->id))
            ->assertSee('Vario / milik pribadi / AB 2779 NX');
    }

    public function test_store_saves_draft_with_employee_snapshot_and_facilities(): void
    {
        $vehicle = Vehicle::create(['company_id' => 1, 'name' => 'Alvez', 'plate_number' => 'K 1734 AT', 'ownership' => 'company']);

        $letter = $this->storeDraft([
            'facilities' => [
                ['type' => 'device', 'communication_device_id' => '', 'description' => '-', 'inspection_result' => '', 'inspected_on' => ''],
                ['type' => 'transport', 'vehicle_id' => (string) $vehicle->id, 'description' => '', 'inspection_result' => 'Baik, bisa digunakan', 'inspected_on' => '2026-01-05'],
            ],
        ]);

        $this->assertTrue($letter->isDraft());
        $this->assertNull($letter->number);

        $rows = $letter->employees;
        $this->assertSame(['Fadel Irsyad', 'Prastowo Dian'], $rows->pluck('name')->all());
        $this->assertSame('Software Division', $rows[0]->position);
        // Jabatan kosong = ikut data karyawan.
        $this->assertSame('RND', $rows[1]->position);
        $this->assertSame(['Software', 'Hardware'], $rows->pluck('department_name')->all());

        // Baris HT berisi "-" dilewati; transportasi dari master diberi label otomatis.
        $this->assertCount(1, $letter->facilities);
        $transport = $letter->facilities->first();
        $this->assertSame('Alvez / milik perusahaan / K 1734 AT', $transport->description);
        $this->assertSame(self::ADMIN, $transport->inspected_by);
        $this->assertSame('Baik, bisa digunakan (diperiksa pada tanggal 05 Januari 2026)', $transport->inspectionLabel());
    }

    public function test_issue_continues_numbering_from_last_word_letter(): void
    {
        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.numbering'), ['year' => '2026', 'last_number' => '70'])
            ->assertSessionHasNoErrors();

        $september = $this->storeDraft(['letter_date' => '2026-09-24']);
        $october = $this->storeDraft(['letter_date' => '2026-10-01']);

        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $september->id))->assertSessionHas('success');
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $october->id))->assertSessionHas('success');

        $september->refresh();
        $this->assertTrue($september->isIssued());
        $this->assertSame('071/ST-ATC/IX/2026', $september->number);
        $this->assertSame('072/ST-ATC/X/2026', $october->fresh()->number);
        // Peserta contoh belum punya atasan level 3/2: hanya kolom tetap, dan itu yang dibekukan.
        $this->assertSame(['HRD', 'GA HSE'], $september->initial_boxes);
        $this->assertSame(72, DocumentSequence::lastNumber(1, DocumentSequence::TYPE_ASSIGNMENT_LETTER, 2026));

        // Tahun baru mulai lagi dari 001.
        $january = $this->storeDraft(['letter_date' => '2027-01-04', 'departure_date' => '2027-01-04', 'work_start_date' => '2027-01-04', 'return_date' => '2027-01-04']);
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $january->id));
        $this->assertSame('001/ST-ATC/I/2027', $january->fresh()->number);
    }

    public function test_draft_shows_estimated_number_that_matches_issue(): void
    {
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.numbering'), ['year' => '2026', 'last_number' => '70']);
        $draft = $this->storeDraft(['letter_date' => '2026-09-24']);

        $this->assertSame('071/ST-ATC/IX/2026', $draft->estimatedNumber());
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.index'))
            ->assertOk()->assertSee('071/ST-ATC/IX/2026')->assertSee('Perkiraan')->assertSee('Pratinjau')->assertSee('Ubah');
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $draft->id))
            ->assertOk()->assertSee('071/ST-ATC/IX/2026')->assertSee('Perkiraan');
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.print', $draft->id))
            ->assertOk()->assertSee('071/ST-ATC/IX/2026 (perkiraan)');

        $issued = $this->issue($draft);
        $this->assertSame('071/ST-ATC/IX/2026', $issued->number);
        $this->assertNull($issued->estimatedNumber());
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.index'))
            ->assertDontSee('Perkiraan')->assertSee('Cetak');
    }

    public function test_last_number_cannot_go_below_issued_letters(): void
    {
        $letter = $this->storeDraft(['letter_date' => '2026-09-24']);
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id));

        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.numbering'), ['year' => '2026', 'last_number' => '0'])
            ->assertSessionHasErrors('last_number');
    }

    public function test_issued_letter_without_signed_scan_can_still_be_edited_and_keeps_its_number(): void
    {
        $letter = $this->storeDraft();
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id));
        $number = $letter->fresh()->number;

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.edit', $letter->id))->assertOk();
        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['city' => 'Jakarta']))
            ->assertSessionHas('success');

        $letter->refresh();
        $this->assertSame('Jakarta', $letter->city);
        $this->assertSame($number, $letter->number);
        $this->assertTrue($letter->isIssued());
        $this->assertSame(1, DB::table('notifications')->where('employee_id', self::PERMANENT)->where('title', 'Surat Tugas Diperbarui')->count());

        // Terbit dua kali tetap tidak mengambil nomor baru; surat terbit tidak bisa dihapus.
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id))->assertSessionHas('error');
        $this->asRole('hr_admin')->delete(route('admin.assignment-letters.destroy', $letter->id))->assertSessionHas('error');
        $this->assertSame($number, $letter->fresh()->number);
    }

    public function test_issued_edit_refreshes_the_employee_snapshot_but_signed_scan_locks_the_letter(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $letter = $this->storeDraft();
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id));

        DB::table('employees')->where('id', self::PERMANENT)->update(['full_name' => 'Nama Baru']);
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $this->payload())->assertSessionHas('success');
        $this->assertSame('Nama Baru', $letter->fresh()->employees->first()->name);

        $this->asRole('hr_admin')->post(route('admin.assignment-letters.signed.store', $letter->id), [
            'signed_file' => \Illuminate\Http\UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('success');

        // Sudah ditandatangani Direktur: final.
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.edit', $letter->id))->assertSessionHas('error');
        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['city' => 'Surabaya']))
            ->assertSessionHas('error');
        $this->assertSame('Yogyakarta', $letter->fresh()->city);

        // Pindaian dihapus: boleh diubah lagi.
        $this->asRole('hr_admin')->delete(route('admin.assignment-letters.signed.destroy', $letter->id));
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['city' => 'Surabaya']))->assertSessionHas('success');
    }

    public function test_revision_tab_offers_issued_letters_and_prefills_the_form_from_the_chosen_one(): void
    {
        $draft = $this->storeDraft();
        $issued = $this->issue($this->storeDraft(['destinations' => [
            ['activity' => 'Riset dan update firmware di Bendungan Ciawi', 'address' => 'Jl. Panatraco, Megamendung', 'distance_km' => '590'],
            ['activity' => 'Tujuan kedua yang akan dibuang', 'address' => 'Bogor', 'distance_km' => '10'],
        ]]));

        // Tanpa surat dipilih: hanya pemilih, belum ada formulir. Draft tidak ikut di daftar.
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.create', ['tab' => 'revision']))
            ->assertOk()
            ->assertSee('Perbaikan Surat Tugas')
            ->assertSee('Surat Baru')
            ->assertSee('value="'.$issued->id.'"', false)
            ->assertDontSee('value="'.$draft->id.'"', false)
            ->assertDontSee('id="assignmentLetterForm"', false);

        // Surat dipilih: formulir terisi data surat itu, siap diubah sebelum disimpan.
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.create', ['tab' => 'revision', 'source' => $issued->id]))
            ->assertOk()
            ->assertSee('id="assignmentLetterForm"', false)
            ->assertSee('name="revises_letter_id" value="'.$issued->id.'"', false)
            ->assertSee('Tujuan kedua yang akan dibuang')
            ->assertSee('Fadel Irsyad')
            ->assertSee('belum ada hasil pindai')
            ->assertSee('Perbaikan atas surat tugas '.$issued->number);

        // Surat yang tak ada / bukan surat terbit dialihkan kembali ke pemilih.
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.create', ['tab' => 'revision', 'source' => $draft->id]))
            ->assertRedirect(route('admin.assignment-letters.create', ['tab' => 'revision']));

        // Hanya yang boleh membuat surat (manage) yang bisa membuka halamannya.
        $this->asRole('finance_admin')->get(route('admin.assignment-letters.create'))->assertForbidden();
    }

    public function test_issued_edit_keeps_letter_month_and_blocks_budget_relevant_changes(): void
    {
        $letter = $this->storeDraft();
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id));

        // Nomor memuat bulan/tahun: tanggal surat tidak boleh pindah bulan.
        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['letter_date' => '2026-02-05']))
            ->assertSessionHas('error');
        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['letter_date' => '2026-01-20']))
            ->assertSessionHas('success');

        // Anggaran aktif memakai surat ini: peserta, tanggal, dan jarak terkunci; tujuan/fasilitas bebas.
        DB::table('budget_requests')->insert(['employee_id' => self::PERMANENT, 'title' => 'Dinas', 'status' => 'pending', 'assignment_letter_id' => $letter->id]);

        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['return_date' => '2026-01-12']))
            ->assertSessionHas('error');
        $this->asRole('hr_admin')
            ->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['employees' => [['employee_id' => self::PERMANENT, 'position' => '']]]))
            ->assertSessionHas('error');
        $this->assertCount(2, $letter->fresh()->employees);

        $changed = $this->payload();
        $changed['destinations'][0]['activity'] = 'Riset ulang firmware';
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $changed)->assertSessionHas('success');
        $this->assertSame('Riset ulang firmware', $letter->fresh()->destinations->first()->activity);
    }

    public function test_revision_of_a_signed_letter_gets_the_next_number_and_refers_to_the_old_one(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $old = $this->storeDraft(['destinations' => [
            ['activity' => 'Riset dan update firmware di Bendungan Ciawi', 'address' => '', 'distance_km' => '590'],
            ['activity' => 'Tujuan yang akan dibuang', 'address' => '', 'distance_km' => '10'],
        ]]);
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $old->id));
        $second = $this->issue($this->storeDraft()); // nomor 002: "surat terakhir"

        $this->asRole('hr_admin')->post(route('admin.assignment-letters.signed.store', $old->id), [
            'signed_file' => \Illuminate\Http\UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ]);

        // Seperti draft Word: salah satu tujuan dibuang langsung di formulir perbaikan, lalu disimpan.
        $payload = $this->payload([
            'destinations' => [['activity' => 'Riset dan update firmware di Bendungan Ciawi', 'address' => '', 'distance_km' => '590']],
            'revises_letter_id' => $old->id,
            'revision_reason' => 'Salah tujuan',
        ]);
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.store'), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $revision = AssignmentLetter::latest('id')->first();

        $this->assertTrue($revision->isDraft());
        $this->assertSame($old->id, $revision->revises_letter_id);
        $this->assertSame('Salah tujuan', $revision->revision_reason);
        $this->assertCount(2, $revision->employees);
        $this->assertCount(1, $revision->destinations, 'perubahan isi tersimpan di surat perbaikan');
        $this->assertCount(2, $old->fresh()->destinations, 'surat lama tidak berubah');

        // Satu perbaikan terbuka per surat.
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.store'), $payload)->assertSessionHas('error');
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.create', ['tab' => 'revision', 'source' => $old->id]))
            ->assertRedirect(route('admin.assignment-letters.show', $revision->id));
        $this->assertSame(1, AssignmentLetter::where('revises_letter_id', $old->id)->count());
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $revision->id))->assertSee('Alasan perbaikan:')->assertSee('Salah tujuan');

        // Surat yang diperbaiki harus surat terbit; draft ditolak dan tidak membuat draft baru.
        $draft = $this->storeDraft();
        $before = AssignmentLetter::count();
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.store'), $this->payload(['revises_letter_id' => $draft->id]))->assertSessionHas('error');
        $this->assertSame($before, AssignmentLetter::count());

        // Surat lama dan perbaikannya tumpang tindih tanggal, itu bukan bentrok.
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $revision->id))
            ->assertOk()
            ->assertSee($old->fresh()->number)
            ->assertDontSee('juga tercantum di surat tugas '.$old->fresh()->number);

        $issued = $this->issue($revision);
        $this->assertSame('003/ST-ATC/I/2026', $issued->number);
        $this->assertSame($second->number, '002/ST-ATC/I/2026');

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.print', $revision->id))
            ->assertSee('memberikan perbaikan surat tugas 001/ST-ATC/I/2026 kepada karyawan kami');
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $old->id))->assertSee('Diperbaiki oleh surat tugas');
        $this->assertSame(1, DB::table('notifications')->where('employee_id', self::PERMANENT)->where('title', 'Surat Tugas Perbaikan')->count());

        // Surat lama tidak disentuh.
        $this->assertTrue($old->fresh()->isIssued());
        $this->assertTrue($old->fresh()->hasSignedFile());
    }

    public function test_revision_reason_is_optional(): void
    {
        $issued = $this->issue($this->storeDraft());

        $this->asRole('hr_admin')->post(route('admin.assignment-letters.store'), $this->payload(['revises_letter_id' => $issued->id]))
            ->assertSessionHasNoErrors();

        $revision = AssignmentLetter::latest('id')->first();
        $this->assertSame($issued->id, $revision->revises_letter_id);
        $this->assertNull($revision->revision_reason);
    }

    public function test_issue_refuses_employee_who_became_ineligible(): void
    {
        $letter = $this->storeDraft();
        DB::table('employees')->where('id', self::CONTRACT)->update(['employment_status' => 'outsourcing']);

        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id))->assertSessionHas('error');

        $this->assertTrue($letter->fresh()->isDraft());
        $this->assertSame(0, DocumentSequence::lastNumber(1, DocumentSequence::TYPE_ASSIGNMENT_LETTER, 2026));
    }

    public function test_draft_can_be_deleted_and_issued_letter_cancelled_then_copied(): void
    {
        $draft = $this->storeDraft();
        $this->asRole('hr_admin')->delete(route('admin.assignment-letters.destroy', $draft->id))->assertRedirect(route('admin.assignment-letters.index'));
        $this->assertNull(AssignmentLetter::find($draft->id));

        $letter = $this->storeDraft();
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id));

        $this->asRole('hr_admin')->post(route('admin.assignment-letters.cancel', $letter->id), [])->assertSessionHasErrors('cancel_reason');
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.cancel', $letter->id), ['cancel_reason' => 'Salah tanggal']);
        $this->assertTrue($letter->fresh()->isCancelled());

        $this->asRole('hr_admin')->post(route('admin.assignment-letters.duplicate', $letter->id))->assertRedirect();
        $copy = AssignmentLetter::latest('id')->first();
        $this->assertNotSame($letter->id, $copy->id);
        $this->assertTrue($copy->isDraft());
        $this->assertNull($copy->number);
        $this->assertNull($copy->initial_boxes);
        $this->assertCount(2, $copy->employees);
        $this->assertCount(1, $copy->destinations);
    }

    public function test_print_renders_letter_like_the_word_template(): void
    {
        $letter = $this->storeDraft();
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id));

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.print', $letter->id))
            ->assertOk()
            ->assertSee('SURAT TUGAS')
            ->assertSee('001/ST-ATC/I/2026')
            ->assertSee('Yogyakarta, 05 Januari 2026')
            ->assertSee('Sofyan Ariyanto, S.T')
            ->assertSee('002/SOFTW/I/2024')
            ->assertSee('Untuk Riset dan update firmware di Bendungan Ciawi yang beralamat di Jl. Panatraco, Megamendung, Bogor, Jawa Barat dan berjarak -/+ 590 km dari PT. Arta Teknologi Comunindo.')
            ->assertSee('Yang bersangkutan akan berangkat pada 06 Januari 2026, memulai pekerjaan pada 07 Januari 2026 dan akan kembali pada 09 Januari 2026 (3 hari kerja).')
            ->assertSee('Kereta Api')
            ->assertSee('GA HSE')
            ->assertDontSee('DRAFT')
            ->assertDontSee('<ol class="destinations">', false);
    }

    public function test_signed_copy_is_uploaded_after_issue_and_shown_to_participants(): void
    {
        Storage::fake('local');
        $letter = $this->storeDraft();
        $upload = fn (UploadedFile $file, string $role = 'hr_admin') => $this->asRole($role)
            ->post(route('admin.assignment-letters.signed.store', $letter->id), ['signed_file' => $file]);

        $upload(UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf'))->assertSessionHas('error');
        $letter = $this->issue($letter);

        $upload(UploadedFile::fake()->create('scan.docx', 20))->assertSessionHasErrors('signed_file');
        $upload(UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf'), 'finance_admin')->assertForbidden();

        // Tombol "Upload Surat Tugas" di kolom Aksi daftar surat; setelah unggah kembali ke daftar.
        $index = route('admin.assignment-letters.index');
        $this->asRole('hr_admin')->get($index)
            ->assertSee(route('admin.assignment-letters.signed.store', $letter->id))
            ->assertSee('Upload Surat Tugas');
        $this->asRole('finance_admin')->get($index)->assertDontSee(route('admin.assignment-letters.signed.store', $letter->id));
        $this->asRole('hr_admin')->from($index)
            ->post(route('admin.assignment-letters.signed.store', $letter->id), ['signed_file' => UploadedFile::fake()->create('scan.pdf', 200, 'application/pdf')])
            ->assertRedirect($index)
            ->assertSessionHas('success', 'Surat bertanda tangan 001/ST-ATC/I/2026 diunggah.');

        $letter->refresh();
        $this->assertSame('Surat Tugas 001-ST-ATC-I-2026 (TTD).pdf', $letter->signed_file_name);
        $this->assertSame(self::ADMIN, $letter->signed_uploaded_by);
        Storage::disk('local')->assertExists($letter->signed_file_path);

        $this->asRole('hr_admin')->get($index)
            ->assertSee('Surat sudah diunggah')
            ->assertSee('Lihat Surat Tugas')
            ->assertDontSee('Upload Surat Tugas');
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $letter->id))
            ->assertSee($letter->signed_file_name)
            ->assertSee(route('admin.assignment-letters.signed.show', $letter->id));
        $this->asRole('finance_admin')->get(route('admin.assignment-letters.signed.show', $letter->id))->assertOk();

        // Peserta melihatnya dari halaman cetak portal; karyawan lain tidak.
        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.assignment-letters.print', $letter->id))
            ->assertSee(route('employee.assignment-letters.signed', $letter->id));
        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.assignment-letters.signed', $letter->id))->assertOk();
        $this->withSession(['employee_id' => self::DIRECTOR])
            ->get(route('employee.assignment-letters.signed', $letter->id))->assertNotFound();

        // Unggahan baru menggantikan file lama.
        $oldPath = $letter->signed_file_path;
        $upload(UploadedFile::fake()->image('scan.jpg'))->assertSessionHas('success', 'Surat bertanda tangan 001/ST-ATC/I/2026 diganti.');
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertStringEndsWith('(TTD).jpg', $letter->fresh()->signed_file_name);

        $this->asRole('hr_admin')->delete(route('admin.assignment-letters.signed.destroy', $letter->id))->assertSessionHas('success');
        $this->assertFalse($letter->fresh()->hasSignedFile());
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.index'))->assertSee('Belum diunggah');
    }

    public function test_print_numbers_destinations_when_there_are_several(): void
    {
        $letter = $this->storeDraft([
            'purpose' => 'melaksanakan beberapa pekerjaan',
            'destinations' => [
                ['activity' => 'Melakukan prospek ke BPBD Provinsi Jawa Tengah', 'address' => 'Jl. Imam Bonjol No.15, Kota Semarang', 'distance_km' => '139'],
                ['activity' => 'Melakukan kunjungan ke Dinas PUSDATARU', 'address' => 'Jl. Madukoro Raya, Kota Semarang', 'distance_km' => '141'],
            ],
        ]);

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.print', $letter->id))
            ->assertOk()
            ->assertSeeInOrder([
                'Untuk melaksanakan beberapa pekerjaan :',
                '<ol class="destinations">',
                '<li>Melakukan prospek ke BPBD Provinsi Jawa Tengah',
                '<li>Melakukan kunjungan ke Dinas PUSDATARU',
                '</ol>',
            ], false);
    }

    public function test_permissions_follow_roles(): void
    {
        $this->asRole('payroll_admin')->get(route('admin.assignment-letters.index'))->assertForbidden();

        $this->asRole('finance_admin')->get(route('admin.assignment-letters.index'))->assertOk();
        $this->asRole('finance_admin')->get(route('admin.assignment-letters.create'))->assertForbidden();
        $this->asRole('finance_admin')->get(route('admin.assignment-facilities.index'))->assertForbidden();

        $this->asRole('hr_admin')->get(route('admin.assignment-facilities.index'))->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $vehicle = Vehicle::create(['company_id' => 1, 'name' => 'Alvez', 'plate_number' => 'K 1734 AT', 'ownership' => 'company']);
        \App\Models\CommunicationDevice::create(['company_id' => 1, 'code' => 'HT 009/ATC/VIII/2024']);
        $draft = $this->storeDraft(['facilities' => [
            ['type' => 'transport', 'vehicle_id' => (string) $vehicle->id, 'description' => '', 'inspection_result' => '', 'inspected_on' => ''],
        ]]);

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.index'))->assertOk()->assertSee('Fadel Irsyad');
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.edit', $draft->id))->assertOk()
            // Label kendaraan ada di data JSON form (garis miring di-escape).
            ->assertSee('Alvez \/ milik perusahaan \/ K 1734 AT', false);
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.print', $draft->id))->assertOk()->assertSee('DRAFT');
        $this->asRole('hr_admin')->get(route('admin.assignment-facilities.index'))->assertOk()
            ->assertSee('K 1734 AT')->assertSee('HT 009/ATC/VIII/2024');
    }

    public function test_letters_are_scoped_to_admin_company(): void
    {
        $letter = $this->storeDraft();
        DB::table('employees')->where('id', self::OTHER_COMPANY)->update(['role' => 'hr_admin']);

        $this->withSession(['admin_id' => self::OTHER_COMPANY])
            ->get(route('admin.assignment-letters.show', $letter->id))
            ->assertNotFound();
    }

    public function test_conflicts_warn_about_leave_and_overlapping_letters(): void
    {
        $vehicle = Vehicle::create(['company_id' => 1, 'name' => 'Alvez', 'plate_number' => 'K 1734 AT', 'ownership' => 'company']);
        $withVehicle = ['facilities' => [['type' => 'transport', 'vehicle_id' => (string) $vehicle->id, 'description' => '', 'inspection_result' => '', 'inspected_on' => '']]];

        $first = $this->storeDraft($withVehicle);
        $this->storeDraft($withVehicle + ['employees' => [['employee_id' => self::PERMANENT, 'position' => '']]]);
        DB::table('leave_requests')->insert([
            'employee_id' => self::CONTRACT, 'start_date' => '2026-01-08', 'end_date' => '2026-01-10', 'status' => 'approved',
        ]);
        // Surat yang dibatalkan tidak dihitung bentrok.
        $cancelled = $this->storeDraft();
        $cancelled->update(['status' => AssignmentLetter::STATUS_CANCELLED]);

        $conflicts = AssignmentLetterConflicts::for($first->fresh());

        $this->assertCount(3, $conflicts);
        $this->assertStringContainsString('Prastowo Dian punya cuti 08 Januari 2026 – 10 Januari 2026 (sudah disetujui)', $conflicts[0]);
        $this->assertStringContainsString("Fadel Irsyad juga tercantum di surat tugas yang masih draft", $conflicts[1]);
        $this->assertStringContainsString('Kendaraan Alvez K 1734 AT juga dipakai', $conflicts[2]);

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $first->id))
            ->assertOk()
            ->assertSee('Periksa jadwal yang bentrok');
    }

    public function test_facility_seeder_is_idempotent_and_keeps_edits(): void
    {
        $this->seed(\Database\Seeders\AssignmentFacilitySeeder::class);

        $this->assertSame(7, Vehicle::where('company_id', 1)->count());
        $this->assertSame(6, \App\Models\CommunicationDevice::where('company_id', 1)->count());
        $this->assertSame('Alvez / milik perusahaan / K 1734 AT', Vehicle::where('plate_number', 'K 1734 AT')->first()->letterLabel());

        Vehicle::where('plate_number', 'K 1734 AT')->update(['name' => 'Alvez Hitam', 'is_active' => false]);
        $this->seed(\Database\Seeders\AssignmentFacilitySeeder::class);

        $this->assertSame(7, Vehicle::count());
        $alvez = Vehicle::where('plate_number', 'K 1734 AT')->first();
        $this->assertSame('Alvez Hitam', $alvez->name);
        $this->assertFalse($alvez->is_active);
    }

    public function test_initial_boxes_follow_superior_levels(): void
    {
        DB::table('employees')->insert([
            ['id' => 10, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Leader Software', 'job_level' => 3, 'employment_status' => 'permanent', 'role' => 'employee', 'is_active' => true],
            ['id' => 11, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Manager Software', 'job_level' => 2, 'employment_status' => 'permanent', 'role' => 'employee', 'is_active' => true],
            ['id' => 12, 'company_id' => 1, 'department_id' => 2, 'full_name' => 'Manager Hardware', 'job_level' => 2, 'employment_status' => 'permanent', 'role' => 'employee', 'is_active' => true],
        ]);
        // Fadel (L4 Software): rantai approval cuti lengkap L3 → L2 → Direktur.
        foreach ([10, 11, self::DIRECTOR] as $step => $approverId) {
            DB::table('employee_approvers')->insert(['employee_id' => self::PERMANENT, 'request_type' => 'leave', 'step_order' => $step + 1, 'approver_id' => $approverId]);
        }
        // Prastowo (L4 Hardware): tanpa rantai, pakai atasan langsung (manager_id) sebagai cadangan.
        DB::table('employees')->where('id', self::CONTRACT)->update(['manager_id' => 12]);
        DB::table('employees')->where('id', 12)->update(['manager_id' => self::DIRECTOR]);

        $draft = $this->storeDraft();
        $this->assertSame(['LV 3 SOFTWARE', 'LV 2 SOFTWARE', 'LV 2 HARDWARE', 'HRD', 'GA HSE'], $draft->initialBoxes());

        // Peserta level 3 hanya butuh level 2; peserta level 2 tidak butuh atasan.
        DB::table('employee_approvers')->insert([
            ['employee_id' => 10, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => 11],
            ['employee_id' => 11, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => self::DIRECTOR],
        ]);
        DB::table('employees')->whereIn('id', [10, 11])->update(['employment_status' => 'permanent']);
        $leaders = $this->storeDraft(['employees' => [['employee_id' => 10, 'position' => ''], ['employee_id' => 11, 'position' => '']]]);
        $this->assertSame(['LV 2 SOFTWARE', 'HRD', 'GA HSE'], $leaders->initialBoxes());

        // Pratinjau form membawa data atasan yang sama.
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.create'))->assertOk()->assertSee('LV 3 SOFTWARE');

        // Saat terbit, label dibekukan; perubahan rantai sesudahnya tidak mengubah surat.
        $issued = $this->issue($draft);
        DB::table('employee_approvers')->where('employee_id', self::PERMANENT)->delete();
        $this->assertSame(['LV 3 SOFTWARE', 'LV 2 SOFTWARE', 'LV 2 HARDWARE', 'HRD', 'GA HSE'], $issued->fresh()->initialBoxes());
    }

    public function test_two_superiors_in_one_unit_get_their_names(): void
    {
        DB::table('employees')->insert([
            ['id' => 10, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Budi', 'job_level' => 2, 'employment_status' => 'permanent', 'role' => 'employee', 'is_active' => true],
            ['id' => 11, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Citra', 'job_level' => 2, 'employment_status' => 'permanent', 'role' => 'employee', 'is_active' => true],
        ]);
        DB::table('employees')->where('id', self::PERMANENT)->update(['manager_id' => 10]);
        DB::table('employees')->where('id', self::CONTRACT)->update(['manager_id' => 11]);

        $this->assertSame(['LV 2 SOFTWARE', 'LV 2 SOFTWARE (Citra)', 'HRD', 'GA HSE'], $this->storeDraft()->initialBoxes());
    }

    public function test_availability_flags_leave_and_other_letters_while_picking(): void
    {
        $draft = $this->storeDraft(['employees' => [['employee_id' => self::PERMANENT, 'position' => '']]]);
        DB::table('leave_requests')->insert([
            'employee_id' => self::CONTRACT, 'start_date' => '2026-01-07', 'end_date' => '2026-01-07', 'status' => 'approved',
        ]);

        $data = $this->asRole('hr_admin')
            ->getJson(route('admin.assignment-letters.availability', ['from' => '2026-01-07', 'to' => '2026-01-08']))
            ->assertOk()
            ->json('data');

        $this->assertSame(['Cuti 07 Jan 2026 (sudah disetujui)'], $data[self::CONTRACT]);
        $this->assertSame(['Sudah di surat tugas yang masih draft (06 Jan – 09 Jan 2026)'], $data[self::PERMANENT]);
        $this->assertArrayNotHasKey(self::DIRECTOR, $data);

        // Saat mengedit draft itu sendiri, surat itu tidak dihitung bentrok.
        $data = $this->asRole('hr_admin')
            ->getJson(route('admin.assignment-letters.availability', ['from' => '2026-01-07', 'to' => '2026-01-08', 'exclude' => $draft->id]))
            ->json('data');
        $this->assertArrayNotHasKey(self::PERMANENT, $data);
        $this->assertArrayHasKey(self::CONTRACT, $data);

        $this->asRole('hr_admin')
            ->getJson(route('admin.assignment-letters.availability', ['from' => '2026-01-08', 'to' => '2026-01-07']))
            ->assertStatus(422);
        $this->asRole('finance_admin')
            ->getJson(route('admin.assignment-letters.availability', ['from' => '2026-01-07', 'to' => '2026-01-08']))
            ->assertForbidden();

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.edit', $draft->id))
            ->assertOk()
            ->assertSee('data-exclude="'.$draft->id.'"', false)
            ->assertSee(route('admin.assignment-letters.availability'), false);
    }

    public function test_sentences_follow_word_letter_patterns(): void
    {
        $letter = new AssignmentLetter([
            'departure_date' => '2026-01-05',
            'work_start_date' => '2026-01-05',
            'return_date' => '2026-01-05',
        ]);
        $this->assertSame('Yang bersangkutan akan berangkat dan memulai pekerjaan pada 05 Januari 2026 dan akan kembali pada 05 Januari 2026 (1 hari kerja).', $letter->scheduleSentence());

        $single = new AssignmentLetterDestination(['activity' => 'OP perangkat PSDA DIY di Pos Plataran', 'address' => 'Plataran, Kalasan, Sleman', 'distance_km' => '8.3']);
        $letter->setRelation('destinations', collect([$single]));
        $this->assertNull($letter->purposeHeading());
        $this->assertSame(['Untuk OP perangkat PSDA DIY di Pos Plataran yang beralamat di Plataran, Kalasan, Sleman dan berjarak -/+ 8,3 km dari PT. Arta Teknologi Comunindo.'], $letter->destinationSentences());

        $letter->purpose = 'melaksanakan beberapa pekerjaan';
        $this->assertSame('Untuk melaksanakan beberapa pekerjaan :', $letter->purposeHeading());
        $this->assertStringStartsWith('OP perangkat', $letter->destinationSentences()[0]);

        $noAddress = new AssignmentLetterDestination(['activity' => 'Kunjungan prospek di Jakarta.', 'distance_km' => '556']);
        $this->assertSame('Kunjungan prospek di Jakarta yang berjarak -/+ 556 km dari PT. Arta Teknologi Comunindo.', $noAddress->sentence());
    }
}
