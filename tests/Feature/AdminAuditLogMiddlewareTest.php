<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminActivityLogger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminAuditLogMiddlewareTest extends TestCase
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

    public function test_admin_activity_logger_records_successful_write_requests(): void
    {
        DB::table('employees')->insert([
            'id' => 30,
            'company_id' => 9,
            'email' => 'admin@example.test',
            'password' => 'secret',
            'full_name' => 'Admin',
            'role' => 'admin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Route::post('/admin/employees', fn () => new Response('', 302))->name('admin.employees.store');

        session(['admin_id' => 30]);
        $request = Request::create('/admin/employees', 'POST', ['full_name' => 'New Employee']);
        $request->setLaravelSession(app('session.store'));
        $request->headers->set('user-agent', 'FeatureTest');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        (new AdminActivityLogger())->handle($request, fn () => new Response('', 302));

        $this->assertDatabaseHas('admin_activity_logs', [
            'employee_id' => 30,
            'company_id' => 9,
            'module' => 'employees',
            'action' => 'store',
            'route_name' => 'admin.employees.store',
            'method' => 'POST',
            'path' => 'admin/employees',
        ]);
    }

    public function test_admin_activity_logger_skips_configured_superadmin_email(): void
    {
        DB::table('employees')->insert([
            'id' => 35,
            'company_id' => 9,
            'email' => 'superadmin@gmail.com',
            'password' => 'secret',
            'full_name' => 'Superadmin',
            'role' => 'superadmin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Route::post('/admin/roles', fn () => new Response('', 302))->name('admin.roles.store');

        session(['admin_id' => 35]);
        $request = Request::create('/admin/roles', 'POST', ['name' => 'Role Baru']);
        $request->setLaravelSession(app('session.store'));
        $request->headers->set('user-agent', 'FeatureTest');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        (new AdminActivityLogger())->handle($request, fn () => new Response('', 302));

        $this->assertDatabaseCount('admin_activity_logs', 0);
    }
}
