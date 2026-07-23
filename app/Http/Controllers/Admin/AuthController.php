<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Employee;
use App\Support\AdminPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    private const EXCLUDED_ACTIVITY_LOG_EMAILS = ['superadmin@gmail.com'];

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

        if (!$employee || !Hash::check($request->password, $employee->password)) {
            return back()->with('error', 'Email atau password salah.')->withInput();
        }

        if (!app(AdminPermission::class)->isAdminUser($employee)) {
            return back()->with('error', 'Akses admin ditolak.')->withInput();
        }

        session(['admin_id' => $employee->id]);
        $this->logAuthActivity($employee, 'login', $request);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        $employee = Employee::find(session('admin_id'));
        if ($employee) {
            $this->logAuthActivity($employee, 'logout', $request);
        }

        session()->forget('admin_id');
        return redirect()->route('admin.login');
    }

    private function logAuthActivity(Employee $employee, string $action, Request $request): void
    {
        if (! Schema::hasTable('admin_activity_logs') || $this->isExcludedFromActivityLog($employee)) {
            return;
        }

        AdminActivityLog::create([
            'employee_id' => $employee->id,
            'company_id' => $employee->company_id,
            'module' => 'auth',
            'action' => $action,
            'route_name' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => null,
        ]);
    }

    private function isExcludedFromActivityLog(Employee $employee): bool
    {
        return in_array(strtolower(trim((string) $employee->email)), self::EXCLUDED_ACTIVITY_LOG_EMAILS, true);
    }
}
