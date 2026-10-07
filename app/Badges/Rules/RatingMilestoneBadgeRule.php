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

class RatingMilestoneBadgeRule implements BadgeRuleInterface
{
    protected array $ratingMilestones = [
        2.5 => 'RATING_25',
        3.0 => 'RATING_30',
        3.5 => 'RATING_35',
        4.0 => 'RATING_40',
        4.5 => 'RATING_45',
        5.0 => 'RATING_50',
    ];

    public function badgeCode(): string
    {
        return 'RATING_MILESTONES';
    }

    public function condition($event): bool
    {
        return $event instanceof MatchResultConfirmed;
    }

    public function handle($event): void
    {
        /** @var MatchResultConfirmed $event */
        $match = $event->match;
        $userIds = [];

        // Lấy tất cả user ID tham gia trận đấu
        if ($match instanceof Matches) {
            $userIds = DB::table('team_members')
                ->whereIn('team_id', [$match->home_team_id, $match->away_team_id])
                ->pluck('user_id')->toArray();
        } elseif ($match instanceof MiniMatch) {
            $userIds = DB::table('mini_team_members')
                ->whereIn('mini_team_id', [$match->team1_id, $match->team2_id])
                ->pluck('user_id')->toArray();
            
            if (empty($userIds) && $match->participant1_id && $match->participant2_id) {
                $userIds = [$match->participant1_id, $match->participant2_id];
            }
        } elseif ($match instanceof QuickMatch) {
            $userIds = DB::table('match_histories')
                ->where('quick_match_id', $match->id)
                ->pluck('user_id')->toArray();
        }

        $userIds = array_unique(array_filter($userIds));
        if (empty($userIds)) {
            return;
        }

        // Lấy rating mới nhất của họ
        $users = DB::table('users')
            ->join('user_sport', 'users.id', '=', 'user_sport.user_id')
            ->join('user_sport_scores', 'user_sport_scores.user_sport_id', '=', 'user_sport.id')
            ->whereIn('users.id', $userIds)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->select('users.id', DB::raw('MAX(user_sport_scores.score_value) as max_rating'))
            ->groupBy('users.id')
            ->get();

        $badgeService = app(BadgeService::class);

        foreach ($users as $user) {
            $rating = (float) $user->max_rating;

            foreach ($this->ratingMilestones as $milestone => $code) {
                if ($rating >= $milestone) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $user->id)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge($user->id, $code);
                    }
                }
            }
        }
    }
}