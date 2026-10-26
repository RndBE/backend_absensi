<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\AssignmentLetterApprovalController;
use App\Http\Controllers\Api\ProfileController;
use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Paraf digital surat tugas di aplikasi mobile: antrean, detail, setuju/tolak, dan tanda tangan
 * digital di profil. Aturannya sama dengan portal karyawan (lihat AssignmentLetterApprovalTest).
 */
class ApiAssignmentApprovalTest extends AssignmentLetterTestCase
{
    private const LEADER_SOFTWARE = 10;
    private const MANAGER_SOFTWARE = 11;
    private const MANAGER_HARDWARE = 12;
    private const GA_HSE = 13;
    private const SUPERADMIN = 14;

    private AssignmentLetter $letter;

    protected function setUp(): void
    {
        parent::setUp();

        config(['assignment_letters.require_approval' => true]);
        Storage::fake('public');
        Storage::fake('local');

        $person = fn (int $id, string $name, int $level, int $department, string $role = 'employee') => [
            'id' => $id, 'company_id' => 1, 'department_id' => $department, 'full_name' => $name,
            'position' => 'Staff', 'job_level' => $level, 'employment_status' => 'permanent',
            'role' => $role, 'is_active' => true, 'employee_code' => sprintf('%03d/X/I/2024', $id),
        ];

        DB::table('employees')->insert([
            $person(self::LEADER_SOFTWARE, 'Leader Software', 3, 1),
            $person(self::MANAGER_SOFTWARE, 'Manager Software', 2, 1),
            $person(self::MANAGER_HARDWARE, 'Manager Hardware', 2, 2),
            $person(self::GA_HSE, 'Widya GA HSE', 4, 3, 'admin'),
            $person(self::SUPERADMIN, 'Admin Sistem', 4, 3, 'superadmin'),
        ]);

        // Fadel: L3 Software lalu L2 Software. Prastowo: langsung L2 Hardware.
        DB::table('employee_approvers')->insert([
            ['employee_id' => self::PERMANENT, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => self::LEADER_SOFTWARE],
            ['employee_id' => self::PERMANENT, 'request_type' => 'leave', 'step_order' => 2, 'approver_id' => self::MANAGER_SOFTWARE],
            ['employee_id' => self::CONTRACT, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => self::MANAGER_HARDWARE],
        ]);

        $this->letter = $this->issue($this->storeDraft());
        // Pemberitahuan penerbitan tidak relevan di sini; yang dihitung hanya yang muncul sesudahnya.
        DB::table('notifications')->delete();
    }

    private function asEmployee(int $id): static
    {
        Sanctum::actingAs(Employee::findOrFail($id));

        return $this;
    }

    private function approvalFor(string $role, ?int $approverId = null): AssignmentLetterApproval
    {
        return $this->letter->approvals()
            ->where('role', $role)
            ->when($approverId, fn ($q) => $q->where('approver_id', $approverId))
            ->firstOrFail();
    }

    /** Beri $employeeId gambar tanda tangan; mengembalikan path-nya di disk publik. */
    private function giveSignature(int $employeeId, string $name = 'ttd.png'): string
    {
        $path = UploadedFile::fake()->image($name, 300, 100)->store('employees/signatures', 'public');
        DB::table('employees')->where('id', $employeeId)->update(['signature' => $path]);

        return $path;
    }

    private function approve(int $as, array $body = [], ?int $letterId = null)
    {
        return $this->asEmployee($as)->postJson('/api/assignment-approvals/'.($letterId ?? $this->letter->id).'/approve', $body);
    }

    private function reject(int $as, array $body = [], ?int $letterId = null)
    {
        return $this->asEmployee($as)->postJson('/api/assignment-approvals/'.($letterId ?? $this->letter->id).'/reject', $body);
    }

    private function queue(int $as): array
    {
        return $this->asEmployee($as)->getJson('/api/approvals')->assertOk()->json('data.assignment_letter');
    }

    // ── antrean ───────────────────────────────────────────────────────────────

