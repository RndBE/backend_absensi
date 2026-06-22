<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
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

    /**
     * Jam clock-in/out aktual karyawan pada tanggal tertentu — untuk auto-isi
     * jam mulai/selesai lembur hari libur.
     */
    public function attendanceTimes(Request $request)
    {
        $request->validate(['date' => 'required|date']);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $request->query('date'))
            ->first();

        return response()->json([
            'found'     => (bool) $attendance,
            'clock_in'  => $attendance && $attendance->clock_in ? substr($attendance->clock_in, 0, 5) : null,
            'clock_out' => $attendance && $attendance->clock_out ? substr($attendance->clock_out, 0, 5) : null,
        ]);
    }

    public function store(Request $request)
    {
        $durationRule = ['nullable', 'regex:/^(?:\d+|(?:[01]\d|2[0-3]):[0-5]\d)$/'];

        $validated = $request->validate([
            'date' => 'required|date',
            'overtime_type' => 'required|in:workday,holiday',
            'planned_start' => 'nullable|date_format:H:i',
            'planned_end' => 'nullable|date_format:H:i',
            'pre_shift_duration' => $durationRule,
            'pre_shift_break' => $durationRule,
            'post_shift_duration' => $durationRule,
            'post_shift_break' => $durationRule,
            'break_duration' => $durationRule,
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
            if ($end->lessThan($start)) {
                $end->addDay(); // lembur melewati tengah malam
            }
            $totalDuration = (int) $start->diffInMinutes($end);
            $breakDuration = $this->durationToMinutes($validated['break_duration'] ?? 0);

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
            $preDuration = $this->durationToMinutes($validated['pre_shift_duration'] ?? 0);
            $preBreak = $this->durationToMinutes($validated['pre_shift_break'] ?? 0);
            $postDuration = $this->durationToMinutes($validated['post_shift_duration'] ?? 0);
            $postBreak = $this->durationToMinutes($validated['post_shift_break'] ?? 0);

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

    private function durationToMinutes($value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return 0;
    }
}
