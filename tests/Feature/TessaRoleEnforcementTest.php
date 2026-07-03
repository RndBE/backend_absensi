<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tessa mengikuti role HRIS: aktor = pemilik token, kapabilitas = role-nya.
 *
 * Skema dibangun manual (bukan migration penuh) karena sebagian migration
 * memakai sintaks khusus MySQL yang gagal di sqlite test.
 */
class TessaRoleEnforcementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->string('position')->nullable();
            $table->string('photo')->nullable();
            $table->string('job_level')->nullable();
            $table->string('employment_status')->nullable();
            $table->string('gender')->nullable();
            $table->date('join_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index(['tokenable_type', 'tokenable_id']);
        });
    }

    private function makeEmployee(string $role, string $email, ?string $phone = null): Employee
    {
        $company = Company::firstOrCreate(['name' => 'PT Tessa']);

        return Employee::create([
            'employee_code' => 'EMP-'.substr(md5($email), 0, 8),
            'company_id' => $company->id,
            'full_name' => ucfirst($role).' '.$email,
            'email' => $email,
            'phone' => $phone,
            'password' => 'secret123', // cast 'hashed' → otomatis di-hash
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function tessaToken(Employee $employee): string
    {
        return $employee->createToken('tessa', ['tessa'])->plainTextToken;
    }

    public function test_session_login_reports_is_admin_by_hris_role(): void
    {
        config(['services.tessa.api_key' => 'svc-key']);
        $this->makeEmployee('hr_admin', 'hr@t.test');
        $this->makeEmployee('employee', 'emp@t.test');

        $admin = $this->postJson('/api/tessa/session', ['email' => 'hr@t.test', 'password' => 'secret123'], ['X-Api-Key' => 'svc-key']);
        $admin->assertOk()->assertJson(['success' => true, 'is_admin' => true]);
        $this->assertNotEmpty($admin->json('token'));

        $emp = $this->postJson('/api/tessa/session', ['email' => 'emp@t.test', 'password' => 'secret123'], ['X-Api-Key' => 'svc-key']);
        $emp->assertOk()->assertJson(['success' => true, 'is_admin' => false]);
    }

    public function test_login_by_phone_resolves_role(): void
    {
        config(['services.tessa.api_key' => 'svc-key']);
        $this->makeEmployee('hr_admin', 'hr@t.test', '081234567890');

        // Format lokal
        $r1 = $this->postJson('/api/tessa/session', ['phone' => '081234567890'], ['X-Api-Key' => 'svc-key']);
        $r1->assertOk()->assertJson(['success' => true, 'is_admin' => true]);
        $this->assertSame('hr_admin', $r1->json('employee.role'));
        $this->assertNotEmpty($r1->json('token'));

        // Format internasional → nomor yang sama harus resolve ke orang yang sama.
        $this->postJson('/api/tessa/session', ['phone' => '+6281234567890'], ['X-Api-Key' => 'svc-key'])
            ->assertOk()->assertJson(['is_admin' => true]);
    }

    public function test_login_by_phone_unknown_number_rejected(): void
    {
        config(['services.tessa.api_key' => 'svc-key']);
        $this->makeEmployee('employee', 'emp@t.test', '081100000000');

        $this->postJson('/api/tessa/session', ['phone' => '089999999999'], ['X-Api-Key' => 'svc-key'])
            ->assertStatus(404);
    }

    public function test_login_by_phone_requires_service_key(): void
    {
        config(['services.tessa.api_key' => 'svc-key']);
        $this->makeEmployee('employee', 'emp@t.test', '081234567890');

        $this->postJson('/api/tessa/session', ['phone' => '081234567890'])->assertStatus(401);
    }

    public function test_session_requires_service_key(): void
    {
        config(['services.tessa.api_key' => 'svc-key']);
        $this->makeEmployee('employee', 'emp@t.test');

        $this->postJson('/api/tessa/session', ['email' => 'emp@t.test', 'password' => 'secret123'])
            ->assertStatus(401);
    }

    public function test_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/tessa/employees')->assertStatus(401);
    }

    public function test_non_tessa_token_rejected(): void
    {
        $emp = $this->makeEmployee('employee', 'emp@t.test');
        $token = $emp->createToken('mobile', ['mobile'])->plainTextToken; // tanpa ability "tessa"

        $this->withToken($token)->getJson('/api/tessa/employees')->assertStatus(403);
    }

    public function test_employee_cannot_assign_schedules(): void
    {
        $emp = $this->makeEmployee('employee', 'emp@t.test');

        $this->withToken($this->tessaToken($emp))->postJson('/api/tessa/schedules', [
            'assignments' => [['employee_id' => $emp->id, 'date' => '2026-07-01', 'shift' => 'Pagi']],
        ])->assertStatus(403);
    }

    public function test_admin_passes_schedule_role_gate(): void
    {
        $admin = $this->makeEmployee('hr_admin', 'hr@t.test');

        // Tak ada shift "Pagi" → gagal per-baris, TAPI bukan 403 → berarti lolos gate role.
        $this->withToken($this->tessaToken($admin))->postJson('/api/tessa/schedules', [
            'assignments' => [['employee_id' => $admin->id, 'date' => '2026-07-01', 'shift' => 'Pagi']],
        ])->assertOk();
    }

    public function test_employee_list_scoped_to_self(): void
    {
        $emp = $this->makeEmployee('employee', 'emp@t.test');
        $this->makeEmployee('employee', 'other@t.test');

        $resp = $this->withToken($this->tessaToken($emp))->getJson('/api/tessa/employees');
        $resp->assertOk();
        $this->assertSame([$emp->id], collect($resp->json('data'))->pluck('id')->all());
    }

    public function test_admin_sees_all_employees(): void
    {
        $admin = $this->makeEmployee('hr_admin', 'hr@t.test');
        $this->makeEmployee('employee', 'emp@t.test');

        $resp = $this->withToken($this->tessaToken($admin))->getJson('/api/tessa/employees');
        $resp->assertOk();
        $this->assertGreaterThanOrEqual(2, count($resp->json('data')));
    }

    public function test_employee_cannot_create_request_for_other(): void
    {
        $emp = $this->makeEmployee('employee', 'emp@t.test');
        $other = $this->makeEmployee('employee', 'other@t.test');

        // Buat pengajuan cuti atas nama ORANG LAIN → tertahan gate role (leaves.create).
        $this->withToken($this->tessaToken($emp))->postJson('/api/tessa/requests/leave', [
            'employee_id' => $other->id,
            'reason' => 'x',
        ])->assertStatus(403);
    }

    public function test_employee_can_view_only_own_profile(): void
    {
        $emp = $this->makeEmployee('employee', 'emp@t.test');
        $other = $this->makeEmployee('employee', 'other@t.test');

        $this->withToken($this->tessaToken($emp))->getJson('/api/tessa/employees/'.$other->id)->assertStatus(403);
        $this->withToken($this->tessaToken($emp))->getJson('/api/tessa/employees/'.$emp->id)->assertOk();
    }
}
