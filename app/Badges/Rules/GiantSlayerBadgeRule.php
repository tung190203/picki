<?php

namespace App\Badges\Rules;

use App\Events\MatchResultConfirmed;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;

class GiantSlayerBadgeRule implements BadgeRuleInterface
{
    public function badgeCode(): string
    {
        return 'GIANT_SLAYER';
    }

    public function condition($event): bool
    {
        return $event instanceof MatchResultConfirmed;
    }

    public function handle($event): void
    {
        /** @var MatchResultConfirmed $event */
        $match = $event->match;
        
        $winnerUserIds = [];
        $loserUserIds = [];
        $sportId = null;

        // Phân tách thành viên thắng và thua dựa theo loại match
        if ($match instanceof Matches) {
            $sportId = $match->group?->tournamentType?->tournament?->sport_id ?? 1;
            $winnerTeamId = $match->winner_id;
            
            if (!$winnerTeamId) return;

            $loserTeamId = ($match->home_team_id == $winnerTeamId) ? $match->away_team_id : $match->home_team_id;

            $winnerUserIds = DB::table('team_members')->where('team_id', $winnerTeamId)->pluck('user_id')->toArray();
            $loserUserIds = DB::table('team_members')->where('team_id', $loserTeamId)->pluck('user_id')->toArray();
            
        } elseif ($match instanceof MiniMatch) {
            $sportId = $match->miniTournament?->sport_id ?? 1;
            
            if ($match->team_win_id) {
                // Đấu đội
                $winnerTeamId = $match->team_win_id;
                $loserTeamId = ($match->team1_id == $winnerTeamId) ? $match->team2_id : $match->team1_id;

                $winnerUserIds = DB::table('mini_team_members')->where('mini_team_id', $winnerTeamId)->pluck('user_id')->toArray();
                $loserUserIds = DB::table('mini_team_members')->where('mini_team_id', $loserTeamId)->pluck('user_id')->toArray();
            } elseif ($match->participant_win_id) {
                // Đấu đơn
                $winnerId = $match->participant_win_id;
                $loserId = ($match->participant1_id == $winnerId) ? $match->participant2_id : $match->participant1_id;
                
                $winnerUserIds = [$winnerId];
                $loserUserIds = [$loserId];
            } else {
                return;
            }

        } elseif ($match instanceof QuickMatch) {
            $sportId = $match->sport_id ?? 1;
            $winnerSide = $match->winner; // 'team_a' or 'team_b'

            if (!$winnerSide) return;

            $loserSide = ($winnerSide == 'team_a') ? 'team_b' : 'team_a';

            $winnerUserIds = DB::table('match_histories')
                ->where('quick_match_id', $match->id)
                ->where('team_side', $winnerSide)
                ->pluck('user_id')->toArray();
                
            $loserUserIds = DB::table('match_histories')
                ->where('quick_match_id', $match->id)
                ->where('team_side', $loserSide)
                ->pluck('user_id')->toArray();
        }

        if (empty($winnerUserIds) || empty($loserUserIds)) {
            return;
        }

        // Lấy trình độ (rating) của các thành viên
        $allUserIds = array_unique(array_merge($winnerUserIds, $loserUserIds));
        $ranks = User::getBatchVNRanks($allUserIds, $sportId);

        // Hàm tính trung bình trình độ của 1 team
        $calculateAvgRating = function ($userIds) use ($ranks) {
            $sum = 0;
            $count = 0;
            foreach ($userIds as $uid) {
                if (isset($ranks[$uid]) && $ranks[$uid] > 0) {
                    $sum += (float) $ranks[$uid];
                    $count++;
                }
            }
            return $count > 0 ? ($sum / $count) : 0;
        };

        $winnerAvgRating = $calculateAvgRating($winnerUserIds);
        $loserAvgRating = $calculateAvgRating($loserUserIds);

        // Nếu bất kỳ đội nào có rating = 0 (tức là thành viên chưa có hạng), thì bỏ qua logic phát huy hiệu.
        if ($winnerAvgRating <= 0 || $loserAvgRating <= 0) {
            return;
        }

        // Kiểm tra điều kiện: Thắng người có trình cao hơn mình >= 0.5
        // Nghĩa là: trình của đội thắng <= trình của đội thua - 0.5
        if ($winnerAvgRating <= ($loserAvgRating - 0.5)) {
            /** @var BadgeService $badgeService */
            $badgeService = app(BadgeService::class);

            foreach ($winnerUserIds as $uid) {
                if (!$uid) continue;
                $badgeService->awardBadge((int) $uid, $this->badgeCode());
            }
        }
    }
}
