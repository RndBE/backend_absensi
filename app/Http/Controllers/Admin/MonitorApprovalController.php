<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRequest;
use App\Models\BudgetRequest;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\TravelReport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MonitorApprovalController extends Controller
{
    private const TYPE_MAP = [
        'leave'      => ['label' => 'Cuti',            'model' => LeaveRequest::class,    'request_type' => 'leave'],
        'overtime'   => ['label' => 'Lembur',           'model' => OvertimeRequest::class,  'request_type' => 'overtime'],
        'attendance' => ['label' => 'Koreksi Presensi', 'model' => AttendanceRequest::class,'request_type' => 'attendance'],
        'budget'     => ['label' => 'Anggaran',         'model' => BudgetRequest::class,    'request_type' => 'budget'],
        'travel'     => ['label' => 'LHP',              'model' => TravelReport::class,     'request_type' => 'travel_report'],
    ];

    public function index(Request $request)
    {
        $admin = Employee::find(session('admin_id'));

        if ($admin->role !== 'superadmin') {
            abort(403, 'Halaman ini hanya untuk Superadmin.');
        }

        $filterType   = $request->get('type', 'all');
        $filterStatus = $request->get('status', 'active');
        $search       = $request->get('search');

        $statuses = $filterStatus === 'done'
            ? ['approved', 'rejected']
            : ($filterStatus === 'all' ? ['pending', 'in_review', 'approved', 'rejected'] : ['pending', 'in_review']);

        $typesToLoad = $filterType === 'all' ? array_keys(self::TYPE_MAP) : [$filterType];

        $allRequests = collect();

        foreach ($typesToLoad as $typeKey) {
            $meta = self::TYPE_MAP[$typeKey] ?? null;
            if (!$meta) continue;

            $extraWith = [];
            if ($typeKey === 'leave') {
                $extraWith = ['leaveType:id,name'];
            }

            $query = $meta['model']::with(array_merge([
                'employee:id,full_name,employee_code,photo,department_id,job_level',
                'employee.department:id,name',
                'approvalLogs' => fn ($q) => $q->with('approver:id,full_name,position')->orderBy('step_order')->orderBy('created_at'),
            ], $extraWith))
                ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
                ->whereIn('status', $statuses);

            if ($search) {
                $query->whereHas('employee', fn ($q) => $q
                    ->where('full_name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%"));
            }

            $query->orderBy('created_at', 'desc')->each(function ($item) use (&$allRequests, $typeKey, $meta) {
                $chain = EmployeeApprover::getChain($item->employee_id, $meta['request_type']);

                $allRequests->push([
                    'type'       => $typeKey,
                    'type_label' => $meta['label'],
                    'item'       => $item,
                    'chain'      => $chain,
                ]);
            });
        }

        $allRequests = $allRequests->sortByDesc(fn ($r) => $r['item']->created_at)->values();

        // Summary counts for badges
        $summary = [];
        foreach (self::TYPE_MAP as $key => $meta) {
            $summary[$key] = $meta['model']::whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
                ->whereIn('status', ['pending', 'in_review'])
                ->count();
        }
        $summary['all'] = array_sum($summary);

        // Paginate manually
        $page      = (int) $request->get('page', 1);
        $perPage   = 20;
        $paginator = new LengthAwarePaginator(
            $allRequests->slice(($page - 1) * $perPage, $perPage)->values(),
            $allRequests->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('admin.monitor-approvals.index', [
            'requests'     => $paginator,
            'filterType'   => $filterType,
            'filterStatus' => $filterStatus,
            'search'       => $search,
            'summary'      => $summary,
            'typeMap'      => self::TYPE_MAP,
        ]);
    }
}
