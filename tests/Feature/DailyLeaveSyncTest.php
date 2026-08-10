<?php

namespace Tests\Feature;

use App\Jobs\SyncLeaveToDailyJob;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\DailyLeaveSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DailyLeaveSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.daily.url' => 'http://daily.test',
            'services.daily.internal_secret' => 'bridge-secret',
            'services.daily.verify_ssl' => true,
        ]);

        $this->createSyncSchema();
    }

    // ---------------------------------------------------------------- pemetaan

    public static function mappingProvider(): array
    {
        return [
            'sakit' => ['Sakit', DailyLeaveSync::TYPE_SAKIT],
            'cuti tahunan' => ['Cuti Tahunan', DailyLeaveSync::TYPE_CUTI],
            'cuti melahirkan' => ['Cuti Melahirkan', DailyLeaveSync::TYPE_CUTI],
            'beda huruf besar kecil' => ['CUTI TAHUNAN', DailyLeaveSync::TYPE_CUTI],
            'ada spasi berlebih' => ['  Sakit  ', DailyLeaveSync::TYPE_SAKIT],
            'izin datang terlambat' => ['Izin Datang Terlambat', null],
            'izin pulang cepat' => ['Izin Pulang Cepat', null],
            'work from home' => ['Work From Home', null],
            'jenis baru belum dipetakan' => ['Cuti Menikah', null],
            'nama kosong' => ['', null],
        ];
    }

    #[DataProvider('mappingProvider')]
    public function test_it_maps_leave_type_name_to_daily_type(string $name, ?string $expected): void
    {
        $leave = new LeaveRequest;
        $leave->setRelation('leaveType', new LeaveType(['name' => $name]));

        $this->assertSame($expected, DailyLeaveSync::typeFor($leave));
        $this->assertSame($expected !== null, DailyLeaveSync::shouldSync($leave));
    }

    public function test_leave_without_type_is_never_synced(): void
    {
        $leave = new LeaveRequest;
        $leave->setRelation('leaveType', null);

        $this->assertNull(DailyLeaveSync::typeFor($leave));
        $this->assertFalse(DailyLeaveSync::shouldSync($leave));
        $this->assertFalse(DailyLeaveSync::isUnmapped($leave));
    }

    public function test_unknown_leave_type_is_reported_as_unmapped(): void
    {
        $leave = new LeaveRequest;
        $leave->setRelation('leaveType', new LeaveType(['name' => 'Cuti Menikah']));

        $this->assertTrue(DailyLeaveSync::isUnmapped($leave));

        $mapped = new LeaveRequest;
        $mapped->setRelation('leaveType', new LeaveType(['name' => 'Sakit']));

        $this->assertFalse(DailyLeaveSync::isUnmapped($mapped));
    }

    // --------------------------------------------------------------------- job

    public function test_job_pushes_approved_leave_to_daily(): void
    {
        Http::fake(['http://daily.test/api/internal/leaves/sync' => Http::response([
            'success' => true,
            'created' => true,
            'overlapping_manual_ids' => [],
        ], 201)]);

        $leave = $this->makeLeave('Sakit', '2026-08-11', '2026-08-13');

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertSent(function ($request) use ($leave) {
            return $request->url() === 'http://daily.test/api/internal/leaves/sync'
                && $request->method() === 'POST'
                && $request->header('X-Internal-Secret')[0] === 'bridge-secret'
                && $request['external_id'] === (string) $leave->id
                && $request['email'] === 'staff@example.test'
                && $request['type'] === 'sakit'
                && $request['start_date'] === '2026-08-11'
                && $request['end_date'] === '2026-08-13';
        });
    }

    public function test_job_sends_the_reason_field(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        $leave = $this->makeLeave('Sakit', '2026-08-11', '2026-08-11');
        $leave->forceFill(['reason' => 'Surat dokter, rawat inap 3 hari'])->saveQuietly();

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertSent(fn ($request) => $request['reason'] === 'Surat dokter, rawat inap 3 hari');
    }

    /**
     * Kontrak Daily membatasi reason 500 karakter. Tanpa dipotong, alasan panjang
     * berbalas 422 dan cutinya tidak pernah tercatat.
     */
    public function test_job_truncates_reason_to_the_contract_limit(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-11');
        $leave->forceFill(['reason' => str_repeat('a', 640)])->saveQuietly();

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertSent(fn ($request) => strlen((string) $request['reason']) === 500);
    }

    public function test_job_sends_null_reason_when_it_is_blank(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 201)]);

        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-11');
        $leave->forceFill(['reason' => '   '])->saveQuietly();

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertSent(fn ($request) => $request['reason'] === null);
    }

    public function test_job_deletes_from_daily_when_leave_is_no_longer_approved(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'deleted' => true])]);

        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12', 'rejected');

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'http://daily.test/api/internal/leaves/'.$leave->id);
    }

    public function test_job_deletes_from_daily_when_leave_no_longer_exists(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'deleted' => false])]);

        (new SyncLeaveToDailyJob(9191))->handle();

        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && $request->url() === 'http://daily.test/api/internal/leaves/9191');
    }

    /**
     * Jenis ini tidak pernah dikirim, jadi tidak boleh ada HTTP sama sekali —
     * termasuk DELETE. Kalau bocor jadi terkirim, karyawannya hilang dari daftar
     * "belum lapor" padahal hari itu tetap bekerja.
     */
    public function test_job_never_contacts_daily_for_wfh_or_partial_leave(): void
    {
        Http::fake();

        foreach (['Work From Home', 'Izin Datang Terlambat', 'Cuti Menikah'] as $index => $typeName) {
            $leave = $this->makeLeave($typeName, '2026-08-1'.$index, '2026-08-1'.$index);

            (new SyncLeaveToDailyJob($leave->id))->handle();
        }

        Http::assertNothingSent();
    }

    public function test_job_skips_when_employee_has_no_email(): void
    {
        Http::fake();

        $employee = Employee::create([
            'employee_code' => 'EMP-NOMAIL',
            'company_id' => 1,
            'full_name' => 'Tanpa Email',
            'email' => null,
            'password' => 'secret',
            'role' => 'employee',
            'is_active' => true,
        ]);

        $leave = $this->makeLeave('Sakit', '2026-08-11', '2026-08-11', 'approved', $employee->id);

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertNothingSent();
    }

    public function test_job_skips_quietly_when_bridge_is_not_configured(): void
    {
        config(['services.daily.internal_secret' => null]);
        Http::fake();

        $leave = $this->makeLeave('Sakit', '2026-08-11', '2026-08-11');

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertNothingSent();
    }

    /**
     * 404 (pegawai belum punya akun Daily), 422 (jenis belum dipetakan), dan 403
     * (secret salah) tidak akan membaik kalau diulang. Dicatat, lalu selesai —
     * jangan bikin job menggantung di queue selamanya.
     */
    #[DataProvider('nonRetryableStatusProvider')]
    public function test_job_does_not_retry_on_permanent_failures(int $status): void
    {
        Http::fake(['*' => Http::response(['success' => false], $status)]);

        $leave = $this->makeLeave('Sakit', '2026-08-11', '2026-08-11');

        (new SyncLeaveToDailyJob($leave->id))->handle();

        Http::assertSentCount(1);
    }

    public static function nonRetryableStatusProvider(): array
    {
        return [
            '404 akun Daily tidak ada' => [404],
            '422 payload ditolak' => [422],
            '403 secret ditolak' => [403],
        ];
    }

    public function test_job_throws_on_server_error_so_the_queue_retries(): void
    {
        Http::fake(['*' => Http::response(['success' => false], 500)]);

        $leave = $this->makeLeave('Sakit', '2026-08-11', '2026-08-11');

        $this->expectException(RuntimeException::class);

        (new SyncLeaveToDailyJob($leave->id))->handle();
    }

    // -------------------------------------------------------------------- hook

    public function test_approving_a_leave_queues_the_daily_sync(): void
    {
        Queue::fake();

        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12', 'pending');
        $leave->update(['status' => 'approved']);

        Queue::assertPushed(SyncLeaveToDailyJob::class,
            fn (SyncLeaveToDailyJob $job) => $job->leaveRequestId === $leave->id);
    }

    public function test_revoking_an_approved_leave_queues_the_daily_sync(): void
    {
        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12');

        Queue::fake();
        $leave->update(['status' => 'rejected']);

        Queue::assertPushed(SyncLeaveToDailyJob::class,
            fn (SyncLeaveToDailyJob $job) => $job->leaveRequestId === $leave->id);
    }

    public function test_editing_a_leave_without_status_change_does_not_queue_a_sync(): void
    {
        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12');

        Queue::fake();
        $leave->update(['reason' => 'diperbarui']);

        Queue::assertNothingPushed();
    }

    public function test_a_pending_to_rejected_change_does_not_queue_a_sync(): void
    {
        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12', 'pending');

        Queue::fake();
        $leave->update(['status' => 'rejected']);

        Queue::assertNothingPushed();
    }

    /**
     * Dengan QUEUE_CONNECTION=sync job dieksekusi saat dispatch. Daily yang mati tidak
     * boleh ikut menggagalkan proses ACC — approval-nya harus tetap tersimpan.
     */
    public function test_approval_still_succeeds_when_daily_is_unreachable(): void
    {
        config(['queue.default' => 'sync']);
        Http::fake(fn () => throw new ConnectionException('Daily tidak bisa dihubungi'));

        $leave = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12', 'pending');

        $leave->update(['status' => 'approved']);

        $this->assertSame('approved', $leave->fresh()->status);
    }

    // ----------------------------------------------------------------- command

    public function test_command_queues_sync_for_approved_and_revoked_leaves(): void
    {
        Queue::fake();

        $approved = $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12');
        $revoked = $this->makeLeave('Sakit', '2026-08-13', '2026-08-13', 'rejected');
        $wfh = $this->makeLeave('Work From Home', '2026-08-14', '2026-08-14');

        $this->artisan('daily:sync-leaves', ['--days' => 3650])
            ->assertExitCode(0);

        Queue::assertPushed(SyncLeaveToDailyJob::class, 2);
        Queue::assertPushed(SyncLeaveToDailyJob::class,
            fn (SyncLeaveToDailyJob $job) => $job->leaveRequestId === $approved->id);
        Queue::assertPushed(SyncLeaveToDailyJob::class,
            fn (SyncLeaveToDailyJob $job) => $job->leaveRequestId === $revoked->id);
        Queue::assertNotPushed(SyncLeaveToDailyJob::class,
            fn (SyncLeaveToDailyJob $job) => $job->leaveRequestId === $wfh->id);
    }

    public function test_command_dry_run_queues_nothing(): void
    {
        Queue::fake();

        $this->makeLeave('Cuti Tahunan', '2026-08-11', '2026-08-12');

        $this->artisan('daily:sync-leaves', ['--days' => 3650, '--dry-run' => true])
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_command_fails_when_bridge_is_not_configured(): void
    {
        config(['services.daily.internal_secret' => null]);
        Queue::fake();

        $this->artisan('daily:sync-leaves')->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    // ----------------------------------------------------------------- helpers

    private function makeLeave(
        string $leaveTypeName,
        string $startDate,
        string $endDate,
        string $status = 'approved',
        ?int $employeeId = null
    ): LeaveRequest {
        $type = LeaveType::firstOrCreate(['name' => trim($leaveTypeName) ?: 'Tanpa Nama']);

        if ($leaveTypeName !== trim($leaveTypeName)) {
            $type->forceFill(['name' => $leaveTypeName])->save();
        }

        return LeaveRequest::withoutEvents(fn () => LeaveRequest::create([
            'employee_id' => $employeeId ?? $this->staffEmployeeId(),
            'leave_type_id' => $type->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => 1,
            'reason' => 'test',
            'status' => $status,
        ]));
    }

    private function staffEmployeeId(): int
    {
        return Employee::firstOrCreate(
            ['email' => 'staff@example.test'],
            [
                'employee_code' => 'EMP-SYNC',
                'company_id' => 1,
                'full_name' => 'Sync Staff',
                'password' => 'secret',
                'role' => 'employee',
                'is_active' => true,
            ]
        )->id;
    }

    private function createSyncSchema(): void
    {
        foreach (['leave_requests', 'leave_types', 'attendances', 'employees'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('delegate_to')->nullable();
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->string('status')->default('present');
            $table->boolean('is_late')->default(false);
            $table->string('review_status')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('full_name');
            $table->string('email')->nullable()->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
