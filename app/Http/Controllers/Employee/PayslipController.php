<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRunDetail;
use App\Services\BpjsCalculator;
use App\Support\PayslipFilename;
use App\Support\PayslipLoanSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PayslipController extends Controller
{
    /** Berapa menit akses slip gaji tetap terbuka setelah verifikasi kata sandi. */
    private const UNLOCK_MINUTES = 10;

    public function index(Request $request)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        // Wajib verifikasi kata sandi dulu; data slip tidak diambil sampai terbuka.
        $unlocked = $this->isUnlocked($request, $employee);

        $payslips = $unlocked
            ? PayrollRunDetail::with(['payrollRun:id,period,status'])
                ->where('employee_id', $employee->id)
                ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', ['published', 'locked']))
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('employee.payslips.index', [
            'employee' => $employee,
            'payslips' => $payslips,
            'locked' => ! $unlocked,
        ]);
    }

    public function unlock(Request $request)
    {
        $request->validate(['password' => ['required', 'string']], [], ['password' => 'kata sandi']);

        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        if (! $employee->password || ! Hash::check($request->password, $employee->password)) {
            return back()->withErrors(['password' => 'Kata sandi salah.']);
        }

        session(['payslip_unlock' => [
            'id' => $employee->id,
            'until' => now()->addMinutes(self::UNLOCK_MINUTES)->timestamp,
        ]]);

        return redirect()->route('employee.payslips.index');
    }

    public function downloadPdf(Request $request, $id)
    {
        /** @var Employee $employee */
        $employee = $request->attributes->get('employee');

        if (! $this->isUnlocked($request, $employee)) {
            return redirect()->route('employee.payslips.index');
        }

        $detail = PayrollRunDetail::with([
            'employee',
            'employee.department:id,name',
            'employee.activePayroll',
            'payrollRun:id,period,status',
        ])->where('employee_id', $employee->id)
          ->whereHas('payrollRun', fn ($q) => $q->whereIn('status', ['published', 'locked']))
          ->findOrFail($id);

        $company = Company::find($detail->employee->company_id);
        $bpjsData = $this->buildBpjsData($detail);
        $loanSummary = PayslipLoanSummary::fromComponents($detail->components);
        $logoBase64 = $this->buildLogoBase64($company);

        $pdf = Pdf::loadView('admin.payslips.pdf', compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary'));
        $pdf->setPaper('A4', 'portrait');

        $filename = PayslipFilename::make($detail->employee->employee_code, $detail->payrollRun->period);

        return $pdf->download($filename);
    }

    /** Apakah akses slip gaji sudah terbuka (kata sandi terverifikasi & belum kedaluwarsa). */
    private function isUnlocked(Request $request, Employee $employee): bool
    {
        $unlock = $request->session()->get('payslip_unlock');

        return is_array($unlock)
            && ($unlock['id'] ?? null) === $employee->id
            && ($unlock['until'] ?? 0) > now()->timestamp;
    }

    private function buildLogoBase64(?Company $company): ?string
    {
        if (! $company?->logo) {
            return null;
        }

        $logoPath = storage_path('app/public/' . $company->logo);
        if (! file_exists($logoPath)) {
            return null;
        }

        $logoMime = mime_content_type($logoPath);

        return 'data:' . $logoMime . ';base64,' . base64_encode(file_get_contents($logoPath));
    }

    private function buildBpjsData(PayrollRunDetail $detail): array
    {
        $payroll = $detail->employee->activePayroll;
        if (! $payroll) {
            return ['items' => [], 'total' => 0];
        }

        $periodDate = Carbon::parse($detail->payrollRun->period . '-01');
        $bpjs = (new BpjsCalculator($periodDate->format('Y-m-d')))->calculate((float) $payroll->basic_salary);
        $bpjs = \App\Support\PayrollBpjs::applyEligibility($bpjs, $payroll, $periodDate);
        // Karyawan resign: JKK/JKM/JHT tetap DITAMPILKAN sebagai Rp 0 (bukan disembunyikan).
        $resigned = \App\Support\PayrollBpjs::isResignedInMonth($detail->employee, $periodDate);

        $items = [[
            'label' => 'Rate BPJS Kesehatan',
            'amount' => $bpjs['kesehatan']['basis'],
            'is_basis' => true,
        ]];

        $tkHasContrib = ($bpjs['jht']['company'] + $bpjs['jkk']['company'] + $bpjs['jkm']['company'] + $bpjs['jp']['company'] > 0) || $resigned;
        if ($tkHasContrib) {
            $items[] = [
                'label' => 'Rate BPJS Ketenagakerjaan',
                'amount' => $bpjs['jht']['basis'],
                'is_basis' => true,
            ];
        }

        if ($bpjs['jkk']['company'] > 0 || $resigned) {
            $items[] = ['label' => 'JKK (Jaminan Kecelakaan Kerja)', 'amount' => $bpjs['jkk']['company'], 'is_basis' => false];
        }
        if ($bpjs['jkm']['company'] > 0 || $resigned) {
            $items[] = ['label' => 'JKM (Jaminan Kematian)', 'amount' => $bpjs['jkm']['company'], 'is_basis' => false];
        }
        if ($bpjs['jht']['company'] > 0 || $resigned) {
            $items[] = ['label' => 'JHT Perusahaan (Jaminan Hari Tua)', 'amount' => $bpjs['jht']['company'], 'is_basis' => false];
        }
        if ($bpjs['jp']['company'] > 0) {
            $items[] = ['label' => 'JP Perusahaan (Jaminan Pensiun)', 'amount' => $bpjs['jp']['company'], 'is_basis' => false];
        }
        if ($bpjs['kesehatan']['company'] > 0) {
            $items[] = ['label' => 'BPJS Kesehatan Perusahaan', 'amount' => $bpjs['kesehatan']['company'], 'is_basis' => false];
        }

        return [
            'raw' => $bpjs,
            'items' => $items,
            'total' => collect($items)->sum('amount'),
        ];
    }
}
