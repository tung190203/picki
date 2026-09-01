<?php

namespace App\Console;

use App\Jobs\SendMiniTournamentDraftRemindersJob;
use App\Jobs\SendMiniTournamentRemindersJob;
use App\Models\DeviceToken;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new SendMiniTournamentRemindersJob())
            ->everyMinute()
            ->withoutOverlapping(60);
        $schedule->job(new SendMiniTournamentDraftRemindersJob())
            ->everyMinute()
            ->withoutOverlapping(60);
        $schedule->command('system:send-notifications')->everyMinute();
        $schedule->command('clubs:send-scheduled-notifications')->everyMinute();
        $schedule->call(function () {
            DeviceToken::where('last_seen_at', '<', now()->subDays(60))->delete();
        })->daily();

        $schedule->command('activities:auto-complete')->everyTwoMinutes();
        $schedule->command('tournaments:auto-close')->everyMinute();
        $schedule->command('tournaments:cleanup-empty')->hourly()->withoutOverlapping();
        $schedule->command('mini-tournaments:auto-close')->everyMinute();
        $schedule->command('mini-tournaments:rollover-recurrence')->daily();
        $schedule->command('mini-tournaments:create-auto-payments')->everyMinute();
        $schedule->command('users:sync-online-status')->everyMinute();
        $schedule->command('clubs:precompute-ranks')->hourly();
        $schedule->command('ranks:snapshot-weekly')
            ->weeklyOn(0, '23:59')
            ->withoutOverlapping(60)
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('ranks:snapshot-weekly FAILED at ' . now());
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('ranks:snapshot-weekly OK at ' . now());
            });
        $schedule->command('tournaments:backfill-end-date')->dailyAt('00:05');
        $schedule->command('notifications:prune-old --days=30')->dailyAt('02:00');
        $schedule->command('verification-codes:prune-expired')->dailyAt('02:15');
        $schedule->command('queue:prune-failed --hours=168')->dailyAt('02:30');
        $schedule->command('admin-push-notifications:process-scheduled')
            ->everyMinute()
            ->withoutOverlapping(60);
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
