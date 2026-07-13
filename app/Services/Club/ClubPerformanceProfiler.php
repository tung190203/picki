<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 0: Performance Profiler for Club module.
 *
 * Inject timing marks into hot paths to identify bottleneck:
 *   - DB query time
 *   - Model hydration time
 *   - Resource serialization time
 *   - JSON encode time
 *
 * Usage:
 *   $profiler = app(ClubPerformanceProfiler::class);
 *   $profiler->start('club_detail', $club->id);
 *   // ... code to profile ...
 *   $profiler->mark('db');
 *   // ... more code ...
 *   $profiler->mark('resource');
 *   // ... more code ...
 *   $profiler->end();
 */
class ClubPerformanceProfiler
{
    /** @var array<string, array> */
    private array $marks = [];

    private string $label = '';

    private ?int $clubId = null;

    private float $startTime = 0;

    private float $encodeStartTime = 0;

    private int $queryCount = 0;

    private ?string $url = null;

    private ?int $userId = null;

    /**
     * Start profiling a request.
     */
    public function start(string $label, ?int $clubId = null): void
    {
        $this->label = $label;
        $this->clubId = $clubId;
        $this->startTime = microtime(true);
        $this->marks = [];
        $this->queryCount = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
        $this->url = request()->fullUrl();
        $this->userId = auth()->id();
    }

    /**
     * Mark a phase checkpoint.
     */
    public function mark(string $phase): void
    {
        $this->marks[$phase] = microtime(true);
    }

    /**
     * Start JSON encode phase (call this BEFORE json_encode).
     */
    public function startEncode(): void
    {
        $this->encodeStartTime = microtime(true);
    }

    /**
     * End profiling and log results.
     *
     * @param  mixed  $data  The data that will be json_encoded
     * @param  array  $extra  Additional context
     */
    public function end($data = null, array $extra = []): void
    {
        $totalTime = microtime(true) - $this->startTime;
        $totalMs = round($totalTime * 1000);

        $phases = [];
        $prevTime = $this->startTime;

        foreach ($this->marks as $phase => $time) {
            $phaseMs = round(($time - $prevTime) * 1000);
            $pct = $totalTime > 0 ? round(($phaseMs / $totalMs) * 100) : 0;
            $phases[$phase] = [
                'ms' => $phaseMs,
                'pct' => $pct,
            ];
            $prevTime = $time;
        }

        $encodeMs = 0;
        if ($this->encodeStartTime > 0 && $data !== null) {
            $responseSize = strlen(json_encode($data));
            $encodeMs = round((microtime(true) - $this->encodeStartTime) * 1000);
            $extra['response_kb'] = round($responseSize / 1024, 1);
        }

        $dbTime = $this->getDbTime();
        $dbPct = $totalTime > 0 ? round(($dbTime / $totalTime) * 100) : 0;
        $serializationMs = $this->getSerializationTime($phases);
        $hydrationMs = $this->getHydrationTime($phases);

        // Determine bottleneck
        $bottleneck = $this->determineBottleneck($dbTime, $hydrationMs, $serializationMs, $encodeMs, $totalMs);

        Log::channel('club_performance')->info('Club performance profile', array_merge([
            'label' => $this->label,
            'club_id' => $this->clubId,
            'url' => $this->url,
            'user_id' => $this->userId,
            'total_ms' => $totalMs,
            'db_ms' => round($dbTime * 1000),
            'db_pct' => $dbPct,
            'hydration_ms' => $hydrationMs,
            'serialization_ms' => $serializationMs,
            'encode_ms' => $encodeMs,
            'query_count' => $this->getQueryCount(),
            'phases' => $phases,
            'bottleneck' => $bottleneck,
        ], $extra));

        $this->reset();
    }

    /**
     * Profile a closure and return result.
     *
     * @template T
     * @param  string  $label  Phase label
     * @param  callable(): T  $callback
     * @return T
     */
    public function profile(string $label, callable $callback)
    {
        $start = microtime(true);
        $result = $callback();
        $this->marks[$label] = microtime(true) - $start;
        return $result;
    }

