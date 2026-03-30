<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePayrollComponent;
use App\Models\PayrollGroup;
use App\Models\PayrollLog;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PayrollRunController extends Controller
{
    public function index()
    {
        $runs = PayrollRun::with(['payrollGroup', 'creator:id,full_name'])
            ->withCount('details')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $groups = PayrollGroup::where('is_active', true)->orderBy('name')->get();

        return view('admin.payroll-runs.index', compact('runs', 'groups'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'period' => 'required|date_format:Y-m',
            'payroll_group_id' => 'nullable|exists:payroll_groups,id',
        ]);

        $admin = Employee::find(session('admin_id'));

        $existing = PayrollRun::where('period', $request->period)
            ->where('payroll_group_id', $request->payroll_group_id)
            ->where('status', 'draft')
            ->first();

        if ($existing) {
            return redirect()->route('admin.payroll-runs.show', $existing->id)
                ->with('success', 'Payroll run sudah ada, melanjutkan draft.');
        }

        $run = PayrollRun::create([
            'period' => $request->period,
            'payroll_group_id' => $request->payroll_group_id,
            'created_by' => $admin->id,
        ]);

        $this->generateDetails($run);
        $this->logAction($run, 'created', $admin->id, 'Payroll run dibuat');

        return redirect()->route('admin.payroll-runs.show', $run->id)
            ->with('success', 'Payroll run berhasil dibuat.');
    }

    public function show($id)
    {
        $run = PayrollRun::with(['payrollGroup', 'creator:id,full_name', 'logs.performer:id,full_name'])->findOrFail($id);

        $details = PayrollRunDetail::where('payroll_run_id', $id)
            ->with(['employee:id,full_name,employee_code,department_id,position', 'employee.department:id,name'])
            ->orderBy('net_salary', 'desc')
            ->get();

        return view('admin.payroll-runs.show', compact('run', 'details'));
    }

    public function updateDetail(Request $request, $runId, $detailId)
    {
        $run = PayrollRun::findOrFail($runId);

        if ($run->status !== 'draft') {
            return back()->with('error', 'Payroll hanya bisa diedit saat status draft.');
        }

        $detail = PayrollRunDetail::where('payroll_run_id', $runId)->findOrFail($detailId);

        $request->validate([
            'components' => 'required|array',
            'components.*.name' => 'required|string',
            'components.*.type' => 'required|in:earning,deduction',
            'components.*.amount' => 'required|numeric|min:0',
        ]);

        $components = $request->components;
        $totalEarning = $detail->basic_salary;
        $totalDeduction = 0;

        foreach ($components as $comp) {
            if ($comp['type'] === 'earning') {
                $totalEarning += (float) $comp['amount'];
            } else {
                $totalDeduction += (float) $comp['amount'];
            }
        }

        $detail->update([
            'components' => $components,
            'total_earning' => $totalEarning,
            'total_deduction' => $totalDeduction,
            'net_salary' => $totalEarning - $totalDeduction,
            'is_manual_edited' => true,
        ]);

        $this->recalculateRunTotals($run);

        return back()->with('success', 'Detail payroll berhasil diperbarui.');
    }

    public function finalize($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));

        if ($run->status !== 'draft') {
            return back()->with('error', 'Hanya payroll draft yang bisa di-finalize.');
        }

        $run->update(['status' => 'finalized', 'finalized_at' => now()]);
        $this->logAction($run, 'finalized', $admin->id);

        return back()->with('success', 'Payroll berhasil di-finalize.');
    }

    public function publish($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));

        if ($run->status !== 'finalized') {
            return back()->with('error', 'Hanya payroll finalized yang bisa di-publish.');
        }

        $run->update(['status' => 'published', 'published_at' => now()]);
        $this->logAction($run, 'published', $admin->id);

        return back()->with('success', 'Payslip berhasil di-publish. Karyawan sekarang bisa melihat slip gaji.');
    }

    public function unpublish($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));

        if ($run->status !== 'published') {
            return back()->with('error', 'Hanya payroll published yang bisa di-unpublish.');
        }

        $run->update(['status' => 'finalized', 'published_at' => null]);
        $this->logAction($run, 'unpublished', $admin->id);

        return back()->with('success', 'Payslip berhasil di-unpublish.');
    }

    public function lock($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));

        if (!in_array($run->status, ['finalized', 'published'])) {
            return back()->with('error', 'Hanya payroll finalized/published yang bisa di-lock.');
        }

        $run->update(['status' => 'locked', 'locked_at' => now()]);
        $this->logAction($run, 'locked', $admin->id);

        return back()->with('success', 'Payroll berhasil di-lock. Data tidak bisa diubah.');
    }

    public function unlock($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));

        if ($run->status !== 'locked') {
            return back()->with('error', 'Hanya payroll locked yang bisa di-unlock.');
        }

        $run->update(['status' => 'finalized', 'locked_at' => null]);
        $this->logAction($run, 'unlocked', $admin->id);

        return back()->with('success', 'Payroll berhasil di-unlock.');
    }

    public function reopen($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));

        if ($run->status !== 'finalized') {
            return back()->with('error', 'Hanya payroll finalized yang bisa di-reopen.');
        }

        $run->update(['status' => 'draft', 'finalized_at' => null]);
        $this->logAction($run, 'reopened', $admin->id);

        return back()->with('success', 'Payroll di-reopen ke draft.');
    }

    public function regenerate($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));

        if ($run->status !== 'draft') {
            return back()->with('error', 'Hanya payroll draft yang bisa di-regenerate.');
        }

        $run->details()->delete();
        $this->generateDetails($run);
        $this->logAction($run, 'regenerated', $admin->id);

        return back()->with('success', 'Detail payroll berhasil di-regenerate.');
    }

    public function destroy($id)
    {
        $run = PayrollRun::findOrFail($id);

        if (in_array($run->status, ['published', 'locked'])) {
            return back()->with('error', 'Tidak bisa hapus payroll yang sudah published/locked.');
        }

        $run->details()->delete();
        $run->logs()->delete();
        $run->delete();

        return redirect()->route('admin.payroll-runs.index')->with('success', 'Payroll run berhasil dihapus.');
    }

    private function logAction(PayrollRun $run, string $action, int $performedBy, ?string $notes = null): void
    {
        PayrollLog::create([
            'payroll_run_id' => $run->id,
            'action' => $action,
            'performed_by' => $performedBy,
            'notes' => $notes,
        ]);
    }

    private function generateDetails(PayrollRun $run): void
    {
        $admin = Employee::find(session('admin_id'));

        $query = EmployeePayroll::where('is_active', true)
            ->whereHas('employee', function ($q) use ($admin) {
                $q->where('company_id', $admin->company_id)->where('is_active', true);
            });

        if ($run->payroll_group_id) {
            $query->where('payroll_group_id', $run->payroll_group_id);
        }

        $payrolls = $query->with('employee')->get();
        $periodDate = Carbon::parse($run->period . '-01');
        $periodStart = $periodDate->copy()->startOfMonth();
        $periodEnd = $periodDate->copy()->endOfMonth();

        // Collect holiday dates for the period
        $holidayDates = \App\Models\Holiday::where('company_id', $admin->company_id)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $totalDaysInMonth = $periodEnd->day;

        foreach ($payrolls as $payroll) {
            $empId = $payroll->employee_id;
            $employee = $payroll->employee;

            // === PRO-RATE: Join / Resign mid-period ===
            $workingDays = $totalDaysInMonth;
            $proRateReason = null;

            // Check join_date mid-month
            if ($employee->join_date) {
                $joinDate = Carbon::parse($employee->join_date);
                if ($joinDate->between($periodStart, $periodEnd) && $joinDate->day > 1) {
                    $workingDays = $periodEnd->day - $joinDate->day + 1;
                    $proRateReason = 'Join tgl ' . $joinDate->day;
                }
            }

            // Check resign_date mid-month
            if ($employee->resign_date) {
                $resignDate = Carbon::parse($employee->resign_date);
                if ($resignDate->between($periodStart, $periodEnd) && $resignDate->day < $periodEnd->day) {
                    $workingDays = $resignDate->day;
                    $proRateReason = ($proRateReason ? $proRateReason . ', ' : '') . 'Resign tgl ' . $resignDate->day;
                }
            }

            $proRateRatio = $workingDays / $totalDaysInMonth;

            // === SALARY REVISION MID-PERIOD ===
            $basicSalary = (float) $payroll->basic_salary;
            $salaryRevisionNote = null;

            // Check if there are multiple payroll records with different effective dates
            $allPayrolls = EmployeePayroll::where('employee_id', $empId)
                ->where('effective_date', '<=', $periodEnd)
                ->orderBy('effective_date', 'desc')
                ->take(2)
                ->get();

            if ($allPayrolls->count() > 1) {
                $currentPayroll = $allPayrolls[0];
                $previousPayroll = $allPayrolls[1];
                $effectiveDate = Carbon::parse($currentPayroll->effective_date);

                if ($effectiveDate->between($periodStart, $periodEnd) && $effectiveDate->day > 1) {
                    $daysOld = $effectiveDate->day - 1;
                    $daysNew = $totalDaysInMonth - $daysOld;
                    $oldSalary = (float) $previousPayroll->basic_salary;
                    $newSalary = (float) $currentPayroll->basic_salary;
                    $basicSalary = round((($oldSalary * $daysOld) + ($newSalary * $daysNew)) / $totalDaysInMonth, 0);
                    $salaryRevisionNote = "Revisi gaji tgl {$effectiveDate->day}: Rp " . number_format($oldSalary, 0, ',', '.') . " → Rp " . number_format($newSalary, 0, ',', '.');
                }
            }

            // Apply pro-rate to basic salary
            $proratedBasic = round($basicSalary * $proRateRatio, 0);

            // 1. Get manual components assigned to this employee
            $empComponents = EmployeePayrollComponent::where('employee_id', $empId)
                ->where('is_active', true)
                ->where('start_date', '<=', $periodEnd)
                ->where(function ($q) use ($periodStart) {
                    $q->whereNull('end_date')
                      ->orWhere('end_date', '>=', $periodStart);
                })
                ->with('component')
                ->get();

            $components = [];
            $totalEarning = $proratedBasic;
            $totalDeduction = 0;

            // Add pro-rate info component if applicable
            if ($proRateRatio < 1) {
                $components[] = [
                    'id' => null,
                    'name' => 'Pro-Rate Gaji',
                    'type' => 'info',
                    'category' => 'info',
                    'amount' => 0,
                    'is_taxable' => false,
                    'is_auto' => true,
                    'detail' => "{$workingDays}/{$totalDaysInMonth} hari ({$proRateReason})",
                ];
            }
            if ($salaryRevisionNote) {
                $components[] = [
                    'id' => null,
                    'name' => 'Revisi Gaji',
                    'type' => 'info',
                    'category' => 'info',
                    'amount' => 0,
                    'is_taxable' => false,
                    'is_auto' => true,
                    'detail' => $salaryRevisionNote,
                ];
            }

            // Process manual (non-auto) components (also pro-rated if applicable)
            foreach ($empComponents as $ec) {
                $comp = $ec->component;
                if ($comp->is_auto) continue;

                $amount = round((float) $ec->amount * $proRateRatio, 0);
                $components[] = [
                    'id' => $comp->id,
                    'name' => $comp->name,
                    'type' => $comp->type,
                    'category' => $comp->category,
                    'amount' => $amount,
                    'is_taxable' => $comp->is_taxable,
                    'is_auto' => false,
                ];

                if ($comp->type === 'earning') {
                    $totalEarning += $amount;
                } else {
                    $totalDeduction += $amount;
                }
            }

            // 2. Auto-calculate: Keterlambatan & Alpha (skip if exempt)
            if (!$payroll->is_exempt_penalty) {
                $latePenalty = (float) ($payroll->late_penalty_per_day ?? 50000);

                if ($latePenalty > 0) {
                    $lateData = $this->calculateLatePenalty($empId, $periodStart, $periodEnd, $holidayDates, $latePenalty);
                    if ($lateData['days'] > 0) {
                        $components[] = [
                            'id' => null,
                            'name' => 'Potongan Keterlambatan',
                            'type' => 'deduction',
                            'category' => 'recurring',
                            'amount' => $lateData['amount'],
                            'is_taxable' => false,
                            'is_auto' => true,
                            'detail' => $lateData['days'] . ' hari × Rp ' . number_format($latePenalty, 0, ',', '.'),
                        ];
                        $totalDeduction += $lateData['amount'];
                    }
                }

                // Alpha
                $alphaData = $this->calculateAlphaPenalty($empId, $periodStart, $periodEnd, $holidayDates, $payroll->basic_salary);
                if ($alphaData['days'] > 0) {
                    $components[] = [
                        'id' => null,
                        'name' => 'Potongan Alpha',
                        'type' => 'deduction',
                        'category' => 'recurring',
                        'amount' => $alphaData['amount'],
                        'is_taxable' => false,
                        'is_auto' => true,
                        'detail' => $alphaData['days'] . ' hari × Rp ' . number_format($alphaData['per_day'], 0, ',', '.'),
                    ];
                    $totalDeduction += $alphaData['amount'];
                }
            }

            // 3. Auto-calculate: Lembur
            $overtimeMultiplier = (float) ($payroll->overtime_multiplier ?? 1);
            if ($overtimeMultiplier > 0) {
                $overtimeData = $this->calculateOvertime($empId, $periodStart, $periodEnd, $holidayDates, $payroll->basic_salary, $overtimeMultiplier);
                if ($overtimeData['total_amount'] > 0) {
                    $components[] = [
                        'id' => null,
                        'name' => 'Lembur',
                        'type' => 'earning',
                        'category' => 'recurring',
                        'amount' => $overtimeData['total_amount'],
                        'is_taxable' => true,
                        'is_auto' => true,
                        'detail' => $overtimeData['detail'],
                    ];
                    $totalEarning += $overtimeData['total_amount'];
                }
            }

            // 4. Apply pending adjustments for this period
            $pendingAdjustments = \App\Models\PayrollAdjustment::where('employee_id', $empId)
                ->where('target_period', $run->period)
                ->where('status', 'pending')
                ->get();

            foreach ($pendingAdjustments as $adj) {
                $components[] = [
                    'id' => null,
                    'name' => ucfirst($adj->type) . ': ' . $adj->name,
                    'type' => $adj->earning_type,
                    'category' => 'one-time',
                    'amount' => (float) $adj->amount,
                    'is_taxable' => $adj->earning_type === 'earning',
                    'is_auto' => true,
                    'detail' => $adj->notes,
                ];

                if ($adj->earning_type === 'earning') {
                    $totalEarning += (float) $adj->amount;
                } else {
                    $totalDeduction += (float) $adj->amount;
                }

                $adj->update(['status' => 'applied', 'payroll_run_id' => $run->id]);
            }

            // 5. Auto-calculate: BPJS
            $bpjsCalc = new \App\Services\BpjsCalculator($periodStart->format('Y-m-d'));
            $bpjs = $bpjsCalc->calculate((float) $payroll->basic_salary);

            // BPJS Employee portion = deduction
            if ($bpjs['employee_total'] > 0) {
                $components[] = [
                    'id' => null,
                    'name' => 'BPJS (Karyawan)',
                    'type' => 'deduction',
                    'category' => 'recurring',
                    'amount' => $bpjs['employee_total'],
                    'is_taxable' => false,
                    'is_auto' => true,
                    'detail' => 'JHT ' . number_format($bpjs['jht']['employee'], 0, ',', '.') .
                                ' + Kes ' . number_format($bpjs['kesehatan']['employee'], 0, ',', '.') .
                                ' + JP ' . number_format($bpjs['jp']['employee'], 0, ',', '.'),
                ];
                $totalDeduction += $bpjs['employee_total'];
            }

            // BPJS Company portion = info only (not deducted from employee)
            if ($bpjs['company_total'] > 0) {
                $components[] = [
                    'id' => null,
                    'name' => 'BPJS (Perusahaan)',
                    'type' => 'info',
                    'category' => 'info',
                    'amount' => $bpjs['company_total'],
                    'is_taxable' => false,
                    'is_auto' => true,
                    'detail' => 'JHT ' . number_format($bpjs['jht']['company'], 0, ',', '.') .
                                ' + Kes ' . number_format($bpjs['kesehatan']['company'], 0, ',', '.') .
                                ' + JKK ' . number_format($bpjs['jkk']['company'], 0, ',', '.') .
                                ' + JKM ' . number_format($bpjs['jkm']['company'], 0, ',', '.') .
                                ' + JP ' . number_format($bpjs['jp']['company'], 0, ',', '.'),
                ];
            }

            // 6. Auto-calculate: PPh 21
            $ptkpStatus = $payroll->employee->ptkp_status ?? $payroll->ptkp_status ?? 'TK/0';
            if (!$ptkpStatus) $ptkpStatus = 'TK/0';
            $taxMethod = $payroll->tax_method ?? 'gross_up';

            $pph21Calc = new \App\Services\Pph21Calculator($periodStart->format('Y-m-d'));
            $tax = $pph21Calc->calculateMonthly($totalEarning, $ptkpStatus, $taxMethod, $bpjs['employee_total']);

            // Gross-up: add tunjangan pajak as earning
            if ($taxMethod === 'gross_up' && $tax['tunjangan_pajak'] > 0) {
                $components[] = [
                    'id' => null,
                    'name' => 'Tunjangan Pajak (Gross Up)',
                    'type' => 'earning',
                    'category' => 'recurring',
                    'amount' => $tax['tunjangan_pajak'],
                    'is_taxable' => true,
                    'is_auto' => true,
                    'detail' => 'PPh 21 ditanggung perusahaan',
                ];
                $totalEarning += $tax['tunjangan_pajak'];
            }

            // PPh 21 deduction (not for nett method)
            if ($tax['pph21_deduction'] > 0) {
                $isPph21Dtp = $payroll->pph21_dtp ?? false;
                $components[] = [
                    'id' => null,
                    'name' => 'PPh 21' . ($isPph21Dtp ? ' (DTP)' : ''),
                    'type' => $isPph21Dtp ? 'info' : 'deduction',
                    'category' => 'recurring',
                    'amount' => $tax['pph21_deduction'],
                    'is_taxable' => false,
                    'is_auto' => true,
                    'detail' => "Metode: {$taxMethod}, PTKP: {$ptkpStatus}, PKP: Rp " . number_format($tax['pkp'], 0, ',', '.'),
                ];
                if (!$isPph21Dtp) {
                    $totalDeduction += $tax['pph21_deduction'];
                }
            }

            PayrollRunDetail::create([
                'payroll_run_id' => $run->id,
                'employee_id' => $empId,
                'basic_salary' => $proratedBasic,
                'total_earning' => $totalEarning,
                'total_deduction' => $totalDeduction,
                'net_salary' => $totalEarning - $totalDeduction,
                'components' => $components,
            ]);
        }

        $this->recalculateRunTotals($run);
    }

    /**
     * Hitung potongan keterlambatan: Rp 50.000 per hari terlambat
     * Exclude hari libur dan cuti
     */
    private function calculateLatePenalty(int $empId, $periodStart, $periodEnd, array $holidayDates, float $penaltyPerDay = 50000): array
    {

        // Get approved leave dates to exclude
        $leaveDates = $this->getApprovedLeaveDates($empId, $periodStart, $periodEnd);

        // Count late days from attendance
        $lateDays = \App\Models\Attendance::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_late', true)
            ->where('status', 'present')
            ->whereNotIn('date', $holidayDates)
            ->whereNotIn('date', $leaveDates)
            ->count();

        return [
            'days' => $lateDays,
            'amount' => $lateDays * $penaltyPerDay,
        ];
    }

    /**
     * Hitung potongan alpha: (gaji pokok / 30) per hari absent
     * Exclude hari libur, cuti, dan hari off shift
     */
    private function calculateAlphaPenalty(int $empId, $periodStart, $periodEnd, array $holidayDates, float $basicSalary): array
    {
        $perDay = round($basicSalary / 30, 0);
        $leaveDates = $this->getApprovedLeaveDates($empId, $periodStart, $periodEnd);

        // Get off-shift dates
        $offDates = \App\Models\ScheduleAssignment::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereHas('shift', fn($q) => $q->where('is_off', true))
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $absentDays = \App\Models\Attendance::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('status', 'absent')
            ->whereNotIn('date', $holidayDates)
            ->whereNotIn('date', $leaveDates)
            ->whereNotIn('date', $offDates)
            ->count();

        return [
            'days' => $absentDays,
            'amount' => $absentDays * $perDay,
            'per_day' => $perDay,
        ];
    }

    /**
     * Hitung lembur:
     * - Hari biasa: 1/173 × gaji pokok per jam
     * - Hari libur/off shift: 2/173 × gaji pokok per jam (×2)
     */
    private function calculateOvertime(int $empId, $periodStart, $periodEnd, array $holidayDates, float $basicSalary, float $multiplier = 1): array
    {
        $hourlyRate = round(($basicSalary / 173) * $multiplier, 0); // UU: 1/173

        $overtimes = \App\Models\OvertimeRequest::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('status', 'approved')
            ->get();

        if ($overtimes->isEmpty()) {
            return ['total_amount' => 0, 'detail' => ''];
        }

        // Get off-shift dates in period
        $offDates = \App\Models\ScheduleAssignment::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereHas('shift', fn($q) => $q->where('is_off', true))
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $totalAmount = 0;
        $normalHours = 0;
        $holidayHours = 0;

        foreach ($overtimes as $ot) {
            $hours = round($ot->total_duration / 60, 1);
            $dateStr = Carbon::parse($ot->date)->format('Y-m-d');

            // Check if it's a holiday or off-shift day
            $isHoliday = in_array($dateStr, $holidayDates) || in_array($dateStr, $offDates);

            if ($isHoliday) {
                $totalAmount += $hours * $hourlyRate * 2;
                $holidayHours += $hours;
            } else {
                $totalAmount += $hours * $hourlyRate;
                $normalHours += $hours;
            }
        }

        $detail = '';
        if ($normalHours > 0) {
            $detail .= $normalHours . ' jam biasa (× Rp ' . number_format($hourlyRate, 0, ',', '.') . ')';
        }
        if ($holidayHours > 0) {
            if ($detail) $detail .= ', ';
            $detail .= $holidayHours . ' jam libur (× Rp ' . number_format($hourlyRate * 2, 0, ',', '.') . ')';
        }

        return [
            'total_amount' => round($totalAmount, 0),
            'detail' => $detail,
        ];
    }

    /**
     * Get approved leave dates for an employee in a period
     */
    private function getApprovedLeaveDates(int $empId, $periodStart, $periodEnd): array
    {
        $leaves = \App\Models\LeaveRequest::where('employee_id', $empId)
            ->where('status', 'approved')
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get();

        $dates = [];
        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->start_date)->max($periodStart);
            $end = Carbon::parse($leave->end_date)->min($periodEnd);
            while ($start->lte($end)) {
                $dates[] = $start->format('Y-m-d');
                $start->addDay();
            }
        }
        return $dates;
    }

    private function recalculateRunTotals(PayrollRun $run): void
    {
        $run->update([
            'total_earning' => $run->details()->sum('total_earning'),
            'total_deduction' => $run->details()->sum('total_deduction'),
            'total_net' => $run->details()->sum('net_salary'),
        ]);
    }
}
