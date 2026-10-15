<?php

namespace App\Console\Commands;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Services\LeaderboardQualifierService;
use Illuminate\Console\Command;

class FixQualifiedForRanking extends Command
{
    protected $signature = 'fix:qualified-ranking {--dry-run : Show what would be changed without updating}';
    protected $description = 'Fix qualified_for_ranking flag for all completed matches';

    public function handle(LeaderboardQualifierService $qualifier): int
    {
        $dryRun = $this->option('dry-run');
        $this->info($dryRun ? 'DRY RUN MODE - No changes will be made' : 'LIVE MODE - Changes will be saved');
        $this->info('');

        $totalFixed = 0;

        // Fix tournament matches
        $this->info('=== Tournament Matches ===');
        $totalFixed += $this->fixMatches($qualifier, $dryRun);

        // Fix mini matches
        $this->info('');
        $this->info('=== Mini Matches ===');
        $totalFixed += $this->fixMiniMatches($qualifier, $dryRun);

        $this->info('');
        $this->info('========================================');
        $this->info("Total fixes: {$totalFixed}");
        $this->info('========================================');

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->info('Invalidating qualified user cache...');
            $qualifier->forgetUserIdsCache();
            $this->info('Done!');
        }

        return self::SUCCESS;
    }

    private function fixMatches(LeaderboardQualifierService $qualifier, bool $dryRun): int
    {
        $matches = Matches::where('status', 'completed')
            ->where('is_bye', 0)
            ->get();

        $this->info("Found {$matches->count()} completed tournament matches");

        $fixCount = 0;
        $bar = $this->output->createProgressBar($matches->count());
        $bar->start();

        foreach ($matches as $match) {
            $shouldBeQualified = $qualifier->qualifyMatch($match);
            $currentValue = (bool) $match->qualified_for_ranking;

            if ($shouldBeQualified !== $currentValue) {
                $fixCount++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [DRY] Match #%d: 0 -> 1',
                        $match->id
                    ));
                } else {
                    $match->qualified_for_ranking = $shouldBeQualified;
                    $match->saveQuietly();
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info("Matches needing fix: {$fixCount}");

        return $fixCount;
    }

    private function fixMiniMatches(LeaderboardQualifierService $qualifier, bool $dryRun): int
    {
        $matches = MiniMatch::where('status', 'completed')
            ->whereNotNull('team1_id')
            ->whereNotNull('team2_id')
            ->get();

        $this->info("Found {$matches->count()} completed mini matches");

        $fixCount = 0;
        $bar = $this->output->createProgressBar($matches->count());
        $bar->start();

        foreach ($matches as $match) {
            $shouldBeQualified = $qualifier->qualifyMatch($match);
            $currentValue = (bool) $match->qualified_for_ranking;

            if ($shouldBeQualified !== $currentValue) {
                $fixCount++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [DRY] MiniMatch #%d: 0 -> 1',
                        $match->id
                    ));
                } else {
                    $match->qualified_for_ranking = $shouldBeQualified;
                    $match->saveQuietly();
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line('');
        $this->info("Mini matches needing fix: {$fixCount}");

        return $fixCount;
    }
}
