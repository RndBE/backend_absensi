<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeApprover;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Notification;
use App\Models\Employee;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaveController extends Controller
{
    public function balance(Request $request)
    {
        $balances = LeaveBalance::where('employee_id', $request->user()->id)
            ->where('year', now()->year)
            ->with('leaveType')
            ->get();

        return response()->json(['success' => true, 'data' => $balances]);
    }

    public function types()
    {
        return response()->json([
            'success' => true,
            'data' => LeaveType::all(),
        ]);
    }

    public function index(Request $request)
    {
        $request->validate(['period' => 'nullable|date_format:Y-m']);
        $period = $request->period ? Carbon::parse($request->period . '-01') : now();

        $requests = LeaveRequest::where('employee_id', $request->user()->id)
            ->whereYear('created_at', $period->year)
            ->whereMonth('created_at', $period->month)
            ->with(['leaveType', 'attachments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $requests]);
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

        // Check balance
        $balance = LeaveBalance::where('employee_id', $request->user()->id)
            ->where('leave_type_id', $request->leave_type_id)
            ->where('year', now()->year)
            ->first();

        if ($balance && $balance->remaining_days < $request->total_days) {
            return response()->json([
                'success' => false,
                'message' => 'Saldo cuti tidak mencukupi.',
            ], 422);
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
