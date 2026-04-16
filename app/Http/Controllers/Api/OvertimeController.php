<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeApprover;
use App\Models\Holiday;
use App\Models\Notification;
use App\Models\OvertimeRequest;
use App\Models\ScheduleAssignment;
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
            'overtime_type' => 'nullable|in:workday,holiday',
            'reason' => 'required|string',
            'attachments.*' => 'nullable|file|max:10240',
            // Workday fields
            'pre_shift_duration' => 'nullable|integer|min:0',
            'pre_shift_break' => 'nullable|integer|min:0',
            'post_shift_duration' => 'nullable|integer|min:0',
            'post_shift_break' => 'nullable|integer|min:0',
            // Holiday fields
            'planned_start' => 'nullable|date_format:H:i',
            'planned_end' => 'nullable|date_format:H:i',
            'break_duration' => 'nullable|integer|min:0',
            // Legacy: simple duration (backward compat)
            'duration' => 'nullable|integer|min:1',
        ]);

        $overtimeType = $request->overtime_type ?? 'workday';
        $employee = $request->user();

        if ($overtimeType === 'holiday') {
            // HOLIDAY MODE: jam mulai & jam selesai
            if (!$request->planned_start || !$request->planned_end) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jam mulai dan jam selesai wajib diisi untuk lembur hari libur.',
                ], 422);
            }

            $start = Carbon::parse($request->planned_start);
            $end = Carbon::parse($request->planned_end);
            $totalMinutes = max(0, $end->diffInMinutes($start));
            $breakMinutes = $request->break_duration ?? 0;

            $overtimeRequest = OvertimeRequest::create([
                'employee_id' => $employee->id,
                'date' => $request->date,
                'overtime_type' => 'holiday',
                'planned_start' => $request->planned_start,
                'planned_end' => $request->planned_end,
                'break_duration' => $breakMinutes,
                'total_duration' => $totalMinutes,
                'reason' => $request->reason,
                'current_step' => 1,
            ]);
        } else {
            // WORKDAY MODE: pre-shift dan/atau post-shift
            $preShiftDuration = $request->pre_shift_duration ?? 0;
            $preShiftBreak = $request->pre_shift_break ?? 0;
            $postShiftDuration = $request->post_shift_duration ?? $request->duration ?? 0;
            $postShiftBreak = $request->post_shift_break ?? 0;

            // Backward compat: jika hanya kirim 'duration' + 'break_duration'
            if ($request->filled('duration') && !$request->filled('post_shift_duration')) {
                $postShiftDuration = $request->duration;
                $postShiftBreak = $request->break_duration ?? 0;
            }

            $totalDuration = $preShiftDuration + $postShiftDuration;
            $totalBreak = $preShiftBreak + $postShiftBreak;

            $overtimeRequest = OvertimeRequest::create([
                'employee_id' => $employee->id,
                'date' => $request->date,
                'overtime_type' => 'workday',
                'pre_shift_duration' => $preShiftDuration,
                'pre_shift_break' => $preShiftBreak,
                'post_shift_duration' => $postShiftDuration,
                'post_shift_break' => $postShiftBreak,
                'break_duration' => $totalBreak,
                'total_duration' => $totalDuration,
                'reason' => $request->reason,
                'current_step' => 1,
            ]);
        }

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

        $firstApprover = EmployeeApprover::getApproverAt($employee->id, 'overtime', 1);
        if ($firstApprover) {
            $dateFormatted = Carbon::parse($request->date)->format('d/m/Y');
            Notification::create([
                'employee_id' => $firstApprover->id,
                'title' => 'Pengajuan Lembur Baru',
                'message' => "{$employee->full_name} mengajukan lembur tanggal {$dateFormatted}",
                'type' => 'approval',
                'reference_type' => OvertimeRequest::class,
                'reference_id' => $overtimeRequest->id,
            ]);

            FcmService::sendToEmployee($firstApprover, 'Pengajuan Lembur Baru',
                "{$employee->full_name} mengajukan lembur tanggal {$dateFormatted}",
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

        $approvalSteps = [];
        $chain = EmployeeApprover::getChain($overtimeRequest->employee_id, 'overtime');

        foreach ($chain as $step) {
            $log = $overtimeRequest->approvalLogs->where('step_order', $step->step_order)->first();
            if ($log) { $stepStatus = $log->action; }
            elseif ($overtimeRequest->status === 'approved') { $stepStatus = 'approved'; }
            elseif ($overtimeRequest->status === 'rejected') { $stepStatus = $step->step_order <= $overtimeRequest->current_step ? 'rejected' : 'waiting'; }
            elseif ($overtimeRequest->current_step == $step->step_order) { $stepStatus = 'pending'; }
            else { $stepStatus = 'waiting'; }

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

        $data = $overtimeRequest->toArray();
        $data['approval_steps'] = $approvalSteps;
        $data['payable_duration'] = $overtimeRequest->getPayableDuration();
        $data['payable_duration_formatted'] = $overtimeRequest->payable_duration_formatted;

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Check shift info for a given date.
     * Returns whether it's a workday or off/holiday, and shift times.
     */
    public function checkShift(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        $employee = $request->user();
        $date = Carbon::parse($request->date);
        $dayOfWeek = $date->dayOfWeekIso; // 1=Mon ... 7=Sun

        $shiftName = null;
        $startTime = null;
        $endTime = null;
        $isOff = false;

        // 1. Check schedule_assignments override
        $override = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->where('date', $date)
            ->first();

        if ($override && $override->shift) {
            $shiftName = $override->shift->name;
            $startTime = $override->shift->start_time;
            $endTime = $override->shift->end_time;
            $isOff = (bool) $override->shift->is_off;
        } elseif ($employee->schedule_template_id) {
            // 2. Fallback to schedule template
            $employee->loadMissing('scheduleTemplate.days.shift');
            $shift = $employee->scheduleTemplate?->getShiftForDay($dayOfWeek);
            if ($shift) {
                $shiftName = $shift->name;
                $startTime = $shift->start_time;
                $endTime = $shift->end_time;
                $isOff = (bool) $shift->is_off;
            }
        } elseif ($employee->work_schedule_id) {
            // 3. Fallback to work schedule
            $employee->loadMissing('workSchedule');
            if ($employee->workSchedule) {
                $shiftName = $employee->workSchedule->name ?? 'Default';
                $startTime = $employee->workSchedule->start_time;
                $endTime = $employee->workSchedule->end_time;
            }
        }

        // Check if date is a public holiday
        $isHoliday = Holiday::where('date', $date)
            ->where(function ($q) use ($employee) {
                $q->whereNull('company_id')
                  ->orWhere('company_id', $employee->company_id);
            })->exists();

        // Determine overtime type
        $overtimeType = ($isOff || $isHoliday || $startTime === null) ? 'holiday' : 'workday';

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->format('Y-m-d'),
                'overtime_type' => $overtimeType,
                'is_off' => $isOff,
                'is_holiday' => $isHoliday,
                'shift_name' => $shiftName,
                'shift_start' => $startTime ? substr($startTime, 0, 5) : null,
                'shift_end' => $endTime ? substr($endTime, 0, 5) : null,
            ],
        ]);
    }
}
