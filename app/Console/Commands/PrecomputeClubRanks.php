<?php

namespace App\Console\Commands;

use App\Models\Club\Club;
use App\Services\Club\ClubLeaderboardService;
use Illuminate\Console\Command;

class PrecomputeClubRanks extends Command
{
    protected $signature = 'clubs:precompute-ranks';

    protected $description = 'Precompute monthly club rankings for faster leaderboard queries';

    public function handle(ClubLeaderboardService $leaderboardService): int
    {
        $this->info('Precomputing club ranks...');

        $year = now()->year;
        $month = now()->month;

        $ranks = $leaderboardService->precomputeMonthlyClubRanks($month, $year);

        $this->info("Computed ranks for " . count($ranks) . " clubs (Year: {$year}, Month: {$month})");

        return Command::SUCCESS;
    }
}
