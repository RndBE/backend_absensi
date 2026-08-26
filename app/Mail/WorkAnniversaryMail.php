<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkAnniversaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public ?Company $company,
        public int $years
    ) {
    }

    public function build(): self
    {
        $companyName = $this->company->name ?? config('app.name');

        return $this
            ->subject("Selamat! {$this->years} Tahun Bersama {$companyName}")
            ->view('emails.work-anniversary')
            ->with([
                'employee' => $this->employee,
                'company' => $this->company,
                'years' => $this->years,
                'joinLabel' => $this->employee->join_date?->locale('id')->translatedFormat('j F Y'),
            ]);
    }
}
