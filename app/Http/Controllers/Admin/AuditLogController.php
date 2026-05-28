<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = AdminActivityLog::with('employee:id,full_name,employee_code,role')
            ->orderByDesc('created_at');

        if ($admin->role !== 'superadmin') {
            $query->where('company_id', $admin->company_id);
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $logs = $query->paginate(25)->withQueryString();
        $modules = AdminActivityLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $actions = AdminActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $admins = Employee::whereIn('role', ['superadmin', 'admin', 'manager'])
            ->when($admin->role !== 'superadmin', fn ($q) => $q->where('company_id', $admin->company_id))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code']);

        return view('admin.audit-logs.index', compact('logs', 'modules', 'actions', 'admins'));
    }
}
