<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OvertimeController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['period' => 'nullable|date_format:Y-m']);
        $period = $request->period ? Carbon::parse($request->period . '-01') : now();

        $requests = OvertimeRequest::where('employee_id', $request->user()->id)
            ->whereYear('created_at', $period->year)
            ->whereMonth('created_at', $period->month)
            ->with('attachments')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $requests]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'duration' => 'required|integer|min:1',
            'reason' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        $overtimeRequest = OvertimeRequest::create([
            'employee_id' => $request->user()->id,
            'date' => $request->date,
            'total_duration' => $request->duration,
            'reason' => $request->reason,
            'current_step' => 1,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/overtime-requests', 'public');
                $overtimeRequest->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        $approver = $request->user()->approver;
        if ($approver) {
            Notification::create([
                'employee_id' => $approver->id,
                'title' => 'Pengajuan Lembur Baru',
                'message' => "{$request->user()->full_name} mengajukan lembur tanggal {$request->date}",
                'type' => 'approval',
                'reference_type' => OvertimeRequest::class,
                'reference_id' => $overtimeRequest->id,
            ]);

            FcmService::sendToEmployee($approver, 'Pengajuan Lembur Baru',
                "{$request->user()->full_name} mengajukan lembur tanggal {$request->date}",
                ['type' => 'approval', 'reference_type' => 'overtime', 'reference_id' => (string) $overtimeRequest->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan lembur berhasil',
            'data' => $overtimeRequest->load('attachments'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $overtimeRequest = OvertimeRequest::where('employee_id', $request->user()->id)
            ->with(['attachments', 'approvalLogs.approver', 'employee'])
            ->findOrFail($id);

        // Build approval chain
        $approvalSteps = [];
        $employee = $overtimeRequest->employee;
        $current = $employee;
        $stepNum = 1;
        $visited = [];

        $directlyResolved = in_array($overtimeRequest->status, ['approved', 'rejected'])
            && $overtimeRequest->approvalLogs->isEmpty();

        while ($current->approver_id && !in_array($current->approver_id, $visited)) {
            $visited[] = $current->id;
            $approver = Employee::find($current->approver_id);
            if (!$approver) break;

            $log = $overtimeRequest->approvalLogs
                ->where('step_order', $stepNum)
                ->first();

            if ($log) {
                $stepStatus = $log->action;
            } elseif ($directlyResolved) {
                $stepStatus = $overtimeRequest->status;
            } elseif ($overtimeRequest->status === 'approved') {
                $stepStatus = 'approved';
            } elseif ($overtimeRequest->status === 'rejected') {
                $stepStatus = $stepNum <= $overtimeRequest->current_step ? 'rejected' : 'waiting';
            } elseif ($overtimeRequest->current_step == $stepNum) {
                $stepStatus = 'pending';
            } else {
                $stepStatus = 'waiting';
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

        $data = $overtimeRequest->toArray();
        $data['approval_steps'] = $approvalSteps;

        return response()->json(['success' => true, 'data' => $data]);
    }
}
