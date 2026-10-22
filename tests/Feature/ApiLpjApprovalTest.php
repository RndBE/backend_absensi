<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ApprovalController;
use App\Models\Employee;
use App\Models\Lpj;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Persetujuan LPJ untuk app mobile: antrean, detail, setuju/tolak, dan tanggal transfer yang diisi
 * finance. Aturannya sama dengan portal employee (Employee\ApprovalController).
 */
class ApiLpjApprovalTest extends TestCase
{
    private const OWNER = 1;
    private const OUTSIDER = 2;
    private const MANAGER = 3;
    private const FINANCE = 4;
    private const DIRECTOR = 5;
    private const SUPERADMIN = 6;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'notifications', 'approval_logs', 'employee_approvers', 'lpj_items', 'lpjs', 'travel_reports',
            'budget_request_participants', 'budget_request_items', 'budget_requests', 'leave_requests',
            'overtime_requests', 'attendance_requests', 'data_change_requests', 'departments', 'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->string('position')->nullable();
            $table->string('photo')->nullable();
            $table->string('fcm_token')->nullable();
            $table->timestamps();
        });
        Schema::create('budget_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('type')->default('budget');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('surat_tugas_no')->nullable();
            $table->date('surat_tugas_date')->nullable();
            $table->unsignedBigInteger('assignment_letter_id')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->integer('distance_km')->nullable();
            $table->unsignedBigInteger('travel_zone_id')->nullable();
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->integer('lhp_deadline_days')->nullable();
            $table->timestamps();
        });
        Schema::create('budget_request_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->string('type');
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('travel_reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('budget_request_id')->nullable();
            $table->string('destination_city');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('lpjs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('travel_report_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->string('nomor_lpj')->nullable();
            $table->decimal('total_anggaran', 15, 2)->default(0);
            $table->decimal('total_realisasi', 15, 2)->default(0);
            $table->decimal('sisa', 15, 2)->default(0);
            $table->string('status')->default('draft');
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->string('rejection_reason')->nullable();
            $table->text('catatan')->nullable();
            $table->json('kategori_notes')->nullable();
            $table->date('transfer_date')->nullable();
            $table->timestamps();
        });
        Schema::create('lpj_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lpj_id');
            $table->unsignedBigInteger('budget_request_item_id')->nullable();
            $table->string('uraian');
            $table->string('kategori')->nullable();
            $table->string('satuan')->nullable();
            $table->decimal('volume', 10, 2)->default(1);
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('anggaran', 15, 2)->default(0);
            $table->decimal('realisasi', 15, 2)->default(0);
            $table->string('bukti_file')->nullable();
            $table->string('keterangan')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('acted_by_id')->nullable();
            $table->string('action');
            $table->unsignedTinyInteger('step_order')->default(1);
            $table->unsignedBigInteger('approval_rule_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->unsignedTinyInteger('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('title');
            $table->text('message');
            $table->string('type')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        $this->person(self::OWNER, 'Shandy', 'Staff IT');
        $this->person(self::OUTSIDER, 'Rina', 'Staff HR');
        $this->person(self::MANAGER, 'Budi Atasan', 'Manager Operasional');
        $this->person(self::FINANCE, 'Fina Keuangan', 'Finance Staff');
        $this->person(self::DIRECTOR, 'Direktur Utama', 'Direktur');
        $this->person(self::SUPERADMIN, 'Admin Sistem', 'Superadmin', role: 'superadmin');

        // Rantai LPJ Shandy: manager, lalu finance (final).
        $this->chain(self::OWNER, [self::MANAGER, self::FINANCE]);
    }

    // ── antrean ───────────────────────────────────────────────────────────────

    public function test_the_inbox_lists_lpjs_waiting_for_me_at_my_step(): void
    {
        $mine = $this->lpj(step: 1);
        $this->lpj(step: 2);
        $this->lpj(status: 'approved');

        $rows = $this->invoke('index', self::MANAGER)['data']['lpj'];

        $this->assertSame([$mine], array_column($rows, 'id'));
        $this->assertSame('Shandy', $rows[0]['employee']['full_name']);
        $this->assertSame('Perjalanan Surabaya', $rows[0]['budget_request']['title']);
    }

    public function test_the_inbox_shows_a_later_step_to_the_approver_of_that_step(): void
    {
        $waitingForFinance = $this->lpj(step: 2, status: 'in_review');

        $this->assertSame([], $this->invoke('index', self::MANAGER)['data']['lpj']);
        $this->assertSame([$waitingForFinance], array_column($this->invoke('index', self::FINANCE)['data']['lpj'], 'id'));
        $this->assertSame([], $this->invoke('index', self::OUTSIDER)['data']['lpj']);
    }

    // ── detail ────────────────────────────────────────────────────────────────

    public function test_an_approver_sees_the_full_lpj_with_numbers_not_text(): void
    {
        $id = $this->lpj(catatan: 'Semua nota terlampir');
        DB::table('lpj_items')->insert([
            'lpj_id' => $id, 'uraian' => 'Tiket kereta', 'kategori' => 'transport', 'volume' => 2,
            'realisasi' => 150000.5, 'bukti_file' => 'lpj-bukti/tiket.pdf', 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('lpjs')->where('id', $id)->update(['total_realisasi' => 150000.5, 'sisa' => 49999.5]);

        $data = $this->invoke('show', self::MANAGER, [], 'lpj', $id)['data'];

        $this->assertSame($id, $data['id']);
        $this->assertSame('pending', $data['status']);
        $this->assertSame('Shandy', $data['employee']['full_name']);
        $this->assertSame('Staff IT', $data['employee']['position']);
        $this->assertSame('Perjalanan Surabaya', $data['budget_request']['title']);
        $this->assertSame('Semua nota terlampir', $data['catatan']);
        $this->assertSame(1, $data['current_step']);
        $this->assertSame(2, $data['total_steps']);
        $this->assertNull($data['transfer_date']);
        $this->assertIsFloat($data['total_realisasi']);
        $this->assertEquals(150000.5, $data['total_realisasi']);
        $this->assertSame('Tiket kereta', $data['items'][0]['uraian']);
        $this->assertTrue($data['items'][0]['bukti_url'] !== null && str_ends_with($data['items'][0]['bukti_url'], 'lpj-bukti/tiket.pdf'));
        $this->assertFalse($data['items'][0]['bukti_is_image']);
        $this->assertArrayHasKey('per_kategori', $data['summary']);
    }

    public function test_the_detail_carries_the_decisions_made_so_far(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');
        $this->log($id, self::MANAGER, 'approved', 1, 'Nota lengkap');

        $logs = $this->invoke('show', self::FINANCE, [], 'lpj', $id)['data']['approval_logs'];

        $this->assertCount(1, $logs);
        $this->assertSame('approved', $logs[0]['action']);
        $this->assertSame(1, $logs[0]['step_order']);
        $this->assertSame('Nota lengkap', $logs[0]['notes']);
        $this->assertSame('Budi Atasan', $logs[0]['approver']['full_name']);
    }

    public function test_the_transfer_date_comes_as_a_plain_date(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review', transferDate: '2026-10-08');

        $this->assertSame('2026-10-08', $this->invoke('show', self::FINANCE, [], 'lpj', $id)['data']['transfer_date']);
    }

    public function test_only_people_who_belong_to_the_lpj_can_open_it(): void
    {
        $id = $this->lpj();

        $this->assertSame(200, $this->respond('show', self::OWNER, [], 'lpj', $id)->getStatusCode(), 'pemohon');
        $this->assertSame(200, $this->respond('show', self::MANAGER, [], 'lpj', $id)->getStatusCode(), 'approver di rantai');
        $this->assertSame(200, $this->respond('show', self::FINANCE, [], 'lpj', $id)->getStatusCode(), 'approver step lain di rantai');
        $this->assertSame(200, $this->respond('show', self::SUPERADMIN, [], 'lpj', $id)->getStatusCode(), 'superadmin');
        $this->assertSame(403, $this->respond('show', self::OUTSIDER, [], 'lpj', $id)->getStatusCode(), 'orang lain');
    }

    public function test_someone_who_once_decided_can_still_open_it_after_the_chain_changed(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');
        $this->log($id, self::DIRECTOR, 'approved', 1);

        $this->assertSame(200, $this->respond('show', self::DIRECTOR, [], 'lpj', $id)->getStatusCode());
    }

    public function test_an_unknown_lpj_is_not_found(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->invoke('show', self::MANAGER, [], 'lpj', 999);
    }

    // ── siapa yang boleh mengisi tanggal transfer ──────────────────────────────

    public function test_only_finance_at_the_active_step_is_offered_the_transfer_date(): void
    {
        $atFinance = $this->lpj(step: 2, status: 'in_review');
        $atManager = $this->lpj(step: 1);

        $this->assertTrue($this->canSet(self::FINANCE, $atFinance), 'finance di step aktif');
        $this->assertFalse($this->canSet(self::FINANCE, $atManager), 'finance, tetapi bukan step aktif');
        $this->assertFalse($this->canSet(self::MANAGER, $atManager), 'approver aktif yang bukan finance');
        $this->assertFalse($this->canSet(self::OWNER, $atFinance), 'pemohon');
        $this->assertSame(403, $this->respond('show', self::OUTSIDER, [], 'lpj', $atFinance)->getStatusCode(), 'orang lain tidak bisa membukanya');
    }

    public function test_a_finished_lpj_no_longer_offers_the_transfer_date(): void
    {
        foreach (['approved', 'rejected'] as $status) {
            $id = $this->lpj(step: 2, status: $status);

            $this->assertFalse($this->canSet(self::FINANCE, $id), $status);
        }
    }

    public function test_the_superadmin_acting_for_finance_is_offered_the_transfer_date(): void
    {
        $atFinance = $this->lpj(step: 2, status: 'in_review');
        $atManager = $this->lpj(step: 1);

        $this->assertTrue($this->canSet(self::SUPERADMIN, $atFinance), 'menggantikan finance');
        $this->assertFalse($this->canSet(self::SUPERADMIN, $atManager), 'menggantikan manager yang bukan finance');
    }

    // ── setuju ────────────────────────────────────────────────────────────────

    public function test_approving_a_middle_step_forwards_it_and_notifies_the_next_approver(): void
    {
        $id = $this->lpj();

        $response = $this->respond('approve', self::MANAGER, ['notes' => 'Nota lengkap'], 'lpj', $id);

        $this->assertSame(200, $response->getStatusCode());
        $lpj = Lpj::findOrFail($id);
        $this->assertSame('in_review', $lpj->status);
        $this->assertSame(2, (int) $lpj->current_step);
        $this->assertDatabaseHas('approval_logs', [
            'approvable_type' => Lpj::class, 'approvable_id' => $id, 'approver_id' => self::MANAGER,
            'action' => 'approved', 'step_order' => 1, 'notes' => 'Nota lengkap',
        ]);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => self::FINANCE, 'type' => 'approval', 'reference_type' => Lpj::class, 'reference_id' => $id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'employee_id' => self::OWNER, 'reference_type' => Lpj::class, 'reference_id' => $id,
        ]);
    }

    public function test_the_final_approval_completes_the_lpj_and_tells_the_owner(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        $this->assertSame(200, $this->respond('approve', self::FINANCE, [], 'lpj', $id)->getStatusCode());

        $this->assertSame('approved', Lpj::findOrFail($id)->status);
        $notification = DB::table('notifications')->where('employee_id', self::OWNER)->first();
        $this->assertSame('Pengajuan LPJ Disetujui', $notification->title);
        $this->assertSame(Lpj::class, $notification->reference_type);
    }

    public function test_finance_saves_the_transfer_date_together_with_approving(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        $this->respond('approve', self::FINANCE, ['transfer_date' => '2026-10-23T15:45:00'], 'lpj', $id);

        $lpj = Lpj::findOrFail($id);
        $this->assertSame('approved', $lpj->status);
        $this->assertSame('2026-10-23', $lpj->transfer_date->toDateString());
        $this->assertOnlyTheDateIsStored($id, '2026-10-23');
    }

    public function test_a_transfer_date_from_a_non_finance_approver_is_ignored(): void
    {
        $id = $this->lpj();

        $response = $this->respond('approve', self::MANAGER, ['transfer_date' => '2026-10-23'], 'lpj', $id);

        $this->assertSame(200, $response->getStatusCode());
        $lpj = Lpj::findOrFail($id);
        $this->assertSame('in_review', $lpj->status);
        $this->assertNull($lpj->transfer_date);
    }

    public function test_the_superadmin_approving_for_finance_can_set_the_transfer_date(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        $this->respond('approve', self::SUPERADMIN, ['transfer_date' => '2026-10-23'], 'lpj', $id);

        $lpj = Lpj::findOrFail($id);
        $this->assertSame('2026-10-23', $lpj->transfer_date->toDateString());
        $this->assertDatabaseHas('approval_logs', [
            'approvable_id' => $id, 'approver_id' => self::FINANCE, 'acted_by_id' => self::SUPERADMIN, 'action' => 'approved',
        ]);
    }

    public function test_an_invalid_transfer_date_stops_the_approval(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        try {
            $this->respond('approve', self::FINANCE, ['transfer_date' => 'bukan tanggal'], 'lpj', $id);
            $this->fail('Tanggal rusak seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('transfer_date', $e->errors());
        }

        $this->assertSame('in_review', Lpj::findOrFail($id)->status);
        $this->assertSame(0, DB::table('approval_logs')->count());
    }

    public function test_someone_who_is_not_the_active_approver_cannot_approve(): void
    {
        $id = $this->lpj();

        foreach ([self::FINANCE, self::OUTSIDER, self::OWNER] as $who) {
            $response = $this->respond('approve', $who, ['transfer_date' => '2026-10-23'], 'lpj', $id);

            $this->assertSame(403, $response->getStatusCode(), "pengguna $who");
        }

        $lpj = Lpj::findOrFail($id);
        $this->assertSame('pending', $lpj->status);
        $this->assertNull($lpj->transfer_date);
        $this->assertSame(0, DB::table('approval_logs')->count());
    }

    public function test_an_already_processed_lpj_cannot_be_approved_again(): void
    {
        $id = $this->lpj(step: 2, status: 'approved');

        $this->assertSame(422, $this->respond('approve', self::FINANCE, [], 'lpj', $id)->getStatusCode());
        $this->assertSame(422, $this->respond('reject', self::FINANCE, [], 'lpj', $id)->getStatusCode());
    }

    // ── tolak ─────────────────────────────────────────────────────────────────

    public function test_rejecting_closes_the_lpj_and_tells_the_owner_why(): void
    {
        $id = $this->lpj();

        $this->assertSame(200, $this->respond('reject', self::MANAGER, ['notes' => 'Nota hotel belum ada'], 'lpj', $id)->getStatusCode());

        $this->assertSame('rejected', Lpj::findOrFail($id)->status);
        $this->assertDatabaseHas('approval_logs', [
            'approvable_type' => Lpj::class, 'approvable_id' => $id, 'action' => 'rejected', 'notes' => 'Nota hotel belum ada',
        ]);
        $this->assertSame('Pengajuan LPJ Ditolak', DB::table('notifications')->where('employee_id', self::OWNER)->value('title'));
    }

    public function test_the_reason_for_rejecting_is_optional(): void
    {
        $id = $this->lpj();

        $this->assertSame(200, $this->respond('reject', self::MANAGER, [], 'lpj', $id)->getStatusCode());
        $this->assertSame('rejected', Lpj::findOrFail($id)->status);
    }

    public function test_someone_who_is_not_the_active_approver_cannot_reject(): void
    {
        $id = $this->lpj();

        $this->assertSame(403, $this->respond('reject', self::FINANCE, [], 'lpj', $id)->getStatusCode());
        $this->assertSame('pending', Lpj::findOrFail($id)->status);
    }

    // ── simpan tanggal transfer ───────────────────────────────────────────────

    public function test_finance_can_save_the_transfer_date_without_approving(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        $response = $this->respond('saveLpjTransferDate', self::FINANCE, ['transfer_date' => '2026-10-23'], $id);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('2026-10-23', $response->getData(true)['data']['transfer_date']);
        $lpj = Lpj::findOrFail($id);
        $this->assertSame('2026-10-23', $lpj->transfer_date->toDateString());
        $this->assertSame('in_review', $lpj->status, 'belum menyetujui');
        $this->assertSame(0, DB::table('approval_logs')->count());
    }

    public function test_saving_again_replaces_the_date(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review', transferDate: '2026-10-20');

        $this->respond('saveLpjTransferDate', self::FINANCE, ['transfer_date' => '2026-10-25'], $id);

        $this->assertSame('2026-10-25', Lpj::findOrFail($id)->transfer_date->toDateString());
    }

    public function test_only_the_date_is_kept_when_a_time_comes_along(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        $this->respond('saveLpjTransferDate', self::FINANCE, ['transfer_date' => '2026-10-23T15:45:00'], $id);

        $this->assertSame('2026-10-23', Lpj::findOrFail($id)->transfer_date->toDateString());
        $this->assertOnlyTheDateIsStored($id, '2026-10-23');
    }

    public function test_the_transfer_date_is_required_to_save(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        try {
            $this->respond('saveLpjTransferDate', self::FINANCE, [], $id);
            $this->fail('Tanggal kosong seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(['Isi tanggal transfer dulu.'], $e->errors()['transfer_date']);
        }
    }

    public function test_an_invalid_transfer_date_is_rejected_when_saving(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        $this->expectException(ValidationException::class);

        $this->respond('saveLpjTransferDate', self::FINANCE, ['transfer_date' => 'bukan tanggal'], $id);
    }

    public function test_nobody_else_can_save_the_transfer_date(): void
    {
        $atFinance = $this->lpj(step: 2, status: 'in_review');
        $atManager = $this->lpj(step: 1);

        foreach ([
            'manager yang bukan finance' => [self::MANAGER, $atManager],
            'finance bukan di step aktif' => [self::FINANCE, $atManager],
            'pemohon' => [self::OWNER, $atFinance],
            'orang lain' => [self::OUTSIDER, $atFinance],
            'approver non-finance di LPJ lain' => [self::MANAGER, $atFinance],
        ] as $label => [$who, $lpj]) {
            $response = $this->respond('saveLpjTransferDate', $who, ['transfer_date' => '2026-10-23'], $lpj);

            $this->assertSame(403, $response->getStatusCode(), $label);
        }

        $this->assertSame(0, DB::table('lpjs')->whereNotNull('transfer_date')->count());
    }

    public function test_the_transfer_date_cannot_be_saved_after_the_lpj_is_processed(): void
    {
        foreach (['approved', 'rejected'] as $status) {
            $id = $this->lpj(step: 2, status: $status);

            $this->assertSame(422, $this->respond('saveLpjTransferDate', self::FINANCE, ['transfer_date' => '2026-10-23'], $id)->getStatusCode(), $status);
        }

        $this->assertSame(0, DB::table('lpjs')->whereNotNull('transfer_date')->count());
    }

    public function test_the_superadmin_can_save_the_date_for_the_finance_step(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');

        $this->assertSame(200, $this->respond('saveLpjTransferDate', self::SUPERADMIN, ['transfer_date' => '2026-10-23'], $id)->getStatusCode());
        $this->assertNotNull(Lpj::findOrFail($id)->transfer_date);
    }

    // ── monitor (superadmin) ──────────────────────────────────────────────────

    public function test_the_monitor_lists_lpjs_with_their_own_approval_chain(): void
    {
        $id = $this->lpj(step: 2, status: 'in_review');
        $this->log($id, self::MANAGER, 'approved', 1);

        $rows = $this->invoke('monitor', self::SUPERADMIN, ['type' => 'lpj'])['data'];

        $this->assertCount(1, $rows);
        $this->assertSame('lpj', $rows[0]['type']);
        $this->assertSame('LPJ', $rows[0]['type_label']);
        $this->assertSame(2, $rows[0]['total_steps']);
        $this->assertSame(['Budi Atasan', 'Fina Keuangan'], array_column($rows[0]['chain'], 'approver_name'));
        $this->assertSame(['approved', 'waiting'], array_column($rows[0]['chain'], 'action'));
    }

    // ── rute ──────────────────────────────────────────────────────────────────

    public function test_the_routes_exist_behind_sanctum_and_resolve_to_the_right_actions(): void
    {
        foreach ([
            ['GET', 'api/approvals/lpj/12', 'show'],
            ['POST', 'api/approvals/lpj/12/approve', 'approve'],
            ['POST', 'api/approvals/lpj/12/reject', 'reject'],
            ['POST', 'api/approvals/lpj/12/transfer-date', 'saveLpjTransferDate'],
        ] as [$method, $uri, $action]) {
            $route = Route::getRoutes()->match(Request::create('/'.$uri, $method));

            $this->assertSame(ApprovalController::class.'@'.$action, $route->getActionName(), "$method $uri");
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "$method $uri harus butuh login");
        }
    }

    public function test_the_transfer_date_route_only_takes_a_numeric_id(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        Route::getRoutes()->match(Request::create('/api/approvals/lpj/abc/transfer-date', 'POST'));
    }

    // ── bantuan ───────────────────────────────────────────────────────────────

    private function person(int $id, string $name, string $position, string $role = 'employee'): void
    {
        DB::table('employees')->insert([
            'id' => $id, 'full_name' => $name, 'email' => "e{$id}@example.test", 'password' => 'x',
            'position' => $position, 'role' => $role, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Rantai approver LPJ milik $employeeId: urutan array = urutan step. */
    private function chain(int $employeeId, array $approverIds): void
    {
        foreach ($approverIds as $index => $approverId) {
            DB::table('employee_approvers')->insert([
                'employee_id' => $employeeId, 'request_type' => 'lpj', 'step_order' => $index + 1,
                'approver_id' => $approverId, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function lpj(int $step = 1, string $status = 'pending', ?string $catatan = null, ?string $transferDate = null): int
    {
        $budget = DB::table('budget_requests')->insertGetId([
            'employee_id' => self::OWNER, 'title' => 'Perjalanan Surabaya', 'status' => 'approved', 'total_amount' => 200000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('lpjs')->insertGetId([
            'budget_request_id' => $budget, 'employee_id' => self::OWNER, 'total_anggaran' => 200000,
            'total_realisasi' => 0, 'sisa' => 200000, 'status' => $status, 'current_step' => $step,
            'catatan' => $catatan, 'transfer_date' => $transferDate, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function log(int $lpjId, int $approverId, string $action, int $step, ?string $notes = null): void
    {
        DB::table('approval_logs')->insert([
            'approvable_type' => Lpj::class, 'approvable_id' => $lpjId, 'approver_id' => $approverId,
            'action' => $action, 'step_order' => $step, 'notes' => $notes,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Kolom tanggal tidak boleh membawa jam dari isian (sqlite menambahkan 00:00:00, MySQL memotongnya). */
    private function assertOnlyTheDateIsStored(int $lpjId, string $date): void
    {
        $stored = (string) DB::table('lpjs')->where('id', $lpjId)->value('transfer_date');

        $this->assertSame($date, substr($stored, 0, 10));
        $this->assertStringNotContainsString('15:45', $stored, 'jam dari isian tidak ikut tersimpan');
    }

    private function canSet(int $as, int $lpjId): bool
    {
        return $this->invoke('show', $as, [], 'lpj', $lpjId)['data']['can_set_transfer_date'];
    }

    private function respond(string $method, int $as, array $body, ...$args): JsonResponse
    {
        $request = Request::create('/api/approvals', 'POST', $body);
        $request->setUserResolver(fn () => Employee::findOrFail($as));

        return (new ApprovalController())->{$method}($request, ...$args);
    }

    private function invoke(string $method, int $as, array $query = [], ...$args): array
    {
        $request = Request::create('/api/approvals', 'GET', $query);
        $request->setUserResolver(fn () => Employee::findOrFail($as));

        return (new ApprovalController())->{$method}($request, ...$args)->getData(true);
    }
}
