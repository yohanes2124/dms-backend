<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = uniqid();
        
        // Add request ID to headers
        $request->headers->set('X-Request-ID', $requestId);

        // Log incoming request
        $this->logRequest($request, $requestId);

        $response = $next($request);

        // Log response
        $this->logResponse($request, $response, $requestId, $startTime);

        return $response;
    }

    /**
     * Log incoming request
     */
    private function logRequest(Request $request, string $requestId): void
    {
        $data = [
            'request_id' => $requestId,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ];

        // Log input data (excluding sensitive fields)
        $input = $request->except(['password', 'password_confirmation', 'current_password']);
        if (!empty($input)) {
            $data['input'] = $input;
        }

        Log::channel('audit')->info('API Request', $data);
    }

    /**
     * Log response
     */
    private function logResponse(Request $request, Response $response, string $requestId, float $startTime): void
    {
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $data = [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'memory_usage' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB',
            'timestamp' => now()->toISOString(),
        ];

        // Log response data for errors or sensitive operations
        if ($response->getStatusCode() >= 400 || $this->isSensitiveOperation($request)) {
            $content = $response->getContent();
            if ($content && strlen($content) < 1000) {
                $data['response'] = json_decode($content, true) ?: $content;
            }
        }

        $level = $response->getStatusCode() >= 500 ? 'error' : 
                ($response->getStatusCode() >= 400 ? 'warning' : 'info');

        Log::channel('audit')->{$level}('API Response', $data);
    }

    /**
     * Check if this is a sensitive operation that should be logged
     */
    private function isSensitiveOperation(Request $request): bool
    {
        $sensitiveRoutes = [
            'auth/login',
            'auth/logout',
            'auth/register',
            'applications',
            'clearance',
            'change-requests',
            'rooms/assign',
            'rooms/unassign',
            'users',
        ];

        $path = $request->path();
        
        foreach ($sensitiveRoutes as $route) {
            if (str_contains($path, $route)) {
                return true;
            }
        }

        return false;
    }
}