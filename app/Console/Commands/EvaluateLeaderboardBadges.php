<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Services\BadgeService;
use App\Services\LeaderboardQualifierService;
use App\Models\UserSportScore;
use App\Models\User;
use App\Models\Club;
use Illuminate\Support\Facades\DB;

class EvaluateLeaderboardBadges extends Command
{
    protected $signature = 'badges:evaluate-leaderboards';
    protected $description = 'Evaluate Top Leaderboard milestones (Top 100, Top 50, Top 10, Top 3, #1) globally and in clubs';

    protected array $tiers = [
        1 => 'TOP_1',
        3 => 'TOP_3',
        10 => 'TOP_10',
        50 => 'TOP_50',
        100 => 'TOP_100',
    ];

    public function handle(BadgeService $badgeService, LeaderboardQualifierService $qualifierService)
    {
        $this->info('Starting evaluation of leaderboard badges...');

        // Pickleball sport_id is usually 1, but let's fetch it safely
        $sport = \App\Models\Sport::where('slug', 'pickleball')->first();
        $sportId = $sport ? $sport->id : 1;

        $count = 0;

        // 1. Evaluate System Leaderboard
        $this->info('Evaluating System Leaderboard...');
        $leaderboardController = app(\App\Http\Controllers\LeaderboardController::class);
        $systemLeaderboardData = $leaderboardController->getSystemLeaderboard($sportId, 100, 1, 100);
        $globalLeaderboard = $systemLeaderboardData['items'] ?? [];

        foreach ($globalLeaderboard as $userItem) {
            // $userItem is an array/object returned by getSystemLeaderboard
            $userId = is_array($userItem) ? $userItem['id'] : $userItem->id;
            $rank = is_array($userItem) ? $userItem['rank'] : $userItem->rank;
            $count += $this->awardBadgesBasedOnRank($userId, (int)$rank, $badgeService);
        }

        // 2. Evaluate Club Leaderboards
        $this->info('Evaluating Club Leaderboards...');
        $clubLeaderboardService = app(\App\Services\Club\ClubLeaderboardService::class);
        $clubs = Club::where('status', 'active')->get();
        foreach ($clubs as $club) {
            $clubLeaderboard = $clubLeaderboardService->getLeaderboard($club->id, $sportId);

            foreach ($clubLeaderboard as $userItem) {
                // $userItem is an array returned by getLeaderboard
                $userId = $userItem['id'];
                $rank = $userItem['rank'];
                if ($rank > 100) continue; // Optimization
                $count += $this->awardBadgesBasedOnRank($userId, (int)$rank, $badgeService);
            }
        }

        $this->info("Granted {$count} new leaderboard badges.");
    }

    private function awardBadgesBasedOnRank(int $userId, int $rank, BadgeService $badgeService): int
    {
        $awarded = 0;
        foreach ($this->tiers as $threshold => $code) {
            // If user rank is <= 10, they get TOP_100, TOP_50, TOP_10
            if ($rank <= $threshold) {
                $badge = Badge::where('code', $code)->first();
                if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                    $badgeService->awardBadge($userId, $code);
                    $awarded++;
                }
            }
        }
        return $awarded;
    }
}
