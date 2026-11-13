<?php

namespace App\Services\TournamentType;

use App\Models\Matches;
use App\Services\TournamentService;
use Illuminate\Support\Collection;

/**
 * Service chuyên xử lý logic tính standings/rankings
 */
class StandingsService
{
    /**
     * Tính stats cho đội trong toàn giải
     */
    public function getTeamStats(int $teamId, int $tournamentTypeId): array
    {
        $matches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('status', 'completed')
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->with('results')
            ->get();

        return $this->calculateStatsFromMatches($matches, $teamId);
    }

    /**
     * Tính stats cho đội trong một group cụ thể
     */
    public function getTeamStatsInGroup(int $teamId, int $tournamentTypeId, int $groupId): array
    {
        $matches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('group_id', $groupId)
            ->where('status', 'completed')
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)
                    ->orWhere('away_team_id', $teamId);
            })
            ->with('results')
            ->get();

        return $this->calculateStatsFromMatches($matches, $teamId);
    }

    /**
     * Tính stats từ danh sách matches
     * MỖI LEG THẮNG = 3 ĐIỂM
     */
    public function calculateStatsFromMatches(Collection $matches, int $teamId): array
    {
        // Nếu chưa có trận nào → Trả về stats rỗng
        if ($matches->isEmpty()) {
            return [
                'team_id' => $teamId,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'points' => 0,
                'point_diff' => 0,
                'win_rate' => 0,
            ];
        }

        $totalPoints = 0;
        $wins = 0;
        $draws = 0;
        $losses = 0;
        $pWon = 0;
        $pLost = 0;

        // Tính điểm từng LEG (không group)
        foreach ($matches as $leg) {
            $isRally = $leg->relationLoaded('stages') ? $leg->stages->isNotEmpty() : $leg->stages()->exists();
            if ($isRally) {
                $stages = $leg->relationLoaded('stages') ? $leg->stages : $leg->stages()->orderBy('stage_no')->get();
                $activeStage = $stages->where('status', \App\Models\MatchStage::STATUS_COMPLETED)->last()
                    ?? $stages->filter(fn($s) => $s->cumulative_score_a > 0 || $s->cumulative_score_b > 0)->last()
                    ?? $stages->first();

                $homeScore = (int) ($activeStage->cumulative_score_a ?? 0);
                $awayScore = (int) ($activeStage->cumulative_score_b ?? 0);

                if ($leg->home_team_id == $teamId) {
                    $pWon += $homeScore;
                    $pLost += $awayScore;
                } elseif ($leg->away_team_id == $teamId) {
                    $pWon += $awayScore;
                    $pLost += $homeScore;
                }

                if ($leg->winner_id !== null) {
                    if ((int) $leg->winner_id === (int) $teamId) {
                        $wins++;
                        $totalPoints += 3;
                    } else {
                        $losses++;
                    }
                } else {
                    $myScore = ($leg->home_team_id == $teamId) ? $homeScore : $awayScore;
                    $oppScore = ($leg->home_team_id == $teamId) ? $awayScore : $homeScore;
                    if ($myScore > $oppScore) {
                        $wins++;
                        $totalPoints += 3;
                    } elseif ($myScore < $oppScore) {
                        $losses++;
                    } else {
                        $draws++;
                        $totalPoints += 1;
                    }
                }
                continue;
            }

            $homeSetWins = 0;
            $awaySetWins = 0;

            $sets = $leg->results->groupBy('set_number');
            foreach ($sets as $setGroup) {
                $home = $setGroup->firstWhere('team_id', $leg->home_team_id);
                $away = $setGroup->firstWhere('team_id', $leg->away_team_id);

                $homeScore = (int)($home->score ?? 0);
                $awayScore = (int)($away->score ?? 0);

                if ($homeScore > $awayScore) {
                    $homeSetWins++;
                } elseif ($awayScore > $homeScore) {
                    $awaySetWins++;
                }

                // Cộng dồn điểm cho tính point diff
                if ($leg->home_team_id == $teamId) {
                    $pWon += $homeScore;
                    $pLost += $awayScore;
                } elseif ($leg->away_team_id == $teamId) {
                    $pWon += $awayScore;
                    $pLost += $homeScore;
                }
            }

            // Xác định thắng/thua/hòa cho LEG này
            $isMyTeamHome = ($leg->home_team_id == $teamId);
            $mySetWins = $isMyTeamHome ? $homeSetWins : $awaySetWins;
            $opponentSetWins = $isMyTeamHome ? $awaySetWins : $homeSetWins;

            if ($mySetWins > $opponentSetWins) {
                // Thắng LEG này → +3 điểm
                $wins++;
                $totalPoints += 3;
            } elseif ($mySetWins == $opponentSetWins) {
                // Hòa LEG này → +1 điểm
                $draws++;
                $totalPoints += 1;
            } else {
                // Thua LEG này → +0 điểm
                $losses++;
            }
        }

        $played = $matches->count(); // Số leg đã chơi

        return [
            'team_id' => $teamId,
            'played' => $played,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'points' => $totalPoints,
            'points_for' => $pWon,
            'points_against' => $pLost,
            'point_diff' => $pWon - $pLost,
            'win_rate' => $played > 0 ? round(($wins / $played) * 100, 2) : 0,
        ];
    }

    /**
     * Tính standings cho group
     */
    public function calculateGroupStandings(Collection $groupMatches): Collection
    {
        return TournamentService::calculateGroupStandings($groupMatches);
    }

    /**
     * Sắp xếp rankings theo thứ tự: Points → Point Diff → Wins
     */
    public function sortRankings(Collection $rankings): Collection
    {
        return $rankings->sortByDesc(function ($item) {
            return [
                $item['points'],
                $item['point_diff'],
                $item['wins'],
            ];
        })->values();
    }
}