    public function test_each_superior_sees_the_letter_waiting_for_their_initial(): void
    {
        $row = $this->queue(self::LEADER_SOFTWARE)[0];

        $this->assertCount(1, $this->queue(self::LEADER_SOFTWARE));
        $this->assertSame($this->letter->id, $row['id']);
        $this->assertSame($this->approvalFor('level3')->id, $row['approval_id']);
        $this->assertSame('LV 3 SOFTWARE', $row['label']);
        $this->assertSame('001/ST-ATC/I/2026', $row['number']);
        $this->assertSame('Riset dan update firmware di Bendungan Ciawi', $row['title']);
        $this->assertSame('Fadel Irsyad, Prastowo Dian', $row['participant_names']);
        $this->assertSame('Fadel Irsyad, Prastowo Dian', $row['employee']['full_name'], 'bentuk yang sama dengan jenis pengajuan lain');
        $this->assertSame('2026-01-06', $row['departure_date']);
        $this->assertNotNull($row['created_at']);

        $this->assertSame(['LV 2 SOFTWARE'], array_column($this->queue(self::MANAGER_SOFTWARE), 'label'));
        $this->assertSame(['LV 2 HARDWARE'], array_column($this->queue(self::MANAGER_HARDWARE), 'label'));
    }

    public function test_hrd_and_ga_hse_see_their_own_steps(): void
    {
        $this->assertSame(['HRD'], array_column($this->queue(self::ADMIN), 'label'), 'hr_admin hanya memegang izin HRD');
        // Role admin memegang izin HRD dan GA HSE sekaligus, jadi kedua langkah muncul.
        $this->assertContains('GA HSE', array_column($this->queue(self::GA_HSE), 'label'));
    }

    public function test_people_outside_the_flow_see_nothing(): void
    {
        foreach ([self::DIRECTOR, self::PERMANENT, self::OTHER_COMPANY] as $who) {
            $this->assertSame([], $this->queue($who), "pengguna $who");
        }
    }

    /** Pemegang izin HRD dan GA HSE sungguhan keluar, jadi hanya superadmin yang tersisa. */
    private function onlySuperadminHoldsHrdAndGaHse(): void
    {
        DB::table('employees')->whereIn('id', [self::ADMIN, self::GA_HSE])->update(['is_active' => false]);
    }

    public function test_the_superadmin_yields_hrd_and_ga_hse_steps_to_the_real_holders(): void
    {
        $this->assertSame([], $this->queue(self::SUPERADMIN), 'ada pemegang izin HRD dan GA HSE yang aktif');
        $this->assertSame(['HRD', 'GA HSE'], array_column($this->queue(self::GA_HSE), 'label'), 'role admin memegang kedua izin');
    }

    public function test_the_superadmin_holds_both_hrd_and_ga_hse_steps_when_no_real_holder_is_left(): void
    {
        $this->onlySuperadminHoldsHrdAndGaHse();

        $rows = $this->queue(self::SUPERADMIN);

        $this->assertSame(['HRD', 'GA HSE'], array_column($rows, 'label'));
        $this->assertCount(2, array_unique(array_column($rows, 'approval_id')));
    }

    public function test_the_superadmin_cannot_decide_the_ga_hse_step_while_its_holder_is_active(): void
    {
        // Bukan pihak di surat ini lagi, jadi suratnya pun tidak terbuka baginya.
        $this->approve(self::SUPERADMIN, ['approval_id' => $this->approvalFor('ga_hse')->id])->assertNotFound();

        $this->assertSame('pending', $this->approvalFor('ga_hse')->fresh()->status);
        $this->assertNull($this->approvalFor('ga_hse')->fresh()->acted_by);
    }

    public function test_a_decided_step_leaves_the_queue(): void
    {
        $this->approve(self::LEADER_SOFTWARE)->assertOk();

        $this->assertSame([], $this->queue(self::LEADER_SOFTWARE));
        $this->assertSame(['LV 2 SOFTWARE'], array_column($this->queue(self::MANAGER_SOFTWARE), 'label'), 'paraf lain tidak terpengaruh');
    }

    public function test_a_letter_that_got_its_signed_scan_is_no_longer_queued(): void
    {
        DB::table('assignment_letters')->where('id', $this->letter->id)->update(['signed_file_path' => 'assignment-letters/signed/x.pdf']);

        $this->assertSame([], $this->queue(self::LEADER_SOFTWARE));
    }

    // ── detail ────────────────────────────────────────────────────────────────

