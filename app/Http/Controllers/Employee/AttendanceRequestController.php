<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRequest;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceRequestController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $period = $request->query('period') ? Carbon::parse($request->query('period').'-01') : now();

        return view('employee.attendance-requests.index', [
            'employee' => $employee,
            'requests' => AttendanceRequest::where('employee_id', $employee->id)
                ->whereYear('date', $period->year)
                ->whereMonth('date', $period->month)
                ->with('attachments')
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get(),
            'period' => $period,
        ]);
    }

    public function create(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        return view('employee.attendance-requests.create', [
            'employee' => $employee,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'clock_in' => 'nullable|date_format:H:i',
            'clock_out' => 'nullable|date_format:H:i',
            'reason' => 'required|string|max:1000',
            'attachments.*' => 'nullable|file|max:10240',
        ]);

        if (blank($validated['clock_in'] ?? null) && blank($validated['clock_out'] ?? null)) {
            return back()
                ->withInput()
                ->with('error', 'Isi minimal jam clock in atau clock out.');
        }

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        $attendanceRequest = AttendanceRequest::create([
            'employee_id' => $employee->id,
            'date' => $validated['date'],
            'clock_in' => $validated['clock_in'] ?? null,
            'clock_out' => $validated['clock_out'] ?? null,
            'reason' => $validated['reason'],
            'status' => 'pending',
            'current_step' => 1,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('attachments/attendance-requests', 'public');
                $attendanceRequest->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return redirect()
            ->route('employee.attendance-requests.index')
            ->with('success', 'Pengajuan absensi berhasil dikirim.');
    }
}
