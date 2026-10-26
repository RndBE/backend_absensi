<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LpjController;
use App\Models\Employee;
use App\Models\Lpj;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * LPJ untuk app mobile: daftar, detail, dan buat. Aturannya sama dengan portal employee.
 */
class ApiLpjTest extends TestCase
{
    private const ME = 1;
    private const OTHER = 2;
    private const APPROVER = 3;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'notifications', 'approval_logs', 'employee_approvers', 'lpj_items', 'lpjs', 'travel_reports',
            'budget_request_participants', 'budget_request_items', 'budget_requests', 'employees',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
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
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->string('type');
            $table->string('description');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('budget_request_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('budget_request_id');
            $table->unsignedBigInteger('employee_id');
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

        Storage::fake('public');

        $this->person(self::ME, 'Shandy');
        $this->person(self::OTHER, 'Rina');
        $this->person(self::APPROVER, 'Budi Atasan');
        DB::table('employee_approvers')->insert([
            'employee_id' => self::ME, 'request_type' => 'lpj', 'step_order' => 1, 'approver_id' => self::APPROVER,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── daftar ────────────────────────────────────────────────────────────────

    public function test_the_list_shows_only_my_lpjs_newest_first(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');
        $other = $this->budget(self::OTHER, 'Rapat Malang');
        $older = $this->lpj($budget, self::ME, '2026-10-01 08:00:00', nomor: 'LPJ-LAMA');
        $newer = $this->lpj($this->budget(self::ME, 'Survei Medan'), self::ME, '2026-10-20 08:00:00');
        $this->lpj($other, self::OTHER, '2026-10-21 08:00:00');

        $data = $this->invoke('index', $this->listRequest(self::ME))['data'];

        $this->assertSame([$newer, $older], array_column($data, 'id'));
        $this->assertSame('LPJ-LAMA', $data[1]['nomor_lpj']);
        $this->assertTrue($data[0]['is_mine']);
        $this->assertSame('Shandy', $data[0]['employee']['full_name']);
    }

    public function test_a_participant_sees_the_team_lpj_made_by_the_owner_as_not_theirs(): void
    {
        $budget = $this->budget(self::OTHER, 'Rapat bersama');
        $this->participate($budget, self::ME);
        $id = $this->lpj($budget, self::OTHER, '2026-10-01 08:00:00');

        $row = $this->invoke('index', $this->listRequest(self::ME))['data'][0];
        $detail = $this->invoke('show', $this->listRequest(self::ME), $id)['data'];

        $this->assertSame($id, $row['id']);
        $this->assertFalse($row['is_mine']);
        $this->assertSame('Rina', $row['employee']['full_name']);
        $this->assertFalse($detail['is_mine']);
        $this->assertSame('Rina', $detail['employee']['full_name']);
        $this->assertSame('Rapat bersama', $detail['budget_request']['title']);
    }

    public function test_the_budget_owner_sees_a_legacy_lpj_made_by_a_participant(): void
    {
        $budget = $this->budget(self::ME, 'Rapat bersama');
        $this->participate($budget, self::OTHER);
        $id = $this->lpj($budget, self::OTHER, '2026-10-01 08:00:00');

        $row = $this->invoke('index', $this->listRequest(self::ME))['data'][0];

        $this->assertSame($id, $row['id']);
        $this->assertFalse($row['is_mine']);
    }

    public function test_list_rows_carry_money_as_numbers_and_the_related_names(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya', total: 500000);
        DB::table('travel_reports')->insert([
            'employee_id' => self::ME, 'budget_request_id' => $budget, 'destination_city' => 'Surabaya',
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->lpj($budget, self::ME, '2026-10-20 08:00:00', anggaran: 500000, realisasi: 420000.5);

        $row = $this->invoke('index', $this->listRequest(self::ME))['data'][0];

        $this->assertNumber(500000.0, $row['total_anggaran']);
        $this->assertNumber(420000.5, $row['total_realisasi']);
        $this->assertNumber(79999.5, $row['sisa']);
        $this->assertSame('Perjalanan Surabaya', $row['budget_request']['title']);
        $this->assertSame('Surabaya', $row['travel_report']['destination_city']);
        $this->assertSame('pending', $row['status']);
    }

    public function test_the_list_is_empty_when_i_have_no_lpj(): void
    {
        $this->assertSame([], $this->invoke('index', $this->listRequest(self::ME))['data']);
    }

    // ── detail ────────────────────────────────────────────────────────────────

    public function test_the_detail_has_items_proofs_summary_and_approval_history(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya', total: 300000, items: [
            ['transport', 'Tiket kereta', 200000],
            ['meal', 'Uang makan', 100000],
        ]);
        $id = $this->lpj($budget, self::ME, '2026-10-20 08:00:00', anggaran: 300000, realisasi: 350000);
        DB::table('lpj_items')->insert([
            ['lpj_id' => $id, 'uraian' => 'Tiket kereta PP', 'kategori' => 'transport', 'satuan' => 'tiket', 'volume' => 2,
                'realisasi' => 250000, 'bukti_file' => 'lpj-bukti/tiket.jpg', 'keterangan' => 'Kelas ekonomi', 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now()],
            ['lpj_id' => $id, 'uraian' => 'Makan 2 hari', 'kategori' => 'meal', 'satuan' => null, 'volume' => 1,
                'realisasi' => 100000, 'bukti_file' => 'lpj-bukti/nota.pdf', 'keterangan' => null, 'sort_order' => 1,
                'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('approval_logs')->insert([
            'approvable_type' => Lpj::class, 'approvable_id' => $id, 'approver_id' => self::APPROVER,
            'action' => 'rejected', 'step_order' => 1, 'notes' => 'Nota makan belum ada',
            'created_at' => '2026-10-21 09:00:00', 'updated_at' => '2026-10-21 09:00:00',
        ]);

        $data = $this->invoke('show', $this->listRequest(self::ME), $id)['data'];

        $this->assertSame('Perjalanan Surabaya', $data['budget_request']['title']);
        $this->assertCount(2, $data['items']);
        $this->assertSame('Transportasi', $data['items'][0]['kategori_label']);
        $this->assertNumber(2.0, $data['items'][0]['volume']);
        $this->assertNumber(250000.0, $data['items'][0]['realisasi']);
        $this->assertStringEndsWith('storage/lpj-bukti/tiket.jpg', $data['items'][0]['bukti_url']);
        $this->assertTrue($data['items'][0]['bukti_is_image']);
        $this->assertFalse($data['items'][1]['bukti_is_image'], 'PDF bukan gambar');
        $this->assertSame('Nota makan belum ada', $data['approval_logs'][0]['notes']);
        $this->assertSame('Budi Atasan', $data['approval_logs'][0]['approver']['full_name']);

        $summary = $data['summary'];
        $this->assertNumber(300000.0, $summary['total_pemasukan']);
        $this->assertNumber(350000.0, $summary['total_pengeluaran']);
        $this->assertSame(['Tiket kereta', 'Uang makan'], array_column($summary['pemasukan'], 'uraian'));
        $transport = collect($summary['per_kategori'])->firstWhere('kategori', 'transport');
        $this->assertNumber(200000.0, $transport['anggaran']);
        $this->assertNumber(250000.0, $transport['realisasi']);
        $this->assertNumber(50000.0, $transport['over'], 'transportasi melebihi anggarannya');
        $this->assertNumber(50000.0, $summary['total_over_budget']);
    }

    public function test_the_detail_income_is_only_the_costs_the_author_reports(): void
    {
        $budget = $this->budget(self::OTHER, 'Perjalanan tim', total: 550000, items: [
            ['transport', 'Bensin dan tol', 400000],
            ['meal', 'Uang makan Shandy', 75000, self::ME],
            ['meal', 'Uang makan Budi', 75000, self::APPROVER],
        ]);
        $this->participate($budget, self::ME);
        $this->participate($budget, self::APPROVER);
        $ownerLpj = $this->lpj($budget, self::OTHER, '2026-10-01 08:00:00', anggaran: 400000);
        $myLpj = $this->lpj($budget, self::ME, '2026-10-02 08:00:00', anggaran: 75000);

        $owner = $this->invoke('show', $this->listRequest(self::OTHER), $ownerLpj)['data']['summary'];
        $mine = $this->invoke('show', $this->listRequest(self::ME), $myLpj)['data']['summary'];

        $this->assertSame(['Bensin dan tol'], array_column($owner['pemasukan'], 'uraian'));
        $this->assertNumber(400000.0, $owner['total_pemasukan']);
        $this->assertSame(['Uang makan Shandy'], array_column($mine['pemasukan'], 'uraian'));
        $this->assertNumber(75000.0, $mine['total_pemasukan']);

        // Anggaran yang dimuat tanpa kolom pemilik tetap dikenali pemiliknya.
        $partial = Lpj::with('budgetRequest:id,title')->findOrFail($ownerLpj);
        $this->assertSame(['Bensin dan tol'], array_column(\App\Support\LpjSummary::for($partial)['pemasukan']->all(), 'uraian'));
    }

    public function test_an_lpj_made_before_costs_had_owners_still_shows_the_whole_budget_as_income(): void
    {
        $budget = $this->budget(self::OTHER, 'Rapat lama', total: 300000, items: [
            ['transport', 'Tiket', 200000],
            ['meal', 'Makan', 100000],
        ]);
        $this->participate($budget, self::ME);
        $legacy = $this->lpj($budget, self::ME, '2026-10-01 08:00:00', anggaran: 300000);

        $summary = $this->invoke('show', $this->listRequest(self::ME), $legacy)['data']['summary'];

        $this->assertSame(['Tiket', 'Makan'], array_column($summary['pemasukan'], 'uraian'));
        $this->assertNumber(300000.0, $summary['total_pemasukan']);
    }

    public function test_someone_elses_lpj_is_not_found(): void
    {
        $id = $this->lpj($this->budget(self::OTHER, 'Rapat Malang'), self::OTHER, '2026-10-21 08:00:00');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->invoke('show', $this->listRequest(self::ME), $id);
    }

    public function test_a_missing_lpj_is_not_found(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->invoke('show', $this->listRequest(self::ME), 999);
    }

    // ── anggaran yang bisa dibuatkan LPJ ──────────────────────────────────────

    public function test_available_budgets_are_my_approved_or_paid_ones_without_my_lpj(): void
    {
        $approved = $this->budget(self::ME, 'Disetujui', status: 'approved', total: 200000, items: [['transport', 'Tiket', 200000]]);
        $paid = $this->budget(self::ME, 'Sudah cair', status: 'paid');
        $this->budget(self::ME, 'Masih menunggu', status: 'pending');
        $this->budget(self::ME, 'Ditolak', status: 'rejected');
        $this->budget(self::OTHER, 'Milik orang lain', status: 'approved');
        $done = $this->budget(self::ME, 'Sudah ada LPJ', status: 'approved');
        $this->lpj($done, self::ME, '2026-10-01 08:00:00');

        $result = $this->invoke('availableRequests', $this->listRequest(self::ME));

        $this->assertEqualsCanonicalizing([$approved, $paid], array_column($result['data'], 'id'));
        $first = collect($result['data'])->firstWhere('id', $approved);
        $this->assertNumber(200000.0, $first['total_amount']);
        $this->assertEquals([['uraian' => 'Tiket', 'kategori' => 'transport', 'kategori_label' => 'Transportasi', 'jumlah' => 200000]], $first['items']);
        $this->assertNumber(200000, $first['items'][0]['jumlah']);
    }

    public function test_a_participant_without_personal_costs_is_not_offered_the_team_budget(): void
    {
        $budget = $this->budget(self::OTHER, 'Rapat bersama', status: 'approved');
        $this->participate($budget, self::ME);

        $this->assertSame([], $this->invoke('availableRequests', $this->listRequest(self::ME))['data']);
        $this->assertSame([$budget], array_column($this->invoke('availableRequests', $this->listRequest(self::OTHER))['data'], 'id'));
    }

    public function test_each_person_is_offered_the_budget_with_only_the_costs_they_report(): void
    {
        $budget = $this->budget(self::OTHER, 'Perjalanan tim', total: 650000, items: [
            ['transport', 'Bensin dan tol', 400000],
            ['meal', 'Uang makan Rina', 100000, self::OTHER],
            ['meal', 'Uang makan Shandy', 150000, self::ME],
        ]);
        $this->participate($budget, self::ME);

        $mine = $this->invoke('availableRequests', $this->listRequest(self::ME))['data'];
        $owners = $this->invoke('availableRequests', $this->listRequest(self::OTHER))['data'];

        $this->assertSame([$budget], array_column($mine, 'id'));
        $this->assertSame(['Uang makan Shandy'], array_column($mine[0]['items'], 'uraian'));
        $this->assertNumber(150000.0, $mine[0]['total_amount']);
        $this->assertSame(['Bensin dan tol', 'Uang makan Rina'], array_column($owners[0]['items'], 'uraian'));
        $this->assertNumber(500000.0, $owners[0]['total_amount']);
    }

    public function test_the_budget_is_offered_again_to_whoever_has_no_lpj_yet(): void
    {
        $budget = $this->budget(self::ME, 'Rapat bersama', status: 'approved', items: [
            ['transport', 'Bensin', 100000],
            ['meal', 'Uang makan Rina', 50000, self::OTHER],
        ]);
        $this->participate($budget, self::OTHER);
        $this->lpj($budget, self::OTHER, '2026-10-01 08:00:00');

        $this->assertSame([$budget], array_column($this->invoke('availableRequests', $this->listRequest(self::ME))['data'], 'id'));
        $this->assertSame([], $this->invoke('availableRequests', $this->listRequest(self::OTHER))['data'], 'peserta sudah membuat LPJ-nya');
    }

    public function test_available_budgets_come_with_the_expense_categories(): void
    {
        $categories = $this->invoke('availableRequests', $this->listRequest(self::ME))['categories'];

        $this->assertSame('Transportasi', $categories['transport']);
        $this->assertSame(['transport', 'meal', 'lumpsum', 'entertain', 'operasional', 'lainnya'], array_keys($categories));
    }

    // ── buat ──────────────────────────────────────────────────────────────────

    public function test_creating_an_lpj_stores_it_pending_with_totals_from_its_items(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya', total: 500000);

        $response = $this->store([
            'budget_request_id' => $budget,
            'nomor_lpj' => 'ST/012/X/2026',
            'catatan' => 'Selesai tepat waktu',
            'items' => [
                ['kategori' => 'transport', 'uraian' => 'Tiket kereta', 'satuan' => 'tiket', 'volume' => '2', 'realisasi' => '300000', 'keterangan' => 'PP'],
                ['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => '120000.50'],
            ],
        ]);

        $this->assertSame(201, $response->getStatusCode());
        $lpj = Lpj::with('items')->firstOrFail();
        $this->assertSame('pending', $lpj->status);
        $this->assertSame(1, $lpj->current_step);
        $this->assertSame(self::ME, $lpj->employee_id);
        $this->assertSame('ST/012/X/2026', $lpj->nomor_lpj);
        $this->assertSame('Selesai tepat waktu', $lpj->catatan);
        $this->assertEquals(500000, $lpj->total_anggaran, 'anggaran diambil dari pengajuan, bukan dari app');
        $this->assertEquals(420000.5, $lpj->total_realisasi);
        $this->assertEquals(79999.5, $lpj->sisa);
        $this->assertSame(['Tiket kereta', 'Makan'], $lpj->items->pluck('uraian')->all());
        $this->assertSame([0, 1], $lpj->items->pluck('sort_order')->all());
        $this->assertEquals(2, $lpj->items[0]->volume);
        $this->assertEquals(1, $lpj->items[1]->volume, 'volume bawaan 1');
        $this->assertNull($lpj->items[1]->satuan);

        $data = $response->getData(true)['data'];
        $this->assertSame($lpj->id, $data['id']);
        $this->assertNumber(420000.5, $data['total_realisasi']);
        $this->assertSame('LPJ berhasil diajukan', $response->getData(true)['message']);
    }

    public function test_creating_notifies_the_first_approver(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');

        $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $notification = DB::table('notifications')->first();
        $this->assertSame(self::APPROVER, (int) $notification->employee_id);
        $this->assertSame('Pengajuan LPJ Baru', $notification->title);
        $this->assertSame('Shandy mengajukan LPJ untuk Perjalanan Surabaya', $notification->message);
        $this->assertSame(Lpj::class, $notification->reference_type);
        $this->assertSame(Lpj::first()->id, (int) $notification->reference_id);
    }

    public function test_creating_without_a_configured_approver_still_works(): void
    {
        DB::table('employee_approvers')->delete();
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');

        $response = $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_proof_files_are_stored_per_row(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');

        $this->store(
            [
                'budget_request_id' => $budget,
                'items' => [
                    ['kategori' => 'transport', 'uraian' => 'Tiket', 'realisasi' => 1000],
                    ['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 2000],
                    ['kategori' => 'lainnya', 'uraian' => 'Parkir', 'realisasi' => 3000],
                ],
            ],
            ['items' => [
                0 => ['bukti_file' => UploadedFile::fake()->create('tiket.jpg', 20, 'image/jpeg')],
                2 => ['bukti_file' => UploadedFile::fake()->create('parkir.pdf', 50, 'application/pdf')],
            ]],
        );

        $items = Lpj::firstOrFail()->items;
        $this->assertNotNull($items[0]->bukti_file);
        $this->assertNull($items[1]->bukti_file, 'baris tanpa bukti tetap tanpa bukti');
        $this->assertNotNull($items[2]->bukti_file);
        $this->assertStringEndsWith('.jpg', $items[0]->bukti_file);
        $this->assertStringEndsWith('.pdf', $items[2]->bukti_file);
        Storage::disk('public')->assertExists($items[0]->bukti_file);
        Storage::disk('public')->assertExists($items[2]->bukti_file);
    }

    public function test_the_lpj_links_to_the_current_lhp_not_a_rejected_one(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');
        $rejected = $this->lhp($budget, self::ME, 'rejected');
        $current = $this->lhp($budget, self::ME, 'pending');
        $this->lhp($budget, self::OTHER, 'approved');

        $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $this->assertSame($current, Lpj::firstOrFail()->travel_report_id);
        $this->assertNotSame($rejected, Lpj::first()->travel_report_id);
    }

    public function test_an_lpj_without_any_lhp_has_no_link(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');

        $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $this->assertNull(Lpj::firstOrFail()->travel_report_id);
    }

    public function test_the_lpj_links_to_a_participants_lhp_when_the_owner_wrote_none(): void
    {
        $budget = $this->budget(self::ME, 'Rapat bersama');
        $this->participate($budget, self::OTHER);
        $theirs = $this->lhp($budget, self::OTHER, 'approved');

        $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $this->assertSame($theirs, Lpj::firstOrFail()->travel_report_id);
    }

    public function test_a_participant_without_personal_costs_cannot_create_an_lpj(): void
    {
        $budget = $this->budget(self::OTHER, 'Rapat bersama', total: 100000);
        $this->participate($budget, self::ME);

        $response = $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('tidak punya biaya atas nama Anda', $response->getData(true)['message']);
        $this->assertSame(0, Lpj::count());
    }

    public function test_a_participant_creates_an_lpj_for_their_own_costs_only(): void
    {
        $budget = $this->budget(self::OTHER, 'Perjalanan tim', total: 650000, items: [
            ['transport', 'Bensin dan tol', 400000],
            ['meal', 'Uang makan Rina', 100000, self::OTHER],
            ['meal', 'Uang makan Shandy', 150000, self::ME],
        ]);
        $this->participate($budget, self::ME);

        $response = $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan 3 hari', 'realisasi' => 120000]]]);

        $this->assertSame(201, $response->getStatusCode());
        $lpj = Lpj::firstOrFail();
        $this->assertSame(self::ME, $lpj->employee_id);
        $this->assertEquals(150000, $lpj->total_anggaran, 'hanya uang makan miliknya');
        $this->assertEquals(30000, $lpj->sisa);
    }

    public function test_the_owner_lpj_carries_the_shared_costs_and_their_own_but_not_a_participants(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan tim', total: 650000, items: [
            ['transport', 'Bensin dan tol', 400000],
            ['meal', 'Uang makan Shandy', 100000, self::ME],
            ['meal', 'Uang makan Rina', 150000, self::OTHER],
        ]);
        $this->participate($budget, self::OTHER);

        $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'transport', 'uraian' => 'Bensin', 'realisasi' => 380000]]]);

        $this->assertEquals(500000, Lpj::firstOrFail()->total_anggaran);
    }

    public function test_the_owner_and_a_participant_each_get_their_own_lpj_for_one_budget(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan tim', total: 550000, items: [
            ['transport', 'Bensin dan tol', 400000],
            ['meal', 'Uang makan Rina', 150000, self::OTHER],
        ]);
        $this->participate($budget, self::OTHER);
        $body = ['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Biaya', 'realisasi' => 1000]]];

        $this->assertSame(201, $this->store($body, as: self::ME)->getStatusCode());
        $this->assertSame(201, $this->store($body, as: self::OTHER)->getStatusCode());

        $this->assertSame([self::ME, self::OTHER], Lpj::orderBy('id')->pluck('employee_id')->all());
        $this->assertSame([400000.0, 150000.0], Lpj::orderBy('id')->pluck('total_anggaran')->map(fn ($v) => (float) $v)->all());
        $this->assertSame(422, $this->store($body, as: self::OTHER)->getStatusCode(), 'LPJ kedua dari orang yang sama ditolak');
    }

    public function test_a_second_lpj_from_the_same_person_for_the_same_budget_is_refused(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');
        $this->lpj($budget, self::ME, '2026-10-01 08:00:00');

        $response = $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Anda sudah membuat LPJ untuk anggaran ini.', $response->getData(true)['message']);
        $this->assertSame(1, Lpj::count());
    }

    public function test_budgets_that_are_not_mine_or_not_approved_are_refused(): void
    {
        $others = $this->budget(self::OTHER, 'Milik Rina');
        $pending = $this->budget(self::ME, 'Belum disetujui', status: 'pending');
        $rejected = $this->budget(self::ME, 'Ditolak', status: 'rejected');

        foreach ([$others, $pending, $rejected] as $budget) {
            $response = $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

            $this->assertSame(422, $response->getStatusCode(), "anggaran #$budget");
            $this->assertSame('Anggaran tidak ditemukan, belum disetujui, atau Anda bukan pemilik maupun pesertanya.', $response->getData(true)['message']);
        }
        $this->assertSame(0, Lpj::count());
    }

    public function test_the_owner_can_still_create_their_lpj_after_a_participant_made_one(): void
    {
        $budget = $this->budget(self::ME, 'Rapat bersama');
        $this->participate($budget, self::OTHER);
        $this->lpj($budget, self::OTHER, '2026-10-01 08:00:00');

        $response = $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(2, Lpj::count());
    }

    public function test_a_participant_sees_the_owner_lpj_but_not_another_participants(): void
    {
        $budget = $this->budget(self::OTHER, 'Perjalanan tim', total: 550000, items: [
            ['transport', 'Bensin dan tol', 400000],
            ['meal', 'Uang makan Shandy', 75000, self::ME],
            ['meal', 'Uang makan Budi', 75000, self::APPROVER],
        ]);
        $this->participate($budget, self::ME);
        $this->participate($budget, self::APPROVER);
        $ownerLpj = $this->lpj($budget, self::OTHER, '2026-10-01 08:00:00');
        $myLpj = $this->lpj($budget, self::ME, '2026-10-02 08:00:00');
        $this->lpj($budget, self::APPROVER, '2026-10-03 08:00:00');

        $ids = array_column($this->invoke('index', $this->listRequest(self::ME))['data'], 'id');
        $ownerSees = array_column($this->invoke('index', $this->listRequest(self::OTHER))['data'], 'id');

        $this->assertEqualsCanonicalizing([$ownerLpj, $myLpj], $ids);
        $this->assertCount(3, $ownerSees, 'pemilik anggaran melihat semua LPJ anggarannya');
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_input_is_rejected_and_nothing_is_saved(array $overrides, string $field): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');
        $body = array_replace(
            ['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]],
            $overrides,
        );

        try {
            $this->store($body);
            $this->fail('seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }

        $this->assertSame(0, Lpj::count());
        $this->assertSame(0, DB::table('lpj_items')->count());
    }

    public static function invalidBodies(): array
    {
        return [
            'tanpa anggaran' => [['budget_request_id' => null], 'budget_request_id'],
            'anggaran tidak ada' => [['budget_request_id' => 999], 'budget_request_id'],
            'tanpa baris pengeluaran' => [['items' => []], 'items'],
            'kategori kosong' => [['items' => [['uraian' => 'Makan', 'realisasi' => 1000]]], 'items.0.kategori'],
            'kategori tak dikenal' => [['items' => [['kategori' => 'hiburan', 'uraian' => 'Makan', 'realisasi' => 1000]]], 'items.0.kategori'],
            'uraian kosong' => [['items' => [['kategori' => 'meal', 'realisasi' => 1000]]], 'items.0.uraian'],
            'uraian terlalu panjang' => [['items' => [['kategori' => 'meal', 'uraian' => str_repeat('x', 256), 'realisasi' => 1000]]], 'items.0.uraian'],
            'realisasi kosong' => [['items' => [['kategori' => 'meal', 'uraian' => 'Makan']]], 'items.0.realisasi'],
            'realisasi negatif' => [['items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => -1]]], 'items.0.realisasi'],
            'realisasi bukan angka' => [['items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 'banyak']]], 'items.0.realisasi'],
            'volume negatif' => [['items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000, 'volume' => -2]]], 'items.0.volume'],
            'nomor terlalu panjang' => [['nomor_lpj' => str_repeat('9', 101)], 'nomor_lpj'],
        ];
    }

    public function test_an_invalid_second_row_rejects_the_whole_lpj(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');

        try {
            $this->store(['budget_request_id' => $budget, 'items' => [
                ['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000],
                ['kategori' => 'meal', 'uraian' => '', 'realisasi' => 2000],
            ]]);
            $this->fail('seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.1.uraian', $e->errors());
        }

        $this->assertSame(0, Lpj::count(), 'tidak ada LPJ setengah jadi');
    }

    public function test_a_proof_that_is_not_an_image_or_pdf_is_rejected(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');

        try {
            $this->store(
                ['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]],
                ['items' => [0 => ['bukti_file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')]]],
            );
            $this->fail('seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertSame(['Bukti harus berupa gambar (JPG, PNG, GIF, WEBP) atau PDF.'], $e->errors()['items.0.bukti_file']);
        }

        $this->assertSame(0, Lpj::count());
    }

    public function test_an_oversized_proof_is_rejected(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya');

        $this->expectException(ValidationException::class);

        $this->store(
            ['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Makan', 'realisasi' => 1000]]],
            ['items' => [0 => ['bukti_file' => UploadedFile::fake()->create('besar.pdf', 11 * 1024, 'application/pdf')]]],
        );
    }

    public function test_a_zero_expense_is_allowed(): void
    {
        $budget = $this->budget(self::ME, 'Perjalanan Surabaya', total: 100000);

        $response = $this->store(['budget_request_id' => $budget, 'items' => [['kategori' => 'meal', 'uraian' => 'Tidak jadi makan', 'realisasi' => 0]]]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertEquals(100000, Lpj::firstOrFail()->sisa);
    }

    // ── rute ──────────────────────────────────────────────────────────────────

    public function test_the_routes_exist_behind_sanctum_and_resolve_to_the_right_actions(): void
    {
        foreach ([['GET', 'api/lpj', 'index'], ['POST', 'api/lpj', 'store'], ['GET', 'api/lpj/available-requests', 'availableRequests'], ['GET', 'api/lpj/12', 'show']] as [$method, $uri, $action]) {
            $route = Route::getRoutes()->match(Request::create('/'.$uri, $method));

            $this->assertSame(LpjController::class.'@'.$action, $route->getActionName(), "$method $uri");
            $this->assertContains('auth:sanctum', $route->gatherMiddleware(), "$method $uri harus butuh login");
        }
    }

    // ── bantuan ───────────────────────────────────────────────────────────────

    /** Nilai uang harus angka JSON (bukan teks desimal seperti "50000.00"), supaya app tidak perlu menebak. */
    private function assertNumber(float|int $expected, mixed $actual): void
    {
        $this->assertTrue(is_int($actual) || is_float($actual), 'harus angka, bukan teks: '.var_export($actual, true));
        $this->assertEquals($expected, $actual);
    }

    private function person(int $id, string $name): void
    {
        DB::table('employees')->insert([
            'id' => $id, 'full_name' => $name, 'email' => "e{$id}@example.test", 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function budget(int $employeeId, string $title, string $status = 'approved', float $total = 100000, array $items = []): int
    {
        $id = DB::table('budget_requests')->insertGetId([
            'employee_id' => $employeeId, 'title' => $title, 'status' => $status, 'total_amount' => $total,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Tanpa rincian, anggaran punya satu biaya bersama sebesar totalnya. Elemen ke-4 baris
        // adalah pemilik biaya pribadi (id peserta); tanpa itu biayanya bersama.
        $items = $items ?: [['transport', 'Biaya', $total]];
        foreach ($items as $item) {
            DB::table('budget_request_items')->insert([
                'budget_request_id' => $id, 'employee_id' => $item[3] ?? null, 'type' => $item[0], 'description' => $item[1],
                'amount' => $item[2], 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    private function participate(int $budgetId, int $employeeId): void
    {
        DB::table('budget_request_participants')->insert([
            'budget_request_id' => $budgetId, 'employee_id' => $employeeId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lhp(int $budgetId, int $employeeId, string $status): int
    {
        return DB::table('travel_reports')->insertGetId([
            'employee_id' => $employeeId, 'budget_request_id' => $budgetId, 'destination_city' => 'Surabaya',
            'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function lpj(int $budgetId, int $employeeId, string $createdAt, float $anggaran = 100000, float $realisasi = 0, ?string $nomor = null): int
    {
        return DB::table('lpjs')->insertGetId([
            'budget_request_id' => $budgetId, 'travel_report_id' => DB::table('travel_reports')->where('budget_request_id', $budgetId)->value('id'),
            'employee_id' => $employeeId, 'nomor_lpj' => $nomor, 'total_anggaran' => $anggaran,
            'total_realisasi' => $realisasi, 'sisa' => $anggaran - $realisasi, 'status' => 'pending', 'current_step' => 1,
            'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    private function listRequest(int $as): Request
    {
        return $this->asEmployee(Request::create('/api/lpj', 'GET'), $as);
    }

    private function asEmployee(Request $request, int $employeeId): Request
    {
        $request->setUserResolver(fn () => Employee::findOrFail($employeeId));

        return $request;
    }

    private function invoke(string $method, Request $request, ...$args): array
    {
        return (new LpjController())->{$method}($request, ...$args)->getData(true);
    }

    private function store(array $body, array $files = [], int $as = self::ME)
    {
        return (new LpjController())->store($this->asEmployee(Request::create('/api/lpj', 'POST', $body, [], $files), $as));
    }
}