    public function test_an_approver_sees_the_letter_with_every_initial_box(): void
    {
        $data = $this->asEmployee(self::LEADER_SOFTWARE)->getJson('/api/assignment-approvals/'.$this->letter->id)->assertOk()->json('data');

        $this->assertSame('001/ST-ATC/I/2026', $data['number']);
        $this->assertSame('issued', $data['status']);
        $this->assertSame('Fadel Irsyad, Prastowo Dian', $data['participant_names']);
        $this->assertSame(
            ['LV 3 SOFTWARE', 'LV 2 SOFTWARE', 'LV 2 HARDWARE', 'HRD', 'GA HSE'],
            array_column($data['approvals'], 'label')
        );
        $this->assertSame(['pending'], array_unique(array_column($data['approvals'], 'status')));
        $this->assertSame('Leader Software', $data['approvals'][0]['approver_name']);
        $this->assertSame([['id' => $this->approvalFor('level3')->id, 'label' => 'LV 3 SOFTWARE']], $data['my_approvals']);
        $this->assertFalse($data['has_signature']);
        $this->assertStringContainsString('/files/assignment-letters/'.$this->letter->id.'/print?', $data['print_url']);
        $this->assertNull($data['rejection_reason']);
    }

    public function test_the_detail_tells_whether_the_viewer_has_a_signature(): void
    {
        $this->giveSignature(self::LEADER_SOFTWARE);

        $this->assertTrue($this->asEmployee(self::LEADER_SOFTWARE)->getJson('/api/assignment-approvals/'.$this->letter->id)->json('data.has_signature'));
        $this->assertFalse($this->asEmployee(self::MANAGER_SOFTWARE)->getJson('/api/assignment-approvals/'.$this->letter->id)->json('data.has_signature'));
    }

    public function test_only_people_in_the_flow_can_open_it(): void
    {
        foreach ([self::LEADER_SOFTWARE, self::MANAGER_HARDWARE, self::ADMIN, self::GA_HSE] as $who) {
            $this->asEmployee($who)->getJson('/api/assignment-approvals/'.$this->letter->id)->assertOk();
        }

        // Superadmin bukan pihak di surat ini selama pemegang izin HRD/GA HSE sungguhan masih aktif.
        foreach ([self::DIRECTOR, self::PERMANENT, self::OTHER_COMPANY, self::SUPERADMIN] as $who) {
            $this->asEmployee($who)->getJson('/api/assignment-approvals/'.$this->letter->id)->assertNotFound();
        }
    }

    public function test_someone_who_already_decided_can_still_open_it_but_has_nothing_to_decide(): void
    {
        $this->approve(self::LEADER_SOFTWARE)->assertOk();

        $data = $this->asEmployee(self::LEADER_SOFTWARE)->getJson('/api/assignment-approvals/'.$this->letter->id)->assertOk()->json('data');

        $this->assertSame([], $data['my_approvals']);
        $this->assertSame('approved', $data['approvals'][0]['status']);
        $this->assertSame('Disetujui', $data['approvals'][0]['status_label']);
        $this->assertSame('Leader Software', $data['approvals'][0]['acted_by_name']);
        $this->assertNotNull($data['approvals'][0]['acted_at']);
    }

    public function test_an_unknown_letter_is_not_found(): void
    {
        $this->asEmployee(self::LEADER_SOFTWARE)->getJson('/api/assignment-approvals/999')->assertNotFound();
    }

    public function test_the_detail_needs_a_login(): void
    {
        $this->getJson('/api/assignment-approvals/'.$this->letter->id)->assertUnauthorized();
    }

    // ── setuju ────────────────────────────────────────────────────────────────

    public function test_approving_records_the_decision_and_copies_the_signature_into_the_box(): void
    {
        $source = $this->giveSignature(self::LEADER_SOFTWARE);
        $approval = $this->approvalFor('level3');

        $response = $this->approve(self::LEADER_SOFTWARE, ['notes' => '  Setuju, silakan berangkat  ']);

        $response->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Surat tugas disetujui. Tanda tangan Anda dicatat di kolom paraf.');
        $approval->refresh();
        $this->assertSame('approved', $approval->status);
        $this->assertSame(self::LEADER_SOFTWARE, (int) $approval->acted_by);
        $this->assertSame('Setuju, silakan berangkat', $approval->notes);
        $this->assertNotNull($approval->acted_at);
        $this->assertNotNull($approval->signature_path);
        $this->assertNotSame($source, $approval->signature_path, 'salinan milik surat, bukan berkas profil');
        Storage::disk('public')->assertExists($approval->signature_path);

        $box = collect($response->json('data.approvals'))->firstWhere('id', $approval->id);
        $this->assertSame('approved', $box['status']);
        $this->assertNotNull($box['signature_url']);
        $this->assertSame([], $response->json('data.my_approvals'));
    }

