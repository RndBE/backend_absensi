<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollRunDetail;
use App\Models\Department;
use App\Support\AttendanceLateExcuse;
use App\Support\SimpleXlsxExporter;
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
        // Manager dipaksa ke departemennya sendiri; role lain bebas pilih.
        $departmentId = \App\Support\AdminDataScope::departmentId($admin) ?: $request->department_id;
        $employeeId = $request->employee_id;

        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = Attendance::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id))
            ->whereBetween('date', [$start, $end])
            ->with('employee');

        if ($departmentId) $query->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        if ($employeeId) $query->where('employee_id', $employeeId);

        $attendances = $query->orderBy('date')->orderBy('employee_id')->get();

        // Izin datang terlambat yang sudah di-ACC → tidak dihitung terlambat (tetap hadir).
        $leaves = LeaveRequest::with('leaveType')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->format('Y-m-d'))
            ->where('end_date', '>=', $start->format('Y-m-d'))
            ->get()
            ->groupBy('employee_id');

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
            // 'late_excuse' & 'early_departure' = izin parsial, tetap dihitung hadir.
            if (in_array($att->status, ['present', 'late_excuse', 'early_departure'], true)) $summary[$empId]['present']++;
            // Terlambat TIDAK dihitung bila ada izin datang terlambat (status manual late_excuse
            // atau cuti bertipe "datang terlambat" yang menutupi tanggal tsb).
            $excused = $this->isLateExcused($att, $leaves->get($empId));
            $att->late_excused = $att->is_late && $excused; // dipakai tabel detail
            if ($att->is_late && ! $excused) {
                $summary[$empId]['late']++;
            }
            if ($att->status === 'absent') $summary[$empId]['absent']++;
            if ($att->status === 'leave') $summary[$empId]['leave']++;
        }

        $departments = Department::where('company_id', $admin->company_id)->orderBy('name')->get();
        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)
            ->when(\App\Support\AdminDataScope::departmentId($admin), fn ($q, $d) => $q->where('department_id', $d))
            ->orderBy('full_name')->get();

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

        $deptFilter = \App\Support\AdminDataScope::departmentId($admin) ?: $request->department_id;
        if ($deptFilter) $query->whereHas('employee', fn($q) => $q->where('department_id', $deptFilter));
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $data = $query->orderBy('date')->orderBy('employee_id')->get();

        $leaves = LeaveRequest::with('leaveType')
            ->whereHas('employee', fn ($q) => $q->where('company_id', $admin->company_id))
            ->where('status', 'approved')
            ->where('start_date', '<=', $end->format('Y-m-d'))
            ->where('end_date', '>=', $start->format('Y-m-d'))
            ->get()
            ->groupBy('employee_id');

        return $this->streamXlsx("attendance_{$month}.xlsx", [
            'Tanggal', 'Kode Karyawan', 'Nama', 'Departemen', 'Clock In', 'Clock Out', 'Status', 'Terlambat',
        ], $data->map(fn($a) => [
            $a->date->format('Y-m-d'),
            $a->employee->employee_code ?? '',
            $a->employee->full_name ?? '',
            $a->employee->department->name ?? '-',
            $a->clock_in ?? '-',
            $a->clock_out ?? '-',
            $a->status,
            // Terlambat hanya 'Ya' bila tidak ada izin datang terlambat; 'Izin' bila diizinkan.
            $a->is_late ? ($this->isLateExcused($a, $leaves->get($a->employee_id)) ? 'Izin' : 'Ya') : 'Tidak',
        ]), 'Laporan Absensi');
    }

    /**
     * Apakah keterlambatan pada record presensi ini "dimaafkan" — yaitu ada izin datang
     * terlambat (status manual late_excuse) atau cuti bertipe "datang terlambat" yang
     * menutupi tanggal tersebut. Konsisten dengan rekap absensi.
     */
    private function isLateExcused(Attendance $att, $empLeaves): bool
    {
        if (AttendanceLateExcuse::manualPermissionStatusLabel($att->status) !== null) {
            return true;
        }

        $leaveForDate = AttendanceLateExcuse::firstForDate($empLeaves ?? collect(), $att->date);

        return AttendanceLateExcuse::isLateArrivalLeave($leaveForDate);
    }

    // ========================
    // LEAVE REPORT
    // ========================
    public function leave(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? date('Y');
        $status = $request->status;

        $query = LeaveRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id)->when(\App\Support\AdminDataScope::departmentId($admin), fn($e, $d) => $e->where('department_id', $d)))
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

        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->when(\App\Support\AdminDataScope::departmentId($admin), fn ($q, $d) => $q->where('department_id', $d))->orderBy('full_name')->get();

        return view('admin.reports.leave', compact('leaves', 'leaveSummary', 'employees', 'year', 'status'));
    }

    public function exportLeave(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $year = $request->year ?? date('Y');

        $query = LeaveRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id)->when(\App\Support\AdminDataScope::departmentId($admin), fn($e, $d) => $e->where('department_id', $d)))
            ->whereYear('start_date', $year)
            ->with(['employee', 'leaveType']);

        if ($request->status) $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $data = $query->orderByDesc('start_date')->get();

        return $this->streamXlsx("leave_{$year}.xlsx", [
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
        ]), 'Laporan Cuti');
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

        $query = OvertimeRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id)->when(\App\Support\AdminDataScope::departmentId($admin), fn($e, $d) => $e->where('department_id', $d)))
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

        $employees = Employee::where('company_id', $admin->company_id)->where('is_active', true)->when(\App\Support\AdminDataScope::departmentId($admin), fn ($q, $d) => $q->where('department_id', $d))->orderBy('full_name')->get();

        return view('admin.reports.overtime', compact('overtimes', 'otSummary', 'employees', 'month'));
    }

    public function exportOvertime(Request $request)
    {
        $admin = Employee::find(session('admin_id'));
        $month = $request->month ?? date('Y-m');
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $query = OvertimeRequest::whereHas('employee', fn($q) => $q->where('company_id', $admin->company_id)->when(\App\Support\AdminDataScope::departmentId($admin), fn($e, $d) => $e->where('department_id', $d)))
            ->whereBetween('date', [$start, $end])
            ->with('employee');

        if ($request->status) $query->where('status', $request->status);
        if ($request->employee_id) $query->where('employee_id', $request->employee_id);

        $data = $query->orderByDesc('date')->get();

        return $this->streamXlsx("overtime_{$month}.xlsx", [
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
        ]), 'Laporan Lembur');
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

        // Kumpulkan SEMUA nama komponen lintas karyawan (union), dipisah per jenis.
        // Karyawan yang tak punya komponen tertentu otomatis 0 di kolom itu.
        $earningNames = [];
        $deductionNames = [];
        $otherNames = []; // mis. kontribusi/benefit perusahaan (type selain earning/deduction)
        $parsed = [];

        foreach ($details as $d) {
            $comps = is_array($d->components) ? $d->components : (json_decode($d->components, true) ?? []);
            $bucket = ['earning' => [], 'deduction' => [], 'other' => []];

            foreach ($comps as $c) {
                $name = $c['name'] ?? null;
                if (! $name) {
                    continue;
                }
                $amount = (float) ($c['amount'] ?? 0);
                $type = $c['type'] ?? '';

                if ($type === 'earning') {
                    $bucket['earning'][$name] = ($bucket['earning'][$name] ?? 0) + $amount;
                    if (! in_array($name, $earningNames, true)) $earningNames[] = $name;
                } elseif ($type === 'deduction') {
                    $bucket['deduction'][$name] = ($bucket['deduction'][$name] ?? 0) + $amount;
                    if (! in_array($name, $deductionNames, true)) $deductionNames[] = $name;
                } else {
                    $bucket['other'][$name] = ($bucket['other'][$name] ?? 0) + $amount;
                    if (! in_array($name, $otherNames, true)) $otherNames[] = $name;
                }
            }

            $parsed[$d->id] = $bucket;
        }

        $headers = array_merge(
            ['Kode', 'Nama', 'Jabatan', 'Gaji Pokok'],
            $earningNames,
            ['Total Earning'],
            $deductionNames,
            ['Total Potongan', 'Gaji Bersih'],
            $otherNames,
        );

        $rows = $details->map(function ($d) use ($parsed, $earningNames, $deductionNames, $otherNames) {
            $b = $parsed[$d->id] ?? ['earning' => [], 'deduction' => [], 'other' => []];

            $row = [
                $d->employee->employee_code ?? '',
                $d->employee->full_name ?? '',
                $d->employee->position ?? '-',
                (float) $d->basic_salary,
            ];
            foreach ($earningNames as $name) {
                $row[] = (float) ($b['earning'][$name] ?? 0);
            }
            $row[] = (float) $d->total_earning;
            foreach ($deductionNames as $name) {
                $row[] = (float) ($b['deduction'][$name] ?? 0);
            }
            $row[] = (float) $d->total_deduction;
            $row[] = (float) $d->net_salary;
            foreach ($otherNames as $name) {
                $row[] = (float) ($b['other'][$name] ?? 0);
            }

            return $row;
        });

        $companyName = \App\Models\Company::where('id', $admin->company_id)->value('name') ?? 'Perusahaan';
        $binary = \App\Support\PayrollReportExport::build($headers, $rows->all(), $companyName, $period, 4);

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, "payroll_{$period}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ========================
    // HELPER
    // ========================
    private function streamXlsx(string $filename, array $headers, $rows, string $sheetName)
    {
        return response()->streamDownload(function () use ($headers, $rows, $sheetName) {
            echo SimpleXlsxExporter::make($headers, $rows, $sheetName);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
