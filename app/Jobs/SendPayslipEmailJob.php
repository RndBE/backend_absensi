<?php

namespace App\Jobs;

use App\Mail\PayslipPublishedMail;
use App\Models\Company;
use App\Models\PayrollRunDetail;
use App\Support\PayslipBpjsData;
use App\Support\PayslipLoanSummary;
use Barryvdh\DomPDF\Facade\Pdf;
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
        return PayslipBpjsData::fromDetail($detail);
    }
}
