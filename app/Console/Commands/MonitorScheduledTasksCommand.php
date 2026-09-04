<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Heart-beat monitor cho scheduler - phát hiện cron bị stuck/dead.
 *
 * Chạy mỗi 10 phút, ghi lại:
 * - Timestamp chạy gần nhất
 * - Số pending jobs trong queue (database)
 * - Số failed jobs
 *
 * Cho phép kiểm tra nhanh qua:
 *   php artisan tinker --execute="print_r(Cache::get('scheduler:heartbeat'));"
 *
 * Nếu key 'scheduler:heartbeat' quá cũ (>30 phút) → scheduler đã chết.
 */
class MonitorScheduledTasksCommand extends Command
{
    protected $signature = 'system:monitor-tasks';
    protected $description = 'Ghi heart-beat cho scheduler - phát hiện cron bị stuck';

    public function handle(): int
    {
        $pendingJobs = 0;
        $failedJobs = 0;

        try {
            $pendingJobs = (int) DB::table('jobs')->count();
        } catch (\Throwable $e) {
            // Bảng jobs có thể chưa tồn tại nếu chưa chạy migration queue.
            Log::warning('MonitorScheduledTasks: jobs table missing', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            // Bảng failed_jobs có thể chưa tồn tại.
        }

        $payload = [
            'last_run' => now()->toIso8601String(),
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
        ];

        // Cache TTL 30 phút - nếu sau 30 phút key biến mất → scheduler chết.
        Cache::put('scheduler:heartbeat', $payload, 1800);

        $this->info("Heart-beat OK. Pending jobs: {$pendingJobs}, Failed: {$failedJobs}");

        // Cảnh báo sớm nếu queue quá tải.
        if ($pendingJobs > 1000) {
            Log::warning('Queue pending jobs too high', [
                'pending_jobs' => $pendingJobs,
            ]);
        }

        if ($failedJobs > 100) {
            Log::warning('Failed jobs accumulating', [
                'failed_jobs' => $failedJobs,
            ]);
        }

        return self::SUCCESS;
    }
}
