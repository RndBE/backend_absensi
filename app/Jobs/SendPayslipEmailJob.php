<?php

namespace App\Jobs;

use App\Mail\PayslipPublishedMail;
use App\Models\Company;
use App\Models\PayrollRunDetail;
use App\Services\BpjsCalculator;
use App\Support\PayslipLoanSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPayslipEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $payrollRunDetailId)
    {
    }

    public function handle(): void
    {
        $detail = PayrollRunDetail::with([
            'employee',
            'employee.department:id,name',
            'employee.activePayroll',
            'payrollRun:id,period,status',
        ])->findOrFail($this->payrollRunDetailId);

        if (! in_array($detail->payrollRun?->status, ['published', 'locked'], true)) {
            return;
        }

        if (! filled($detail->employee?->email)) {
            return;
        }

        $company = Company::find($detail->employee->company_id);
        $bpjsData = $this->buildBpjsData($detail);
        $loanSummary = PayslipLoanSummary::fromComponents($detail->components);
        $logoBase64 = $this->buildLogoBase64($company);
        $hideBenefits = true;
        $pdfBinary = Pdf::loadView('admin.payslips.pdf', compact('detail', 'company', 'logoBase64', 'bpjsData', 'loanSummary', 'hideBenefits'))
            ->setPaper('A4', 'portrait')
            ->output();

        Mail::to($detail->employee->email)->send(new PayslipPublishedMail($detail, $company, $pdfBinary));
    }

    private function buildLogoBase64(?Company $company): ?string
    {
        if (! $company?->logo) {
            return null;
        }

        $logoPath = storage_path('app/public/'.$company->logo);
        if (! file_exists($logoPath)) {
            return null;
        }

        $logoMime = mime_content_type($logoPath);

        return 'data:'.$logoMime.';base64,'.base64_encode(file_get_contents($logoPath));
    }

    private function buildBpjsData(PayrollRunDetail $detail): array
    {
        $payroll = $detail->employee->activePayroll;
        if (! $payroll) {
            return ['items' => [], 'total' => 0];
        }

        $periodDate = Carbon::parse($detail->payrollRun->period.'-01');
        $bpjs = (new BpjsCalculator($periodDate->format('Y-m-d')))->calculate((float) $payroll->basic_salary);
        $bpjs = \App\Support\PayrollBpjs::dropKetenagakerjaanForResign($bpjs, $detail->employee, $periodDate);
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
