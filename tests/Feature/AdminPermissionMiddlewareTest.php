<?php

namespace Tests\Feature;

use App\Http\Middleware\AdminPermissionMiddleware;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class AdminPermissionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('employee_permission_overrides');
        Schema::dropIfExists('role_permissions');
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

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role', 'permission']);
        });

        Schema::create('employee_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('permission');
            $table->boolean('allowed');
            $table->timestamps();
            $table->unique(['employee_id', 'permission']);
        });
    }

    public function test_permission_middleware_blocks_admin_without_permission(): void
    {
        DB::table('employees')->insert([
            'id' => 20,
            'company_id' => 1,
            'email' => 'manager@example.test',
            'password' => 'secret',
            'full_name' => 'Manager',
            'role' => 'manager',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role' => 'manager',
            'permission' => 'payroll.runs.publish',
            'allowed' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['admin_id' => 20]);
        $request = Request::create('/admin/payroll-runs/1/publish', 'POST');
        $request->setLaravelSession(app('session.store'));

        $response = (new AdminPermissionMiddleware())->handle(
            $request,
            fn () => new Response('allowed'),
            'payroll.runs.publish'
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}
