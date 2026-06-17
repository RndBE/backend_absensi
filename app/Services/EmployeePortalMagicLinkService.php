<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeMagicLink;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class EmployeePortalMagicLinkService
{
    public function create(Employee $employee, string $redirectPath = '/employee/dashboard', ?CarbonInterface $expiresAt = null): array
    {
        $token = Str::random(64);
        $redirectPath = $this->safeRedirectPath($redirectPath);

        $link = EmployeeMagicLink::create([
            'employee_id' => $employee->id,
            'token_hash' => hash('sha256', $token),
            'redirect_path' => $redirectPath,
            'expires_at' => $expiresAt ?? now()->addMinutes(30),
        ]);

        return [
            'link' => $link,
            'token' => $token,
            'url' => url('/employee/magic-login?token='.$token),
        ];
    }

    public function safeRedirectPath(?string $redirectPath): string
    {
        if (! $redirectPath || ! Str::startsWith($redirectPath, '/employee/')) {
            return '/employee/dashboard';
        }

        if (Str::startsWith($redirectPath, ['//', '/employee/magic-login', '/employee/logout'])) {
            return '/employee/dashboard';
        }

        return $redirectPath;
    }
}
