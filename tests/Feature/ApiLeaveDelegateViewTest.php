<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\LeaveController;
use App\Models\Employee;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Karyawan yang ditunjuk sebagai delegasi menerima notifikasi "Anda Ditunjuk Sebagai Delegasi" saat
 * cuti disetujui. Notifikasi itu membuka detail cuti, jadi delegasi harus bisa membacanya, tetapi
 * hanya info dasarnya: alasan, lampiran, dan catatan persetujuan tetap milik pemilik.
 */
class ApiLeaveDelegateViewTest extends TestCase
{
    private const OWNER = 1;

    private const DELEGATE = 2;

    private const STRANGER = 3;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['request_attachments', 'approval_logs', 'employee_approvers', 'leave_requests', 'leave_types', 'employees', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('position')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('max_days')->default(12);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 4, 1)->default(1);
            $table->text('reason');
            $table->unsignedBigInteger('delegate_to')->nullable();
            $table->string('status')->nullable();
            $table->integer('current_step')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_approvers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('request_type');
            $table->integer('step_order');
            $table->unsignedBigInteger('approver_id');
            $table->timestamps();
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('acted_by_id')->nullable();
            $table->string('action');
            $table->integer('step_order')->nullable();
            $table->unsignedBigInteger('approval_rule_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('attachable_type');
            $table->unsignedBigInteger('attachable_id');
            $table->string('file_path');
            $table->string('file_name');
            $table->integer('file_size')->default(0);
            $table->timestamps();
        });

        DB::table('companies')->insert([
            'id' => 1,
            'name' => 'PT Beacon Contoh',
            'logo' => 'logos/beacon.png',
            'address' => 'Jl. Rahasia No. 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([self::OWNER => 'Pemilik Cuti', self::DELEGATE => 'Rekan Delegasi', self::STRANGER => 'Orang Lain'] as $id => $name) {
            DB::table('employees')->insert([
                'id' => $id,
                'company_id' => 1,
                'full_name' => $name,
                'email' => "karyawan{$id}@example.test",
                'password' => 'password',
                'position' => 'Staf',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('leave_types')->insert([
            'id' => 1,
            'name' => 'Cuti Sakit',
            'max_days' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Persetujuan atasan (id 3 sebagai atasan) lengkap dengan catatannya.
        DB::table('employee_approvers')->insert([
            'employee_id' => self::OWNER,
            'request_type' => 'leave',
            'step_order' => 1,
            'approver_id' => self::STRANGER,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function leave(string $status, ?int $delegateTo = self::DELEGATE): int
    {
        // DB::table agar tidak memicu sinkron absensi dan antrean yang melekat pada model saat approved.
        $id = DB::table('leave_requests')->insertGetId([
            'employee_id' => self::OWNER,
            'leave_type_id' => 1,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-04',
            'total_days' => 3,
            'reason' => 'Operasi gigi, surat dokter terlampir',
            'delegate_to' => $delegateTo,
            'status' => $status,
            'current_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('approval_logs')->insert([
            'approvable_type' => \App\Models\LeaveRequest::class,
            'approvable_id' => $id,
            'approver_id' => self::STRANGER,
            'action' => 'approved',
            'step_order' => 1,
            'notes' => 'Catatan internal atasan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('request_attachments')->insert([
            'attachable_type' => \App\Models\LeaveRequest::class,
            'attachable_id' => $id,
            'file_path' => 'leave/surat-dokter.pdf',
            'file_name' => 'surat-dokter.pdf',
            'file_size' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function show(int $viewerId, int $leaveId): array
    {
        $request = Request::create("/api/leave/requests/{$leaveId}", 'GET');
        $request->setUserResolver(fn () => Employee::findOrFail($viewerId));

        return (new LeaveController)->show($request, $leaveId)->getData(true);
    }

    public function test_owner_still_sees_everything_and_is_marked_as_owner(): void
    {
        $id = $this->leave('approved');

        $payload = $this->show(self::OWNER, $id);

        $this->assertTrue($payload['success']);
        $data = $payload['data'];
        $this->assertSame('owner', $data['viewer_role']);
        $this->assertSame('Operasi gigi, surat dokter terlampir', $data['reason']);
        $this->assertCount(1, $data['attachments']);
        $this->assertCount(1, $data['approval_steps']);
        $this->assertSame('Catatan internal atasan', $data['approval_steps'][0]['notes']);
    }

    public function test_delegate_can_open_an_approved_leave_and_sees_only_basic_info(): void
    {
        $id = $this->leave('approved');

        $data = $this->show(self::DELEGATE, $id)['data'];

        $this->assertSame('delegate', $data['viewer_role']);
        $this->assertSame($id, $data['id']);
        $this->assertSame('approved', $data['status']);
        $this->assertSame('2026-11-02', $data['start_date']);
        $this->assertSame('2026-11-04', $data['end_date']);
        $this->assertSame('Cuti Sakit', $data['leave_type']['name']);
        $this->assertSame('Pemilik Cuti', $data['employee']['full_name']);
        $this->assertSame('Rekan Delegasi', $data['delegate']['full_name']);
    }

    public function test_delegate_never_receives_reason_attachments_or_approval_notes(): void
    {
        $id = $this->leave('approved');

        $data = $this->show(self::DELEGATE, $id)['data'];
        $json = json_encode($data);

        $this->assertArrayNotHasKey('reason', $data);
        $this->assertArrayNotHasKey('attachments', $data);
        $this->assertArrayNotHasKey('approval_logs', $data);
        $this->assertArrayNotHasKey('approval_steps', $data);
        $this->assertStringNotContainsString('Operasi gigi', $json);
        $this->assertStringNotContainsString('surat-dokter', $json);
        $this->assertStringNotContainsString('Catatan internal', $json);
        // Data pribadi lain milik pemilik juga tidak ikut.
        $this->assertArrayNotHasKey('email', $data['employee']);
        $this->assertArrayNotHasKey('password', $data['employee']);
    }

    public function test_delegate_view_carries_the_same_facts_as_the_delegation_email(): void
    {
        $id = $this->leave('approved');

        $data = $this->show(self::DELEGATE, $id)['data'];

        // Isi email "Delegasi Tugas": perusahaan, nama penerima, pemberi delegasi, jenis, dan tanggal.
        $this->assertSame('PT Beacon Contoh', $data['company']['name']);
        $this->assertStringEndsWith('storage/logos/beacon.png', $data['company']['logo']);
        $this->assertSame('Rekan Delegasi', $data['delegate']['full_name']);
        $this->assertSame('Pemilik Cuti', $data['employee']['full_name']);
        $this->assertSame('Cuti Sakit', $data['leave_type']['name']);
        $this->assertSame('2026-11-02', $data['start_date']);
        $this->assertSame('2026-11-04', $data['end_date']);
        $this->assertSame('approved', $data['status']);
    }

    public function test_delegate_view_exposes_only_company_name_and_logo(): void
    {
        $id = $this->leave('approved');

        $company = $this->show(self::DELEGATE, $id)['data']['company'];

        $this->assertSame(['name', 'logo'], array_keys($company));
        $this->assertStringNotContainsString('Rahasia', json_encode($company));
    }

    public function test_delegate_view_has_no_logo_when_company_has_none(): void
    {
        DB::table('companies')->where('id', 1)->update(['logo' => null]);
        $id = $this->leave('approved');

        $company = $this->show(self::DELEGATE, $id)['data']['company'];

        $this->assertSame('PT Beacon Contoh', $company['name']);
        $this->assertNull($company['logo']);
    }

    #[DataProvider('notYetApprovedStatuses')]
    public function test_delegate_cannot_open_a_leave_that_is_not_approved(string $status): void
    {
        $id = $this->leave($status);

        $this->expectException(ModelNotFoundException::class);

        $this->show(self::DELEGATE, $id);
    }

    public static function notYetApprovedStatuses(): array
    {
        return [
            'menunggu' => ['pending'],
            'ditolak' => ['rejected'],
        ];
    }

    public function test_unrelated_employee_cannot_open_the_leave(): void
    {
        $id = $this->leave('approved');

        $this->expectException(ModelNotFoundException::class);

        $this->show(self::STRANGER, $id);
    }

    public function test_leave_without_delegate_is_not_visible_to_others(): void
    {
        $id = $this->leave('approved', delegateTo: null);

        $this->expectException(ModelNotFoundException::class);

        $this->show(self::DELEGATE, $id);
    }
}
