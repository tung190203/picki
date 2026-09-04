<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Dispatch một Job class qua console để có thể chạy từ scheduler
 * với runInBackground() (vì $schedule->job() trong Laravel 10 trả về CallbackEvent).
 *
 * Cú pháp:
 *   php artisan app:dispatch-job "App\Jobs\SendMiniTournamentRemindersJob"
 */
class DispatchJobCommand extends Command
{
    protected $signature = 'app:dispatch-job {jobClass : FQCN của Job class cần chạy}';

    protected $description = 'Dispatch một Job class - dùng làm wrapper cho scheduler runInBackground';

    public function handle(): int
    {
        $jobClass = $this->argument('jobClass');

        if (!class_exists($jobClass)) {
            $this->error("Class không tồn tại: {$jobClass}");
            return self::FAILURE;
        }

        try {
            $instance = app($jobClass);
        } catch (\Throwable $e) {
            $this->error("Không thể khởi tạo {$jobClass}: " . $e->getMessage());
            return self::FAILURE;
        }

        if (!method_exists($instance, 'handle')) {
            $this->error("Class {$jobClass} không có method handle()");
            return self::FAILURE;
        }

        try {
            $instance->handle();
            $this->info("Job {$jobClass} đã chạy xong.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Job {$jobClass} thất bại: " . $e->getMessage());
            report($e);
            return self::FAILURE;
        }
    }
}
