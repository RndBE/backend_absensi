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

        Schema::dropIfExists('admin_activity_logs');
        Schema::dropIfExists('employees');
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('full_name');
            $table->string('role')->default('employee');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('admin_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('module');
            $table->string('action');
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
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

    public function test_admin_login_does_not_log_excluded_superadmin_email(): void
    {
        DB::table('employees')->insert([
            'id' => 35,
            'company_id' => 1,
            'email' => 'superadmin@gmail.com',
            'password' => Hash::make('password'),
            'full_name' => 'Superadmin',
            'role' => 'superadmin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'superadmin@gmail.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertSame(35, session('admin_id'));
        $this->assertDatabaseCount('admin_activity_logs', 0);
    }

    public function test_audit_log_page_hides_existing_logs_from_excluded_superadmin_email(): void
    {
        DB::table('employees')->insert([
            [
                'id' => 35,
                'company_id' => 1,
                'email' => 'superadmin@gmail.com',
                'password' => Hash::make('password'),
                'full_name' => 'Hidden Root',
                'role' => 'superadmin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 36,
                'company_id' => 1,
                'email' => 'admin@example.test',
                'password' => Hash::make('password'),
                'full_name' => 'Visible Admin',
                'role' => 'superadmin',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('admin_activity_logs')->insert([
            [
                'employee_id' => 35,
                'company_id' => 1,
                'module' => 'auth',
                'action' => 'login',
                'route_name' => 'admin.login',
                'method' => 'POST',
                'path' => 'admin/login',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_id' => 36,
                'company_id' => 1,
                'module' => 'employees',
                'action' => 'store',
                'route_name' => 'admin.employees.store',
                'method' => 'POST',
                'path' => 'admin/employees',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this
            ->withSession(['admin_id' => 36])
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('Visible Admin')
            ->assertSee('employees')
            ->assertDontSee('Hidden Root')
            ->assertDontSee('auth');
    }
}
