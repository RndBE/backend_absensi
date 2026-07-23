<?php

namespace App\Http\Middleware;

use App\Models\AdminActivityLog;
use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class AdminActivityLogger
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const EXCLUDED_EMAILS = ['superadmin@gmail.com'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            $this->log($request);
        }

        return $response;
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        return in_array($request->method(), self::WRITE_METHODS, true)
            && $response->getStatusCode() < 400
            && session('admin_id')
            && Schema::hasTable('admin_activity_logs');
    }

    private function log(Request $request): void
    {
        $admin = Employee::find(session('admin_id'));
        if (! $admin || $this->isExcludedAdmin($admin)) {
            return;
        }

        $routeName = $request->route()?->getName();
        [$module, $action] = $this->moduleAndAction($routeName, $request);

        AdminActivityLog::create([
            'employee_id' => $admin->id,
            'company_id' => $admin->company_id,
            'module' => $module,
            'action' => $action,
            'route_name' => $routeName,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'metadata' => [
                'route_parameters' => $request->route()?->parameters() ?? [],
            ],
        ]);
    }

    private function moduleAndAction(?string $routeName, Request $request): array
    {
        if ($routeName && str_starts_with($routeName, 'admin.')) {
            $parts = explode('.', substr($routeName, 6));
            $module = $parts[0] ?? 'admin';
            $action = $parts[count($parts) - 1] ?? strtolower($request->method());

            return [$module, $action];
        }

        return ['admin', strtolower($request->method())];
    }

    private function isExcludedAdmin(Employee $admin): bool
    {
        return in_array(strtolower(trim((string) $admin->email)), self::EXCLUDED_EMAILS, true);
    }
}
