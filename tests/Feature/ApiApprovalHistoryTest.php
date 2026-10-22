<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ApprovalHistoryController;
use App\Models\AssignmentLetter;
use App\Models\AssignmentLetterApproval;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\DataChangeRequest;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Lpj;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use App\Support\AssignmentLetterApprovalFlow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Riwayat keputusan pemberi persetujuan di app mobile: log persetujuan semua jenis pengajuan dan
 * paraf surat tugas, terbaru di atas, dengan saringan status/jenis/bulan dan muat bertahap.
 */
class ApiApprovalHistoryTest extends AssignmentLetterTestCase
{
    private const LEADER_SOFTWARE = 10;
    private const MANAGER_SOFTWARE = 11;
    private const MANAGER_HARDWARE = 12;
    private const SUPERADMIN = 14;

    private AssignmentLetter $letter;

    protected function setUp(): void
    {
        parent::setUp();

        config(['assignment_letters.require_approval' => true]);
        Storage::fake('public');
        Storage::fake('local');

        // Tabel dasar tidak memuat acted_by_id, jenis cuti, dan perubahan data.
        Schema::dropIfExists('approval_logs');
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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('data_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $person = fn (int $id, string $name, int $level, int $department, string $role = 'employee') => [
            'id' => $id, 'company_id' => 1, 'department_id' => $department, 'full_name' => $name,
            'position' => 'Staff', 'job_level' => $level, 'employment_status' => 'permanent',
            'role' => $role, 'is_active' => true, 'employee_code' => sprintf('%03d/X/I/2024', $id),
        ];
        DB::table('employees')->insert([
            $person(self::LEADER_SOFTWARE, 'Leader Software', 3, 1),
            $person(self::MANAGER_SOFTWARE, 'Manager Software', 2, 1),
            $person(self::MANAGER_HARDWARE, 'Manager Hardware', 2, 2),
            $person(self::SUPERADMIN, 'Admin Sistem', 4, 3, 'superadmin'),
        ]);
        DB::table('employee_approvers')->insert([
            ['employee_id' => self::PERMANENT, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => self::LEADER_SOFTWARE],
            ['employee_id' => self::PERMANENT, 'request_type' => 'leave', 'step_order' => 2, 'approver_id' => self::MANAGER_SOFTWARE],
            ['employee_id' => self::CONTRACT, 'request_type' => 'leave', 'step_order' => 1, 'approver_id' => self::MANAGER_HARDWARE],
        ]);

        $this->letter = $this->issue($this->storeDraft());
        DB::table('notifications')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function history(int $as, array $query = []): array
    {
        Sanctum::actingAs(Employee::findOrFail($as));

        return $this->getJson('/api/approvals/history?'.http_build_query($query))->assertOk()->json();
    }

    private function keys(array $response): array
    {
        return array_column($response['data'], 'key');
    }

    private function leave(): int
    {
        $type = DB::table('leave_types')->insertGetId(['name' => 'Cuti Tahunan', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('leave_requests')->insertGetId([
            'employee_id' => self::PERMANENT, 'leave_type_id' => $type, 'start_date' => '2026-10-08', 'end_date' => '2026-10-09',
            'total_days' => 2, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function dated(string $table, array $extra): int
    {
        return DB::table($table)->insertGetId([
            'employee_id' => self::PERMANENT, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ] + $extra);
    }

    /** Catat keputusan $approver pada pengajuan bertipe $class pada waktu $at. */
    private function decide(string $class, int $requestId, int $approver, string $action, string $at, array $extra = []): int
    {
        return DB::table('approval_logs')->insertGetId($extra + [
            'approvable_type' => $class, 'approvable_id' => $requestId, 'approver_id' => $approver, 'action' => $action,
            'step_order' => 1, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function decideLetter(string $role, int $by, string $at, bool $reject = false): AssignmentLetterApproval
    {
        $approval = $this->letter->approvals()->where('role', $role)->when($role === 'level3' || $role === 'level2', fn ($q) => $q->where('approver_id', $by))->firstOrFail();
        Carbon::setTestNow($at);
        $reject
            ? AssignmentLetterApprovalFlow::reject($approval, Employee::findOrFail($by), 'Tanggal bentrok')
            : AssignmentLetterApprovalFlow::approve($approval, Employee::findOrFail($by), 'Silakan berangkat');
        Carbon::setTestNow();

        return $approval->fresh();
    }

    // ── isi ───────────────────────────────────────────────────────────────────

    public function test_each_request_type_is_summarised_in_one_line_with_who_asked(): void
    {
        $leave = $this->leave();
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-05']);
        $attendance = $this->dated('attendance_requests', ['date' => '2026-10-06']);
        $budget = $this->dated('budget_requests', ['title' => 'Perjalanan Surabaya']);
        $report = $this->dated('travel_reports', ['destination_city' => 'Surabaya']);
        $lpj = DB::table('lpjs')->insertGetId(['budget_request_id' => $budget, 'employee_id' => self::PERMANENT, 'created_at' => now(), 'updated_at' => now()]);
        $change = $this->dated('data_change_requests', ['field_name' => 'phone']);

        foreach ([
            [LeaveRequest::class, $leave], [OvertimeRequest::class, $overtime], [AttendanceRequest::class, $attendance],
            [BudgetRequest::class, $budget], [TravelReport::class, $report], [Lpj::class, $lpj], [DataChangeRequest::class, $change],
        ] as $i => [$class, $id]) {
            $this->decide($class, $id, self::MANAGER_SOFTWARE, 'approved', '2026-10-1'.$i.' 09:00:00');
        }

        $rows = collect($this->history(self::MANAGER_SOFTWARE)['data'])->keyBy('type');

        $this->assertSame('Cuti Tahunan 08/10/2026 - 09/10/2026', $rows['leave']['title']);
        $this->assertSame('Lembur 05/10/2026', $rows['overtime']['title']);
        $this->assertSame('Presensi 06/10/2026', $rows['attendance']['title']);
        $this->assertSame('Perjalanan Surabaya', $rows['budget']['title']);
        $this->assertSame('LHP Surabaya', $rows['travel_report']['title']);
        $this->assertSame('LPJ Perjalanan Surabaya', $rows['lpj']['title']);
        $this->assertSame('Ubah phone', $rows['data-change']['title']);
        $this->assertSame(['Cuti', 'Lembur', 'Presensi', 'Anggaran', 'LHP', 'LPJ', 'Perubahan Data'], [
            $rows['leave']['type_label'], $rows['overtime']['type_label'], $rows['attendance']['type_label'],
            $rows['budget']['type_label'], $rows['travel_report']['type_label'], $rows['lpj']['type_label'], $rows['data-change']['type_label'],
        ]);
        $this->assertSame(['full_name' => 'Fadel Irsyad', 'department' => 'Software'], $rows['leave']['employee']);
    }

    public function test_a_row_carries_the_decision_the_note_the_step_and_the_request_to_open(): void
    {
        $leave = $this->leave();
        $this->decide(LeaveRequest::class, $leave, self::MANAGER_SOFTWARE, 'rejected', '2026-10-10 09:00:00', ['notes' => 'Kuota habis', 'step_order' => 2]);

        $row = $this->history(self::MANAGER_SOFTWARE)['data'][0];

        $this->assertSame('rejected', $row['decision']);
        $this->assertSame('Kuota habis', $row['notes']);
        $this->assertSame(2, $row['step']);
        $this->assertSame($leave, $row['request_id']);
        $this->assertSame('leave', $row['type']);
        $this->assertTrue($row['available']);
        $this->assertNull($row['on_behalf']);
        $this->assertSame(Carbon::parse('2026-10-10 09:00:00')->toIso8601String(), $row['decided_at']);
    }

    public function test_only_my_own_decisions_appear_newest_first(): void
    {
        $a = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $b = $this->dated('overtime_requests', ['date' => '2026-10-02']);
        $c = $this->dated('overtime_requests', ['date' => '2026-10-03']);
        $first = $this->decide(OvertimeRequest::class, $a, self::MANAGER_SOFTWARE, 'approved', '2026-10-10 08:00:00');
        $this->decide(OvertimeRequest::class, $b, self::LEADER_SOFTWARE, 'approved', '2026-10-11 08:00:00');
        $last = $this->decide(OvertimeRequest::class, $c, self::MANAGER_SOFTWARE, 'rejected', '2026-10-12 08:00:00');

        $this->assertSame(['log-'.$last, 'log-'.$first], $this->keys($this->history(self::MANAGER_SOFTWARE)));
        $this->assertSame([], $this->history(self::DIRECTOR)['data']);
    }

    public function test_only_decisions_count_not_other_log_entries_or_unrelated_types(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $kept = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-10 08:00:00');
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'cancelled', '2026-10-11 08:00:00');
        $this->decide('App\\Models\\LoanRequest', 1, self::MANAGER_SOFTWARE, 'approved', '2026-10-12 08:00:00');

        $this->assertSame(['log-'.$kept], $this->keys($this->history(self::MANAGER_SOFTWARE)));
    }

    public function test_a_deleted_request_stays_in_the_history_but_cannot_be_opened(): void
    {
        $this->decide(LeaveRequest::class, 999, self::MANAGER_SOFTWARE, 'approved', '2026-10-10 08:00:00');

        $row = $this->history(self::MANAGER_SOFTWARE)['data'][0];

        $this->assertFalse($row['available']);
        $this->assertSame('Pengajuan telah dihapus', $row['title']);
        $this->assertNull($row['employee']);
        $this->assertSame('approved', $row['decision']);
    }

    public function test_an_empty_history_is_an_empty_list(): void
    {
        $response = $this->history(self::DIRECTOR);

        $this->assertSame([], $response['data']);
        $this->assertFalse($response['meta']['has_more']);
    }

    // ── surat tugas ───────────────────────────────────────────────────────────

    public function test_letter_initials_decided_by_me_are_listed_with_the_letter_and_my_box(): void
    {
        $approval = $this->decideLetter('level3', self::LEADER_SOFTWARE, '2026-10-10 10:00:00');

        $row = $this->history(self::LEADER_SOFTWARE)['data'][0];

        $this->assertSame('letter-'.$approval->id, $row['key']);
        $this->assertSame('assignment_letter', $row['type']);
        $this->assertSame('Surat Tugas', $row['type_label']);
        $this->assertSame($this->letter->id, $row['request_id']);
        $this->assertSame('approved', $row['decision']);
        $this->assertSame('Silakan berangkat', $row['notes']);
        $this->assertSame('LV 3 SOFTWARE', $row['step']);
        $this->assertSame('Surat tugas 001/ST-ATC/I/2026 (LV 3 SOFTWARE)', $row['title']);
        $this->assertSame('Fadel Irsyad, Prastowo Dian', $row['employee']['full_name']);
        $this->assertTrue($row['available']);
    }

    public function test_letter_initials_decided_by_other_people_are_not_mine(): void
    {
        $theirs = $this->decideLetter('level3', self::LEADER_SOFTWARE, '2026-10-10 10:00:00');

        $this->assertSame(['letter-'.$theirs->id], $this->keys($this->history(self::LEADER_SOFTWARE)));
        $this->assertSame([], $this->history(self::MANAGER_SOFTWARE)['data']);
        $this->assertSame([], $this->history(self::SUPERADMIN)['data']);
    }

    public function test_a_rejected_letter_initial_shows_the_reason(): void
    {
        $this->decideLetter('level2', self::MANAGER_SOFTWARE, '2026-10-10 10:00:00', reject: true);

        $row = $this->history(self::MANAGER_SOFTWARE)['data'][0];

        $this->assertSame('rejected', $row['decision']);
        $this->assertSame('Tanggal bentrok', $row['notes']);
    }

    public function test_a_step_skipped_by_hrd_is_not_the_holders_decision(): void
    {
        $approval = $this->letter->approvals()->where('role', 'level3')->firstOrFail();
        $approval->update(['status' => 'skipped', 'acted_by' => self::SUPERADMIN, 'acted_at' => '2026-10-10 10:00:00']);

        $this->assertSame([], $this->history(self::SUPERADMIN)['data']);
        $this->assertSame([], $this->history(self::LEADER_SOFTWARE)['data']);
    }

    public function test_letters_and_other_decisions_are_merged_by_time(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $early = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-09 08:00:00');
        $letter = $this->decideLetter('level2', self::MANAGER_SOFTWARE, '2026-10-10 08:00:00');
        $late = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'rejected', '2026-10-11 08:00:00');

        $this->assertSame(['log-'.$late, 'letter-'.$letter->id, 'log-'.$early], $this->keys($this->history(self::MANAGER_SOFTWARE)));
    }

    // ── saringan ──────────────────────────────────────────────────────────────

    public function test_the_status_filter_keeps_only_approvals_or_only_rejections(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $approved = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-10 08:00:00');
        $rejected = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'rejected', '2026-10-11 08:00:00');
        $letter = $this->decideLetter('level2', self::MANAGER_SOFTWARE, '2026-10-12 08:00:00', reject: true);

        $this->assertSame(['log-'.$approved], $this->keys($this->history(self::MANAGER_SOFTWARE, ['status' => 'approved'])));
        $this->assertSame(['letter-'.$letter->id, 'log-'.$rejected], $this->keys($this->history(self::MANAGER_SOFTWARE, ['status' => 'rejected'])));
        $this->assertCount(3, $this->history(self::MANAGER_SOFTWARE, ['status' => 'all'])['data']);
        $this->assertCount(3, $this->history(self::MANAGER_SOFTWARE)['data']);
    }

    public function test_the_type_filter_keeps_one_type_including_letters(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $report = $this->dated('travel_reports', ['destination_city' => 'Bandung']);
        $o = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-10 08:00:00');
        $t = $this->decide(TravelReport::class, $report, self::MANAGER_SOFTWARE, 'approved', '2026-10-11 08:00:00');
        $l = $this->decideLetter('level2', self::MANAGER_SOFTWARE, '2026-10-12 08:00:00');

        $this->assertSame(['log-'.$o], $this->keys($this->history(self::MANAGER_SOFTWARE, ['type' => 'overtime'])));
        $this->assertSame(['log-'.$t], $this->keys($this->history(self::MANAGER_SOFTWARE, ['type' => 'travel_report'])));
        $this->assertSame(['letter-'.$l->id], $this->keys($this->history(self::MANAGER_SOFTWARE, ['type' => 'assignment_letter'])));
        $this->assertSame([], $this->history(self::MANAGER_SOFTWARE, ['type' => 'leave'])['data']);
    }

    public function test_the_month_filter_uses_the_month_the_decision_was_made(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-09-01']);
        $sept = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-09-30 23:59:59');
        $octFirst = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-01 00:00:00');
        $octLast = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-31 23:59:59');
        $nov = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-11-01 00:00:00');
        $letter = $this->decideLetter('level2', self::MANAGER_SOFTWARE, '2026-10-15 08:00:00');

        $this->assertSame(['log-'.$octLast, 'letter-'.$letter->id, 'log-'.$octFirst], $this->keys($this->history(self::MANAGER_SOFTWARE, ['period' => '2026-10'])));
        $this->assertSame(['log-'.$sept], $this->keys($this->history(self::MANAGER_SOFTWARE, ['period' => '2026-09'])));
        $this->assertSame(['log-'.$nov], $this->keys($this->history(self::MANAGER_SOFTWARE, ['period' => '2026-11'])));
        $this->assertSame([], $this->history(self::MANAGER_SOFTWARE, ['period' => '2026-12'])['data']);
    }

    public function test_the_filters_combine(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $match = $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'rejected', '2026-10-10 08:00:00');
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-11 08:00:00');
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'rejected', '2026-09-11 08:00:00');
        $this->decide(TravelReport::class, $this->dated('travel_reports', ['destination_city' => 'X']), self::MANAGER_SOFTWARE, 'rejected', '2026-10-12 08:00:00');

        $this->assertSame(['log-'.$match], $this->keys($this->history(self::MANAGER_SOFTWARE, ['status' => 'rejected', 'type' => 'overtime', 'period' => '2026-10'])));
    }

    public function test_bad_filters_are_refused(): void
    {
        Sanctum::actingAs(Employee::findOrFail(self::MANAGER_SOFTWARE));

        foreach (['status=ditunda', 'type=cuti', 'period=2026-13', 'period=Oktober', 'page=0', 'per_page=0', 'per_page=51'] as $query) {
            $this->getJson('/api/approvals/history?'.$query)->assertUnprocessable();
        }
    }

    // ── muat bertahap ─────────────────────────────────────────────────────────

    public function test_pages_follow_one_another_without_gaps_or_repeats_and_say_when_more_remain(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $ids = [];
        foreach (range(1, 5) as $day) {
            $ids[] = 'log-'.$this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', sprintf('2026-10-%02d 08:00:00', $day));
        }
        $newestFirst = array_reverse($ids);

        $one = $this->history(self::MANAGER_SOFTWARE, ['per_page' => 2, 'page' => 1]);
        $two = $this->history(self::MANAGER_SOFTWARE, ['per_page' => 2, 'page' => 2]);
        $three = $this->history(self::MANAGER_SOFTWARE, ['per_page' => 2, 'page' => 3]);
        $four = $this->history(self::MANAGER_SOFTWARE, ['per_page' => 2, 'page' => 4]);

        $this->assertSame(array_slice($newestFirst, 0, 2), $this->keys($one));
        $this->assertSame(array_slice($newestFirst, 2, 2), $this->keys($two));
        $this->assertSame(array_slice($newestFirst, 4, 2), $this->keys($three));
        $this->assertSame([true, true, false], [$one['meta']['has_more'], $two['meta']['has_more'], $three['meta']['has_more']]);
        $this->assertSame([], $four['data']);
        $this->assertFalse($four['meta']['has_more']);
        $this->assertSame(['page' => 2, 'per_page' => 2], array_intersect_key($two['meta'], ['page' => 1, 'per_page' => 1]));
    }

    public function test_exactly_one_full_page_does_not_claim_there_is_more(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        foreach (range(1, 2) as $day) {
            $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', sprintf('2026-10-%02d 08:00:00', $day));
        }

        $this->assertFalse($this->history(self::MANAGER_SOFTWARE, ['per_page' => 2])['meta']['has_more']);
    }

    public function test_a_page_defaults_to_fifteen_rows(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        foreach (range(1, 16) as $minute) {
            $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', sprintf('2026-10-10 08:%02d:00', $minute));
        }

        $response = $this->history(self::MANAGER_SOFTWARE);

        $this->assertCount(15, $response['data']);
        $this->assertTrue($response['meta']['has_more']);
        $this->assertCount(1, $this->history(self::MANAGER_SOFTWARE, ['page' => 2])['data']);
    }

    public function test_decisions_at_the_same_moment_keep_a_stable_order_across_pages(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $at = '2026-10-10 08:00:00';
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', $at);
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', $at);
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', $at);
        $letter = $this->decideLetter('level2', self::MANAGER_SOFTWARE, $at);

        $all = $this->keys($this->history(self::MANAGER_SOFTWARE, ['per_page' => 50]));
        $paged = array_merge(
            $this->keys($this->history(self::MANAGER_SOFTWARE, ['per_page' => 1, 'page' => 1])),
            $this->keys($this->history(self::MANAGER_SOFTWARE, ['per_page' => 1, 'page' => 2])),
            $this->keys($this->history(self::MANAGER_SOFTWARE, ['per_page' => 1, 'page' => 3])),
            $this->keys($this->history(self::MANAGER_SOFTWARE, ['per_page' => 1, 'page' => 4])),
        );

        $this->assertCount(4, array_unique($all));
        $this->assertSame($all, $paged);
        // Pada waktu yang sama: paraf surat tugas dulu, lalu log dari id terbesar.
        $logIds = DB::table('approval_logs')->orderByDesc('id')->pluck('id')->map(fn ($id) => 'log-'.$id)->all();
        $this->assertSame(['letter-'.$letter->id, ...$logIds], $all);
    }

    // ── atas nama ─────────────────────────────────────────────────────────────

    public function test_a_decision_made_for_me_by_the_superadmin_shows_on_both_sides(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-10 08:00:00', ['acted_by_id' => self::SUPERADMIN]);

        $mine = $this->history(self::MANAGER_SOFTWARE)['data'];
        $theirs = $this->history(self::SUPERADMIN)['data'];

        $this->assertCount(1, $mine);
        $this->assertSame(['direction' => 'by_other', 'name' => 'Admin Sistem'], $mine[0]['on_behalf']);
        $this->assertCount(1, $theirs);
        $this->assertSame(['direction' => 'for_other', 'name' => 'Manager Software'], $theirs[0]['on_behalf']);
    }

    public function test_deciding_myself_is_not_on_behalf_even_when_the_actor_is_recorded(): void
    {
        $overtime = $this->dated('overtime_requests', ['date' => '2026-10-01']);
        $this->decide(OvertimeRequest::class, $overtime, self::MANAGER_SOFTWARE, 'approved', '2026-10-10 08:00:00', ['acted_by_id' => self::MANAGER_SOFTWARE]);

        $this->assertNull($this->history(self::MANAGER_SOFTWARE)['data'][0]['on_behalf']);
    }

    // ── rute ──────────────────────────────────────────────────────────────────

    public function test_the_history_needs_a_login(): void
    {
        $this->getJson('/api/approvals/history')->assertUnauthorized();
    }

    public function test_the_route_resolves_behind_sanctum_and_does_not_collide_with_the_detail_route(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/approvals/history', 'GET'));

        $this->assertSame(ApprovalHistoryController::class.'@index', $route->getActionName());
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
    }
}
