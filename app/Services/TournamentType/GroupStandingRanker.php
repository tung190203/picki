<?php

namespace App\Services\TournamentType;

use App\Models\Group;
use App\Models\Matches;
use Illuminate\Support\Collection;

/**
 * Helper tính BXH cho 1 group có áp dụng ranking rules đã cấu hình.
 *
 * Mục đích:
 * - Xác định 1st/2nd/3rd trong từng bảng theo ranking rules + HEAD_TO_HEAD.
 * - KHÔNG dùng TournamentService::calculateGroupStandings() vì method đó
 *   thiếu rule HEAD_TO_HEAD → khi 2 đội bằng điểm + bằng hiệu số thì xếp sai.
 *
 * Dùng cho:
 * - CrossGroupComparisonService::buildCandidates (xác định Nhì/Ba từng bảng
 *   để build candidate pool cho cross-group ranking).
 * - TeamPairingService::arrangeManual (resolve Nhì/Ba theo standings).
 * - Các chỗ khác muốn xác định thứ hạng chuẩn theo ranking rules đã config.
 *
 * Lưu ý:
 * - "Source of truth" cho thứ hạng nội bộ của từng bảng là ranking rules đã được
 *   config trong `format_specific_config[0].ranking`. Cross-group ranking
 *   cũng dùng chính các rule này.
 * - `exclude_bottom_team_matches` KHÔNG ảnh hưởng tới thứ hạng nội bộ — chỉ
 *   ảnh hưởng stats dùng cho cross-group comparison (xử lý ở CrossGroupComparisonService).
 */
class GroupStandingRanker
{
    /**
     * Tính BXH cho 1 group, có áp dụng ranking rules + HEAD_TO_HEAD.
     *
     * Mỗi entry trả về có shape:
     *   - 'rank': int (1-based, 1 = Nhất)
     *   - 'team_id': int
     *   - 'team_name': string|null
     *   - 'played', 'wins', 'draws', 'losses': int
     *   - 'points': int (Thắng=3, Hòa=1, Thua=0)
     *   - 'point_diff': int (points_for - points_against, theo hiệu số điểm trong set)
     *   - 'win_rate': float
     *
     * @param  Group $group
     * @param  array $rankingRules  Mảng rule ID đã chuẩn hóa (đã bao gồm fallback POINTS_WON + HEAD_TO_HEAD nếu thiếu)
     * @return Collection<int, array> Đã sort, có 'rank'
     */
    public static function rank(Group $group, array $rankingRules): Collection
    {
        $matches = $group->matches()->where('status', Matches::STATUS_COMPLETED)->get();

        if ($matches->isEmpty()) {
            return collect();
        }

        // ✅ FIX: Khởi tạo stats theo thứ tự teams trong group (group_team.order)
        // — GIỐNG TournamentTypeController::getRank() dùng `$group->teams()->get()`.
        //
        // Lý do: Nếu dùng thứ tự xuất hiện trong matches, thứ tự khởi tạo sẽ khác
        // nhau giữa các lần chạy/khác với getRank(). Khi H2H tạo thành cycle
        // (3 đội đồng hạng: A thắng B, B thắng C, C thắng A), PHP usort không
        // stable → kết quả cuối cùng phụ thuộc thứ tự khởi tạo ban đầu.
        //
        // Dùng `$group->teams()` (đã orderBy group_team.order) đảm bảo thứ tự
        // khởi tạo giống getRank() → kết quả sort giống nhau → nhất quán với API /rank.
        $teams = $group->teams()->orderBy('group_team.order')->get();
        $teamStats = collect();
        foreach ($teams as $team) {
            $teamStats->push(self::emptyStats($team->id, $team));
        }

        // Tính stats từ matches (giữ nguyên thứ tự khởi tạo để kết quả sort deterministic)
        $teamStats = self::buildStatsFromMatches($matches, $teamStats);

        // Sort theo rankingRules + HEAD_TO_HEAD
        $sorted = $teamStats->sort(function (array $a, array $b) use ($rankingRules, $matches) {
            return self::compareByRankingRules($a, $b, $rankingRules, $matches);
        })->values();

        // Gắn rank (1-based)
        return $sorted->map(function (array $entry, int $idx) {
            $entry['rank'] = $idx + 1;
            return $entry;
        });
    }

    /**
     * Lấy team ở đúng hạng trong group (1-based).
     * Trả null nếu group không có team ở hạng đó.
     *
     * @param  Group $group
     * @param  int   $rank         1-based rank (1 = Nhất, 2 = Nhì, 3 = Ba, ...)
     * @param  array $rankingRules
     * @return array|null
     */
    public static function getTeamAtRank(Group $group, int $rank, array $rankingRules): ?array
    {
        $standings = self::rank($group, $rankingRules);
        return $standings->get($rank - 1);
    }

