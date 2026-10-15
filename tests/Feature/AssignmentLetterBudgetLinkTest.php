<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BudgetController;
use App\Models\AssignmentLetter;
use App\Models\BudgetRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tahap 2 surat tugas: anggaran memilih surat tugas (portal & API), LHP mengambil datanya,
 * notifikasi ke peserta, dan cetak surat oleh peserta.
 */
class AssignmentLetterBudgetLinkTest extends AssignmentLetterTestCase
{
    private AssignmentLetter $letter;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('travel_zones')->insert([
            ['zone' => '1', 'name' => 'Dalam Kota', 'min_km' => 0, 'max_km' => 50, 'meal_allowance' => 25000],
            ['zone' => '3', 'name' => 'Luar Provinsi', 'min_km' => 201, 'max_km' => null, 'meal_allowance' => 75000],
        ]);

        // Surat terbit: Fadel (PERMANENT) & Prastowo (CONTRACT), dua lokasi, terjauh 590 km.
        $this->letter = $this->issue($this->storeDraft([
            'letter_date' => '2026-09-24',
            'departure_date' => '2026-09-25',
            'work_start_date' => '2026-09-25',
            'return_date' => '2026-09-27',
            'destinations' => [
                ['activity' => 'Prospek di BBWS Bengawan Solo', 'address' => 'Sukoharjo, Jawa Tengah', 'distance_km' => '55.6'],
                ['activity' => 'Riset di Bendungan Ciawi', 'address' => 'Bogor, Jawa Barat', 'distance_km' => '590'],
            ],
        ]));
    }

    private function budgetPayload(array $overrides = []): array
    {
        return array_replace([
            'type' => 'budget',
            'title' => '',
            'description' => 'Perjalanan dinas',
            // Isian manual ini harus kalah oleh data surat tugas.
            'surat_tugas_no' => 'ASAL-ISI',
            'surat_tugas_date' => '2026-01-01',
            'distance_km' => 10,
            'departure_date' => '2026-01-01',
            'return_date' => '2026-01-02',
            'participants' => [self::INTERN],
            'items' => [['type' => 'transport', 'description' => 'Tol', 'amount' => 150000]],
        ], $overrides);
    }

    private function submitAs(int $employeeId, array $overrides = [])
    {
        return $this->withSession(['employee_id' => $employeeId])
            ->post(route('employee.budget-requests.store'), $this->budgetPayload($overrides + ['title' => 'Dinas Bogor']));
    }

    public function test_issue_notifies_every_employee_in_the_letter(): void
    {
        $notified = DB::table('notifications')
            ->where('reference_type', AssignmentLetter::class)
            ->where('reference_id', $this->letter->id)
            ->pluck('employee_id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([self::PERMANENT, self::CONTRACT], $notified);
        $this->assertStringContainsString($this->letter->number, DB::table('notifications')->value('message'));
    }

    public function test_budget_form_offers_only_issued_letters_that_list_the_employee(): void
    {
        $draft = $this->storeDraft(['employees' => [['employee_id' => self::PERMANENT, 'position' => '']]]);

        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.budget-requests.create'))
            ->assertOk()
            ->assertSee('name="assignment_letter_id"', false)
            ->assertSee($this->letter->number)
            ->assertDontSee('value="'.$draft->id.'"', false);

        // Direktur tidak tercantum sebagai peserta.
        $this->withSession(['employee_id' => self::DIRECTOR])
            ->get(route('employee.budget-requests.create'))
            ->assertOk()
            ->assertDontSee($this->letter->number);
    }

    public function test_portal_budget_takes_its_travel_data_from_the_letter(): void
    {
        $this->submitAs(self::PERMANENT, ['assignment_letter_id' => $this->letter->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('employee.budget-requests.index'));

        $budget = BudgetRequest::with('participants')->sole();
        $this->assertSame($this->letter->id, $budget->assignment_letter_id);
        $this->assertSame($this->letter->number, $budget->surat_tugas_no);
        $this->assertSame('2026-09-24', $budget->surat_tugas_date->toDateString());
        $this->assertSame('2026-09-25', $budget->departure_date->toDateString());
        $this->assertSame('2026-09-27', $budget->return_date->toDateString());
        $this->assertSame(590, (int) $budget->distance_km);
        $this->assertSame('Luar Provinsi', $budget->travelZone->name);
        // Peserta = orang lain di surat tugas, bukan pilihan manual di form.
        $this->assertSame([self::CONTRACT], $budget->participants->pluck('id')->all());
    }

    public function test_one_letter_backs_one_active_budget_until_it_is_rejected(): void
    {
        $this->submitAs(self::PERMANENT, ['assignment_letter_id' => $this->letter->id])->assertSessionHasNoErrors();

        $this->submitAs(self::CONTRACT, ['assignment_letter_id' => $this->letter->id])
            ->assertSessionHasErrors('assignment_letter_id');
        $this->assertSame(1, BudgetRequest::count());

        // Peserta lain tidak lagi ditawari surat ini.
        $this->withSession(['employee_id' => self::CONTRACT])
            ->get(route('employee.budget-requests.create'))
            ->assertDontSee($this->letter->number);

        BudgetRequest::query()->update(['status' => 'rejected']);

        $this->submitAs(self::CONTRACT, ['assignment_letter_id' => $this->letter->id])->assertSessionHasNoErrors();
        $this->assertSame(2, BudgetRequest::where('assignment_letter_id', $this->letter->id)->count());
    }

    public function test_letter_cannot_be_used_by_outsiders_or_after_cancellation(): void
    {
        $this->submitAs(self::DIRECTOR, ['assignment_letter_id' => $this->letter->id])
            ->assertSessionHasErrors('assignment_letter_id');

        $this->letter->update(['status' => AssignmentLetter::STATUS_CANCELLED]);
        $this->submitAs(self::PERMANENT, ['assignment_letter_id' => $this->letter->id])
            ->assertSessionHasErrors('assignment_letter_id');

        $this->assertSame(0, BudgetRequest::count());
    }

    public function test_budget_without_letter_keeps_manual_input(): void
    {
        $this->submitAs(self::PERMANENT)->assertSessionHasNoErrors();

        $budget = BudgetRequest::with('participants')->sole();
        $this->assertNull($budget->assignment_letter_id);
        $this->assertSame('ASAL-ISI', $budget->surat_tugas_no);
        $this->assertSame([self::INTERN], $budget->participants->pluck('id')->all());
    }

    public function test_editing_can_keep_or_release_the_letter(): void
    {
        $this->submitAs(self::PERMANENT, ['assignment_letter_id' => $this->letter->id])->assertSessionHasNoErrors();
        $budget = BudgetRequest::sole();

        // Surat milik anggaran ini sendiri tetap ditawarkan saat edit.
        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.budget-requests.edit', $budget->id))
            ->assertOk()
            ->assertSee($this->letter->number);

        $this->withSession(['employee_id' => self::PERMANENT])
            ->put(route('employee.budget-requests.update', $budget->id), $this->budgetPayload([
                'title' => 'Dinas Bogor', 'assignment_letter_id' => $this->letter->id,
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame($this->letter->id, $budget->fresh()->assignment_letter_id);

        $this->withSession(['employee_id' => self::PERMANENT])
            ->put(route('employee.budget-requests.update', $budget->id), $this->budgetPayload([
                'title' => 'Dinas Bogor', 'assignment_letter_id' => '',
            ]))
            ->assertSessionHasNoErrors();
        $this->assertNull($budget->fresh()->assignment_letter_id);
        $this->assertSame('ASAL-ISI', $budget->fresh()->surat_tugas_no);
    }

    public function test_api_lists_letters_and_links_them_on_store(): void
    {
        $employee = Employee::findOrFail(self::CONTRACT);

        $list = Request::create('/api/budget/assignment-letters', 'GET');
        $list->setUserResolver(fn () => $employee);
        $data = app(BudgetController::class)->assignmentLetters($list)->getData(true)['data'];

        $this->assertCount(1, $data);
        $this->assertSame($this->letter->number, $data[0]['number']);
        $this->assertSame(590, $data[0]['distance_km']);
        $this->assertSame('Luar Provinsi', $data[0]['travel_zone']['name']);

        $store = Request::create('/api/budget/requests', 'POST', [
            'type' => 'budget',
            'title' => 'Dinas Bogor',
            'assignment_letter_id' => $this->letter->id,
            'items' => json_encode([['type' => 'transport', 'description' => 'Tol', 'amount' => 100000]]),
        ]);
        $store->setUserResolver(fn () => $employee);
        $response = app(BudgetController::class)->store($store);

        $this->assertSame(201, $response->getStatusCode());
        $budget = BudgetRequest::with('participants')->sole();
        $this->assertSame($this->letter->number, $budget->surat_tugas_no);
        $this->assertSame([self::PERMANENT], $budget->participants->pluck('id')->all());

        // Aplikasi lama tanpa assignment_letter_id tetap bisa mengajukan.
        $legacy = Request::create('/api/budget/requests', 'POST', [
            'type' => 'budget',
            'title' => 'Tanpa surat',
            'items' => json_encode([['type' => 'meal', 'amount' => 50000]]),
        ]);
        $legacy->setUserResolver(fn () => $employee);
        $this->assertSame(201, app(BudgetController::class)->store($legacy)->getStatusCode());

        // Surat yang sudah dipakai ditolak dengan 422.
        $this->expectException(ValidationException::class);
        $again = Request::create('/api/budget/requests', 'POST', [
            'type' => 'budget', 'title' => 'Dobel', 'assignment_letter_id' => $this->letter->id,
            'items' => json_encode([['type' => 'meal', 'amount' => 1]]),
        ]);
        $again->setUserResolver(fn () => Employee::findOrFail(self::PERMANENT));
        app(BudgetController::class)->store($again);
    }

    public function test_employee_in_the_letter_can_print_it(): void
    {
        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.assignment-letters.print', $this->letter->id))
            ->assertOk()
            ->assertSee($this->letter->number);

        $this->withSession(['employee_id' => self::DIRECTOR])
            ->get(route('employee.assignment-letters.print', $this->letter->id))
            ->assertNotFound();

        $draft = $this->storeDraft();
        $this->withSession(['employee_id' => self::PERMANENT])
            ->get(route('employee.assignment-letters.print', $draft->id))
            ->assertNotFound();
    }

    public function test_admin_budget_detail_goes_back_to_where_it_was_opened_from(): void
    {
        $this->submitAs(self::PERMANENT, ['assignment_letter_id' => $this->letter->id])->assertSessionHasNoErrors();
        $budget = BudgetRequest::sole();

        // Dari Persetujuan: kembali ke Persetujuan dengan tab dan departemen yang sama.
        $approvals = '/admin/approvals?tab=budget&department_id=3';
        $this->asRole('finance_admin')
            ->get(route('admin.budget-requests.show', ['id' => $budget->id, 'back' => $approvals]))
            ->assertOk()
            ->assertSee('Kembali ke Persetujuan')
            ->assertSee(e($approvals), false);

        // Dari daftar Anggaran: kembali ke daftar itu lengkap dengan filternya.
        $list = '/admin/budget-requests?status=pending&page=2';
        $this->asRole('finance_admin')
            ->get(route('admin.budget-requests.show', ['id' => $budget->id, 'back' => $list]))
            ->assertOk()
            ->assertSee('Kembali ke Anggaran')
            ->assertSee(e($list), false);

        // Tanpa asal: daftar bawaan.
        $this->asRole('finance_admin')->get(route('admin.budget-requests.show', $budget->id))
            ->assertOk()
            ->assertDontSee('Kembali ke')
            ->assertSee(route('admin.budget-requests.index'), false);

        // Alamat di luar /admin ditolak, jatuh ke daftar bawaan.
        $this->asRole('finance_admin')
            ->get(route('admin.budget-requests.show', ['id' => $budget->id, 'back' => 'https://evil.test/admin/x']))
            ->assertOk()
            ->assertDontSee('evil.test');
    }

    public function test_admin_budget_list_links_remember_the_filtered_list(): void
    {
        $this->submitAs(self::PERMANENT, ['assignment_letter_id' => $this->letter->id])->assertSessionHasNoErrors();
        $budget = BudgetRequest::sole();

        $this->asRole('finance_admin')->get(route('admin.budget-requests.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee(e(route('admin.budget-requests.show', ['id' => $budget->id, 'back' => '/admin/budget-requests?status=pending'])), false);
    }

    public function test_admin_letter_page_lists_linked_budget_and_lhp_uses_letter_purpose(): void
    {
        $this->submitAs(self::PERMANENT, ['assignment_letter_id' => $this->letter->id])->assertSessionHasNoErrors();
        $budget = BudgetRequest::sole();

        $this->asRole('hr_admin')->get(route('admin.assignment-letters.show', $this->letter->id))
            ->assertOk()
            ->assertSee('Anggaran Terkait')
            ->assertSee('Dinas Bogor')
            ->assertSee('0 / 2 peserta');

        $budget->update(['status' => 'approved']);

        // Peserta (bukan pengaju) memakai anggaran yang sama untuk LHP-nya.
        $this->withSession(['employee_id' => self::CONTRACT])
            ->get(route('employee.travel-reports.create'))
            ->assertOk()
            ->assertSee('data-departure="2026-09-25"', false)
            ->assertSee('data-return="2026-09-27"', false)
            ->assertSee('Untuk :', false)
            ->assertSee('Riset di Bendungan Ciawi yang beralamat di Bogor, Jawa Barat dan berjarak -/+ 590 km', false);
    }
}
