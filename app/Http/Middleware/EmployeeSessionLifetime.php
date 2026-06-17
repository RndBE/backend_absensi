<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeSessionLifetime
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('employee') || $request->is('employee/*')) {
            config([
                'session.lifetime' => (int) config('session.employee_lifetime', 10080),
                'session.expire_on_close' => false,
            ]);
        }

        return $next($request);
    }
}
