<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;

class EvaluateRatingBadges extends Command
{
    protected $signature = 'badges:evaluate-rating';
    protected $description = 'Evaluate rating milestones (3.0, 3.5, 4.0, etc.) for all users';

    protected array $ratingMilestones = [
        3.0 => 'RATING_30',
        3.5 => 'RATING_35',
        4.0 => 'RATING_40',
        4.5 => 'RATING_45',
        5.0 => 'RATING_50',
    ];

    public function handle(BadgeService $badgeService)
    {
        $this->info('Starting evaluation of rating badges...');

        // Fetch users with their max vndupr score
        $users = DB::table('users')
            ->join('user_sport', 'users.id', '=', 'user_sport.user_id')
            ->join('user_sport_scores', 'user_sport_scores.user_sport_id', '=', 'user_sport.id')
            ->whereNull('users.deleted_at')
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->select('users.id', DB::raw('MAX(user_sport_scores.score_value) as max_rating'))
            ->groupBy('users.id')
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $rating = (float) $user->max_rating;

            foreach ($this->ratingMilestones as $milestone => $code) {
                if ($rating >= $milestone) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $user->id)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge($user->id, $code);
                        $count++;
                    }
                }
            }
        }

        $this->info("Granted {$count} new rating badges.");
    }
}
