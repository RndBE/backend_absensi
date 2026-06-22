<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeaveController extends Controller
{
    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $period = $request->query('period') ? Carbon::parse($request->query('period').'-01') : now();

        return view('employee.leaves.index', [
            'employee' => $employee,
            'balances' => LeaveBalance::with('leaveType')
                ->where('employee_id', $employee->id)
                ->where('year', now()->year)
                ->whereHas('leaveType', fn ($query) => $query->where('name', 'Cuti Tahunan'))
                ->get(),
            'requests' => LeaveRequest::with('leaveType')
                ->where('employee_id', $employee->id)
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
        $balances = LeaveBalance::with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('year', now()->year)
            ->get()
            ->keyBy('leave_type_id');

        return view('employee.leaves.create', [
            'employee' => $employee,
            'leaveTypes' => $balances
                ->pluck('leaveType')
                ->filter()
                ->sortBy('name')
                ->values(),
            'balances' => $balances,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'required|numeric|min:0.5',
            'reason' => 'required|string|max:1000',
            'delegate_to' => 'nullable|exists:employees,id',
        ]);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');
        $totalDays = Carbon::parse($validated['start_date'])
            ->startOfDay()
            ->diffInDays(Carbon::parse($validated['end_date'])->startOfDay()) + 1;
        $validated['total_days'] = $totalDays;

        // Hanya Cuti Tahunan yang berkuota; izin & tipe lain bebas saldo.
        $leaveType = LeaveType::find($validated['leave_type_id']);

        if ($leaveType && $leaveType->name === 'Cuti Tahunan') {
            $balance = LeaveBalance::where('employee_id', $employee->id)
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', now()->year)
                ->first();

            if (! $balance) {
                return back()
                    ->withInput()
                    ->with('error', 'Saldo cuti belum tersedia.');
            }

            if ((float) $balance->remaining_days < (float) $validated['total_days']) {
                return back()
                    ->withInput()
                    ->with('error', 'Saldo cuti tidak mencukupi.');
            }
        }

        LeaveRequest::create($validated + [
            'employee_id' => $employee->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);

        return redirect()
            ->route('employee.leaves.index')
            ->with('success', 'Pengajuan cuti berhasil dikirim.');
    }
}
