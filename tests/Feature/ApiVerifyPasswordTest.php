<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiVerifyPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_authenticated_employee_can_verify_current_password(): void
    {
        $employee = Employee::create([
            'full_name' => 'Payroll Viewer',
            'email' => 'viewer@example.test',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/auth/verify-password', [
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Password valid');
    }

    public function test_verify_current_password_rejects_wrong_password(): void
    {
        $employee = Employee::create([
            'full_name' => 'Payroll Viewer',
            'email' => 'viewer@example.test',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/auth/verify-password', [
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }
}
