<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Basic health check
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env')
        ]);
    }

    /**
     * Detailed health check with dependencies
     */
    public function detailed()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
            'jwt' => $this->checkJWT(),
        ];

        $overall = collect($checks)->every(fn($check) => $check['status'] === 'healthy');

        return response()->json([
            'success' => true,
            'status' => $overall ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'checks' => $checks,
            'uptime' => $this->getUptime(),
            'memory_usage' => $this->getMemoryUsage(),
        ], $overall ? 200 : 503);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $duration = round((microtime(true) - $start) * 1000, 2);

            // Test a simple query
            $userCount = DB::table('users')->count();
            $roomCount = DB::table('rooms')->count();

            return [
                'status' => 'healthy',
                'response_time_ms' => $duration,
                'details' => [
                    'connection' => 'active',
                    'users_count' => $userCount,
                    'rooms_count' => $roomCount,
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'details' => [
                    'connection' => 'failed'
                ]
            ];
        }
    }

    /**
     * Check cache system
     */
    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . time();
            $value = 'test_value';

            Cache::put($key, $value, 60);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($retrieved === $value) {
                return [
                    'status' => 'healthy',
                    'response_time_ms' => $duration,
                    'details' => [
                        'driver' => config('cache.default'),
                        'operations' => 'put/get/forget successful'
                    ]
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Cache value mismatch',
                    'details' => [
                        'driver' => config('cache.default'),
                        'expected' => $value,
                        'retrieved' => $retrieved
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'details' => [
                    'driver' => config('cache.default')
                ]
            ];
        }
    }

    /**
     * Check storage system
     */
    private function checkStorage(): array
    {
        try {
            $start = microtime(true);
            $filename = 'health_check_' . time() . '.txt';
            $content = 'health check test';

            Storage::put($filename, $content);
            $retrieved = Storage::get($filename);
            Storage::delete($filename);

            $duration = round((microtime(true) - $start) * 1000, 2);

            if ($retrieved === $content) {
                return [
                    'status' => 'healthy',
                    'response_time_ms' => $duration,
                    'details' => [
                        'disk' => config('filesystems.default'),
                        'operations' => 'put/get/delete successful'
                    ]
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => 'Storage content mismatch',
                    'details' => [
                        'disk' => config('filesystems.default')
                    ]
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'details' => [
                    'disk' => config('filesystems.default')
                ]
            ];
        }
    }

    /**
     * Check JWT configuration
     */
    private function checkJWT(): array
    {
        try {
            $secret = config('jwt.secret');
            $ttl = config('jwt.ttl');

            if (empty($secret)) {
                return [
                    'status' => 'unhealthy',
                    'error' => 'JWT secret not configured',
                    'details' => [
                        'secret_configured' => false,
                        'ttl' => $ttl
                    ]
                ];
            }

            return [
                'status' => 'healthy',
                'details' => [
                    'secret_configured' => true,
                    'ttl_minutes' => $ttl,
                    'algorithm' => config('jwt.algo', 'HS256')
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'details' => []
            ];
        }
    }

    /**
     * Get application uptime
     */
    private function getUptime(): array
    {
        $uptimeFile = storage_path('framework/uptime');
        
        if (!file_exists($uptimeFile)) {
            file_put_contents($uptimeFile, time());
        }

        $startTime = (int) file_get_contents($uptimeFile);
        $uptime = time() - $startTime;

        return [
            'seconds' => $uptime,
            'human' => $this->formatUptime($uptime),
            'started_at' => date('Y-m-d H:i:s', $startTime)
        ];
    }

    /**
     * Get memory usage information
     */
    private function getMemoryUsage(): array
    {
        return [
            'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit')
        ];
    }

    /**
     * Format uptime in human readable format
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        if ($seconds > 0 || empty($parts)) $parts[] = "{$seconds}s";

        return implode(' ', $parts);
    }
}