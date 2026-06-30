<?php

namespace App\Mail;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveDelegationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public LeaveRequest $leave,
        public Employee $delegate,
    ) {
    }

    public function build(): self
    {
        $this->leave->loadMissing(['employee.company', 'leaveType', 'delegate']);

        return $this
            ->subject('Delegasi Tugas')
            ->view('emails.leave-delegation')
            ->with([
                'leave' => $this->leave,
                'delegate' => $this->delegate,
                'company' => $this->leave->employee?->company,
            ]);
    }
}
