<?php

namespace Tests\Feature;

use App\Models\ApprovalLog;
use App\Models\Employee;
use App\Support\BudgetSignatureSlots;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Kotak tanda tangan approver di cetak Form Pengajuan Anggaran terisi tanda tangan profil dan
 * tanggal setuju, setelah orang di kotak itu sendiri menyetujui.
 */
class BudgetApproverSignatureTest extends TestCase
{
    private Employee $leader;

    private Employee $manager;

    private Employee $finance;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->leader = $this->person(7, 'Leader Hardware', 'LEADER HARDWARE', 3, 'employees/signatures/leader.png');
        $this->manager = $this->person(5, 'Muhammad Subarkah', 'HARDWARE MANAGER', 2, 'employees/signatures/manager.png');
        $this->finance = $this->person(20, 'Maritza', 'ACCOUNTING & FINANCE', 3, 'employees/signatures/finance.png');
    }

    public function test_each_approver_box_gets_the_signature_and_date_once_that_person_approved(): void
    {
        $signed = BudgetSignatureSlots::signed($this->chain(), [
            $this->log($this->leader, 'approved', '2026-10-29 09:00:00', 1),
            $this->log($this->manager, 'approved', '2026-10-29 11:24:30', 2),
        ]);

        $this->assertSame(['pj', 'manager'], array_keys($signed));
        $this->assertSame('29 / 10 / 2026', $signed['manager']['date']);
        $this->assertStringContainsString('storage/', (string) $signed['manager']['signature']);
        $this->assertArrayNotHasKey('finance', $signed, 'Finance belum menyetujui, kotaknya tetap manual');
    }

    public function test_a_box_approved_by_a_superadmin_on_someones_behalf_stays_manual(): void
    {
        $log = $this->log($this->manager, 'approved', '2026-10-29 11:00:00', 1);
        $log->acted_by_id = 99;

        $this->assertSame([], BudgetSignatureSlots::signed($this->chain(), [$log]));
    }

    public function test_approvals_before_the_last_rejection_no_longer_count(): void
    {
        $signed = BudgetSignatureSlots::signed($this->chain(), [
            $this->log($this->leader, 'approved', '2026-10-20 09:00:00', 1),
            $this->log($this->manager, 'rejected', '2026-10-20 10:00:00', 2),
            $this->log($this->leader, 'approved', '2026-10-22 09:00:00', 3),
        ]);

        $this->assertSame(['pj'], array_keys($signed));
        $this->assertSame('22 / 10 / 2026', $signed['pj']['date']);
    }

    public function test_an_approver_without_a_signature_file_still_gets_the_date(): void
    {
        $this->manager->signature = null;

        $signed = BudgetSignatureSlots::signed($this->chain(), [$this->log($this->manager, 'approved', '2026-10-29 11:24:30', 1)]);

        $this->assertNull($signed['manager']['signature']);
        $this->assertSame('29 / 10 / 2026', $signed['manager']['date']);
    }

    public function test_the_print_shows_the_signature_in_the_managers_box(): void
    {
        $budget = new \App\Models\BudgetRequest(['title' => 'Perjalanan ke Kantor Bendung Mrican', 'total_amount' => 0, 'status' => 'in_review']);
        $budget->created_at = Carbon::parse('2026-10-29 10:22:16');
        $budget->setRelation('employee', $this->person(34, 'Ilham Yoga Pratama', 'STAFF', 4, null));
        $budget->setRelation('items', collect());
        $budget->setRelation('attachments', collect());
        $budget->setRelation('participants', collect());
        $budget->setRelation('approvalLogs', collect([$this->log($this->manager, 'approved', '2026-10-29 11:24:30', 1)]));

        $html = view('budget-requests.print', ['budgetRequest' => $budget, 'approvalChain' => $this->chain(), 'backUrl' => '#'])->render();

        $this->assertStringContainsString('alt="Tanda tangan Muhammad Subarkah"', $html);
        $this->assertStringContainsString('(29 / 10 / 2026)', $html);
        $this->assertStringNotContainsString('alt="Tanda tangan Maritza"', $html, 'Finance belum menyetujui');
    }

    private function person(int $id, string $name, string $position, int $level, ?string $signature): Employee
    {
        if ($signature) {
            Storage::disk('public')->put($signature, UploadedFile::fake()->image('ttd.png', 120, 40)->getContent());
        }

        return (new Employee)->forceFill([
            'id' => $id, 'full_name' => $name, 'position' => $position, 'job_level' => $level, 'signature' => $signature,
        ]);
    }

    private function chain(): array
    {
        return [
            (object) ['step_order' => 1, 'approver' => $this->leader],
            (object) ['step_order' => 2, 'approver' => $this->manager],
            (object) ['step_order' => 3, 'approver' => $this->finance],
        ];
    }

    private function log(Employee $approver, string $action, string $at, int $id): ApprovalLog
    {
        $log = (new ApprovalLog)->forceFill([
            'id' => $id, 'approver_id' => $approver->id, 'acted_by_id' => null, 'action' => $action,
        ]);
        $log->created_at = Carbon::parse($at);
        $log->setRelation('approver', $approver);

        return $log;
    }
}
