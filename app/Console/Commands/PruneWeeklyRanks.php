<?php

namespace App\Console\Commands;

use App\Models\WeeklyRank;
use Illuminate\Console\Command;

class PruneWeeklyRanks extends Command
{
    protected $signature = 'ranks:prune-weekly {--keep=2 : Number of recent historical weekly snapshots to retain}';

    protected $description = 'Prune old weekly rank records, keeping only the N most recent historical snapshots';

    public function handle(): int
    {
        $keep = max(1, (int) $this->option('keep'));
        $this->info("Pruning old weekly ranks (retaining top {$keep} historical snapshot(s))...");

        $distinctDates = WeeklyRank::query()
            ->whereNotNull('recorded_at')
            ->select('recorded_at')
            ->distinct()
            ->orderByDesc('recorded_at')
            ->pluck('recorded_at');

        if ($distinctDates->count() <= $keep) {
            $this->info("No pruning needed. Historical snapshot count ({$distinctDates->count()}) is <= {$keep}.");
            return Command::SUCCESS;
        }

        $datesToKeep = $distinctDates->take($keep);
        $oldestKeptDate = $datesToKeep->last();

        $deleted = WeeklyRank::query()
            ->whereNotNull('recorded_at')
            ->where('recorded_at', '<', $oldestKeptDate)
            ->delete();

        $this->info("Successfully pruned {$deleted} old weekly rank record(s) older than {$oldestKeptDate}.");
        return Command::SUCCESS;
    }
}
