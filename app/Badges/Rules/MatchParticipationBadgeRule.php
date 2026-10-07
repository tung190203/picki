<?php

namespace App\Badges\Rules;

use App\Events\MatchResultConfirmed;
use App\Models\Badge;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;

class MatchParticipationBadgeRule implements BadgeRuleInterface
{
    protected array $tiers = [
        10 => 'MATCH_10',
        100 => 'MATCH_100',
        300 => 'MATCH_300',
        500 => 'MATCH_500',
        1000 => 'MATCH_1000',
        3000 => 'MATCH_3000',
    ];

    public function badgeCode(): string
    {
        return 'MATCH_PARTICIPATION_TIERED';
    }

    public function condition($event): bool
    {
        return $event instanceof MatchResultConfirmed;
    }

    public function handle($event): void
    {
        /** @var MatchResultConfirmed $event */
        $match = $event->match;
        $sportId = 1; // Default to pickleball

        $userIds = [];

        // Lấy tất cả user có tham gia trong trận đấu này
        if ($match instanceof Matches) {
            $sportId = $match->group?->tournamentType?->tournament?->sport_id ?? $sportId;
            $userIds = DB::table('team_members')
                ->whereIn('team_id', [$match->home_team_id, $match->away_team_id])
                ->pluck('user_id')->toArray();
        } elseif ($match instanceof MiniMatch) {
            $sportId = $match->miniTournament?->sport_id ?? $sportId;
            if ($match->team1_id && $match->team2_id) {
                $userIds = DB::table('mini_team_members')
                    ->whereIn('mini_team_id', [$match->team1_id, $match->team2_id])
                    ->pluck('user_id')->toArray();
            } else {
                $userIds = [$match->participant1_id, $match->participant2_id];
            }
        } elseif ($match instanceof QuickMatch) {
            $sportId = $match->sport_id ?? 1;
            $userIds = DB::table('match_histories')
                ->where('quick_match_id', $match->id)
                ->pluck('user_id')->toArray();
        }

        $userIds = array_unique(array_filter($userIds));
        if (empty($userIds)) {
            return;
        }

        $badgeService = app(BadgeService::class);

        // Đọc trường total_matches đã được cache/cộng dồn trong bảng user_sport để xử lý siêu tốc
        $userSports = DB::table('user_sport')
            ->whereIn('user_id', $userIds)
            ->where('sport_id', $sportId)
            ->get(['user_id', 'total_matches'])
            ->keyBy('user_id');

        foreach ($userIds as $userId) {
            if (!isset($userSports[$userId])) {
                continue;
            }

            $totalMatches = (int) $userSports[$userId]->total_matches;

            // Kiểm tra các mốc
            foreach ($this->tiers as $threshold => $code) {
                if ($totalMatches >= $threshold) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge((int) $userId, $code);
                    }
                }
            }
        }
    }
}