    public function test_approving_without_a_signature_still_counts_but_says_so(): void
    {
        $approval = $this->approvalFor('level3');

        $response = $this->approve(self::LEADER_SOFTWARE)->assertOk();

        $this->assertStringContainsString('belum punya tanda tangan digital', $response->json('message'));
        $approval->refresh();
        $this->assertSame('approved', $approval->status);
        $this->assertNull($approval->signature_path);
        $this->assertNull(collect($response->json('data.approvals'))->firstWhere('id', $approval->id)['signature_url']);
    }

    public function test_the_notes_are_optional_when_approving(): void
    {
        $this->approve(self::LEADER_SOFTWARE)->assertOk();

        $this->assertNull($this->approvalFor('level3')->fresh()->notes);
    }

    public function test_a_changed_signature_does_not_alter_an_initial_already_given(): void
    {
        $this->giveSignature(self::LEADER_SOFTWARE);
        $this->approve(self::LEADER_SOFTWARE)->assertOk();
        $given = $this->approvalFor('level3')->fresh()->signature_path;
        $before = Storage::disk('public')->get($given);

        $this->asEmployee(self::LEADER_SOFTWARE)
            ->post('/api/profile/signature', ['signature' => UploadedFile::fake()->image('baru.png', 50, 20)])
            ->assertOk();

        Storage::disk('public')->assertExists($given);
        $this->assertSame($before, Storage::disk('public')->get($given));
    }

    public function test_the_last_initial_completes_the_letter_and_tells_the_staff(): void
    {
        foreach ([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE, self::ADMIN, self::GA_HSE] as $who) {
            $this->approve($who)->assertOk();
        }

        $this->assertNotNull($this->letter->fresh()->approved_at);
        $this->assertSame('Paraf Surat Tugas Lengkap', DB::table('notifications')->where('employee_id', self::ADMIN)->value('title'));
    }

    public function test_someone_without_a_step_waiting_cannot_approve(): void
    {
        // Peserta ikut membuka detail lewat jalurnya sendiri, tetapi tidak memegang paraf.
        $this->approve(self::DIRECTOR)->assertNotFound();
        $this->approve(self::OTHER_COMPANY)->assertNotFound();
        $this->assertSame(0, $this->letter->approvals()->where('status', 'approved')->count());
    }

    public function test_approving_twice_is_refused_the_second_time(): void
    {
        $this->approve(self::LEADER_SOFTWARE)->assertOk();

        $this->approve(self::LEADER_SOFTWARE)
            ->assertForbidden()
            ->assertJsonPath('message', 'Tidak ada persetujuan yang menunggu Anda untuk surat ini.');
        $this->assertSame(1, $this->letter->approvals()->where('status', 'approved')->count());
    }

    public function test_a_letter_with_a_signed_scan_no_longer_takes_initials(): void
    {
        DB::table('assignment_letters')->where('id', $this->letter->id)->update(['signed_file_path' => 'assignment-letters/signed/x.pdf']);

        $this->approve(self::LEADER_SOFTWARE)->assertForbidden();
        $this->assertSame(0, $this->letter->approvals()->where('status', 'approved')->count());
    }

    public function test_notes_are_capped_at_one_thousand_characters(): void
    {
        $this->approve(self::LEADER_SOFTWARE, ['notes' => str_repeat('a', 1001)])->assertUnprocessable()->assertJsonValidationErrors('notes');
        $this->assertSame(0, $this->letter->approvals()->where('status', 'approved')->count());
    }

    // ── tolak ─────────────────────────────────────────────────────────────────

    public function test_rejecting_needs_a_reason(): void
    {
        $this->reject(self::LEADER_SOFTWARE)
            ->assertUnprocessable()
            ->assertJsonPath('errors.notes.0', 'Alasan penolakan wajib diisi supaya pembuat surat tahu apa yang harus diperbaiki.');
        $this->reject(self::LEADER_SOFTWARE, ['notes' => '   '])->assertUnprocessable();

        $this->assertSame('pending', $this->approvalFor('level3')->fresh()->status);
    }

