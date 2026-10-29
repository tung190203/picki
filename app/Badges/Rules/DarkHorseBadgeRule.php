<?php

namespace App\Badges\Rules;

use App\Events\TournamentCompleted;
use App\Models\Team;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\TournamentType\TournamentRankService;

class DarkHorseBadgeRule implements BadgeRuleInterface
{
    public function badgeCode(): string
    {
        return 'DARK_HORSE';
    }

    public function condition($event): bool
    {
        return $event instanceof TournamentCompleted;
    }

    public function handle($event): void
    {
        /** @var TournamentCompleted $event */
        $tournament = $event->tournament;

        $tournamentType = $tournament->tournamentTypes->first();
        if (!$tournamentType) {
            return;
        }

        $teams = Team::with('members')->where('tournament_type_id', $tournamentType->id)->get();
        if ($teams->count() < 9) {
            return;
        }

        $sportId = $tournament->sport_id ?? 1;

        // Lấy tất cả user_id để truy vấn điểm trình (vndupr_score) 1 lần
        $userIds = [];
        foreach ($teams as $team) {
            foreach ($team->members as $member) {
                if ($member->id) {
                    $userIds[] = (int) $member->id;
                }
            }
        }
        $userIds = array_values(array_unique($userIds));
        $userRatings = [];
        if (!empty($userIds)) {
            $scoreRows = \Illuminate\Support\Facades\DB::table('user_sport')
                ->join('user_sport_scores', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
                ->whereIn('user_sport.user_id', $userIds)
                ->where('user_sport.sport_id', (int) $sportId)
                ->where('user_sport_scores.score_type', 'vndupr_score')
                ->select('user_sport.user_id', \Illuminate\Support\Facades\DB::raw('MAX(user_sport_scores.score_value) as rating'))
                ->groupBy('user_sport.user_id')
                ->get();

            foreach ($scoreRows as $row) {
                $userRatings[(int) $row->user_id] = (float) $row->rating;
            }
        }

        // Tính điểm trung bình cho từng đội
        $teamRatings = [];
        foreach ($teams as $team) {
            $sum = 0;
            $count = 0;
            foreach ($team->members as $member) {
                if (isset($userRatings[$member->id]) && $userRatings[$member->id] > 0) {
                    $sum += (float) $userRatings[$member->id];
                    $count++;
                }
            }
            $avgRating = $count > 0 ? ($sum / $count) : 0;
            
            $teamRatings[] = [
                'team_id' => $team->id,
                'avg_rating' => $avgRating,
                'team' => $team
            ];
        }

        // Sắp xếp các đội theo điểm trình độ giảm dần
        usort($teamRatings, function ($a, $b) {
            return $b['avg_rating'] <=> $a['avg_rating'];
        });

        // Tìm Đội Vô Địch
        /** @var TournamentRankService $rankService */
        $rankService = app(TournamentRankService::class);
        $rankings = $rankService->compute((int) $tournamentType->id);
        $overallRankings = $rankings['overall_rankings'] ?? [];

        $championTeamId = null;
        foreach ($overallRankings as $teamInfo) {
            if (!empty($teamInfo['_is_champion'])) {
                $championTeamId = $teamInfo['team_id'];
                break;
            }
        }

        if (!$championTeamId) {
            return;
        }

        // Tìm xem đội vô địch đứng thứ mấy về mặt trình độ (trước khi giải diễn ra / current rating)
        $championRatingRank = 1;
        $currentRank = 1;
        $lastRating = null;
        
        $isDarkHorse = false;
        
        foreach ($teamRatings as $index => $tr) {
            if ($lastRating !== null && $tr['avg_rating'] < $lastRating) {
                $currentRank = $index + 1;
            }
            
            if ($tr['team_id'] == $championTeamId) {
                $championRatingRank = $currentRank;
                break;
            }
            
            $lastRating = $tr['avg_rating'];
        }

        // Yêu cầu: ngoài Top 8 (> 8)
        if ($championRatingRank > 8) {
            /** @var BadgeService $badgeService */
            $badgeService = app(BadgeService::class);
            
            $creatorExists = User::withTrashed()->find($tournament->created_by);
            $createdBy = $creatorExists ? $tournament->created_by : null;

            $championTeam = Team::with('members')->find($championTeamId);
            if ($championTeam) {
                foreach ($championTeam->members as $member) {
                    if (!$member->id) continue;
                    
                    $user = User::withTrashed()->find($member->id);
                    if (!$user) continue;

                    $badgeService->awardBadge((int) $member->id, $this->badgeCode(), $createdBy);
                }
            }
        }
    }
}
