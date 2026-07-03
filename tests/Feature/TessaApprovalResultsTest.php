<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * GET /tessa/approvals/results (service key): keputusan final approval
 * untuk dikirim Tessa via WhatsApp ke pengaju.
 */
class TessaApprovalResultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tessa.api_key' => 'svc-key']);

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id');
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('role')->default('employee');
            $table->timestamps();
        });

        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('status')->default('pending');
            $table->integer('current_step')->default(1);
            $table->timestamps();
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
        $this->getJson('/api/tessa/approvals/results')->assertStatus(401);
    }

    public function test_returns_final_approval_results_for_requesters(): void
    {
        $approved = $this->emp('Shandy', '081211111');
        $rejected = $this->emp('Budi', '081222222');
        $pending = $this->emp('Fadel', '081233333');
        $withoutPhone = $this->emp('Nofiyanto');

        DB::table('overtime_requests')->insert([
            [
                'employee_id' => $approved->id, 'status' => 'approved', 'current_step' => 2,
                'created_at' => now()->subMinutes(5), 'updated_at' => now()->subMinutes(5),
            ],
            [
                'employee_id' => $rejected->id, 'status' => 'rejected', 'current_step' => 1,
                'created_at' => now()->subMinutes(4), 'updated_at' => now()->subMinutes(4),
            ],
            [
                'employee_id' => $pending->id, 'status' => 'pending', 'current_step' => 1,
                'created_at' => now()->subMinutes(3), 'updated_at' => now()->subMinutes(3),
            ],
            [
                'employee_id' => $withoutPhone->id, 'status' => 'approved', 'current_step' => 1,
                'created_at' => now()->subMinutes(2), 'updated_at' => now()->subMinutes(2),
            ],
        ]);

        $response = $this->getJson('/api/tessa/approvals/results?type=overtime', ['X-Api-Key' => 'svc-key']);
        $response->assertOk();

        $this->assertSame(2, $response->json('count'));
        $this->assertSame(1, $response->json('skipped_no_phone'));

        $results = collect($response->json('results'));
        $this->assertSame(['approved', 'rejected'], $results->pluck('status')->all());
        $this->assertSame(['081211111', '081222222'], $results->pluck('employee.phone')->all());
        $this->assertStringContainsString('disetujui', $results[0]['message']);
        $this->assertStringContainsString('ditolak', $results[1]['message']);
    }

    public function test_since_limits_results_to_recently_changed_requests(): void
    {
        $old = $this->emp('Old', '081200001');
        $recent = $this->emp('Recent', '081200002');

        DB::table('overtime_requests')->insert([
            [
                'employee_id' => $old->id, 'status' => 'approved', 'current_step' => 1,
                'created_at' => '2026-07-03 08:00:00', 'updated_at' => '2026-07-03 08:00:00',
            ],
            [
                'employee_id' => $recent->id, 'status' => 'rejected', 'current_step' => 1,
                'created_at' => '2026-07-03 09:00:00', 'updated_at' => '2026-07-03 09:00:00',
            ],
        ]);

        $since = rawurlencode('2026-07-03T08:30:00+07:00');
        $response = $this->getJson("/api/tessa/approvals/results?type=overtime&since={$since}", [
            'X-Api-Key' => 'svc-key',
        ]);
        $response->assertOk();

        $this->assertSame(1, $response->json('count'));
        $this->assertSame($recent->id, $response->json('results.0.employee.id'));
    }
}
