<?php

namespace App\Console;

use App\Jobs\SendMiniTournamentRemindersJob;
use App\Models\DeviceToken;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new SendMiniTournamentRemindersJob())->everyMinute();
        $schedule->command('system:send-notifications')->everyMinute();
        $schedule->command('clubs:send-scheduled-notifications')->everyMinute();
        $schedule->call(function () {
            DeviceToken::where('last_seen_at', '<', now()->subDays(60))->delete();
        })->daily();

        $schedule->command('activities:auto-complete')->everyTwoMinutes();
        $schedule->command('mini-tournaments:rollover-recurrence')->daily();
        $schedule->command('mini-tournaments:create-auto-payments')->everyMinute();
        $schedule->command('mini-tournament:finalize-payments')->everyMinute();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
