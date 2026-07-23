<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    private const EXCLUDED_ACTIVITY_LOG_EMAILS = ['superadmin@gmail.com'];

    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = AdminActivityLog::with('employee:id,full_name,employee_code,role')
            ->orderByDesc('created_at');

        $this->excludeHiddenAdmins($query);

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
        $modulesQuery = AdminActivityLog::query()->select('module')->distinct()->orderBy('module');
        $actionsQuery = AdminActivityLog::query()->select('action')->distinct()->orderBy('action');
        $this->excludeHiddenAdmins($modulesQuery);
        $this->excludeHiddenAdmins($actionsQuery);

        $modules = $modulesQuery->pluck('module');
        $actions = $actionsQuery->pluck('action');
        $admins = Employee::whereIn('role', ['superadmin', 'admin', 'manager'])
            ->whereNotIn('email', self::EXCLUDED_ACTIVITY_LOG_EMAILS)
            ->when($admin->role !== 'superadmin', fn ($q) => $q->where('company_id', $admin->company_id))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code']);

        return view('admin.audit-logs.index', compact('logs', 'modules', 'actions', 'admins'));
    }

    private function excludeHiddenAdmins($query): void
    {
        $query->whereDoesntHave('employee', function ($employeeQuery) {
            $employeeQuery->whereIn('email', self::EXCLUDED_ACTIVITY_LOG_EMAILS);
        });
    }
}