    /**
     * Helper: build map rank (1-based) → team info cho 1 group.
     *
     * Trả về array dạng:
     *   [
     *     1 => ['team_id' => int|null, 'team_name' => string|null],
     *     2 => ['team_id' => int|null, 'team_name' => string|null],
     *     ...
     *   ]
     *
     * Dùng ở các chỗ cần lookup team theo rank đã được sort sẵn bằng
     * ranking rules + HEAD_TO_HEAD (đã bao gồm fallback POINTS_WON + HEAD_TO_HEAD).
     *
     * Lưu ý: Bắt buộc rankingRules phải đã được chuẩn hóa (đã bao gồm fallback
     * HEAD_TO_HEAD nếu thiếu) — gọi y hệt như getRank/recalculateRankings.
     *
     * @param  Group $group
     * @param  array $rankingRules
     * @return array<int, array{team_id: int|null, team_name: string|null}>
     */
    public static function standingsByRankMap(Group $group, array $rankingRules): array
    {
        $standings = self::rank($group, $rankingRules);

        $map = [];
        foreach ($standings as $entry) {
            $rank = (int) ($entry['rank'] ?? 0);
            if ($rank <= 0) {
                continue;
            }
            $map[$rank] = [
                'team_id' => isset($entry['team_id']) ? (int) $entry['team_id'] : null,
                'team_name' => $entry['team_name'] ?? null,
            ];
        }

        return $map;
    }

    /**
     * Tính stats cho từng team từ 1 collection các match.
     *
     * Mỗi LEG là 1 "match" (nhưng phần lớn các giải 1 cặp = 1 leg).
     *
     * Logic giống TournamentTypeController::calculateStatsFromMatches:
     * - Thắng leg = +3 điểm, Hòa = +1, Thua = +0.
     * - points_for/points_against cộng từ score của các set.
     * - win_rate = wins / played * 100.
     *
     * Tham số $initialStats cho phép caller kiểm soát thứ tự khởi tạo
     * (vd: theo `$group->teams()->orderBy('group_team.order')` để deterministic,
     * khớp với TournamentTypeController::getRank).
     *
     * @param  Collection<int, Matches>  $matches
     * @param  Collection<int, array>|null $initialStats  Nếu null thì tự khởi tạo theo thứ tự xuất hiện trong matches.
     * @return Collection<int, array>
     */
    public static function buildStatsFromMatches(Collection $matches, ?Collection $initialStats = null): Collection
    {
        if ($initialStats === null) {
            // Backward-compatible path: khởi tạo theo thứ tự xuất hiện trong matches.
            $statsByTeamId = [];
            foreach ($matches as $match) {
                if ($match->home_team_id) {
                    $statsByTeamId[$match->home_team_id] = self::emptyStats($match->home_team_id, $match->homeTeam);
                }
                if ($match->away_team_id) {
                    $statsByTeamId[$match->away_team_id] = self::emptyStats($match->away_team_id, $match->awayTeam);
                }
            }
            $initialStats = collect(array_values($statsByTeamId));
        }

        // Map stats theo team_id để cộng dồn nhanh
        $statsByTeamId = [];
        foreach ($initialStats as $stats) {
            $statsByTeamId[(int) $stats['team_id']] = $stats;
        }

        foreach ($matches as $leg) {
            $homeId = $leg->home_team_id;
            $awayId = $leg->away_team_id;

            if (!isset($statsByTeamId[$homeId]) || !isset($statsByTeamId[$awayId])) {
                continue;
            }

            // Tính theo từng set trong leg
            $sets = $leg->results->groupBy('set_number');
            $homeSetWins = 0;
            $awaySetWins = 0;
            $homePoints = 0;
            $awayPoints = 0;

            foreach ($sets as $setGroup) {
                $home = $setGroup->firstWhere('team_id', $homeId);
                $away = $setGroup->firstWhere('team_id', $awayId);

                $hScore = (int) ($home->score ?? 0);
                $aScore = (int) ($away->score ?? 0);

                if ($hScore > $aScore) $homeSetWins++;
                elseif ($aScore > $hScore) $awaySetWins++;

                $homePoints += $hScore;
                $awayPoints += $aScore;
            }

            // Cộng dồn vào stats
            $statsByTeamId[$homeId]['played']++;
            $statsByTeamId[$awayId]['played']++;

            $statsByTeamId[$homeId]['points_for'] += $homePoints;
            $statsByTeamId[$homeId]['points_against'] += $awayPoints;
            $statsByTeamId[$awayId]['points_for'] += $awayPoints;
            $statsByTeamId[$awayId]['points_against'] += $homePoints;

            if ($homeSetWins > $awaySetWins) {
                $statsByTeamId[$homeId]['wins']++;
                $statsByTeamId[$homeId]['points'] += 3;
                $statsByTeamId[$awayId]['losses']++;
            } elseif ($homeSetWins < $awaySetWins) {
                $statsByTeamId[$awayId]['wins']++;
                $statsByTeamId[$awayId]['points'] += 3;
                $statsByTeamId[$homeId]['losses']++;
            } else {
                $statsByTeamId[$homeId]['draws']++;
                $statsByTeamId[$awayId]['draws']++;
                $statsByTeamId[$homeId]['points'] += 1;
                $statsByTeamId[$awayId]['points'] += 1;
            }
        }

        // Tính point_diff + win_rate, giữ nguyên thứ tự từ $initialStats
        $result = collect();
        foreach ($initialStats as $stats) {
            $teamId = (int) $stats['team_id'];
            if (!isset($statsByTeamId[$teamId])) {
                continue;
            }
            $row = $statsByTeamId[$teamId];
            $row['point_diff'] = $row['points_for'] - $row['points_against'];
            $row['win_rate'] = $row['played'] > 0
                ? round(($row['wins'] / $row['played']) * 100, 2)
                : 0.0;
            $result->push($row);
        }

        return $result;
    }

