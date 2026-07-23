<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ViolationReportLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ViolationReportController extends Controller
{
    private const DEFAULT_FORM_URL = 'https://tinyurl.com/PELAPORAN-PELANGGARAN-ATC';

    public function index()
    {
        return view('employee.violation-report.index');
    }

    public function open(Request $request): RedirectResponse
    {
        $targetUrl = config('services.violation_report.form_url') ?: self::DEFAULT_FORM_URL;
        $employee = $request->attributes->get('employee');

        if (! $employee instanceof Employee) {
            $employee = Employee::find(session('employee_id'));
        }

        $this->recordOpenLog($request, $employee, $targetUrl);

        return redirect()->away($targetUrl);
    }

    private function recordOpenLog(Request $request, ?Employee $employee, string $targetUrl): void
    {
        if (! Schema::hasTable('violation_report_logs')) {
            return;
        }

        try {
            ViolationReportLog::create([
                'employee_id' => $employee?->id,
                'company_id' => $employee?->company_id,
                'action' => 'open_form',
                'target_url' => $targetUrl,
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'metadata' => [
                    'referer' => $request->headers->get('referer'),
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to record violation report form log.', [
                'employee_id' => $employee?->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
