<?php

namespace Tests\Feature;

use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
use App\Models\Employee;
use App\Support\AssignmentLetterApprovalFlow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Paraf digital surat tugas: surat terbit dulu (nomor keluar), lalu kotak paraf diminta ke
 * atasan L3/L2 peserta (paralel) → HRD → GA HSE. Rantai atasan diambil dari approval cuti.
 */
class AssignmentLetterApprovalTest extends AssignmentLetterTestCase
{
    private const LEADER_SOFTWARE = 10;
    private const MANAGER_SOFTWARE = 11;
    private const MANAGER_HARDWARE = 12;
    private const GA_HSE = 13;

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
            // 'admin' membawa assignment_letters.approve_hse; 'hr_admin' hanya approve_hrd.
            $person(self::GA_HSE, 'Widya GA HSE', 4, 3, 'admin'),
        ]);

        // Fadel: L3 Software → L2 Software. Prastowo: langsung L2 Hardware.
        DB::table('employee_approvers')->insert([
            ['employee_id' => self::PERMANENT, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => self::LEADER_SOFTWARE],
            ['employee_id' => self::PERMANENT, 'request_type' => 'leave', 'step_order' => 2, 'approver_id' => self::MANAGER_SOFTWARE],
            ['employee_id' => self::CONTRACT, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => self::MANAGER_HARDWARE],
            // Rantai anggaran punya Finance di ujung; tidak boleh jadi kotak paraf.
            ['employee_id' => self::PERMANENT, 'request_type' => 'budget', 'step_order' => 1, 'approver_id' => self::GA_HSE],
        ]);
    }

    /** Terbitkan draft; kotak paraf langsung diminta. */
    private function publish(?AssignmentLetter $letter = null): AssignmentLetter
    {
        $letter ??= $this->storeDraft();
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id))->assertSessionHas('success');

        return $letter->fresh();
    }

    private function approvalFor(AssignmentLetter $letter, string $role): AssignmentLetterApproval
    {
        return $letter->approvals()->where('role', $role)->firstOrFail();
    }

    private function approveAsSuperior(AssignmentLetter $letter, int $employeeId)
    {
        return $this->withSession(['employee_id' => $employeeId])
            ->post(route('employee.assignment-approvals.approve', $letter->id));
    }

    private function approveAsHrd(AssignmentLetter $letter)
    {
        return $this->asRole('hr_admin')->post(route('admin.assignment-letters.approve', [$letter->id, $this->approvalFor($letter, 'hrd')->id]));
    }

    private function approveAsGaHse(AssignmentLetter $letter)
    {
        return $this->asRole('admin', self::GA_HSE)
            ->post(route('admin.assignment-letters.approve', [$letter->id, $this->approvalFor($letter, 'ga_hse')->id]));
    }

    private function approveEveryone(AssignmentLetter $letter): void
    {
        foreach ([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE] as $id) {
            $this->approveAsSuperior($letter, $id);
        }
        $this->approveAsHrd($letter);
        $this->approveAsGaHse($letter);
    }

    private function upload(AssignmentLetter $letter)
    {
        return $this->asRole('hr_admin')->post(route('admin.assignment-letters.signed.store', $letter->id), [
            'signed_file' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
        ]);
    }

    public function test_issue_assigns_the_number_and_asks_for_initials_from_the_leave_chain(): void
    {
        $letter = $this->publish();

        // Terbit seperti biasa: nomor keluar, status terbit, peserta diberi tahu.
        $this->assertTrue($letter->isIssued());
        $this->assertSame('001/ST-ATC/I/2026', $letter->number);
        $this->assertSame(1, DB::table('notifications')->where('employee_id', self::PERMANENT)->where('title', 'Surat Tugas Baru')->count());

        // Paraf diminta sekaligus: kotak dari rantai CUTI (bukan anggaran), HRD, lalu GA HSE.
        $this->assertSame(
            [['LV 3 SOFTWARE', 1], ['LV 2 SOFTWARE', 1], ['LV 2 HARDWARE', 1], ['HRD', 2], ['GA HSE', 3]],
            $letter->approvals->map(fn ($a) => [$a->label, $a->step_group])->all()
        );
        $this->assertSame([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE], $letter->approvals->take(3)->pluck('approver_id')->all());
        // Kolom paraf dari approval; initial_boxes hanya untuk kolom tambahan ketikan HRD.
        $this->assertNull($letter->initial_boxes);
        $this->assertSame('pending', $letter->approvalState());
    }

    public function test_issue_is_blocked_when_a_participant_has_no_superior_and_burns_no_number(): void
    {
        DB::table('employees')->insert([
            'id' => 30, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Aris Tanpa Rantai', 'position' => 'Staff',
            'job_level' => 4, 'employment_status' => 'permanent', 'role' => 'employee', 'is_active' => true, 'employee_code' => '030/X/I/2024',
        ]);

        $letter = $this->storeDraft(['employees' => [['employee_id' => 30, 'position' => '']]]);
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.issue', $letter->id))->assertSessionHas('error');

        $letter = $letter->fresh();
        $this->assertTrue($letter->isDraft());
        $this->assertNull($letter->number);
        $this->assertSame(0, $letter->approvals()->count());
        $this->assertSame(0, \App\Models\DocumentSequence::lastNumber(1, \App\Models\DocumentSequence::TYPE_ASSIGNMENT_LETTER, 2026));
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $letter->id))
            ->assertSee('Aris Tanpa Rantai')
            ->assertSee('Belum bisa diterbitkan');
    }

    public function test_hrd_and_ga_hse_do_not_wait_for_the_superiors_or_each_other(): void
    {
        $letter = $this->publish();

        // GA HSE dan HRD boleh memparaf sejak surat terbit, dalam urutan apa pun.
        $this->approveAsGaHse($letter)->assertSessionHas('success');
        $this->approveAsHrd($letter)->assertSessionHas('success');
        $this->assertSame('pending', $letter->fresh()->approvalState(), 'atasan belum selesai');

        $this->approveAsSuperior($letter, self::LEADER_SOFTWARE);
        $this->approveAsSuperior($letter, self::MANAGER_SOFTWARE);
        $this->assertSame('pending', $letter->fresh()->approvalState());

        $this->approveAsSuperior($letter, self::MANAGER_HARDWARE);

        $letter = $letter->fresh();
        $this->assertSame('complete', $letter->approvalState());
        $this->assertNotNull($letter->approved_at);
        $this->assertTrue($letter->isIssued());
    }

    public function test_only_the_assigned_superior_or_permission_holder_can_decide(): void
    {
        $letter = $this->publish();

        // Peserta sendiri bukan pemberi paraf.
        $this->withSession(['employee_id' => self::PERMANENT])
            ->post(route('employee.assignment-approvals.approve', $letter->id))->assertNotFound();

        foreach ([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE] as $id) {
            $this->approveAsSuperior($letter, $id);
        }
        $this->approveAsHrd($letter);

        // hr_admin tidak punya izin GA HSE.
        $this->asRole('hr_admin')
            ->post(route('admin.assignment-letters.approve', [$letter->id, $this->approvalFor($letter, 'ga_hse')->id]))
            ->assertSessionHas('error');
        $this->assertTrue($this->approvalFor($letter, 'ga_hse')->fresh()->isPending());
    }

    public function test_rejection_keeps_the_letter_issued_and_resubmit_only_asks_the_rejector_again(): void
    {
        $letter = $this->publish();
        $this->approveAsSuperior($letter, self::LEADER_SOFTWARE);

        // Alasan wajib.
        $this->withSession(['employee_id' => self::MANAGER_HARDWARE])
            ->post(route('employee.assignment-approvals.reject', $letter->id))->assertSessionHasErrors('notes');
        $this->assertSame('pending', $letter->fresh()->approvalState());

        $this->withSession(['employee_id' => self::MANAGER_HARDWARE])
            ->post(route('employee.assignment-approvals.reject', $letter->id), ['notes' => 'Kendaraan belum diisi']);

        $letter = $letter->fresh();
        $this->assertTrue($letter->isIssued(), 'surat tetap terbit dan bernomor');
        $this->assertSame('001/ST-ATC/I/2026', $letter->number);
        $this->assertSame('rejected', $letter->approvalState());
        $this->assertSame('Kendaraan belum diisi', $letter->rejection_reason);
        $this->assertSame(1, DB::table('notifications')->where('employee_id', self::ADMIN)->where('title', 'Paraf Surat Tugas Ditolak')->count());

        $this->approveAsSuperior($letter, self::MANAGER_SOFTWARE);

        // HRD memperbaiki surat (boleh, belum ada pindaian) lalu mengajukan ulang.
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['city' => 'Jakarta']))->assertSessionHas('success');
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.resubmit', $letter->id))->assertSessionHas('success');

        $letter = $letter->fresh();
        $this->assertSame('pending', $letter->approvalState());
        $this->assertNull($letter->rejection_reason);
        $this->assertTrue($this->approvalFor($letter, 'level3')->isApproved(), 'yang sudah setuju tidak diulang');
        $this->assertTrue($letter->approvals()->where('approver_id', self::MANAGER_SOFTWARE)->first()->isApproved());
        $hardware = $letter->approvals()->where('approver_id', self::MANAGER_HARDWARE)->first();
        $this->assertTrue($hardware->isPending());
        $this->assertNull($hardware->notes);

        // Tidak ada yang ditolak lagi: ajukan ulang kosong ditolak sistem.
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.resubmit', $letter->id))->assertSessionHas('error');

        $this->approveAsSuperior($letter, self::MANAGER_HARDWARE);
        $this->approveAsHrd($letter)->assertSessionHas('success');
    }

    public function test_hrd_can_skip_a_stuck_step_and_the_box_stays_empty(): void
    {
        $letter = $this->publish();
        $stuck = $this->approvalFor($letter, 'level3');

        // Alasan wajib.
        $this->asRole('hr_admin')->post(route('admin.assignment-letters.skip', [$letter->id, $stuck->id]))->assertSessionHasErrors('notes');

        $this->asRole('hr_admin')
            ->post(route('admin.assignment-letters.skip', [$letter->id, $stuck->id]), ['notes' => 'Cuti panjang'])
            ->assertSessionHas('success');

        $stuck = $stuck->fresh();
        $this->assertSame(AssignmentLetterApproval::STATUS_SKIPPED, $stuck->status);
        $this->assertNull($stuck->signature_path);

        $this->approveAsSuperior($letter, self::MANAGER_SOFTWARE);
        $this->approveAsSuperior($letter, self::MANAGER_HARDWARE);
        $this->approveAsHrd($letter);
        $this->approveAsGaHse($letter);

        $this->assertSame('complete', $letter->fresh()->approvalState());
    }

    public function test_signed_scan_can_only_be_uploaded_after_every_initial_is_in(): void
    {
        $letter = $this->publish();

        $this->upload($letter)->assertSessionHas('error');
        $this->assertFalse($letter->fresh()->hasSignedFile());

        $this->approveEveryone($letter);
        $this->upload($letter)->assertSessionHas('success');
        $this->assertTrue($letter->fresh()->hasSignedFile());

        // Sudah final: tidak ada lagi yang bisa mengubah isi.
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['city' => 'Surabaya']))->assertSessionHas('error');
    }

    public function test_editing_after_issue_keeps_decisions_and_resyncs_when_participants_change(): void
    {
        $letter = $this->publish();
        $this->approveAsSuperior($letter, self::LEADER_SOFTWARE);

        // Ubah isi tanpa mengubah peserta: keputusan yang masuk tidak terganggu.
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $this->payload(['city' => 'Jakarta']))->assertSessionHas('success');
        $this->assertTrue($this->approvalFor($letter, 'level3')->fresh()->isApproved());
        $this->assertSame(5, $letter->approvals()->count());

        // Fadel dikeluarkan: atasan software tidak relevan lagi dan dilepas; yang lain tetap.
        $onlyPrastowo = ['employees' => [['employee_id' => self::CONTRACT, 'position' => '']]];
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $this->payload($onlyPrastowo))->assertSessionHas('success');
        $this->assertSame(['LV 2 HARDWARE', 'HRD', 'GA HSE'], $letter->approvals->pluck('label')->all());

        // Fadel kembali: atasannya diminta paraf lagi sebagai kotak baru dan dikabari.
        DB::table('notifications')->where('title', 'Surat Tugas Menunggu Persetujuan')->delete();
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $this->payload())->assertSessionHas('success');

        $letter = $letter->fresh();
        // Urutan cetak tetap L3, L2, lalu HRD/GA HSE walau kotak software baru dibuat belakangan.
        $this->assertSame(['LV 3 SOFTWARE', 'LV 2 SOFTWARE', 'LV 2 HARDWARE', 'HRD', 'GA HSE'], $letter->approvals->pluck('label')->all());
        $this->assertTrue($this->approvalFor($letter, 'level3')->isPending(), 'kotak baru, belum diputuskan');
        $this->assertSame(1, DB::table('notifications')->where('employee_id', self::LEADER_SOFTWARE)->where('title', 'Surat Tugas Menunggu Persetujuan')->count());
        $this->assertSame(0, DB::table('notifications')->where('employee_id', self::MANAGER_HARDWARE)->where('title', 'Surat Tugas Menunggu Persetujuan')->count(), 'yang tidak berubah tidak dikabari lagi');
        $this->assertSame('pending', $letter->approvalState());
    }

    public function test_edit_is_refused_when_a_new_participant_has_no_superior(): void
    {
        $letter = $this->publish();
        DB::table('employees')->insert([
            'id' => 30, 'company_id' => 1, 'department_id' => 1, 'full_name' => 'Aris Tanpa Rantai', 'position' => 'Staff',
            'job_level' => 4, 'employment_status' => 'permanent', 'role' => 'employee', 'is_active' => true, 'employee_code' => '030/X/I/2024',
        ]);

        $withAris = $this->payload(['employees' => [
            ['employee_id' => self::PERMANENT, 'position' => ''],
            ['employee_id' => 30, 'position' => ''],
        ]]);
        $this->asRole('hr_admin')->put(route('admin.assignment-letters.update', $letter->id), $withAris)->assertSessionHas('error');
        $this->assertCount(2, $letter->fresh()->employees);
    }

    public function test_approval_copies_the_signature_and_print_shows_it(): void
    {
        Storage::disk('public')->put('employees/signatures/leader.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
        DB::table('employees')->where('id', self::LEADER_SOFTWARE)->update(['signature' => 'employees/signatures/leader.png']);

        $letter = $this->publish();
        $this->approveAsSuperior($letter, self::LEADER_SOFTWARE);

        $approval = $this->approvalFor($letter, 'level3')->fresh();
        $this->assertSame("assignment-letters/signatures/{$letter->id}-{$approval->id}.png", $approval->signature_path);
        Storage::disk('public')->assertExists($approval->signature_path);

        // Ganti ttd di profil: surat tetap memakai salinan saat disetujui.
        DB::table('employees')->where('id', self::LEADER_SOFTWARE)->update(['signature' => null]);

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.print', $letter->id))
            ->assertOk()
            ->assertSee('paraf-img', false)
            ->assertSee('LV 3 SOFTWARE');
    }

    public function test_pending_letter_shows_up_for_the_superior_and_counts_as_pending(): void
    {
        $letter = $this->publish();

        $this->withSession(['employee_id' => self::LEADER_SOFTWARE])
            ->get(route('employee.assignment-approvals.show', $letter->id))
            ->assertOk()
            ->assertSee('Fadel Irsyad')
            ->assertSee('001/ST-ATC/I/2026');

        $leader = Employee::find(self::LEADER_SOFTWARE);
        $this->assertSame(1, AssignmentLetterApprovalFlow::pendingFor($leader)->count());

        // Selesai diputuskan: tak lagi menunggu.
        $this->approveAsSuperior($letter, self::LEADER_SOFTWARE);
        $this->assertSame(0, AssignmentLetterApprovalFlow::pendingFor($leader)->count());

        // Yang bukan atasan di surat ini tidak bisa membukanya.
        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.assignment-approvals.show', $letter->id))->assertNotFound();

        // Daftar Persetujuan Tim memuatnya selama menunggu.
        $pending = $this->publish($this->storeDraft());
        $this->withSession(['employee_id' => self::LEADER_SOFTWARE])
            ->get(route('employee.approvals.index'))
            ->assertOk()
            ->assertSee(route('employee.assignment-approvals.show', $pending->id));
    }

    public function test_every_approver_is_notified_once_when_the_letter_is_issued(): void
    {
        $letter = $this->publish();

        // Peran 'admin' pada GA HSE di test memegang izin HRD juga, jadi hitung per kotak paraf.
        $notified = fn (int $id, string $label) => DB::table('notifications')
            ->where('employee_id', $id)
            ->where('title', 'Surat Tugas Menunggu Persetujuan')
            ->where('message', 'like', "%sebagai {$label}.")
            ->count();

        foreach ([self::LEADER_SOFTWARE => 'LV 3 SOFTWARE', self::MANAGER_SOFTWARE => 'LV 2 SOFTWARE', self::MANAGER_HARDWARE => 'LV 2 HARDWARE'] as $id => $label) {
            $this->assertSame(1, $notified($id, $label));
        }
        $this->assertSame(1, $notified(self::ADMIN, 'HRD'), 'HRD dikabari sejak surat terbit');
        $this->assertSame(1, $notified(self::GA_HSE, 'GA HSE'), 'GA HSE dikabari sejak surat terbit');

        foreach ([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE] as $id) {
            $this->approveAsSuperior($letter, $id);
        }
        $this->approveAsHrd($letter);
        $this->assertSame(1, $notified(self::ADMIN, 'HRD'), 'tidak dikabari dua kali');
        $this->assertSame(1, $notified(self::GA_HSE, 'GA HSE'), 'tidak dikabari dua kali');

        $this->approveAsGaHse($letter);
        $this->assertSame(1, DB::table('notifications')->where('employee_id', self::ADMIN)->where('title', 'Paraf Surat Tugas Lengkap')->count());
    }

    public function test_hrd_and_ga_hse_see_the_letter_in_the_portal_approval_list_and_can_decide_there(): void
    {
        $letter = $this->publish();
        $this->asRole('hr_admin', self::ADMIN);
        $this->asRole('admin', self::GA_HSE);
        $waitingFor = fn (int $id) => AssignmentLetterApprovalFlow::pendingFor(Employee::find($id))
            ->contains(fn ($a) => (int) $a->assignment_letter_id === (int) $letter->id);

        $this->assertTrue($waitingFor(self::ADMIN), 'HRD melihat surat di Persetujuan Tim');
        $this->assertTrue($waitingFor(self::GA_HSE), 'GA HSE melihat surat di Persetujuan Tim');

        $this->withSession(['employee_id' => self::GA_HSE])
            ->get(route('employee.approvals.index'))
            ->assertOk()
            ->assertSee(route('employee.assignment-approvals.show', $letter->id));

        $this->withSession(['employee_id' => self::GA_HSE])
            ->get(route('employee.assignment-approvals.show', $letter->id))
            ->assertOk();

        $this->withSession(['employee_id' => self::GA_HSE])
            ->post(route('employee.assignment-approvals.approve', $letter->id))
            ->assertSessionHas('success');

        // Peran 'admin' di test memegang izin HRD juga, jadi yang diputuskan bisa kotak HRD atau GA HSE.
        $this->assertTrue($letter->approvals()->where('acted_by', self::GA_HSE)->where('status', 'approved')->exists());

        // Yang bukan atasan dan tak memegang izin tetap tidak bisa membukanya.
        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.assignment-approvals.show', $letter->id))->assertNotFound();
    }

    public function test_approver_uploads_their_own_signature_and_it_fills_their_box(): void
    {
        $employee = fn () => Employee::find(self::LEADER_SOFTWARE);
        $portal = fn () => $this->withSession(['employee_id' => self::LEADER_SOFTWARE]);

        // Tanpa gambar atau bukan gambar: ditolak.
        $portal()->post(route('employee.profile.signature.update'), [])->assertSessionHasErrors('signature');
        $portal()->post(route('employee.profile.signature.update'), ['signature' => UploadedFile::fake()->create('ttd.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('signature');
        $this->assertNull($employee()->signature);

        $portal()->post(route('employee.profile.signature.update'), ['signature' => UploadedFile::fake()->image('ttd.png', 200, 80)])
            ->assertSessionHasNoErrors();
        $first = $employee()->signature;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        // Surat yang disetujui memakai salinan tanda tangan saat itu.
        $letter = $this->publish();
        $this->approveAsSuperior($letter, self::LEADER_SOFTWARE);
        $snapshot = $this->approvalFor($letter, 'level3')->fresh()->signature_path;
        $this->assertNotNull($snapshot);

        // Ganti lalu hapus: file lama dibuang, salinan di surat tetap ada.
        $portal()->post(route('employee.profile.signature.update'), ['signature' => UploadedFile::fake()->image('baru.png', 200, 80)]);
        $this->assertNotSame($first, $employee()->signature);
        Storage::disk('public')->assertMissing($first);

        $portal()->delete(route('employee.profile.signature.destroy'));
        $this->assertNull($employee()->signature);
        Storage::disk('public')->assertExists($snapshot);
    }

    public function test_superadmin_is_not_flooded_with_role_based_requests_when_real_holders_exist(): void
    {
        DB::table('employees')->insert([
            'id' => 50, 'company_id' => 1, 'department_id' => 3, 'full_name' => 'Superadmin', 'position' => 'Admin', 'job_level' => 1,
            'employment_status' => 'permanent', 'role' => 'superadmin', 'is_active' => true, 'employee_code' => '050/X/I/2024',
        ]);

        $letter = $this->publish();
        foreach ([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE] as $id) {
            $this->approveAsSuperior($letter, $id);
        }

        $asked = fn (int $id) => DB::table('notifications')->where('employee_id', $id)->where('title', 'Surat Tugas Menunggu Persetujuan')->count();
        $this->assertSame(1, $asked(self::ADMIN), 'pemegang izin HRD dikabari');
        $this->assertSame(0, $asked(50), 'superadmin tidak dikabari, tapi tetap boleh memutuskan');
        $approval = $this->approvalFor($letter, 'hrd');
        $this->assertTrue(AssignmentLetterApprovalFlow::actionableFor($letter->fresh(), Employee::find(50))->contains('id', $approval->id));
    }

    public function test_hrd_and_ga_hse_see_which_letters_wait_for_them(): void
    {
        $first = $this->publish();
        $second = $this->publish($this->storeDraft());
        $index = fn (array $query = []) => $this->asRole('hr_admin')->get(route('admin.assignment-letters.index', $query))->assertOk();

        // HRD tidak menunggu atasan: kedua surat langsung menunggu paraf HRD.
        $index()->assertSee('2 surat')->assertSee('menunggu paraf Anda')->assertSee('Menunggu paraf saya')
            ->assertSee(route('admin.assignment-letters.show', $first->id).'#paraf-digital', false)
            ->assertSee(route('admin.assignment-letters.show', $second->id).'#paraf-digital', false);

        // Angka muncul di menu admin mana pun (sidebar).
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $second->id))->assertSee('rounded-full min-w-[18px] text-center">2<', false);

        // Setelah HRD memutuskan satu surat, surat itu keluar dari daftar HRD dan filter hanya memuat yang lain.
        $this->approveAsHrd($first);
        $this->assertTrue(AssignmentLetterApprovalFlow::awaitingLetters(Employee::find(self::GA_HSE))->contains('id', $first->id));
        $this->assertFalse(AssignmentLetterApprovalFlow::awaitingLetters(Employee::find(self::ADMIN))->contains('id', $first->id), 'HRD sudah memutuskan');

        $filtered = $index(['status' => 'awaiting']);
        $filtered->assertSee($second->fresh()->number)->assertDontSee($first->fresh()->number);

        foreach ([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE] as $id) {
            $this->approveAsSuperior($first, $id);
        }

        // Selesai semua: tidak ada lagi yang menunggu.
        $this->approveAsGaHse($first);
        $this->assertFalse(AssignmentLetterApprovalFlow::awaitingLetters(Employee::find(self::GA_HSE))->contains('id', $first->id));
    }

    public function test_participants_see_the_letter_right_after_issue_even_before_initials_are_complete(): void
    {
        $letter = $this->storeDraft();
        $portalPrint = fn () => $this->withSession(['employee_id' => self::PERMANENT])->get(route('employee.assignment-letters.print', $letter->id));

        $portalPrint()->assertNotFound(); // draft belum terlihat

        $this->publish($letter);
        $portalPrint()->assertOk();
    }

    public function test_admin_detail_shows_the_right_actions_in_every_stage(): void
    {
        $letter = $this->storeDraft();
        $show = fn () => $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $letter->id))->assertOk();

        // Draft: langsung Terbitkan, tanpa langkah "ajukan"; pratinjau paraf sudah terlihat.
        $show()->assertSee(route('admin.assignment-letters.issue', $letter->id))
            ->assertSee('LV 3 SOFTWARE');

        $this->publish($letter);
        $show()->assertDontSee(route('admin.assignment-letters.issue', $letter->id))
            ->assertSee('Menunggu paraf 0/5')
            ->assertSee(route('admin.assignment-letters.edit', $letter->id))
            ->assertDontSee(route('admin.assignment-letters.resubmit', $letter->id));

        // Ditolak: tombol Ajukan Ulang muncul.
        $this->withSession(['employee_id' => self::LEADER_SOFTWARE])
            ->post(route('employee.assignment-approvals.reject', $letter->id), ['notes' => 'Salah tanggal']);
        $show()->assertSee('Paraf ditolak')
            ->assertSee(route('admin.assignment-letters.resubmit', $letter->id))
            ->assertSee('Salah tanggal');

        // HRD melihat tombol setujui begitu giliran grupnya terbuka.
        $second = $this->publish($this->storeDraft());
        foreach ([self::LEADER_SOFTWARE, self::MANAGER_SOFTWARE, self::MANAGER_HARDWARE] as $id) {
            $this->approveAsSuperior($second, $id);
        }
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $second->id))
            ->assertSee(route('admin.assignment-letters.approve', [$second->id, $this->approvalFor($second, 'hrd')->id]));

        $this->approveAsHrd($second);
        $this->approveAsGaHse($second);
        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $second->id))
            ->assertSee('Paraf lengkap')
            ->assertSee('Semua paraf sudah terisi');
    }

    public function test_history_tab_lists_the_assignment_letter_decisions_the_employee_made(): void
    {
        $letter = $this->publish();

        $history = fn (int $id) => $this->withSession(['employee_id' => $id])->get(route('employee.approvals.index', ['tab' => 'history']));

        // Belum memutuskan: riwayat kosong.
        $history(self::LEADER_SOFTWARE)->assertOk()->assertSee('Belum ada riwayat');

        $this->approveAsSuperior($letter, self::LEADER_SOFTWARE);
        $history(self::LEADER_SOFTWARE)->assertOk()
            ->assertDontSee('Belum ada riwayat')
            ->assertSee('Fadel Irsyad')
            ->assertSee('Paraf LV 3 SOFTWARE')
            ->assertSee(route('employee.assignment-approvals.show', $letter->id));

        // Penolakan juga tercatat, lengkap dengan catatannya.
        $this->withSession(['employee_id' => self::MANAGER_HARDWARE])
            ->post(route('employee.assignment-approvals.reject', $letter->id), ['notes' => 'Kendaraan belum diisi']);
        $history(self::MANAGER_HARDWARE)->assertSee('Ditolak')->assertSee('Kendaraan belum diisi');

        // Atasan lain yang belum memutuskan tidak melihat keputusan orang lain.
        $history(self::MANAGER_SOFTWARE)->assertSee('Belum ada riwayat');

        $this->assertSame(1, AssignmentLetterApprovalFlow::historyCount(Employee::find(self::LEADER_SOFTWARE)));
    }

    public function test_letter_without_approval_requirement_issues_without_asking_for_initials(): void
    {
        config(['assignment_letters.require_approval' => false]);

        $issued = $this->issue($this->storeDraft());

        $this->assertTrue($issued->isIssued());
        $this->assertSame(0, $issued->approvals()->count());
        $this->assertSame('none', $issued->approvalState());
        $this->assertSame(['LV 3 SOFTWARE', 'LV 2 SOFTWARE', 'LV 2 HARDWARE', 'HRD', 'GA HSE'], $issued->initialBoxes());

        // Tanpa paraf yang diminta, pindaian bisa langsung diunggah.
        $this->upload($issued)->assertSessionHas('success');
    }
}