    public function test_rejecting_marks_the_box_and_tells_the_staff_why(): void
    {
        $response = $this->reject(self::MANAGER_SOFTWARE, ['notes' => 'Tanggal berangkat bentrok dengan rapat']);

        $response->assertOk()->assertJsonPath('message', 'Surat tugas ditolak dan dikembalikan ke pembuatnya.');
        $approval = $this->approvalFor('level2', self::MANAGER_SOFTWARE)->fresh();
        $this->assertSame('rejected', $approval->status);
        $this->assertSame('Tanggal berangkat bentrok dengan rapat', $approval->notes);
        $this->assertSame(self::MANAGER_SOFTWARE, (int) $approval->acted_by);

        $letter = $this->letter->fresh();
        $this->assertSame('Tanggal berangkat bentrok dengan rapat', $letter->rejection_reason);
        $this->assertSame(self::MANAGER_SOFTWARE, (int) $letter->rejected_by);
        $this->assertSame('issued', $letter->status, 'surat tetap terbit; pembuatnya memperbaiki lalu mengajukan ulang');
        $this->assertSame('Tanggal berangkat bentrok dengan rapat', $response->json('data.rejection_reason'));
        $this->assertSame('Manager Software', $response->json('data.rejected_by'));
        $this->assertSame(1, DB::table('notifications')->where('employee_id', self::ADMIN)->where('title', 'Paraf Surat Tugas Ditolak')->count());
    }

    public function test_someone_without_a_step_waiting_cannot_reject(): void
    {
        $this->reject(self::DIRECTOR, ['notes' => 'tidak berhak'])->assertNotFound();
        $this->assertSame(0, $this->letter->approvals()->where('status', 'rejected')->count());
    }

    public function test_a_decided_step_cannot_be_rejected_afterwards(): void
    {
        $this->approve(self::LEADER_SOFTWARE)->assertOk();

        $this->reject(self::LEADER_SOFTWARE, ['notes' => 'berubah pikiran'])->assertForbidden();
        $this->assertSame('approved', $this->approvalFor('level3')->fresh()->status);
    }

    // ── pemegang beberapa paraf ───────────────────────────────────────────────

    public function test_without_an_approval_id_the_first_waiting_step_is_decided(): void
    {
        $this->onlySuperadminHoldsHrdAndGaHse();
        $this->approve(self::SUPERADMIN)->assertOk();

        $this->assertSame('approved', $this->approvalFor('hrd')->fresh()->status);
        $this->assertSame('pending', $this->approvalFor('ga_hse')->fresh()->status);
    }

    public function test_the_approval_id_picks_which_of_several_steps_is_decided(): void
    {
        $this->onlySuperadminHoldsHrdAndGaHse();
        $this->approve(self::SUPERADMIN, ['approval_id' => $this->approvalFor('ga_hse')->id])->assertOk();

        $this->assertSame('approved', $this->approvalFor('ga_hse')->fresh()->status);
        $this->assertSame('pending', $this->approvalFor('hrd')->fresh()->status);
        $this->assertSame(['HRD'], array_column($this->queue(self::SUPERADMIN), 'label'));
    }

    public function test_an_approval_id_that_is_not_yours_to_decide_is_refused(): void
    {
        $someoneElses = $this->approvalFor('level3')->id;

        $this->approve(self::GA_HSE, ['approval_id' => $someoneElses])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Anda tidak bisa memutuskan langkah ini sekarang (bukan giliran atau bukan hak Anda).');
        $this->reject(self::GA_HSE, ['approval_id' => $someoneElses, 'notes' => 'bukan milik saya'])->assertUnprocessable();

        $this->assertSame('pending', $this->approvalFor('level3')->fresh()->status);
        $this->assertSame('pending', $this->approvalFor('ga_hse')->fresh()->status);
    }

    // ── tanda tangan di profil ────────────────────────────────────────────────

