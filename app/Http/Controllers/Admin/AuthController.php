<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('admin_id')) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $employee = Employee::where('email', $request->email)
            ->first();

        if (
            !$employee
            || !$employee->is_active
            || !in_array($employee->role, ['admin', 'superadmin'], true)
            || !Hash::check($request->password, $employee->password)
        ) {
            return back()->with('error', 'Email atau password salah.')->withInput();
        }

        session(['admin_id' => $employee->id]);
        return redirect()->route('admin.dashboard');
    }

    public function logout()
    {
        session()->forget('admin_id');
        return redirect()->route('admin.login');
    }
}
