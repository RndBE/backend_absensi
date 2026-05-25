<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        $query = AdminAuditLog::with('employee:id,full_name,employee_code')
            ->where('company_id', $admin->company_id);

        if ($request->route_name) {
            $query->where('route_name', 'like', '%' . $request->route_name . '%');
        }

        if ($request->method) {
            $query->where('method', $request->method);
        }

        $logs = $query->latest()->paginate(30)->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }
}
