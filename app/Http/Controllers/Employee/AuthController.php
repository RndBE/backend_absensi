<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeMagicLink;
use App\Services\EmployeePortalMagicLinkService;
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

    public function magicLogin(Request $request, EmployeePortalMagicLinkService $magicLinks)
    {
        $token = (string) $request->query('token', '');

        if ($token === '') {
            return redirect()->route('employee.login')
                ->with('error', 'Link portal sudah digunakan atau sudah kedaluwarsa.');
        }

        $link = EmployeeMagicLink::with('employee')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $link || $link->used_at || $link->expires_at->isPast()) {
            return redirect()->route('employee.login')
                ->with('error', 'Link portal sudah digunakan atau sudah kedaluwarsa.');
        }

        if (! $link->employee || ! $link->employee->is_active) {
            return redirect()->route('employee.login')
                ->with('error', 'Akun anda tidak aktif.');
        }

        $link->forceFill([
            'used_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ])->save();

        session()->forget('employee_id');
        session()->put('employee_id', $link->employee_id);
        session()->regenerate();

        return redirect($magicLinks->safeRedirectPath($link->redirect_path));
    }

    public function logout()
    {
        session()->forget('employee_id');

        return redirect()->route('employee.login');
    }
}
