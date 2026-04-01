<?php

namespace App\Services\TournamentType;

use App\Models\Matches;
use App\Models\TournamentType;
use App\Services\TournamentService;
use Illuminate\Support\Collection;

/**
 * Service chuyên xử lý logic bracket và score calculation
 */
class BracketService
{
    /**
     * Tính chi tiết điểm số cho một leg
     */
    public function calculateLegDetails($leg): array
    {
        $homeTeamId = $leg->home_team_id;
        $awayTeamId = $leg->away_team_id;

        $sets = [];
        $homeSetWins = 0;
        $awaySetWins = 0;

        $groupedSets = $leg->results->groupBy('set_number');

        foreach ($groupedSets as $setNumber => $setGroup) {
            $home = $setGroup->firstWhere('team_id', $homeTeamId);
            $away = $setGroup->firstWhere('team_id', $awayTeamId);

            $homeScore = (int) ($home->score ?? 0);
            $awayScore = (int) ($away->score ?? 0);

            if ($homeScore > $awayScore) {
                $homeSetWins++;
            } elseif ($awayScore > $homeScore) {
                $awaySetWins++;
            }

            $sets['set_' . $setNumber] = [
                ['team_id' => $homeTeamId, 'score' => $homeScore],
                ['team_id' => $awayTeamId, 'score' => $awayScore],
            ];
        }

        // Xác định winner
        if ($homeSetWins > $awaySetWins) {
            return [
                'sets' => $sets,
                'home_score_calculated' => 3,
                'away_score_calculated' => 0,
                'winner_team_id' => $homeTeamId,
            ];
        }

        if ($awaySetWins > $homeSetWins) {
            return [
                'sets' => $sets,
                'home_score_calculated' => 0,
                'away_score_calculated' => 3,
                'winner_team_id' => $awayTeamId,
            ];
        }

        // Hòa hoặc chưa có kết quả
        return [
            'sets' => $sets,
            'home_score_calculated' => 0,
            'away_score_calculated' => 0,
            'winner_team_id' => null,
        ];
    }

    /**
     * Tính tổng điểm aggregate cho nhiều legs
     */
    public function calculateAggregateScore(
        Collection $matchGroup,
        int $homeTeamId,
        int $awayTeamId
    ): array {
        $homeTotal = 0;
        $awayTotal = 0;

        foreach ($matchGroup as $leg) {
            if ($leg->status === 'completed') {
                $details = $this->calculateLegDetails($leg);

                if ($details['winner_team_id'] === $homeTeamId) {
                    $homeTotal += 3;
                } elseif ($details['winner_team_id'] === $awayTeamId) {
                    $awayTotal += 3;
                }
            }
        }

        return [
            'home' => $homeTotal,
            'away' => $awayTotal,
        ];
    }

    /**
     * Xác định winner dựa trên aggregate score
     */
    public function determineWinner(
        int $homeTotal,
        int $awayTotal,
        ?int $homeTeamId,
        ?int $awayTeamId,
        $firstMatch,
        Collection $matchGroup
    ): ?int {
        // Nếu chưa hoàn thành hết các legs
        if (!$matchGroup->every(fn($l) => $l->status === 'completed')) {
            return null;
        }

        if ($homeTotal > $awayTotal) {
            return $homeTeamId;
        }

        if ($awayTotal > $homeTotal) {
            return $awayTeamId;
        }

        // TH Hòa → Kiểm tra advance thủ công
        if ($homeTotal === $awayTotal && $firstMatch->next_match_id) {
            $nextMatch = Matches::find($firstMatch->next_match_id);
            if ($nextMatch) {
                $advancedTeamId = ($firstMatch->next_position === 'home')
                    ? $nextMatch->home_team_id
                    : $nextMatch->away_team_id;

                if ($advancedTeamId === $homeTeamId) {
                    return $homeTeamId;
                }
                if ($advancedTeamId === $awayTeamId) {
                    return $awayTeamId;
                }
            }
        }

        return null;
    }

    /**
     * Format team data cho response
     */
    public function formatTeam($team, ?string $placeholderText = null, ?int $tournamentId = null): ?array
    {
        return TournamentService::formatTeam($team, $placeholderText, $tournamentId);
    }

    /**
     * Lấy tên round dựa trên số cặp đấu
     */
    public function getRoundName(int $round, int $pairCount, int $format): string
    {
        if ($round === 1 && $format == TournamentType::FORMAT_MIXED) {
            return 'Vòng bảng';
        }

        return match ($pairCount) {
            1 => 'Chung kết',
            2 => 'Bán kết',
            4 => 'Tứ kết',
            8 => 'Vòng 1/8',
            16 => 'Vòng 1/16',
            32 => 'Vòng 1/32',
            default => "Vòng {$round}",
        };
    }

    /**
     * Lấy text cho ranking
     */
    public function getRankText(int $rank): string
    {
        return match($rank) {
            1 => 'Nhất',
            2 => 'Nhì',
            3 => 'Ba',
            4 => 'Tư',
            default => "Hạng {$rank}",
        };
    }

    /**
     * Tính điểm set thắng cho single match
     */
    public function calculateSingleMatchWins($match): array
    {
        $homeWins = 0;
        $awayWins = 0;
        $sets = $match->results->groupBy('set_number');

        foreach ($sets as $set) {
            $h = $set->firstWhere('team_id', $match->home_team_id);
            $a = $set->firstWhere('team_id', '!=', $match->home_team_id);

            if ((int)($h->score ?? 0) > (int)($a->score ?? 0)) {
                $homeWins++;
            } elseif ((int)($a->score ?? 0) > (int)($h->score ?? 0)) {
                $awayWins++;
            }
        }

        return ['home' => $homeWins, 'away' => $awayWins];
    }
}
