<?php

namespace Tests\Feature;

use App\Http\Middleware\EmployeeSessionLifetime;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class EmployeeSessionLifetimeTest extends TestCase
{
    public function test_employee_routes_use_seven_day_session_lifetime(): void
    {
        config(['session.lifetime' => 120]);

        $request = Request::create('/employee/dashboard');

        (new EmployeeSessionLifetime())->handle($request, function () {
            $this->assertSame(10080, config('session.lifetime'));
            $this->assertFalse(config('session.expire_on_close'));

            return new Response('ok');
        });
    }

    public function test_admin_routes_keep_default_session_lifetime(): void
    {
        config(['session.lifetime' => 120]);

        $request = Request::create('/admin/dashboard');

        (new EmployeeSessionLifetime())->handle($request, function () {
            $this->assertSame(120, config('session.lifetime'));

            return new Response('ok');
        });
    }
}
