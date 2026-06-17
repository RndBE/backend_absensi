<?php

namespace App\Mail;

use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmployeePortalMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public string $magicUrl,
        public CarbonInterface $expiresAt
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject('Akses Dashboard HRIS Beacon')
            ->view('emails.employee-portal-magic-link')
            ->with([
                'employee' => $this->employee,
                'magicUrl' => $this->magicUrl,
                'expiresAt' => $this->expiresAt,
            ]);
    }
}
