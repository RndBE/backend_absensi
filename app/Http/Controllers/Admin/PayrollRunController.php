<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendPayslipEmailJob;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePayroll;
use App\Models\EmployeePayrollComponent;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LoanRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollLog;
use App\Models\PayrollRun;
use App\Models\PayrollRunDetail;
use App\Models\ScheduleAssignment;
use App\Services\BpjsCalculator;
use App\Services\Pph21Calculator;
use App\Support\LoanPayrollComponentSync;
use App\Support\ScheduledWorkingDays;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayrollRunController extends Controller
{
    private const ALPHA_PENALTY_PER_DAY = 100000;

    public function index()
    {
        $runs = PayrollRun::with(['creator:id,full_name'])
            ->withCount('details')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        $admin = Employee::find(session('admin_id'));

        // Karyawan untuk picker: yang aktif (punya payroll aktif), DAN yang sudah resign
        // (payroll-nya sudah nonaktif tapi tetap punya data payroll) agar bisa dipilih untuk
        // payroll bulan mereka resign. Filter per-periode dilakukan di sisi klien.
        $employees = Employee::where('company_id', $admin->company_id)
            ->where(function ($q) {
                $q->where(function ($active) {
                    $active->where('is_active', true)->whereHas('activePayroll');
                })->orWhere(function ($resigned) {
                    $resigned->whereNotNull('resign_date')->whereHas('payroll');
                });
            })
            ->with(['department:id,name'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_code', 'department_id', 'is_active', 'resign_date', 'last_working_date']);

        return view('admin.payroll-runs.index', compact('runs', 'employees'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'period' => 'required|date_format:Y-m',
            'employee_ids' => 'required|array|min:1',
            'employee_ids.*' => 'exists:employees,id',
        ]);

        $admin = Employee::find(session('admin_id'));

        $run = PayrollRun::create([
            'period' => $request->period,
            'created_by' => $admin->id,
        ]);

        $this->generateDetails($run, $request->employee_ids);
        $this->logAction($run, 'created', $admin->id, 'Payroll run dibuat untuk '.count($request->employee_ids).' karyawan');

        return redirect()->route('admin.payroll-runs.show', $run->id)
            ->with('success', 'Payroll run berhasil dibuat untuk '.count($request->employee_ids).' karyawan.');
    }

    public function show($id)
    {
        $run = PayrollRun::with(['creator:id,full_name', 'logs.performer:id,full_name'])->findOrFail($id);

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
            'components.*.type' => 'required|in:earning,deduction,info',
            'components.*.amount' => 'required|numeric|min:0',
        ]);

        $components = $request->components;
        $totalEarning = $detail->basic_salary;
        $totalDeduction = 0;

        foreach ($components as $comp) {
            if ($comp['type'] === 'earning') {
                $totalEarning += (float) $comp['amount'];
            } elseif ($comp['type'] === 'deduction') {
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
        $this->applyLoanDeductions($run);
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
        $this->queuePayslipEmails($run);

        return back()->with('success', 'Payslip berhasil di-publish. Email slip gaji sedang dikirim ke karyawan.');
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

        if (! in_array($run->status, ['finalized', 'published'])) {
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

        // Get existing employee IDs from current details before deleting
        $employeeIds = $run->details()->pluck('employee_id')->toArray();

        $run->details()->delete();
        $this->generateDetails($run, $employeeIds);
        $this->logAction($run, 'regenerated', $admin->id);

        return back()->with('success', 'Detail payroll berhasil di-regenerate.');
    }

    public function injectBpjs($id)
    {
        $run = PayrollRun::findOrFail($id);
        $admin = Employee::find(session('admin_id'));
        $periodDate = Carbon::parse($run->period.'-01');
        $periodStart = $periodDate->copy()->startOfMonth();

        $details = $run->details()->with('employee.activePayroll')->get();
        $injected = 0;

        foreach ($details as $detail) {
            $payroll = $detail->employee?->activePayroll;
            if (! $payroll) {
                continue;
            }

            $comps = is_array($detail->components) ? $detail->components : json_decode($detail->components, true) ?? [];

            // Remove ALL existing BPJS components (both old combined and new split)
            $comps = array_values(array_filter($comps, function ($c) {
                $name = $c['name'] ?? '';

                return ! str_contains($name, 'BPJS')
                    && ! in_array($name, ['JHT Karyawan', 'JHT Perusahaan', 'JKK Perusahaan', 'JKM Perusahaan', 'JP Karyawan', 'JP Perusahaan'], true);
            }));

            // Recalculate totals from remaining non-BPJS components
            $totalEarning = (float) $detail->basic_salary;
            $totalDeduction = 0;
            foreach ($comps as $c) {
                if (($c['type'] ?? '') === 'earning') {
                    $totalEarning += (float) ($c['amount'] ?? 0);
                } elseif (($c['type'] ?? '') === 'deduction') {
                    $totalDeduction += (float) ($c['amount'] ?? 0);
                }
            }

            // Calculate fresh BPJS
            $bpjsCalc = new BpjsCalculator($periodStart->format('Y-m-d'));
            $bpjs = $bpjsCalc->calculate((float) $payroll->basic_salary);
            $bpjs = $this->filterBpjsByRegistration($payroll, $bpjs);

            // ── BPJS Karyawan: tiap program jadi baris terpisah ──
            if ($bpjs['kesehatan']['employee'] > 0) {
                $comps[] = ['id' => null, 'name' => 'BPJS Kesehatan', 'type' => 'deduction', 'category' => 'recurring',
                    'amount' => $bpjs['kesehatan']['employee'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '1% x Rp '.number_format($bpjs['kesehatan']['basis'], 0, ',', '.')];
                $totalDeduction += $bpjs['kesehatan']['employee'];
            }
            if ($bpjs['jht']['employee'] > 0) {
                $comps[] = ['id' => null, 'name' => 'JHT Karyawan', 'type' => 'deduction', 'category' => 'recurring',
                    'amount' => $bpjs['jht']['employee'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '2% x Rp '.number_format($bpjs['jht']['basis'], 0, ',', '.')];
                $totalDeduction += $bpjs['jht']['employee'];
            }
            if ($bpjs['jp']['employee'] > 0) {
                $comps[] = ['id' => null, 'name' => 'JP Karyawan', 'type' => 'deduction', 'category' => 'recurring',
                    'amount' => $bpjs['jp']['employee'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '1% x Rp '.number_format($bpjs['jp']['basis'], 0, ',', '.')];
                $totalDeduction += $bpjs['jp']['employee'];
            }

            // ── BPJS Perusahaan: tiap program jadi baris terpisah (info only) ──
            if ($bpjs['kesehatan']['company'] > 0) {
                $comps[] = ['id' => null, 'name' => 'BPJS Kesehatan Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['kesehatan']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '4% x Rp '.number_format($bpjs['kesehatan']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jht']['company'] > 0) {
                $comps[] = ['id' => null, 'name' => 'JHT Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jht']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '3.7% x Rp '.number_format($bpjs['jht']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jkk']['company'] > 0) {
                $comps[] = ['id' => null, 'name' => 'JKK Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jkk']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '0.24% x Rp '.number_format($bpjs['jkk']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jkm']['company'] > 0) {
                $comps[] = ['id' => null, 'name' => 'JKM Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jkm']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '0.3% x Rp '.number_format($bpjs['jkm']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jp']['company'] > 0) {
                $comps[] = ['id' => null, 'name' => 'JP Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jp']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '2% x Rp '.number_format($bpjs['jp']['basis'], 0, ',', '.')];
            }

            $detail->update([
                'components' => $comps,
                'total_earning' => $totalEarning,
                'total_deduction' => $totalDeduction,
                'net_salary' => $totalEarning - $totalDeduction,
            ]);

            $injected++;
        }

        $this->recalculateRunTotals($run);
        $this->logAction($run, 'inject_bpjs', $admin->id, "BPJS diinjeksi ke {$injected} karyawan");

        return back()->with('success', "BPJS berhasil diinjeksi ke {$injected} karyawan.");
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

    private function queuePayslipEmails(PayrollRun $run): void
    {
        PayrollRunDetail::with('employee:id,email')
            ->where('payroll_run_id', $run->id)
            ->get()
            ->filter(fn (PayrollRunDetail $detail) => filled($detail->employee?->email))
            ->each(fn (PayrollRunDetail $detail) => SendPayslipEmailJob::dispatch($detail->id));
    }

    private function generateDetails(PayrollRun $run, array $employeeIds = []): void
    {
        $admin = Employee::find(session('admin_id'));

        $periodDate = Carbon::parse($run->period.'-01');
        $periodStart = $periodDate->copy()->startOfMonth();
        $periodEnd = $periodDate->copy()->endOfMonth();

        // Acuan keluar karyawan = hari kerja terakhir (fallback ke tanggal resign bila kosong).
        $exitInPeriod = fn ($q) => $q->whereRaw(
            'COALESCE(last_working_date, resign_date) BETWEEN ? AND ?',
            [$periodStart->toDateString(), $periodEnd->toDateString()]
        );

        // Get payrolls for selected employees.
        // Sertakan juga karyawan yang resign di periode ini agar payroll bulan terakhirnya
        // tetap tergenerate — meski saat resign EmployeePayroll-nya sudah dinonaktifkan.
        $query = EmployeePayroll::whereHas('employee', function ($q) use ($admin, $exitInPeriod) {
                $q->where('company_id', $admin->company_id)
                    ->where(function ($sub) use ($exitInPeriod) {
                        $sub->where('is_active', true)->orWhere($exitInPeriod);
                    });
            })
            ->where(function ($q) use ($exitInPeriod) {
                // Payroll aktif, ATAU payroll (nonaktif) milik karyawan yang keluar di periode ini.
                $q->where('is_active', true)
                    ->orWhereHas('employee', $exitInPeriod);
            });

        if (! empty($employeeIds)) {
            $query->whereIn('employee_id', $employeeIds);
        }

        // Satu payroll per karyawan: utamakan yang aktif, lalu effective_date terbaru.
        $payrolls = $query->with('employee')->get()
            ->sortByDesc('effective_date')
            ->sortByDesc(fn ($p) => $p->is_active ? 1 : 0)
            ->unique('employee_id')
            ->values();

        // Collect holiday dates for the period
        $holidayDates = Holiday::where('company_id', $admin->company_id)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $dailyReportLateCounts = $this->fetchDailyReportLateCounts(
            $payrolls->pluck('employee.email'),
            $periodStart,
            $periodEnd
        );

        $totalDaysInMonth = $periodEnd->day;

        foreach ($payrolls as $payroll) {
            $empId = $payroll->employee_id;
            $employee = $payroll->employee;

            // === PRO-RATE: Join / Resign mid-period (berbasis HARI KERJA TERJADWAL) ===
            // Rasio = hari kerja terjadwal yang dijalani / total hari kerja terjadwal bulan itu.
            $proRateReason = null;
            $effectiveStart = $periodStart->copy();
            $effectiveEnd = $periodEnd->copy();
            $prorated = false;

            // Join mid-month → mulai dihitung dari tanggal join.
            if ($employee->join_date) {
                $joinDate = Carbon::parse($employee->join_date);
                if ($joinDate->between($periodStart, $periodEnd) && $joinDate->gt($periodStart)) {
                    $effectiveStart = $joinDate->copy();
                    $proRateReason = 'Join '.$joinDate->format('d/m');
                    $prorated = true;
                }
            }

            // Keluar mid-month — pakai hari kerja terakhir (fallback tanggal resign).
            $exitDateRaw = $employee->last_working_date ?: $employee->resign_date;
            if ($exitDateRaw) {
                $exitDate = Carbon::parse($exitDateRaw);
                if ($exitDate->between($periodStart, $periodEnd) && $exitDate->lt($periodEnd)) {
                    $effectiveEnd = $exitDate->copy();
                    $proRateReason = ($proRateReason ? $proRateReason.', ' : '').'Kerja s/d '.$exitDate->format('d/m');
                    $prorated = true;
                }
            }

            // Pembagi = hari kerja SEBULAN PENUH berbasis pola hari kerja mingguan.
            // Hari libur nasional dihitung kerja HANYA bila karyawan memang ada shift di
            // hari libur itu (mis. security). Kalau tidak, libur tidak dihitung.
            // Untuk assignment-only, pola mingguan + kebiasaan kerja-saat-libur disimpulkan
            // dari jadwalnya lalu diterapkan sebulan penuh.
            // Tanpa jadwal sama sekali → 0, fallback ke hari kalender.
            $scheduledDaysInMonth = ScheduledWorkingDays::monthlyWorkingDays($employee, $periodStart, $periodEnd, $holidayDates);
            $usesSchedule = $scheduledDaysInMonth > 0;
            $totalDaysRef = $usesSchedule ? $scheduledDaysInMonth : $totalDaysInMonth;

            if ($prorated) {
                // Pembilang = hari kerja terjadwal yang dijalani (lompati OFF; libur yang ada
                // shift-nya tetap dihitung kerja karena override menang atas libur).
                $workedDays = $usesSchedule
                    ? ScheduledWorkingDays::count($employee, $effectiveStart, $effectiveEnd, $holidayDates)
                    : ($effectiveStart->copy()->startOfDay()->diffInDays($effectiveEnd->copy()->startOfDay()) + 1);
                $proRateRatio = $totalDaysRef > 0 ? min(1, $workedDays / $totalDaysRef) : 1;
            } else {
                $workedDays = $totalDaysRef;
                $proRateRatio = 1;
            }

            $proRateDayLabel = $usesSchedule ? 'hari kerja' : 'hari';

            // === SALARY REVISION MID-PERIOD ===
            $basicSalary = (float) $payroll->basic_salary;
            $salaryRevisionNote = null;

            // Ambil 2 record gaji terbaru yang VALID (basic_salary > 0). Record gaji 0
            // (placeholder/invalid, mis. terbentuk saat karyawan baru dibuat) diabaikan
            // agar tidak salah dianggap "revisi gaji" dan mencampur nilai jadi lebih kecil.
            $allPayrolls = EmployeePayroll::where('employee_id', $empId)
                ->where('effective_date', '<=', $periodEnd)
                ->where('basic_salary', '>', 0)
                ->orderBy('effective_date', 'desc')
                ->take(2)
                ->get();

            // Kalau gaji utama 0 (record aktif ternyata placeholder), pakai gaji valid terbaru.
            if ($basicSalary <= 0 && $allPayrolls->isNotEmpty()) {
                $basicSalary = (float) $allPayrolls[0]->basic_salary;
            }

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
                    $salaryRevisionNote = "Revisi gaji tgl {$effectiveDate->day}: Rp ".number_format($oldSalary, 0, ',', '.').' → Rp '.number_format($newSalary, 0, ',', '.');
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
                    'detail' => "{$workedDays}/{$totalDaysRef} {$proRateDayLabel} ({$proRateReason})",
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
                if ($comp->is_auto) {
                    continue;
                }

                if (LoanPayrollComponentSync::isLoanComponentName($comp->name)) {
                    continue;
                }

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
            if (! $payroll->is_exempt_penalty) {
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
                            'detail' => $lateData['days'].' hari × Rp '.number_format($latePenalty, 0, ',', '.'),
                        ];
                        $totalDeduction += $lateData['amount'];
                    }
                }

                $disciplineLateDays = (int) ($dailyReportLateCounts[strtolower((string) $employee->email)] ?? 0);
                if ($disciplineLateDays > 0 && $latePenalty > 0) {
                    $disciplineComponent = $this->buildDisciplinePenaltyComponent($disciplineLateDays, $latePenalty);
                    $components[] = $disciplineComponent;
                    $totalDeduction += $disciplineComponent['amount'];
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
                        'detail' => $alphaData['days'].' hari × Rp '.number_format($alphaData['per_day'], 0, ',', '.'),
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
            $pendingAdjustments = PayrollAdjustment::where('employee_id', $empId)
                ->where('target_period', $run->period)
                ->where('status', 'pending')
                ->get();

            foreach ($pendingAdjustments as $adj) {
                $components[] = [
                    'id' => null,
                    'name' => ucfirst($adj->type).': '.$adj->name,
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

            // 5. Auto-calculate: Pinjaman karyawan
            foreach ($this->loanDeductionComponents($empId, $run->period) as $loanComponent) {
                $components[] = $loanComponent;
                $totalDeduction += (float) $loanComponent['amount'];
            }

            // 6. Auto-calculate: BPJS (tiap program jadi komponen terpisah)
            $bpjsCalc = new BpjsCalculator($periodStart->format('Y-m-d'));
            $bpjs = $bpjsCalc->calculate((float) $payroll->basic_salary);
            $bpjs = $this->filterBpjsByRegistration($payroll, $bpjs);

            // BPJS Karyawan — masing-masing program sebagai deduction terpisah
            if ($bpjs['kesehatan']['employee'] > 0) {
                $components[] = ['id' => null, 'name' => 'BPJS Kesehatan', 'type' => 'deduction', 'category' => 'recurring',
                    'amount' => $bpjs['kesehatan']['employee'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '1% x Rp '.number_format($bpjs['kesehatan']['basis'], 0, ',', '.')];
                $totalDeduction += $bpjs['kesehatan']['employee'];
            }
            if ($bpjs['jht']['employee'] > 0) {
                $components[] = ['id' => null, 'name' => 'JHT Karyawan', 'type' => 'deduction', 'category' => 'recurring',
                    'amount' => $bpjs['jht']['employee'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '2% x Rp '.number_format($bpjs['jht']['basis'], 0, ',', '.')];
                $totalDeduction += $bpjs['jht']['employee'];
            }
            if ($bpjs['jp']['employee'] > 0) {
                $components[] = ['id' => null, 'name' => 'JP Karyawan', 'type' => 'deduction', 'category' => 'recurring',
                    'amount' => $bpjs['jp']['employee'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '1% x Rp '.number_format($bpjs['jp']['basis'], 0, ',', '.')];
                $totalDeduction += $bpjs['jp']['employee'];
            }

            // BPJS Perusahaan — masing-masing program sebagai info terpisah
            if ($bpjs['kesehatan']['company'] > 0) {
                $components[] = ['id' => null, 'name' => 'BPJS Kesehatan Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['kesehatan']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '4% x Rp '.number_format($bpjs['kesehatan']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jht']['company'] > 0) {
                $components[] = ['id' => null, 'name' => 'JHT Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jht']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '3.7% x Rp '.number_format($bpjs['jht']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jkk']['company'] > 0) {
                $components[] = ['id' => null, 'name' => 'JKK Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jkk']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '0.24% x Rp '.number_format($bpjs['jkk']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jkm']['company'] > 0) {
                $components[] = ['id' => null, 'name' => 'JKM Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jkm']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '0.3% x Rp '.number_format($bpjs['jkm']['basis'], 0, ',', '.')];
            }
            if ($bpjs['jp']['company'] > 0) {
                $components[] = ['id' => null, 'name' => 'JP Perusahaan', 'type' => 'info', 'category' => 'info',
                    'amount' => $bpjs['jp']['company'], 'is_taxable' => false, 'is_auto' => true,
                    'detail' => '2% x Rp '.number_format($bpjs['jp']['basis'], 0, ',', '.')];
            }

            // 7. Auto-calculate: PPh 21
            $ptkpStatus = $payroll->employee->ptkp_status ?? $payroll->ptkp_status ?? 'TK/0';
            if (! $ptkpStatus) {
                $ptkpStatus = 'TK/0';
            }
            $taxMethod = $payroll->tax_method ?? 'gross_up';

            $pph21Calc = new Pph21Calculator($periodStart->format('Y-m-d'));
            $isDecember = ($periodDate->month === 12);

            // Deteksi bulan terakhir karyawan (keluar di periode ini) — pakai hari kerja
            // terakhir, fallback ke tanggal resign.
            $exitDateForTax = $employee->last_working_date ?: $employee->resign_date;
            $resignDate = $exitDateForTax ? Carbon::parse($exitDateForTax) : null;
            $isResignMonth = $resignDate && $resignDate->between($periodStart, $periodEnd);

            // Bruto dasar PPh 21: gaji pokok + earning taxable + premi pemberi
            // kerja yang objek pajak (JKK, JKM, BPJS Kesehatan 4%). Tidak sama
            // dengan total_earning (premi pemberi kerja bukan penghasilan tunai,
            // tapi tetap objek PPh; earning non-taxable dikecualikan dari pajak).
            $brutoTaxable = Pph21Calculator::taxableBrutoFromComponents($proratedBasic, $components);

            if ($isDecember) {
                // ── Desember: Penghitungan Kembali berdasarkan penghasilan sebenarnya ──
                // Ambil akumulasi bruto & PPh21 Jan-Nov dari payroll run detail tahun ini
                $year = $periodDate->year;
                $prevDetails = PayrollRunDetail::whereHas('payrollRun', function ($q) use ($year) {
                    $q->whereYear('period', $year)
                        ->whereMonth('period', '<=', 11) // Jan-Nov only
                        ->where('status', '!=', 'draft');
                })->where('employee_id', $empId)->get();

                $brutoJanToNov = 0;
                $taxJanToNov = 0;
                foreach ($prevDetails as $pd) {
                    $comps = is_array($pd->components) ? $pd->components : json_decode($pd->components, true) ?? [];
                    // Bruto pajak Jan-Nov = dasar PPh (termasuk premi pemberi kerja
                    // yang objek pajak), bukan total_earning tunai.
                    $brutoJanToNov += Pph21Calculator::taxableBrutoFromComponents((float) $pd->basic_salary, $comps);
                    // Sum PPh21 deduction components
                    foreach ($comps as $c) {
                        if (str_contains($c['name'] ?? '', 'PPh') && ($c['type'] ?? '') === 'deduction') {
                            $taxJanToNov += (float) ($c['amount'] ?? 0);
                        }
                    }
                }

                $tax = $pph21Calc->calculateDecember(
                    brutoDecember      : $brutoTaxable,
                    brutoJanToNov      : $brutoJanToNov,
                    bpjsEmployeeMonthly: $bpjs['employee_total'],
                    ptkpStatus         : $ptkpStatus,
                    taxMethod          : $taxMethod,
                    taxJanToNov        : $taxJanToNov
                );
                $detailNote = "Desember — Penghitungan Kembali Tahunan\n"
                            .'Bruto Jan-Nov: Rp '.number_format($brutoJanToNov, 0, ',', '.').' | '
                            .'Pajak Jan-Nov: Rp '.number_format($taxJanToNov, 0, ',', '.').' | '
                            .'PKP Aktual: Rp '.number_format($tax['pkp'], 0, ',', '.');
            } elseif ($isResignMonth) {
                // ── Bulan terakhir karyawan resign: penghitungan progresif (PMK-168/2023) ──
                // Disetahunkan berdasarkan jumlah bulan bekerja tahun ini, lalu dikurangi
                // PPh21 yang sudah dipotong bulan-bulan sebelumnya (true-up masa pajak terakhir).
                $year = $periodDate->year;
                $joinDate = $employee->join_date ? Carbon::parse($employee->join_date) : null;
                $firstMonth = ($joinDate && $joinDate->year === $year) ? $joinDate->month : 1;
                $monthsWorked = max(1, $periodDate->month - $firstMonth + 1);

                // Akumulasi PPh21 yang sudah dipotong bulan-bulan sebelumnya tahun ini
                $prevDetails = PayrollRunDetail::whereHas('payrollRun', function ($q) use ($year, $periodDate) {
                    $q->where('period', 'like', $year.'-%')
                        ->where('period', '<', $periodDate->format('Y-m'))
                        ->where('status', '!=', 'draft');
                })->where('employee_id', $empId)->get();

                $taxAlreadyPaid = 0;
                foreach ($prevDetails as $pd) {
                    $comps = is_array($pd->components) ? $pd->components : json_decode($pd->components, true) ?? [];
                    foreach ($comps as $c) {
                        if (str_contains($c['name'] ?? '', 'PPh') && ($c['type'] ?? '') === 'deduction') {
                            $taxAlreadyPaid += (float) ($c['amount'] ?? 0);
                        }
                    }
                }

                $tax = $pph21Calc->calculateFinalMonth(
                    avgBrutoMonthly: (float) $payroll->basic_salary,
                    ptkpStatus     : $ptkpStatus,
                    taxMethod      : $taxMethod,
                    bpjsEmployee   : $bpjs['employee_total'],
                    monthsWorked   : $monthsWorked,
                    taxAlreadyPaid : $taxAlreadyPaid
                );
                $detailNote = 'Bulan terakhir (resign '.$resignDate->format('d/m/Y').') — '
                    .'Penghitungan progresif PMK-168/2023 | '
                    ."Masa kerja: {$monthsWorked} bln, PPh21 sudah dipotong: Rp ".number_format($taxAlreadyPaid, 0, ',', '.').' | '
                    .'PKP: Rp '.number_format($tax['pkp'], 0, ',', '.').', '
                    .'Pajak periode: Rp '.number_format($tax['tax_for_period'], 0, ',', '.');
            } else {
                // ── Jan-Nov: annualized × 12 (metode normal) ──
                $tax = $pph21Calc->calculateMonthly($brutoTaxable, $ptkpStatus, $taxMethod, $bpjs['employee_total']);
                $detailNote = 'Jan-Nov - TER bulanan PP 58/2023 | '
                    ."Metode: {$taxMethod}, PTKP: {$ptkpStatus}, "
                    .'Kategori TER: '.($tax['ter_category'] ?? '-').', '
                    .'Tarif: '.number_format((float) ($tax['ter_rate'] ?? 0), 2, ',', '.').'%';
            }

            // Gross-up: add tunjangan pajak as earning
            if ($taxMethod === 'gross_up' && ($tax['tunjangan_pajak'] ?? 0) > 0) {
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
                    'name' => 'PPh 21'.($isPph21Dtp ? ' (DTP)' : '').($isDecember ? ' *Desember' : ''),
                    'type' => $isPph21Dtp ? 'info' : 'deduction',
                    'category' => 'recurring',
                    'amount' => $tax['pph21_deduction'],
                    'is_taxable' => false,
                    'is_auto' => true,
                    'detail' => $detailNote,
                ];
                if (! $isPph21Dtp) {
                    $totalDeduction += $tax['pph21_deduction'];
                }
            }

            if (($tax['pph21_refund'] ?? 0) > 0) {
                $components[] = [
                    'id' => null,
                    'name' => 'Pengembalian PPh 21',
                    'type' => 'earning',
                    'category' => 'one-time',
                    'amount' => $tax['pph21_refund'],
                    'is_taxable' => false,
                    'is_auto' => true,
                    'detail' => 'PPh21 sudah dipotong: Rp '.number_format((float) ($tax['tax_already_paid'] ?? 0), 0, ',', '.')
                        .' | Pajak periode final: Rp '.number_format((float) ($tax['tax_for_period'] ?? 0), 0, ',', '.')
                        .' | Dikembalikan di payroll bulan terakhir.',
                ];
                $totalEarning += $tax['pph21_refund'];
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

    private function filterBpjsByRegistration(EmployeePayroll $payroll, array $bpjs): array
    {
        if (! filled($payroll->bpjs_kesehatan)) {
            $bpjs['kesehatan']['company'] = 0;
            $bpjs['kesehatan']['employee'] = 0;
        }

        if (! filled($payroll->bpjs_ketenagakerjaan)) {
            foreach (['jht', 'jkk', 'jkm', 'jp'] as $key) {
                $bpjs[$key]['company'] = 0;
                $bpjs[$key]['employee'] = 0;
            }
        }

        $bpjs['company_total'] = collect(['kesehatan', 'jht', 'jkk', 'jkm', 'jp'])
            ->sum(fn (string $key) => (float) ($bpjs[$key]['company'] ?? 0));
        $bpjs['employee_total'] = collect(['kesehatan', 'jht', 'jkk', 'jkm', 'jp'])
            ->sum(fn (string $key) => (float) ($bpjs[$key]['employee'] ?? 0));
        $bpjs['grand_total'] = $bpjs['company_total'] + $bpjs['employee_total'];

        return $bpjs;
    }

    private function loanDeductionComponents(int $employeeId, string $period): array
    {
        $loans = LoanRequest::where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('remaining_amount', '>', 0)
            ->where(function ($query) use ($period) {
                $query->whereNull('start_period')
                    ->orWhere('start_period', '<=', $period);
            })
            ->orderBy('id')
            ->get();

        $components = [];

        foreach ($loans as $loan) {
            $remainingBefore = (float) $loan->remaining_amount;

            // Nominal cicilan bulan ini: pakai jadwal per-bulan bila ada override
            // untuk periode ini, jika tidak pakai monthly_installment (default).
            $baseInstallment = $this->loanInstallmentForPeriod($loan, $period);
            $deductionAmount = min($baseInstallment, $remainingBefore);

            if ($deductionAmount <= 0) {
                continue;
            }

            $remainingAfter = max($remainingBefore - $deductionAmount, 0);
            $totalRepayable = $this->loanTotalRepayable($loan);
            $paidAfter = max($totalRepayable - $remainingAfter, 0);

            $components[] = [
                'id' => null,
                'name' => 'Potongan Pinjaman',
                'type' => 'deduction',
                'category' => 'recurring',
                'amount' => $deductionAmount,
                'is_taxable' => false,
                'is_auto' => true,
                'detail' => 'Cicilan pinjaman periode '.$period,
                'loan' => [
                    'id' => $loan->id,
                    'principal_amount' => (float) $loan->amount,
                    'interest_rate' => (float) ($loan->interest_rate ?? 0),
                    'interest_amount' => (float) ($loan->interest_amount ?? 0),
                    'total_repayable' => $totalRepayable,
                    'installment_amount' => $baseInstallment,
                    'installment_number' => $this->loanInstallmentNumber($loan, $remainingAfter),
                    'installment_count' => (int) $loan->installment_count,
                    'paid_amount' => $paidAfter,
                    'remaining_amount' => $remainingAfter,
                    'status' => $remainingAfter <= 0 ? 'lunas' : 'berjalan',
                ],
            ];
        }

        return $components;
    }

    /**
     * Nominal cicilan untuk periode tertentu. Bila pinjaman punya jadwal per-bulan
     * (installment_schedule) dan periode ini terdaftar, pakai nominal itu; jika tidak,
     * pakai monthly_installment (default).
     */
    private function loanInstallmentForPeriod(LoanRequest $loan, string $period): float
    {
        $schedule = $loan->installment_schedule;

        if (is_array($schedule) && array_key_exists($period, $schedule) && $schedule[$period] !== null && $schedule[$period] !== '') {
            return (float) $schedule[$period];
        }

        return (float) $loan->monthly_installment;
    }

    private function loanInstallmentNumber(LoanRequest $loan, float $remainingAfter): int
    {
        $installment = (float) $loan->monthly_installment;
        if ($installment <= 0) {
            return 1;
        }

        $paidAfter = max($this->loanTotalRepayable($loan) - $remainingAfter, 0);

        return min((int) $loan->installment_count, max(1, (int) ceil($paidAfter / $installment)));
    }

    private function loanTotalRepayable(LoanRequest $loan): float
    {
        $totalRepayable = (float) ($loan->total_repayable ?? 0);

        return $totalRepayable > 0 ? $totalRepayable : (float) $loan->amount;
    }

    private function applyLoanDeductions(PayrollRun $run): void
    {
        $run->loadMissing('details');

        foreach ($run->details as $detail) {
            $components = is_array($detail->components)
                ? $detail->components
                : (json_decode((string) $detail->components, true) ?: []);

            $changed = false;

            foreach ($components as &$component) {
                $loanData = $component['loan'] ?? null;
                if (! is_array($loanData) || ! empty($loanData['balance_applied'])) {
                    continue;
                }

                $loanId = $loanData['id'] ?? null;
                if (! $loanId) {
                    continue;
                }

                $loan = LoanRequest::find($loanId);
                if (! $loan || $loan->status !== 'active') {
                    continue;
                }

                $deductionAmount = min((float) ($component['amount'] ?? 0), (float) $loan->remaining_amount);
                if ($deductionAmount <= 0) {
                    continue;
                }

                $remainingAfter = max((float) $loan->remaining_amount - $deductionAmount, 0);
                $totalRepayable = $this->loanTotalRepayable($loan);
                $loan->update([
                    'remaining_amount' => $remainingAfter,
                    'status' => $remainingAfter <= 0 ? 'paid' : 'active',
                    'paid_at' => $remainingAfter <= 0 ? now() : null,
                ]);

                $component['loan']['remaining_amount'] = $remainingAfter;
                $component['loan']['paid_amount'] = max($totalRepayable - $remainingAfter, 0);
                $component['loan']['total_repayable'] = $totalRepayable;
                $component['loan']['status'] = $remainingAfter <= 0 ? 'lunas' : 'berjalan';
                $component['loan']['balance_applied'] = true;
                $changed = true;
            }

            unset($component);

            if ($changed) {
                $detail->update(['components' => $components]);
            }
        }
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
        $lateDays = Attendance::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('is_late', true)
            ->where('status', 'present')
            ->where(fn ($query) => $query->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->whereNotIn('date', $holidayDates)
            ->whereNotIn('date', $leaveDates)
            ->count();

        return [
            'days' => $lateDays,
            'amount' => $lateDays * $penaltyPerDay,
        ];
    }

    private function fetchDailyReportLateCounts(Collection $emails, Carbon $periodStart, Carbon $periodEnd): array
    {
        $baseUrl = rtrim((string) config('services.daily.url'), '/');
        $secret = (string) config('services.daily.internal_secret');
        $emails = $emails
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        if ($baseUrl === '' || $secret === '' || $emails->isEmpty()) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'X-Internal-Secret' => $secret,
            ])
                ->timeout(10)
                ->get($baseUrl.'/api/internal/payroll/daily-report-late', [
                    'start' => $periodStart->toDateString(),
                    'end' => $periodEnd->toDateString(),
                    'emails' => $emails->all(),
                ]);

            if (! $response->ok()) {
                Log::warning('Gagal mengambil data telat laporan harian untuk payroll.', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            return collect($response->json('data') ?? [])
                ->mapWithKeys(fn ($row) => [
                    strtolower((string) ($row['email'] ?? '')) => (int) ($row['late_days'] ?? 0),
                ])
                ->filter(fn ($days, $email) => $email !== '')
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('Tidak dapat terhubung ke DailyCloseApp saat menghitung potongan kedisiplinan.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function buildDisciplinePenaltyComponent(int $lateDays, float $penaltyPerDay): array
    {
        $amount = $lateDays * $penaltyPerDay;

        return [
            'id' => null,
            'name' => 'Potongan Kedisiplinan',
            'type' => 'deduction',
            'category' => 'recurring',
            'amount' => $amount,
            'is_taxable' => false,
            'is_auto' => true,
            'detail' => $lateDays.' hari × Rp '.number_format($penaltyPerDay, 0, ',', '.'),
        ];
    }

    /**
     * Hitung potongan alpha: Rp 100.000 per hari absent
     * Exclude hari libur, cuti, dan hari off shift
     */
    private function calculateAlphaPenalty(int $empId, $periodStart, $periodEnd, array $holidayDates, float $basicSalary): array
    {
        $perDay = self::ALPHA_PENALTY_PER_DAY;
        $periodStart = Carbon::parse($periodStart)->startOfDay();
        $periodEnd = Carbon::parse($periodEnd)->startOfDay();
        $countUntil = $periodEnd->copy()->min(Carbon::today());
        $leaveDates = $this->getApprovedLeaveDates($empId, $periodStart, $periodEnd);

        // Get off-shift dates
        $offDates = ScheduleAssignment::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $countUntil])
            ->whereHas('shift', fn ($q) => $q->where('is_off', true))
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $excludedDates = array_flip(array_merge($holidayDates, $leaveDates, $offDates));

        $explicitAbsentDates = Attendance::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $countUntil])
            ->where('status', 'absent')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->reject(fn ($date) => isset($excludedDates[$date]))
            ->values();

        $attendanceDates = Attendance::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $countUntil])
            ->where(fn ($query) => $query->whereNull('review_status')->orWhere('review_status', 'approved'))
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->flip();

        $employee = Employee::with(['scheduleTemplate.days.shift'])->find($empId);
        $templateDays = $employee?->scheduleTemplate?->days?->keyBy('day_of_week') ?? collect();
        $overrides = ScheduleAssignment::with('shift')
            ->where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $countUntil])
            ->get()
            ->keyBy(fn ($assignment) => Carbon::parse($assignment->date)->format('Y-m-d'));

        $inferredAbsentDates = collect();
        for ($date = $periodStart->copy(); $date->lte($countUntil); $date->addDay()) {
            $dateString = $date->format('Y-m-d');
            if (isset($excludedDates[$dateString]) || isset($attendanceDates[$dateString])) {
                continue;
            }

            $shift = $overrides->get($dateString)?->shift
                ?? $templateDays->get($date->dayOfWeekIso)?->shift;

            if ($shift && ! $shift->is_off) {
                $inferredAbsentDates->push($dateString);
            }
        }

        $absentDays = $explicitAbsentDates->merge($inferredAbsentDates)->unique()->count();

        return [
            'days' => $absentDays,
            'amount' => $absentDays * $perDay,
            'per_day' => $perDay,
        ];
    }

    /**
     * Hitung lembur sesuai PP No. 35 Tahun 2021 (turunan UU Cipta Kerja).
     *
     * Upah per jam lembur: 1/173 × gaji pokok × multiplier
     *
     * Hari kerja biasa:
     *   jam ke-1      = 1,5 × upah/jam
     *   jam ke-2 dst  = 2   × upah/jam
     *
     * Hari libur/istirahat (pola 5 hari kerja, 8 jam):
     *   jam 1–8 = 2×  |  jam 9 = 3×  |  jam 10+ = 4×
     *
     * Hari libur/istirahat (pola 6 hari kerja, 7 jam):
     *   jam 1–7 = 2×  |  jam 8 = 3×  |  jam 9+  = 4×
     *
     * Tarif progresif diterapkan per hari (per OvertimeRequest), bukan akumulasi.
     */
    private function calculateOvertime(int $empId, $periodStart, $periodEnd, array $holidayDates, float $basicSalary, float $multiplier = 1): array
    {
        $baseRate = ($basicSalary / 173) * $multiplier;

        $overtimes = OvertimeRequest::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('status', 'approved')
            ->get();

        if ($overtimes->isEmpty()) {
            return ['total_amount' => 0, 'detail' => ''];
        }

        // Pola hari kerja per minggu dari work schedule karyawan
        $employee = Employee::with(['workSchedule', 'scheduleTemplate.days.shift'])->find($empId);
        $workDaysPerWeek = $employee?->workSchedule?->work_days ?? 5;

        // Tanggal yang karyawan DIJADWALKAN MASUK (ada shift kerja / override non-off).
        // Dipakai untuk kasus security dsb: kalau lembur ter-tag 'holiday' PADAHAL ada
        // shift kerja di tanggal itu, lemburnya tetap dihitung tarif hari kerja biasa.
        $workingDates = ScheduleAssignment::where('employee_id', $empId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->whereHas('shift', fn ($q) => $q->where('is_off', false))
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $totalAmount = 0;
        $workdayMins = 0;
        $holidayMins = 0;
        $shortHolidayMins = 0;
        $workdayAmount = 0;
        $holidayAmount = 0;
        $shortHolidayAmount = 0;

        foreach ($overtimes as $ot) {
            $payableMinutes = $ot->getPayableDuration();
            if ($payableMinutes <= 0) {
                continue;
            }

            $dateStr = Carbon::parse($ot->date)->format('Y-m-d');

            // Klasifikasi hari kerja vs libur MENGIKUTI overtime_type yang sudah ditetapkan
            // & divalidasi saat pengajuan (karyawan/approver memilih 'workday'/'holiday'),
            // konsisten dengan seluruh aplikasi (laporan lembur, dashboard, rekap absensi).
            // Security yang bertugas di tanggal merah diajukan sebagai 'workday' → tarif hari
            // kerja, walaupun tanggalnya libur nasional. Tabel Holiday TIDAK lagi menimpa tipe ini.
            //
            // Pengaman satu arah: kalau lembur ter-tag 'holiday' PADAHAL karyawan memang
            // dijadwalkan masuk (ada shift kerja) di tanggal itu, tetap dihitung tarif HARI KERJA.
            $hasWorkingShift = in_array($dateStr, $workingDates, true);
            $isHoliday = ! $hasWorkingShift && $ot->overtime_type === 'holiday';
            $isShortestWorkdayHoliday = $isHoliday
                && $this->isSixDayOfficialHolidayOnShortestWorkday($employee, $dateStr, $holidayDates, $workDaysPerWeek);

            // Hitung per hari agar tarif progresif ter-reset setiap hari
            $amount = $this->computeOvertimeAmount($payableMinutes, $baseRate, $isHoliday, $workDaysPerWeek, $isShortestWorkdayHoliday);
            $totalAmount += $amount;

            if ($isShortestWorkdayHoliday) {
                $shortHolidayMins += $payableMinutes;
                $shortHolidayAmount += $amount;
            } elseif ($isHoliday) {
                $holidayMins += $payableMinutes;
                $holidayAmount += $amount;
            } else {
                $workdayMins += $payableMinutes;
                $workdayAmount += $amount;
            }
        }

        $detail = $this->buildOvertimeDetail(
            $workdayMins, $workdayAmount,
            $holidayMins, $holidayAmount,
            $shortHolidayMins, $shortHolidayAmount,
            $baseRate, $workDaysPerWeek
        );

        return [
            'total_amount' => round($totalAmount, 0),
            'detail' => $detail,
        ];
    }

    /**
     * Hitung upah lembur untuk satu hari berdasarkan PP No. 35 Tahun 2021.
     */
    private function computeOvertimeAmount(int $minutes, float $baseRate, bool $isHoliday, int $workDaysPerWeek = 5, bool $isShortestWorkdayHoliday = false): float
    {
        $hours = $minutes / 60;

        if (! $isHoliday) {
            $first = min($hours, 1.0);
            $rest = max(0.0, $hours - 1.0);

            return ($first * 1.5 + $rest * 2.0) * $baseRate;
        }

        // Ambang batas jam berbeda tergantung pola kerja per minggu
        $threshold = ($workDaysPerWeek >= 6 && $isShortestWorkdayHoliday) ? 5.0 : (($workDaysPerWeek >= 6) ? 7.0 : 8.0);
        $tier1 = min($hours, $threshold);
        $tier2 = min(max(0.0, $hours - $threshold), 1.0);
        $tier3 = max(0.0, $hours - $threshold - 1.0);

        return ($tier1 * 2.0 + $tier2 * 3.0 + $tier3 * 4.0) * $baseRate;
    }

    /**
     * Bangun string detail lembur untuk ditampilkan di slip gaji.
     */
    private function buildOvertimeDetail(int $workdayMins, float $workdayAmount, int $holidayMins, float $holidayAmount, int $shortHolidayMins, float $shortHolidayAmount, float $baseRate, int $workDaysPerWeek): string
    {
        $perJam = 'Rp '.number_format(round($baseRate, 0), 0, ',', '.');
        $parts = [];

        if ($workdayMins > 0) {
            $h = round($workdayMins / 60, 1);
            $parts[] = 'Hari kerja: '.number_format($h, 1, ',', '.').' jam'
                .' (1j×1,5 + selebihnya×2, @'.$perJam.'/jam)'
                .' = Rp '.number_format(round($workdayAmount, 0), 0, ',', '.');
        }

        if ($holidayMins > 0) {
            $h = round($holidayMins / 60, 1);
            $threshold = ($workDaysPerWeek >= 6) ? 7 : 8;
            $parts[] = 'Hari libur: '.number_format($h, 1, ',', '.').' jam'
                .' (1–'.$threshold.'j×2, '.($threshold + 1).'j×3, dst×4, @'.$perJam.'/jam)'
                .' = Rp '.number_format(round($holidayAmount, 0), 0, ',', '.');
        }

        if ($shortHolidayMins > 0) {
            $h = round($shortHolidayMins / 60, 1);
            $parts[] = 'Hari libur hari kerja terpendek: '.number_format($h, 1, ',', '.').' jam'
                .' (1-5j x2, 6j x3, dst x4, @'.$perJam.'/jam)'
                .' = Rp '.number_format(round($shortHolidayAmount, 0), 0, ',', '.');
        }

        return implode(' | ', $parts);
    }

    private function isSixDayOfficialHolidayOnShortestWorkday(?Employee $employee, string $dateStr, array $holidayDates, int $workDaysPerWeek): bool
    {
        if (! $employee || $workDaysPerWeek < 6 || ! in_array($dateStr, $holidayDates, true)) {
            return false;
        }

        $template = $employee->scheduleTemplate;
        if (! $template) {
            return false;
        }

        $workdayDurations = $template->days
            ->map(fn ($day) => $day->shift)
            ->filter(fn ($shift) => $shift && ! $shift->is_off)
            ->map(fn ($shift) => $this->getPayrollShiftDurationMinutes($shift))
            ->filter(fn ($duration) => $duration > 0)
            ->values();

        if ($workdayDurations->count() < 6 || $workdayDurations->min() === $workdayDurations->max()) {
            return false;
        }

        $assignedShift = ScheduleAssignment::with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('date', $dateStr)
            ->first()
            ?->shift;

        $dateShift = $assignedShift ?: $template->getShiftForDay(Carbon::parse($dateStr)->dayOfWeekIso);
        if (! $dateShift || $dateShift->is_off) {
            return false;
        }

        return $this->getPayrollShiftDurationMinutes($dateShift) === $workdayDurations->min();
    }

    private function getPayrollShiftDurationMinutes($shift): int
    {
        return abs($shift->getShiftDurationMinutes());
    }

    /**
     * Get approved leave dates for an employee in a period
     */
    private function getApprovedLeaveDates(int $empId, $periodStart, $periodEnd): array
    {
        $leaves = LeaveRequest::where('employee_id', $empId)
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
