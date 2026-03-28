<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        // Notify approver
        $approver = $request->user()->approver;
        if ($approver) {
            Notification::create([
                'employee_id' => $approver->id,
                'title' => 'Pengajuan Cuti Baru',
                'message' => "{$request->user()->full_name} mengajukan cuti dari {$request->start_date} s/d {$request->end_date}",
                'type' => 'approval',
                'reference_type' => LeaveRequest::class,
                'reference_id' => $leaveRequest->id,
            ]);

            FcmService::sendToEmployee($approver, 'Pengajuan Cuti Baru',
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

        // Build approval chain
        $approvalSteps = [];
        $employee = $leaveRequest->employee;
        $current = $employee;
        $stepNum = 1;
        $visited = [];

        // Check if it was directly approved/rejected without going through chain
        $directlyResolved = in_array($leaveRequest->status, ['approved', 'rejected'])
            && $leaveRequest->approvalLogs->isEmpty();

        while ($current->approver_id && !in_array($current->approver_id, $visited)) {
            $visited[] = $current->id;
            $approver = Employee::find($current->approver_id);
            if (!$approver) break;

            // Check if this step has a log
            $log = $leaveRequest->approvalLogs
                ->where('step_order', $stepNum)
                ->first();

            if ($log) {
                $stepStatus = $log->action; // approved / rejected
            } elseif ($directlyResolved) {
                // Super admin approved/rejected directly — all steps follow final status
                $stepStatus = $leaveRequest->status;
            } elseif ($leaveRequest->status === 'approved') {
                // Fully approved but this step has no individual log (shouldn't happen normally)
                $stepStatus = 'approved';
            } elseif ($leaveRequest->status === 'rejected') {
                $stepStatus = $stepNum <= $leaveRequest->current_step ? 'rejected' : 'waiting';
            } elseif ($leaveRequest->current_step == $stepNum) {
                $stepStatus = 'pending'; // menunggu approver ini
            } else {
                $stepStatus = 'waiting'; // belum sampai step ini
            }

            $approvalSteps[] = [
                'step' => $stepNum,
                'approver_id' => $approver->id,
                'approver_name' => $approver->full_name,
                'approver_position' => $approver->position,
                'status' => $stepStatus,
                'notes' => $log?->notes,
                'approved_at' => $log?->created_at?->toDateTimeString(),
            ];

            $current = $approver;
            $stepNum++;
        }

        $data = $leaveRequest->toArray();
        $data['approval_steps'] = $approvalSteps;

        return response()->json(['success' => true, 'data' => $data]);
    }
}
