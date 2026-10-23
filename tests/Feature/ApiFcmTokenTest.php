<?php

namespace Tests\Feature;

use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Token FCM: satu HP hanya terhubung ke satu akun. Akun yang pernah login di HP yang sama tidak boleh
 * terus memegang tokennya, karena notifikasi akun itu (mis. surat tugas menunggu persetujuan) akan
 * ikut masuk ke HP orang lain.
 */
class ApiFcmTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
        DB::table('employees')->insert([
            [
                'id' => 1, 'company_id' => 1, 'employee_code' => 'EMP001', 'full_name' => 'Budi',
                'email' => 'budi@example.test', 'password' => 'x', 'fcm_token' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 2, 'company_id' => 1, 'employee_code' => 'EMP002', 'full_name' => 'Sari',
                'email' => 'sari@example.test', 'password' => 'x', 'fcm_token' => 'token-hp-budi',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function test_registering_a_token_takes_it_away_from_other_accounts(): void
    {
        Sanctum::actingAs(Employee::findOrFail(1));

        $this->postJson('/api/profile/fcm-token', ['fcm_token' => 'token-hp-budi'])->assertOk();

        $this->assertSame('token-hp-budi', Employee::find(1)->fcm_token);
        $this->assertNull(Employee::find(2)->fcm_token);
    }

    public function test_registering_a_different_token_leaves_other_accounts_alone(): void
    {
        Sanctum::actingAs(Employee::findOrFail(1));

        $this->postJson('/api/profile/fcm-token', ['fcm_token' => 'token-lain'])->assertOk();

        $this->assertSame('token-lain', Employee::find(1)->fcm_token);
        $this->assertSame('token-hp-budi', Employee::find(2)->fcm_token);
    }

    public function test_logout_without_a_token_unlinks_the_account(): void
    {
        Sanctum::actingAs(Employee::findOrFail(2));

        $this->postJson('/api/auth/logout')->assertOk();

        $this->assertNull(Employee::find(2)->fcm_token);
    }

    public function test_logout_with_this_phones_token_unlinks_the_account(): void
    {
        Sanctum::actingAs(Employee::findOrFail(2));

        $this->postJson('/api/auth/logout', ['fcm_token' => 'token-hp-budi'])->assertOk();

        $this->assertNull(Employee::find(2)->fcm_token);
    }

    public function test_logout_from_an_old_phone_keeps_the_newer_phones_token(): void
    {
        Sanctum::actingAs(Employee::findOrFail(2));

        $this->postJson('/api/auth/logout', ['fcm_token' => 'token-hp-lama'])->assertOk();

        $this->assertSame('token-hp-budi', Employee::find(2)->fcm_token);
    }
}
