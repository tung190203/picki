<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Services\BadgeService;
use App\Services\LeaderboardQualifierService;
use App\Models\UserSportScore;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EvaluateLeaderboardBadges extends Command
{
    protected $signature = 'badges:evaluate-leaderboards
                            {--wipe : Force wipe all existing leaderboard badges before re-evaluating}';
    protected $aliases = ['badges:evaluate-leaderboard'];
    protected $description = 'Evaluate Top Leaderboard milestones (Top 100, Top 50, Top 10, Top 3, #1) globally';

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

        // Fetch Badge models for our tiers
        $badges = Badge::whereIn('code', array_values($this->tiers))->get()->keyBy('code');

        if ($this->option('wipe')) {
            $this->warn('Wiping all existing leaderboard badges...');
            $deleted = UserBadge::whereIn('badge_id', $badges->pluck('id'))->delete();
            $this->info("Wiped {$deleted} existing leaderboard badge records.");
        }

        // 1. Fetch System Leaderboard
        $this->info('Evaluating System Leaderboard...');
        $leaderboardController = app(\App\Http\Controllers\LeaderboardController::class);
        $systemLeaderboardData = $leaderboardController->getSystemLeaderboard($sportId, 100, 1, 100);
        $globalLeaderboard = $systemLeaderboardData['items'] ?? [];

        // Build list of valid user_ids for each badge tier based on current system leaderboard
        $validAssignments = [];
        $parsedUsers = [];

        foreach ($globalLeaderboard as $userItem) {
            $userId = (int) (is_array($userItem) ? ($userItem['id'] ?? $userItem['user_id'] ?? 0) : ($userItem->id ?? $userItem->user_id ?? 0));
            $rank = (int) (is_array($userItem) ? ($userItem['rank'] ?? 0) : ($userItem->rank ?? 0));

            if (!$userId || !$rank) {
                continue;
            }

            $parsedUsers[] = ['user_id' => $userId, 'rank' => $rank];

            foreach ($this->tiers as $threshold => $code) {
                if ($rank <= $threshold && isset($badges[$code])) {
                    $validAssignments[$badges[$code]->id][] = $userId;
                }
            }
        }

        // 2. Revoke invalid / stale leaderboard badges
        // Any user who currently has a TOP_* badge but does not qualify on the system leaderboard is revoked.
        $this->info('Revoking invalid / stale leaderboard badges...');
        $revokedCount = 0;
        foreach ($badges as $code => $badge) {
            $validUserIds = $validAssignments[$badge->id] ?? [];
            $query = UserBadge::where('badge_id', $badge->id);
            if (!empty($validUserIds)) {
                $query->whereNotIn('user_id', $validUserIds);
            }
            $revokedCount += $query->delete();
        }
        $this->info("Revoked {$revokedCount} invalid/stale leaderboard badge(s).");

        // 3. Award badges to qualifying users
        $awardedCount = 0;
        foreach ($parsedUsers as $item) {
            $awardedCount += $this->awardBadgesBasedOnRank($item['user_id'], $item['rank'], $badgeService, $badges);
        }

        $this->info("Granted {$awardedCount} new leaderboard badges.");
    }

    private function awardBadgesBasedOnRank(int $userId, int $rank, BadgeService $badgeService, $badges): int
    {
        $awarded = 0;
        foreach ($this->tiers as $threshold => $code) {
            if ($rank <= $threshold && isset($badges[$code])) {
                $badge = $badges[$code];
                if (!UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                    $badgeService->awardBadge($userId, $code);
                    $awarded++;
                }
            }
        }
        return $awarded;
    }
}
