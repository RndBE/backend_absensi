<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * GET /tessa/approvals/pending (service key): pengajuan menunggu + APPROVER STEP AKTIF,
 * agar Tessa mem-WA approver yang tepat. Uji progres antar-step.
 */
class TessaPendingApprovalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tessa.api_key' => 'svc-key']);

        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('employee_code')->unique();
            $t->unsignedBigInteger('company_id');
            $t->string('full_name');
            $t->string('email')->unique();
            $t->string('phone')->nullable();
            $t->boolean('is_active')->default(true);
            $t->string('role')->default('employee');
            $t->timestamps();
        });
        Schema::create('overtime_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->string('status')->default('pending');
            $t->integer('current_step')->default(1);
            $t->timestamps();
        });
        Schema::create('employee_approvers', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('employee_id');
            $t->unsignedBigInteger('approver_id');
            $t->string('request_type');
            $t->integer('step_order');
            $t->timestamps();
        });
    }

    private function emp(string $name, ?string $phone = null): Employee
    {
        return Employee::create([
            'employee_code' => 'EMP-'.substr(md5($name), 0, 8),
            'company_id' => Company::firstOrCreate(['name' => 'PT Tessa'])->id,
            'full_name' => $name,
            'email' => strtolower($name).'@t.test',
            'phone' => $phone,
            'is_active' => true,
            'role' => 'employee',
        ]);
    }

    public function test_requires_service_key(): void
    {
        $this->getJson('/api/tessa/approvals/pending')->assertStatus(401);
    }

    public function test_returns_active_step_approver_and_advances(): void
    {
        // Chain lembur Shandy: Fadel (step 1) → Nofiyanto (step 2).
        $shandy = $this->emp('Shandy');
        $fadel = $this->emp('Fadel', '081211111');
        $nofi = $this->emp('Nofiyanto', '081222222');

        DB::table('employee_approvers')->insert([
            ['employee_id' => $shandy->id, 'approver_id' => $fadel->id, 'request_type' => 'overtime', 'step_order' => 1],
            ['employee_id' => $shandy->id, 'approver_id' => $nofi->id, 'request_type' => 'overtime', 'step_order' => 2],
        ]);
        $otId = DB::table('overtime_requests')->insertGetId([
            'employee_id' => $shandy->id, 'status' => 'pending', 'current_step' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Step 1 aktif → approver = Fadel.
        $r1 = $this->getJson('/api/tessa/approvals/pending?type=overtime', ['X-Api-Key' => 'svc-key']);
        $r1->assertOk();
        $this->assertSame(1, $r1->json('count'));
        $this->assertSame($fadel->id, $r1->json('pending.0.approver.id'));
        $this->assertSame('081211111', $r1->json('pending.0.approver.phone'));
        $this->assertSame(1, $r1->json('pending.0.current_step'));

        // Fadel approve → maju ke step 2 → approver = Nofiyanto.
        DB::table('overtime_requests')->where('id', $otId)->update(['current_step' => 2, 'status' => 'in_review']);

        $r2 = $this->getJson('/api/tessa/approvals/pending?type=overtime', ['X-Api-Key' => 'svc-key']);
        $this->assertSame($nofi->id, $r2->json('pending.0.approver.id'));
        $this->assertSame(2, $r2->json('pending.0.current_step'));
    }

    public function test_approver_without_phone_is_reported_but_not_listed(): void
    {
        $shandy = $this->emp('Shandy');
        $fadel = $this->emp('Fadel'); // tanpa nomor

        DB::table('employee_approvers')->insert([
            ['employee_id' => $shandy->id, 'approver_id' => $fadel->id, 'request_type' => 'overtime', 'step_order' => 1],
        ]);
        DB::table('overtime_requests')->insert([
            'employee_id' => $shandy->id, 'status' => 'pending', 'current_step' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->getJson('/api/tessa/approvals/pending?type=overtime', ['X-Api-Key' => 'svc-key']);
        $resp->assertOk();
        $this->assertSame(0, $resp->json('count'));
        $this->assertSame(1, $resp->json('skipped_no_phone'));
    }
}