    public function test_a_signature_can_be_uploaded_and_shows_in_the_profile(): void
    {
        $response = $this->asEmployee(self::LEADER_SOFTWARE)
            ->post('/api/profile/signature', ['signature' => UploadedFile::fake()->image('ttd.png', 300, 100)])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tanda tangan digital berhasil disimpan.')
            ->assertJsonPath('has_signature', true);

        $path = Employee::findOrFail(self::LEADER_SOFTWARE)->signature;
        $this->assertStringStartsWith('employees/signatures/', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertNotNull($response->json('signature'));
        $this->assertStringContainsString('/storage/', $response->json('signature'));

        $profile = $this->asEmployee(self::LEADER_SOFTWARE)->getJson('/api/profile')->assertOk();
        $this->assertTrue($profile->json('data.has_signature'));
        $this->assertNotNull($profile->json('data.signature'));
    }

    public function test_a_profile_without_a_signature_says_so(): void
    {
        $profile = $this->asEmployee(self::LEADER_SOFTWARE)->getJson('/api/profile')->assertOk();

        $this->assertFalse($profile->json('data.has_signature'));
        $this->assertNull($profile->json('data.signature'));
    }

    public function test_a_new_signature_replaces_the_old_file(): void
    {
        $old = $this->giveSignature(self::LEADER_SOFTWARE);

        $this->asEmployee(self::LEADER_SOFTWARE)
            ->post('/api/profile/signature', ['signature' => UploadedFile::fake()->image('baru.jpg', 200, 80)])
            ->assertOk();

        Storage::disk('public')->assertMissing($old);
        $new = Employee::findOrFail(self::LEADER_SOFTWARE)->signature;
        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertExists($new);
    }

    public function test_a_refused_signature_leaves_the_current_one_untouched(): void
    {
        $current = $this->giveSignature(self::LEADER_SOFTWARE);

        foreach ([
            ['berkas teks' => UploadedFile::fake()->create('ttd.txt', 10, 'text/plain'), 'File harus berupa gambar.'],
            ['format gif' => UploadedFile::fake()->image('ttd.gif'), 'Gambar harus berformat PNG, JPG, atau WEBP.'],
            ['terlalu besar' => UploadedFile::fake()->image('ttd.png')->size(2049), 'Ukuran gambar maksimal 2 MB.'],
        ] as $case) {
            [$file, $message] = array_values($case);

            $this->asEmployee(self::LEADER_SOFTWARE)->post('/api/profile/signature', ['signature' => $file], ['Accept' => 'application/json'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.signature.0', $message);
        }

        $this->asEmployee(self::LEADER_SOFTWARE)->post('/api/profile/signature', [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.signature.0', 'Pilih file gambar tanda tangan.');

        $this->assertSame($current, Employee::findOrFail(self::LEADER_SOFTWARE)->signature);
        Storage::disk('public')->assertExists($current);
    }

    public function test_the_signature_can_be_removed(): void
    {
        $path = $this->giveSignature(self::LEADER_SOFTWARE);

        $this->asEmployee(self::LEADER_SOFTWARE)->deleteJson('/api/profile/signature')
            ->assertOk()
            ->assertJsonPath('message', 'Tanda tangan digital dihapus.')
            ->assertJsonPath('has_signature', false)
            ->assertJsonPath('signature', null);

        $this->assertNull(Employee::findOrFail(self::LEADER_SOFTWARE)->signature);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_removing_a_signature_that_does_not_exist_is_harmless(): void
    {
        $this->asEmployee(self::LEADER_SOFTWARE)->deleteJson('/api/profile/signature')->assertOk()->assertJsonPath('has_signature', false);
    }

    public function test_one_person_cannot_touch_another_persons_signature(): void
    {
        $theirs = $this->giveSignature(self::MANAGER_SOFTWARE);

        $this->asEmployee(self::LEADER_SOFTWARE)->deleteJson('/api/profile/signature')->assertOk();
        $this->asEmployee(self::LEADER_SOFTWARE)->post('/api/profile/signature', ['signature' => UploadedFile::fake()->image('ttd.png')])->assertOk();

        $this->assertSame($theirs, Employee::findOrFail(self::MANAGER_SOFTWARE)->signature);
        Storage::disk('public')->assertExists($theirs);
    }

    public function test_the_signature_needs_a_login(): void
    {
        $this->postJson('/api/profile/signature', [])->assertUnauthorized();
        $this->deleteJson('/api/profile/signature')->assertUnauthorized();
    }

    // ── rute ──────────────────────────────────────────────────────────────────

    public function test_the_routes_exist_behind_sanctum_and_resolve_to_the_right_actions(): void
    {
        foreach ([
            ['GET', 'api/assignment-approvals/12', AssignmentLetterApprovalController::class.'@show'],
            ['POST', 'api/assignment-approvals/12/approve', AssignmentLetterApprovalController::class.'@approve'],
            ['POST', 'api/assignment-approvals/12/reject', AssignmentLetterApprovalController::class.'@reject'],
            ['POST', 'api/profile/signature', ProfileController::class.'@uploadSignature'],
            ['DELETE', 'api/profile/signature', ProfileController::class.'@deleteSignature'],
        ] as [$method, $uri, $action]) {
            $route = Route::getRoutes()->match(Request::create('/'.$uri, $method));

            $this->assertSame($action, $route->getActionName(), "$method $uri");
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "$method $uri harus butuh login");
        }
    }

    public function test_the_letter_id_must_be_numeric(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        Route::getRoutes()->match(Request::create('/api/assignment-approvals/abc/approve', 'POST'));
    }
}
