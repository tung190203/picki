<?php

namespace App\Console;

use App\Jobs\SendMiniTournamentDraftRemindersJob;
use App\Jobs\SendMiniTournamentRemindersJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Lưu ý quan trọng:
     *  - KHÔNG dùng closure (`$schedule->call(fn() => ...)`) - không gọi được
     *    runInBackground()/withoutOverlapping() và làm chậm schedule:run.
     *  - KHÔNG dùng `$schedule->job(new X())->runInBackground()` - trong Laravel 10
     *    `job()` trả về CallbackEvent nên runInBackground() throw RuntimeException.
     *  - Cách an toàn: dùng `$schedule->command(...)` cho mọi command, có thể thêm
     *    runInBackground()/withoutOverlapping() tùy ý.
     *
     *  Các Jobs (Reminder) sẽ được dispatch qua command wrapper
     *  `app:dispatch-job SendMiniTournamentRemindersJob` để chạy nền đúng cách.
     */
    protected function schedule(Schedule $schedule): void
    {
        // === Reminders (mỗi 2 phút) ===
        // Wrap job trong command để chạy được runInBackground() trong Laravel 10.
        $schedule->command('app:dispatch-job ' . SendMiniTournamentRemindersJob::class)
            ->everyTwoMinutes()
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule-reminders.log'));

        $schedule->command('app:dispatch-job ' . SendMiniTournamentDraftRemindersJob::class)
            ->everyTwoMinutes()
            ->withoutOverlapping(180)
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/schedule-draft-reminders.log'));

        // === Notification commands (mỗi 2 phút) ===
        $schedule->command('system:send-notifications')
            ->everyTwoMinutes()
            ->withoutOverlapping(180)
            ->runInBackground();

        $schedule->command('clubs:send-scheduled-notifications')
            ->everyTwoMinutes()
            ->withoutOverlapping(180)
            ->runInBackground();

        $schedule->command('admin-push-notifications:process-scheduled')
            ->everyTwoMinutes()
            ->withoutOverlapping(180)
            ->runInBackground();

        // === Auto-close commands (mỗi 2 phút) ===
        $schedule->command('tournaments:auto-close')
            ->everyTwoMinutes()
            ->withoutOverlapping(180)
            ->runInBackground();

        $schedule->command('mini-tournaments:auto-close')
            ->everyTwoMinutes()
            ->withoutOverlapping(180)
            ->runInBackground();

        // === Payment + Online status (mỗi 5 phút, có batch limit) ===
        $schedule->command('mini-tournaments:create-auto-payments --limit=50')
            ->everyFiveMinutes()
            ->withoutOverlapping(600)
            ->runInBackground();

        $schedule->command('users:sync-online-status --limit=200')
            ->everyFiveMinutes()
            ->withoutOverlapping(600)
            ->runInBackground();

        // === Activities auto-complete (5 phút thay vì 2) ===
        $schedule->command('activities:auto-complete --limit=100')
            ->everyFiveMinutes()
            ->withoutOverlapping(600)
            ->runInBackground();

        // === Cleanup empty tournaments (hourly, có batch limit) ===
        $schedule->command('tournaments:cleanup-empty --limit=200')
            ->hourly()
            ->withoutOverlapping(3600)
            ->runInBackground()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('tournaments:cleanup-empty FAILED at ' . now());
            });

        // === Heart-beat monitor (mỗi 10 phút, rất nhẹ) ===
        $schedule->command('system:monitor-tasks')
            ->everyTenMinutes()
            ->withoutOverlapping(900)
            ->runInBackground();

        // === Daily/Weekly commands ===
        $schedule->command('mini-tournaments:rollover-recurrence')
            ->dailyAt('01:00')
            ->withoutOverlapping(3600)
            ->runInBackground();

        $schedule->command('tournaments:backfill-end-date')
            ->dailyAt('00:05')
            ->runInBackground();

        $schedule->command('clubs:precompute-ranks')
            ->hourly()
            ->withoutOverlapping(3600)
            ->runInBackground();

        $schedule->command('ranks:snapshot-weekly')
            ->weeklyOn(0, '23:59')
            ->withoutOverlapping(60)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('ranks:snapshot-weekly FAILED at ' . now());
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('ranks:snapshot-weekly OK at ' . now());
            });

        $schedule->command('notifications:prune-old --days=30')
            ->dailyAt('02:00')
            ->runInBackground();

        $schedule->command('verification-codes:prune-expired')
            ->dailyAt('02:15')
            ->runInBackground();

        $schedule->command('queue:prune-failed --hours=168')
            ->dailyAt('02:30')
            ->runInBackground();

        // === Device token cleanup (daily) ===
        $schedule->command('device-tokens:cleanup-stale --days=60')
            ->dailyAt('03:30')
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
