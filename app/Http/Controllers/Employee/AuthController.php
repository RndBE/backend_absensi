<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('employee_id')) {
            return redirect()->route('employee.dashboard');
        }

        return view('employee.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $employee = Employee::where('email', $request->email)->first();

        if (! $employee || ! Hash::check($request->password, $employee->password)) {
            return back()->with('error', 'Email atau password salah.')->withInput();
        }

        if (! $employee->is_active) {
            return back()->with('error', 'Akun anda tidak aktif.')->withInput();
        }

        session(['employee_id' => $employee->id]);

        return redirect()->route('employee.dashboard');
    }

    public function logout()
    {
        session()->forget('employee_id');

        return redirect()->route('employee.login');
    }
}
