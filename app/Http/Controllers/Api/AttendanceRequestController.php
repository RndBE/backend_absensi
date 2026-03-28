<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use App\Models\Notification;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceRequestController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['period' => 'nullable|date_format:Y-m']);
        $period = $request->period ? Carbon::parse($request->period . '-01') : now();

        $requests = AttendanceRequest::where('employee_id', $request->user()->id)
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
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'reason' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        $attRequest = AttendanceRequest::create([
            'employee_id' => $request->user()->id,
            'date' => $request->date,
            'clock_in' => $request->clock_in,
            'clock_out' => $request->clock_out,
            'reason' => $request->reason,
            'current_step' => 1,
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/attendance-requests', 'public');
                $attRequest->attachments()->create([
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
                'title' => 'Pengajuan Presensi Baru',
                'message' => "{$request->user()->full_name} mengajukan presensi tanggal {$request->date}",
                'type' => 'approval',
                'reference_type' => AttendanceRequest::class,
                'reference_id' => $attRequest->id,
            ]);

            FcmService::sendToEmployee($approver, 'Pengajuan Presensi Baru',
                "{$request->user()->full_name} mengajukan presensi tanggal {$request->date}",
                ['type' => 'approval', 'reference_type' => 'attendance', 'reference_id' => (string) $attRequest->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan presensi berhasil',
            'data' => $attRequest->load('attachments'),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $attRequest = AttendanceRequest::where('employee_id', $request->user()->id)
            ->with(['attachments', 'approvalLogs.approver', 'employee'])
            ->findOrFail($id);

        // Build approval chain
        $approvalSteps = [];
        $employee = $attRequest->employee;
        $current = $employee;
        $stepNum = 1;
        $visited = [];

        $directlyResolved = in_array($attRequest->status, ['approved', 'rejected'])
            && $attRequest->approvalLogs->isEmpty();

        while ($current->approver_id && !in_array($current->approver_id, $visited)) {
            $visited[] = $current->id;
            $approver = Employee::find($current->approver_id);
            if (!$approver) break;

            $log = $attRequest->approvalLogs
                ->where('step_order', $stepNum)
                ->first();

            if ($log) {
                $stepStatus = $log->action;
            } elseif ($directlyResolved) {
                $stepStatus = $attRequest->status;
            } elseif ($attRequest->status === 'approved') {
                $stepStatus = 'approved';
            } elseif ($attRequest->status === 'rejected') {
                $stepStatus = $stepNum <= $attRequest->current_step ? 'rejected' : 'waiting';
            } elseif ($attRequest->current_step == $stepNum) {
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

        $data = $attRequest->toArray();
        $data['approval_steps'] = $approvalSteps;

        return response()->json(['success' => true, 'data' => $data]);
    }
}
