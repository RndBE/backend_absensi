<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiDailyTokenBridgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employees');
        Schema::dropIfExists('companies');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code')->unique();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_authenticated_employee_can_request_daily_token_by_email(): void
    {
        config([
            'services.daily.url' => 'http://daily.test',
            'services.daily.internal_secret' => 'bridge-secret',
        ]);

        $company = Company::create(['name' => 'PT Arta']);
        $employee = Employee::create([
            'employee_code' => 'EMP001',
            'company_id' => $company->id,
            'full_name' => 'Mobile Staff',
            'email' => 'staff@example.test',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        Http::fake([
            'http://daily.test/api/internal/mobile-token' => Http::response([
                'success' => true,
                'token' => 'daily-token',
                'user' => ['email' => 'staff@example.test'],
            ]),
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/daily/token');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('token', 'daily-token');

        Http::assertSent(fn ($request) => $request->url() === 'http://daily.test/api/internal/mobile-token'
            && $request->header('X-Internal-Secret')[0] === 'bridge-secret'
            && $request['email'] === 'staff@example.test');
    }
}
