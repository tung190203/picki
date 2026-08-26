<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOldNotifications extends Command
{
    protected $signature = 'notifications:prune-old {--days=30 : Delete notifications older than N days}';

    protected $description = 'Delete notifications older than the specified number of days';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoffDate = now()->subDays($days);

        $this->info("Pruning notifications older than {$days} days (before {$cutoffDate})...");

        $deleted = DB::table('notifications')
            ->where('created_at', '<', $cutoffDate)
            ->delete();

        $this->info("Successfully pruned {$deleted} old notification(s).");
        return Command::SUCCESS;
    }
}
