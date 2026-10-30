<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Karyawan yang dinonaktifkan atau resign tidak boleh terus memakai app dengan token lama:
 * request berikutnya ditolak 401 (app lalu kembali ke halaman login) dan tokennya dicabut.
 */
class ApiInactiveEmployeeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('employees');
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('employee_code')->unique();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('fcm_token')->nullable();
            $table->boolean('is_active')->default(true);
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
        DB::table('employees')->insert([
            [
                'id' => 1, 'employee_code' => 'EMP001', 'full_name' => 'Budi', 'email' => 'budi@example.test',
                'password' => 'x', 'fcm_token' => 'token-hp-budi', 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 2, 'employee_code' => 'EMP002', 'full_name' => 'Sari', 'email' => 'sari@example.test',
                'password' => 'x', 'fcm_token' => 'token-hp-sari', 'is_active' => false,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function test_active_employee_passes_through(): void
    {
        $token = Employee::findOrFail(1)->createToken('mobile-app')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
    }

    public function test_inactive_employee_is_signed_out_with_401(): void
    {
        $employee = Employee::findOrFail(2);
        $token = $employee->createToken('mobile-app')->plainTextToken;
        $employee->createToken('mobile-app-hp-lain');

        $this->withToken($token)->getJson('/api/dashboard')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Akun anda tidak aktif.']);

        $this->assertSame(0, $employee->tokens()->count());
        $this->assertNull($employee->fresh()->fcm_token);
    }
}
