<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! session('employee_id')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Silakan login terlebih dahulu.',
                ], 401);
            }

            return redirect()->route('employee.login');
        }

        $employee = Employee::find(session('employee_id'));
        if (! $employee || ! $employee->is_active) {
            session()->forget('employee_id');

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun anda tidak aktif.',
                ], 403);
            }

            return redirect()->route('employee.login')->with('error', 'Akun anda tidak aktif.');
        }

        view()->share('currentEmployee', $employee);
        $request->attributes->set('employee', $employee);

        return $next($request);
    }
}
