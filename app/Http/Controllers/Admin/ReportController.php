<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollRunDetail;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index');
    }

    // ========================
    // ATTENDANCE REPORT
    // ========================
    public function attendance(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $month = $request->month ?? date('Y-m');
        $departmentId = $request->department_id;
        $employeeId = $request->employee_id;

        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereBetween('date', [$start, $end])
            ->with('employee');

        if ($departmentId) $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        if ($employeeId) $query->where('employee_id', $employeeId);

        $attendances = $query->orderBy('date')->orderBy('employee_id')->get();

        // Summary per employee
        $summary = [];
        foreach ($attendances as $att) {
            $empId = $att->employee_id;
            if (!isset($summary[$empId])) {
                $summary[$empId] = [
                    'employee' => $att->employee,
                    'present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'total' => 0,
                ];
            }
            $summary[$empId]['total']++;
            if ($att->status === 'present') $summary[$empId]['present']++;
            if ($att->is_late) $summary[$empId]['late']++;
            if ($att->status === 'absent') $summary[$empId]['absent']++;
            if ($att->status === 'leave') $summary[$empId]['leave']++;
        }

        $departments = Department::where('company_id', $admin->company_id)->orderBy('name')->get();
        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('full_name')->get();

        return view('admin.reports.attendance', compact('attendances', 'summary', 'departments', 'employees', 'month', 'departmentId', 'employeeId'));
    }

    public function exportAttendance(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $month = $request->month ?? date('Y-m');
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereBetween('date', [$start, $end])
            ->with('employee');

        if ($request->department_id) $query->whereHas('employee', fn($q) => $q->where('department_id', $request->department_id));
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $data = $query->orderBy('date')->orderBy('employee_id')->get();

        return $this->streamCsv("attendance_{$month}.csv", [
            'Tanggal', 'Kode Karyawan', 'Nama', 'Departemen', 'Clock In', 'Clock Out', 'Status', 'Terlambat',
        ], $data->map(fn($a) => [
            $a->date->format('Y-m-d'),
            $a->employee->employee_code ?? '',
            $a->employee->full_name ?? '',
            $a->employee->department->name ?? '-',
            $a->clock_in ?? '-',
            $a->clock_out ?? '-',
            $a->status,
            $a->is_late ? 'Ya' : 'Tidak',
        ]));
    }

    // ========================
    // LEAVE REPORT
    // ========================
    public function leave(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? date('Y');
        $status = $request->status;

        $query = LeaveRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereYear('start_date', $year)
            ->with(['employee', 'leaveType']);

        if ($status) $query->where('status', $status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $leaves = $query->orderByDesc('start_date')->get();

        // Summary per employee
        $leaveSummary = [];
        foreach ($leaves->where('status', 'approved') as $lv) {
            $empId = $lv->employee_id;
            if (!isset($leaveSummary[$empId])) {
                $leaveSummary[$empId] = ['employee' => $lv->employee, 'total_days' => 0, 'count' => 0];
            }
            $leaveSummary[$empId]['total_days'] += (float) $lv->total_days;
            $leaveSummary[$empId]['count']++;
        }

        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('full_name')->get();

        return view('admin.reports.leave', compact('leaves', 'leaveSummary', 'employees', 'year', 'status'));
    }

    public function exportLeave(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? date('Y');

        $query = LeaveRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereYear('start_date', $year)
            ->with(['employee', 'leaveType']);

        if ($request->status) $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $data = $query->orderByDesc('start_date')->get();

        return $this->streamCsv("leave_{$year}.csv", [
            'Kode Karyawan', 'Nama', 'Jenis Cuti', 'Tanggal Mulai', 'Tanggal Selesai', 'Jumlah Hari', 'Status', 'Alasan',
        ], $data->map(fn($l) => [
            $l->employee->employee_code ?? '',
            $l->employee->full_name ?? '',
            $l->leaveType->name ?? '-',
            $l->start_date->format('Y-m-d'),
            $l->end_date->format('Y-m-d'),
            $l->total_days,
            $l->status,
            $l->reason ?? '',
        ]));
    }

    // ========================
    // OVERTIME REPORT
    // ========================
    public function overtime(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $month = $request->month ?? date('Y-m');
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = OvertimeRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereBetween('date', [$start, $end])
            ->with('employee');

        if ($request->status) $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $overtimes = $query->orderByDesc('date')->get();

        // Summary
        $otSummary = [];
        foreach ($overtimes->where('status', 'approved') as $ot) {
            $empId = $ot->employee_id;
            if (!isset($otSummary[$empId])) {
                $otSummary[$empId] = ['employee' => $ot->employee, 'total_minutes' => 0, 'actual_minutes' => 0, 'count' => 0];
            }
            $otSummary[$empId]['total_minutes'] += (int) $ot->total_duration;
            $otSummary[$empId]['actual_minutes'] += $ot->getPayableDuration();
            $otSummary[$empId]['count']++;
        }

        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->orderBy('full_name')->get();

        return view('admin.reports.overtime', compact('overtimes', 'otSummary', 'employees', 'month'));
    }

    public function exportOvertime(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $month = $request->month ?? date('Y-m');
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = OvertimeRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereBetween('date', [$start, $end])
            ->with('employee');

        if ($request->status) $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $data = $query->orderByDesc('date')->get();

        return $this->streamCsv("overtime_{$month}.csv", [
            'Tanggal', 'Kode Karyawan', 'Nama', 'Tipe', 'Pre-Shift (menit)', 'Post-Shift (menit)',
            'Total (menit)', 'Break (menit)', 'Aktual (menit)', 'Aktual (jam)', 'Clock Out', 'Status', 'Alasan',
        ], $data->map(fn($o) => [
            $o->date->format('Y-m-d'),
            $o->employee->employee_code ?? '',
            $o->employee->full_name ?? '',
            $o->overtime_type ?? 'workday',
            $o->pre_shift_duration ?? 0,
            $o->post_shift_duration ?? 0,
            $o->total_duration ?? 0,
            $o->approved_break ?? $o->break_duration ?? 0,
            $o->actual_duration ?? '-',
            !is_null($o->actual_duration) ? round($o->actual_duration / 60, 1) : '-',
            $o->actual_clock_out ? substr($o->actual_clock_out, 0, 5) : '-',
            $o->status,
            $o->reason ?? '',
        ]));
    }

    // ========================
    // PAYROLL REPORT
    // ========================
    public function payroll(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $period = $request->period ?? date('Y-m');

        $details = PayrollRunDetail::whereHas('payrollRun', fn($q) => $q->where('period', $period))
            ->whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->with(['employee', 'payrollRun'])
            ->get();

        $totals = [
            'basic' => $details->sum('basic_salary'),
            'earning' => $details->sum('total_earning'),
            'deduction' => $details->sum('total_deduction'),
            'net' => $details->sum('net_salary'),
            'count' => $details->count(),
        ];

        return view('admin.reports.payroll', compact('details', 'totals', 'period'));
    }

    public function exportPayroll(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $period = $request->period ?? date('Y-m');

        $details = PayrollRunDetail::whereHas('payrollRun', fn($q) => $q->where('period', $period))
            ->whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->with(['employee'])->get();

        $rows = $details->map(function ($d) {
            $comps = is_array($d->components) ? $d->components : json_decode($d->components, true);
            $pph21 = 0; $bpjsEmp = 0; $bpjsCo = 0; $lembur = 0;
            foreach ($comps ?? [] as $c) {
                if (str_contains($c['name'] ?? '', 'PPh 21') && ($c['type'] ?? '') === 'deduction') $pph21 += $c['amount'] ?? 0;
                if (str_contains($c['name'] ?? '', 'BPJS (Karyawan)')) $bpjsEmp += $c['amount'] ?? 0;
                if (str_contains($c['name'] ?? '', 'BPJS (Perusahaan)')) $bpjsCo += $c['amount'] ?? 0;
                if (str_contains($c['name'] ?? '', 'Lembur')) $lembur += $c['amount'] ?? 0;
            }
            return [
                $d->employee->employee_code ?? '',
                $d->employee->full_name ?? '',
                $d->employee->position ?? '-',
                $d->basic_salary,
                $lembur,
                $d->total_earning,
                $bpjsEmp,
                $pph21,
                $d->total_deduction,
                $d->net_salary,
                $bpjsCo,
            ];
        });

        return $this->streamCsv("payroll_{$period}.csv", [
            'Kode', 'Nama', 'Jabatan', 'Gaji Pokok', 'Lembur', 'Total Earning', 'BPJS Karyawan', 'PPh 21', 'Total Potongan', 'Gaji Bersih', 'BPJS Perusahaan',
        ], $rows);
    }

    // ========================
    // HELPER
    // ========================
    private function streamCsv(string $filename, array $headers, $rows)
    {
        $httpHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, is_array($row) ? $row : $row->toArray());
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $httpHeaders);
    }
}
