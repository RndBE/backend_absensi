<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OvertimeController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $period = $request->query('period') ? Carbon::parse($request->query('period').'-01') : now();

        return view('employee.overtimes.index', [
            'employee' => $employee,
            'requests' => OvertimeRequest::where('employee_id', $employee->id)
                ->whereYear('created_at', $period->year)
                ->whereMonth('created_at', $period->month)
                ->latest()
                ->get(),
            'period' => $period,
        ]);
    }

    public function create(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.overtimes.create', [
            'employee' => $employee,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'overtime_type' => 'required|in:workday,holiday',
            'planned_start' => 'nullable|date_format:H:i',
            'planned_end' => 'nullable|date_format:H:i',
            'pre_shift_duration' => 'nullable|integer|min:0',
            'pre_shift_break' => 'nullable|integer|min:0',
            'post_shift_duration' => 'nullable|integer|min:0',
            'post_shift_break' => 'nullable|integer|min:0',
            'break_duration' => 'nullable|integer|min:0',
            'reason' => 'required|string|max:1000',
        ]);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $type = $validated['overtime_type'];

        if ($type === 'holiday') {
            if (blank($validated['planned_start'] ?? null) || blank($validated['planned_end'] ?? null)) {
                return back()
                    ->withInput()
                    ->with('error', 'Jam mulai dan selesai wajib diisi untuk lembur hari libur.');
            }

            $start = Carbon::parse($validated['planned_start']);
            $end = Carbon::parse($validated['planned_end']);
            $totalDuration = max(0, $end->diffInMinutes($start));
            $breakDuration = (int) ($validated['break_duration'] ?? 0);

            OvertimeRequest::create([
                'employee_id' => $employee->id,
                'date' => $validated['date'],
                'overtime_type' => 'holiday',
                'planned_start' => $validated['planned_start'],
                'planned_end' => $validated['planned_end'],
                'break_duration' => $breakDuration,
                'total_duration' => $totalDuration,
                'reason' => $validated['reason'],
                'status' => 'pending',
                'current_step' => 1,
            ]);
        } else {
            $preDuration = (int) ($validated['pre_shift_duration'] ?? 0);
            $preBreak = (int) ($validated['pre_shift_break'] ?? 0);
            $postDuration = (int) ($validated['post_shift_duration'] ?? 0);
            $postBreak = (int) ($validated['post_shift_break'] ?? 0);

            OvertimeRequest::create([
                'employee_id' => $employee->id,
                'date' => $validated['date'],
                'overtime_type' => 'workday',
                'pre_shift_duration' => $preDuration,
                'pre_shift_break' => $preBreak,
                'post_shift_duration' => $postDuration,
                'post_shift_break' => $postBreak,
                'break_duration' => $preBreak + $postBreak,
                'total_duration' => $preDuration + $postDuration,
                'reason' => $validated['reason'],
                'status' => 'pending',
                'current_step' => 1,
            ]);
        }

        return redirect()
            ->route('employee.overtimes.index')
            ->with('success', 'Pengajuan lembur berhasil dikirim.');
    }
}
