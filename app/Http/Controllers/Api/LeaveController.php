<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Services\FcmService;
use App\Support\LeaveQuota;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaveController extends Controller
{
    public function balance(Request $request)
    {
        $balances = LeaveBalance::where('employee_id', $request->user()->id)
            ->where('year', now()->year)
            ->with('leaveType')
            ->whereHas('leaveType', fn ($query) => $query->where('name', LeaveQuota::ANNUAL_NAME))
            ->get();

        return response()->json(['success' => true, 'data' => $balances]);
    }

    public function types(Request $request)
    {
        $leaveTypes = LeaveBalance::with('leaveType')
            ->where('employee_id', $request->user()->id)
            ->where('year', now()->year)
            ->get()
            ->pluck('leaveType')
            ->filter()
            ->sortBy('name')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $leaveTypes,
        ]);
    }

    public function index(Request $request)
    {
        $request->validate(['period' => 'nullable|date_format:Y-m']);
        $period = $request->period ? Carbon::parse($request->period.'-01') : now();

        $requests = LeaveRequest::where('employee_id', $request->user()->id)
            ->whereYear('created_at', $period->year)
            ->whereMonth('created_at', $period->month)
            ->with(['leaveType', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function companyTimeline(Request $request)
    {
        $employee = $request->user();
        $company = $employee->company;
        $today = Carbon::today();

        // Default: tampilkan 30 hari ke belakang dari hari ini
        $dateFrom = $today->copy()->subDays(29);
        $dateTo = $today;

        // Semua approved leave yang tanggalnya overlap dengan window
        $leaves = LeaveRequest::with([
            'employee:id,full_name,department_id',
            'employee.department:id,name',
            'leaveType:id,name',
        ])
            ->whereHas('employee', fn ($q) => $q->where('company_id', $company->id))
            ->where('status', 'approved')
            ->where('start_date', '<=', $dateTo->toDateString())
            ->where('end_date', '>=', $dateFrom->toDateString())
            ->get();

        // Expand tiap leave ke hari-hari yang ada di window
        $byDate = [];
        for ($d = $today->copy(); ! $d->isBefore($dateFrom); $d->subDay()) {
            $dateStr = $d->toDateString();
            foreach ($leaves as $leave) {
                $s = Carbon::parse($leave->start_date);
                $e = Carbon::parse($leave->end_date);
                if (! $d->isBefore($s) && ! $d->isAfter($e)) {
                    $byDate[$dateStr][] = [
                        'name' => strtoupper($leave->employee->full_name ?? '-'),
                        'department' => strtoupper($leave->employee->department->name ?? '-'),
                        'leave_type' => $leave->leaveType->name ?? 'Cuti',
                        'created_at' => $leave->created_at?->toDateTimeString(),
                    ];
                }
            }
        }

        // Bersihkan tanggal tanpa cuti
        $byDate = array_filter($byDate);

        $days = [];
        foreach ($byDate as $dateStr => $employees) {
            $latestCreatedAt = collect($employees)
                ->pluck('created_at')
                ->filter()
                ->sortDesc()
                ->first();

            $days[] = [
                'date' => $dateStr,
                'created_at' => $latestCreatedAt,
                'employees' => array_values($employees),
            ];
        }

        $timeline = collect($days)
            ->map(fn ($day) => [
                'type' => 'leave',
                'date' => $day['date'],
                'created_at' => $day['created_at'],
                'employees' => $day['employees'],
            ]);

        $birthdays = Employee::with('department:id,name')
            ->where('company_id', $company->id)
            ->whereNotNull('birth_date')
            ->get()
            ->map(function ($birthdayEmployee) use ($dateFrom, $dateTo) {
                $birthdayDate = $birthdayEmployee->birth_date->copy()->year($dateTo->year);

                if ($birthdayDate->isBefore($dateFrom) || $birthdayDate->isAfter($dateTo)) {
                    return null;
                }

                return [
                    'type' => 'birthday',
                    'date' => $birthdayDate->toDateString(),
                    'created_at' => $birthdayDate->startOfDay()->toDateTimeString(),
                    'employee' => [
                        'name' => strtoupper($birthdayEmployee->full_name ?? '-'),
                        'department' => strtoupper($birthdayEmployee->department->name ?? '-'),
                    ],
                ];
            })
            ->filter()
            ->values();

        $timeline = $timeline
            ->merge($birthdays)
            ->sortByDesc(fn ($item) => ($item['date'] ?? '').' '.($item['created_at'] ?? ''))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'company' => [
                    'name' => $company->name,
                    'logo' => $company->logo ? asset('storage/'.$company->logo) : null,
                ],
                'days' => $days,
                'timeline' => $timeline,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'required|numeric|min:0.5',
            'reason' => 'required|string',
            'delegate_to' => 'nullable|exists:employees,id',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        // Hanya Cuti Tahunan yang berkuota; izin & tipe lain bebas saldo.
        $leaveType = LeaveType::find($request->leave_type_id);

        if ($leaveType && $leaveType->name === 'Cuti Tahunan') {
            $balance = LeaveBalance::where('employee_id', $request->user()->id)
                ->where('leave_type_id', $request->leave_type_id)
                ->where('year', now()->year)
                ->first();

            if (! $balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Saldo cuti belum tersedia.',
                ], 422);
            }

            if ($balance->remaining_days < $request->total_days) {
                return response()->json([
                    'success' => false,
                    'message' => 'Saldo cuti tidak mencukupi.',
                ], 422);
            }
        }

        $leaveRequest = LeaveRequest::create([
            'employee_id' => $request->user()->id,
            'leave_type_id' => $request->leave_type_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'total_days' => $request->total_days,
            'reason' => $request->reason,
            'delegate_to' => $request->delegate_to,
            'status' => 'pending',
            'current_step' => 1,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/leave-requests', 'public');
                $leaveRequest->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Notify step 1 approver from employee_approvers table
        $firstApprover = EmployeeApprover::getApproverAt($request->user()->id, 'leave', 1);
        if ($firstApprover) {
            Notification::create([
                'employee_id' => $firstApprover->id,
                'title' => 'Pengajuan Cuti Baru',
                'message' => "{$request->user()->full_name} mengajukan cuti dari {$request->start_date} s/d {$request->end_date}",
                'type' => 'approval',
                'reference_type' => LeaveRequest::class,
                'reference_id' => $leaveRequest->id,
            ]);

            FcmService::sendToEmployee($firstApprover, 'Pengajuan Cuti Baru',
                "{$request->user()->full_name} mengajukan cuti dari {$request->start_date} s/d {$request->end_date}",
                ['type' => 'approval', 'reference_type' => 'leave', 'reference_id' => (string) $leaveRequest->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan cuti berhasil',
            'data' => $leaveRequest->load(['leaveType', 'attachments']),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $leaveRequest = LeaveRequest::where('employee_id', $request->user()->id)
            ->with(['leaveType', 'attachments', 'approvalLogs.approver', 'delegate', 'employee'])
            ->findOrFail($id);

        // Build approval chain from employee_approvers table
        $approvalSteps = [];
        $chain = EmployeeApprover::getChain($leaveRequest->employee_id, 'leave');

        foreach ($chain as $step) {
            $log = $leaveRequest->approvalLogs->where('step_order', $step->step_order)->first();

            if ($log) {
                $stepStatus = $log->action;
            } elseif ($leaveRequest->status === 'approved') {
                $stepStatus = 'approved';
            } elseif ($leaveRequest->status === 'rejected') {
                $stepStatus = $step->step_order <= $leaveRequest->current_step ? 'rejected' : 'waiting';
            } elseif ($leaveRequest->current_step == $step->step_order) {
                $stepStatus = 'pending';
            } else {
                $stepStatus = 'waiting';
            }

            $approvalSteps[] = [
                'step' => $step->step_order,
                'approver_id' => $step->approver->id,
                'approver_name' => $step->approver->full_name,
                'approver_position' => $step->approver->position,
                'status' => $stepStatus,
                'notes' => $log?->notes,
                'approved_at' => $log?->created_at?->toDateTimeString(),
            ];
        }

        $data = $leaveRequest->toArray();
        $data['approval_steps'] = $approvalSteps;

        return response()->json(['success' => true, 'data' => $data]);
    }
}