    /**
     * So sánh 2 entry theo ranking rules + HEAD_TO_HEAD (DESC, ai tốt hơn xếp trên).
     * Trả về -1 / 0 / 1 cho usort.
     */
    public static function compareByRankingRules(array $a, array $b, array $rankingRules, Collection $matches): int
    {
        // Đội chưa đánh (played=0) xếp sau đội đã đánh (giống controller::getRank)
        $aPlayed = (int) ($a['played'] ?? 0);
        $bPlayed = (int) ($b['played'] ?? 0);
        if ($aPlayed === 0 && $bPlayed > 0) return 1;
        if ($bPlayed === 0 && $aPlayed > 0) return -1;

        foreach ($rankingRules as $ruleId) {
            $cmp = match ((int) $ruleId) {
                \App\Models\TournamentType::RANKING_WIN_DRAW_LOSE_POINTS => self::cmpScalar($a, $b, 'points'),
                \App\Models\TournamentType::RANKING_WIN_RATE => self::cmpScalar($a, $b, 'win_rate'),
                // SETS_WON (3) không có ở controller getRank → bỏ qua để consistent
                \App\Models\TournamentType::RANKING_POINTS_WON => self::cmpScalar($a, $b, 'point_diff'),
                \App\Models\TournamentType::RANKING_HEAD_TO_HEAD => self::cmpHeadToHead(
                    (int) $a['team_id'],
                    (int) $b['team_id'],
                    $matches
                ),
                \App\Models\TournamentType::RANKING_RANDOM_DRAW => ((int) $a['team_id']) <=> ((int) $b['team_id']),
                default => 0,
            };

            if ($cmp !== 0) {
                return $cmp;
            }
        }

        // Fallback cuối cùng: point_diff rồi team_id (giống controller)
        $diffCmp = self::cmpScalar($a, $b, 'point_diff');
        if ($diffCmp !== 0) return $diffCmp;
        return ((int) $a['team_id']) <=> ((int) $b['team_id']);
    }

    /**
     * So sánh scalar DESC (giá trị lớn hơn xếp trên).
     */
    private static function cmpScalar(array $a, array $b, string $key): int
    {
        $av = (float) ($a[$key] ?? 0);
        $bv = (float) ($b[$key] ?? 0);
        if ($av === $bv) return 0;
        return $bv <=> $av; // DESC
    }

    /**
     * So sánh đối đầu giữa 2 đội (giống TournamentTypeController::getHeadToHeadResultForRank).
     * Chỉ tính các trận trong collection $matches truyền vào (giới hạn trong group).
     *
     * Return:
     *   -1 nếu team A thắng H2H (nhiều trận hơn)
     *   +1 nếu team B thắng H2H
     *    0 nếu hòa hoặc chưa gặp
     */
    private static function cmpHeadToHead(int $teamA, int $teamB, Collection $matches): int
    {
        $h2hMatches = $matches->filter(function (Matches $match) use ($teamA, $teamB) {
            return ($match->home_team_id === $teamA && $match->away_team_id === $teamB)
                || ($match->home_team_id === $teamB && $match->away_team_id === $teamA);
        });

        if ($h2hMatches->isEmpty()) {
            return 0;
        }

        $aWins = 0;
        $bWins = 0;
        foreach ($h2hMatches as $match) {
            if ($match->winner_id === $teamA) {
                $aWins++;
            } elseif ($match->winner_id === $teamB) {
                $bWins++;
            }
        }

        if ($aWins === $bWins) {
            return 0;
        }
        // Ai thắng nhiều hơn xếp trên:
        // usort trả -1 để A đứng trước.
        return $aWins > $bWins ? -1 : 1;
    }

    /**
     * Empty stats template cho 1 team.
     */
    private static function emptyStats(int $teamId, $team = null): array
    {
        return [
            'team_id' => $teamId,
            'team_name' => $team?->name ?? 'Unknown',
            'played' => 0,
            'wins' => 0,
            'draws' => 0,
            'losses' => 0,
            'points' => 0,
            'points_for' => 0,
            'points_against' => 0,
            'point_diff' => 0,
            'win_rate' => 0.0,
        ];
    }
}