    /**
     * Get DB time from query log.
     */
    private function getDbTime(): float
    {
        $logs = DB::getQueryLog();
        if (empty($logs)) {
            return 0;
        }
        $total = 0;
        foreach ($logs as $log) {
            $total += ($log['time'] ?? 0) / 1000; // convert ms to s
        }
        return $total;
    }

    /**
     * Get query count at end vs start.
     */
    private function getQueryCount(): int
    {
        $current = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
        return max(0, $current - $this->queryCount);
    }

    /**
     * Estimate serialization time from phases.
     */
    private function getSerializationTime(array $phases): int
    {
        // Serialization is usually the phase after 'db' or the last phase before 'encode'
        if (isset($phases['resource'])) {
            return $phases['resource']['ms'];
        }
        if (isset($phases['serialize'])) {
            return $phases['serialize']['ms'];
        }
        return 0;
    }

    /**
     * Estimate hydration time from phases.
     */
    private function getHydrationTime(array $phases): int
    {
        if (isset($phases['hydrate'])) {
            return $phases['hydrate']['ms'];
        }
        return 0;
    }

    /**
     * Determine which phase is the bottleneck.
     */
    private function determineBottleneck(float $dbTime, float $hydrationMs, float $serializationMs, float $encodeMs, float $totalMs): string
    {
        $totalS = $totalMs / 1000;
        if ($totalS === 0) {
            return 'none';
        }

        $dbPct = ($dbTime / $totalS) * 100;
        $hydrationPct = ($hydrationMs / $totalMs) * 100;
        $serializationPct = ($serializationMs / $totalMs) * 100;
        $encodePct = ($encodeMs / $totalMs) * 100;

        if ($dbPct > 50) {
            return 'db';
        }
        if ($hydrationPct > 30) {
            return 'hydration';
        }
        if ($serializationPct > 20) {
            return 'serialization';
        }
        if ($encodePct > 20) {
            return 'encode';
        }
        return 'none';
    }

    /**
     * Reset profiler state.
     */
    public function reset(): void
    {
        $this->marks = [];
        $this->label = '';
        $this->clubId = null;
        $this->startTime = 0;
        $this->encodeStartTime = 0;
        $this->queryCount = 0;
        $this->url = null;
        $this->userId = null;
    }

    /**
     * Quick profile for ClubDetail — wraps getClubDetail + resource + encode.
     * Returns array of timing data WITHOUT logging (for use in tests/benchmarks).
     *
     * @return array{db_ms: int, resource_ms: int, encode_ms: int, total_ms: int, query_count: int, bottleneck: string}
     */
    public function quickProfile(callable $callback): array
    {
        $start = microtime(true);

        // Capture query count before
        $beforeQueries = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;
        $dbStart = microtime(true);
        $result = $callback();
        $dbTime = microtime(true) - $dbStart;

        $resourceStart = microtime(true);
        if ($result instanceof \Illuminate\Http\Resources\Json\JsonResource) {
            $result = $result->response()->getContent();
        }
        $resourceTime = microtime(true) - $resourceStart;

        $encodeStart = microtime(true);
        $json = json_encode($result);
        $encodeTime = microtime(true) - $encodeStart;

        $totalTime = microtime(true) - $start;
        $afterQueries = DB::getQueryLog() ? count(DB::getQueryLog()) : 0;

        $totalMs = round($totalTime * 1000);
        return [
            'db_ms' => round($dbTime * 1000),
            'resource_ms' => round($resourceTime * 1000),
            'encode_ms' => round($encodeTime * 1000),
            'total_ms' => $totalMs,
            'query_count' => max(0, $afterQueries - $beforeQueries),
            'response_kb' => round(strlen($json) / 1024, 1),
            'bottleneck' => $this->determineBottleneck(
                $dbTime,
                0,
                round($resourceTime * 1000),
                round($encodeTime * 1000),
                $totalMs
            ),
        ];
    }
}
