<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminAuth;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminAuthRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employees');
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function test_admin_middleware_rejects_employee_role_from_session(): void
    {
        DB::table('employees')->insert([
            'id' => 1,
            'email' => 'employee@example.test',
            'password' => Hash::make('password'),
            'full_name' => 'Regular Employee',
            'role' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['admin_id' => 1]);
        $request = Request::create('/admin/dashboard');
        $request->setLaravelSession(app('session.store'));

        $response = (new AdminAuth())->handle($request, fn () => new Response('allowed'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        if ($response instanceof RedirectResponse) {
            $this->assertSame(route('admin.login'), $response->getTargetUrl());
        }
        $this->assertNull(session('admin_id'));
    }

    public function test_admin_login_rejects_employee_role(): void
    {
        DB::table('employees')->insert([
            'email' => 'employee@example.test',
            'password' => Hash::make('password'),
            'full_name' => 'Regular Employee',
            'role' => 'employee',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->from(route('admin.login'))->post('/admin/login', [
            'email' => 'employee@example.test',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionMissing('admin_id');
    }
}
