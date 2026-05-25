<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AdminAuditLogger
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldLog($request)) {
            $admin = Employee::find(session('admin_id'));

            AdminAuditLog::create([
                'employee_id' => $admin?->id,
                'company_id' => $admin?->company_id,
                'action' => $request->route()?->getName() ?? $request->path(),
                'route_name' => $request->route()?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
                'status_code' => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 1000),
                'payload' => $this->sanitizePayload($request->except(['_token', '_method'])),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        return str_starts_with($request->path(), 'admin/')
            && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (str_contains($lowerKey, 'password') || str_contains($lowerKey, 'token') || str_contains($lowerKey, 'secret')) {
                $sanitized[$key] = '[FILTERED]';
                continue;
            }

            if ($value instanceof UploadedFile) {
                $sanitized[$key] = '[FILE:' . $value->getClientOriginalName() . ']';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
                continue;
            }

            $sanitized[$key] = is_scalar($value) || is_null($value) ? $value : '[UNSUPPORTED]';
        }

        return $sanitized;
    }
}
