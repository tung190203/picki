<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 0: Performance Monitoring Middleware.
 *
 * Logs slow API responses (>500ms) with full context:
 *   - URL, method, status code
 *   - Total response time
 *   - Query count and total DB time
 *   - User ID (if authenticated)
 *   - Memory peak usage
 *
 * This middleware should be registered in routes/api.php or Kernel.php.
 * It only logs in non-production environments or when APP_LOG_SLOW_API=true.
 */
class PerformanceMonitoringMiddleware
{
    public const SLOW_THRESHOLD_MS = 500;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);
        $startMemory = memory_get_usage(true);

        // Capture query count before
        $queryCountBefore = $this->getQueryCount();

        $response = $next($request);

        $totalTime = microtime(true) - $startTime;
        $totalMs = round($totalTime * 1000);

        // Only log if over threshold and in allowed env
        if ($totalMs < self::SLOW_THRESHOLD_MS) {
            return $response;
        }

        if (! $this->shouldLog()) {
            return $response;
        }

        $queryCountAfter = $this->getQueryCount();
        $queryCount = max(0, $queryCountAfter - $queryCountBefore);
        $dbTime = $this->getDbTime();

        $memoryUsed = round((memory_get_usage(true) - $startMemory) / 1024 / 1024, 1);

        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status' => $response->getStatusCode(),
            'total_ms' => $totalMs,
            'db_ms' => round($dbTime, 2),
            'db_pct' => $totalTime > 0 ? round(($dbTime / $totalTime) * 100) : 0,
            'query_count' => $queryCount,
            'memory_mb' => $memoryUsed,
            'user_id' => auth()->id(),
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 200),
        ];

        // Add route info if available
        if ($request->route()) {
            $logData['route'] = $request->route()->getName() ?? $request->route()->uri();
            $logData['route_action'] = $request->route()->getActionName();
        }

        // Determine bottleneck
        $logData['bottleneck'] = $this->determineBottleneck($dbTime, $totalTime, $queryCount);

        // Add Club-specific context if club ID in route
        $clubId = $this->extractClubId($request);
        if ($clubId) {
            $logData['club_id'] = $clubId;
        }

        Log::channel('slow_api')->warning('Slow API response (>500ms)', $logData);

        return $response;
    }

    /**
     * Check if slow API logging should be active.
     */
    private function shouldLog(): bool
    {
        $env = config('app.env', 'production');
        $enabled = env('APP_LOG_SLOW_API', false);

        // Always log in local/dev/staging
        if (in_array($env, ['local', 'development', 'dev', 'staging'], true)) {
            return true;
        }

        // In production, only log if explicitly enabled
        return $enabled === true || $enabled === 'true';
    }

    /**
     * Get total DB time from query log.
     */
    private function getDbTime(): float
    {
        $logs = DB::getQueryLog();
        if (empty($logs)) {
            return 0;
        }
        $total = 0;
        foreach ($logs as $log) {
            $total += ($log['time'] ?? 0) / 1000;
        }
        return $total;
    }

    /**
     * Get current query count.
     */
    private function getQueryCount(): int
    {
        return DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
    }

    /**
     * Determine bottleneck type based on profiling data.
     */
    private function determineBottleneck(float $dbTime, float $totalTime, int $queryCount): string
    {
        if ($totalTime === 0) {
            return 'none';
        }

        $dbPct = ($dbTime / $totalTime) * 100;

        if ($dbPct > 60) {
            return 'db';
        }
        if ($queryCount > 20) {
            return 'n_plus_one';
        }
        if ($dbPct > 40) {
            return 'db_partial';
        }

        return 'php';
    }

    /**
     * Extract club ID from route parameters.
     */
    private function extractClubId(Request $request): ?int
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        // Common parameter names for club ID
        foreach (['clubId', 'club_id', 'id'] as $param) {
            $value = $route->parameter($param);
            if ($value !== null && is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }
}
