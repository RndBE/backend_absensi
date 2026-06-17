<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\PayrollRunDetail;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayslipPublishedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollRunDetail $detail,
        public ?Company $company,
        public string $pdfBinary
    ) {
    }

    public function build(): self
    {
        $periodLabel = Carbon::parse($this->detail->payrollRun->period.'-01')
            ->locale('id')
            ->translatedFormat('F Y');

        return $this
            ->subject('Slip Gaji '.$periodLabel)
            ->view('emails.payslip-published')
            ->with([
                'detail' => $this->detail,
                'employee' => $this->detail->employee,
                'company' => $this->company,
                'periodLabel' => $periodLabel,
            ])
            ->attachData(
                $this->pdfBinary,
                'Payslip_'.$this->detail->employee->employee_code.'_'.$this->detail->payrollRun->period.'.pdf',
                ['mime' => 'application/pdf']
            );
    }
}
