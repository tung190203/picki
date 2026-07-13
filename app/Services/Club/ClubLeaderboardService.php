<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\MatchHistory;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Models\Sport;
use App\Models\VnduprHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClubLeaderboardService
{
    /**
     * Tính rank của club dựa trên tổng điểm members trong tháng
     * Cache 6 giờ; pre-computed bởi scheduler mỗi ngày.
     * Tránh compute on-demand — nếu cache miss thì trả về null để API không bị block.
     */
    public function calculateClubRank(Club $club, ?int $month = null, ?int $year = null): ?int
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        // Check if pre-computed rank exists
        $precomputedRank = Cache::get("club_ranks:{$year}:{$month}");
        if ($precomputedRank && isset($precomputedRank[$club->id])) {
            return $precomputedRank[$club->id];
        }

        // Cache miss: compute on-demand as fallback so API không trả null
        // cho đến khi scheduler chạy. Cache kết quả 6h để tránh recompute.
        $rank = $this->computeClubRank($club, $month, $year);

        return Cache::remember(
            "club_rank:{$club->id}:{$year}:{$month}",
            now()->addHours(6),
            fn () => $rank
        );
    }

    /**
     * Pre-compute all club ranks for a given month/year
     * Should be called by a scheduled job daily
     */
    public function precomputeMonthlyClubRanks(?int $month = null, ?int $year = null): array
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Single optimized query to get all club scores
        $clubScores = DB::select("
            SELECT 
                cm.club_id,
                COALESCE(SUM(latest_scores.score_value), 0) as total_score
            FROM club_members cm
            INNER JOIN users u ON u.id = cm.user_id
            INNER JOIN clubs c ON c.id = cm.club_id AND c.status = 1
            LEFT JOIN (
                SELECT 
                    vh.user_id,
                    vh.score_after as score_value,
                    ROW_NUMBER() OVER (PARTITION BY vh.user_id ORDER BY vh.created_at DESC) as rn
                FROM vndupr_history vh
                WHERE vh.created_at BETWEEN ? AND ?
            ) as latest_scores ON latest_scores.user_id = cm.user_id AND latest_scores.rn = 1
            WHERE cm.membership_status = 'joined' AND cm.status = 'active'
            GROUP BY cm.club_id
            ORDER BY total_score DESC
        ", [$startDate, $endDate]);

        // Build rank map
        $rankMap = [];
        $rank = 1;
        foreach ($clubScores as $score) {
            $rankMap[$score->club_id] = $rank++;
        }

        // Cache the pre-computed ranks for 1 hour
        Cache::put("club_ranks:{$year}:{$month}", $rankMap, 3600);

        return $rankMap;
    }

    private function computeClubRank(Club $club, int $month, int $year): ?int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Optimized single query - get all club scores in one go
        $allClubScores = DB::select("
            SELECT 
                cm.club_id,
                COALESCE(SUM(latest_scores.score_value), 0) as total_score
            FROM club_members cm
            INNER JOIN clubs c ON c.id = cm.club_id AND c.status = 1
            LEFT JOIN (
                SELECT 
                    vh.user_id,
                    vh.score_after as score_value,
                    ROW_NUMBER() OVER (PARTITION BY vh.user_id ORDER BY vh.created_at DESC) as rn
                FROM vndupr_history vh
                WHERE vh.created_at BETWEEN ? AND ?
            ) as latest_scores ON latest_scores.user_id = cm.user_id AND latest_scores.rn = 1
            WHERE cm.membership_status = 'joined' AND cm.status = 'active'
            GROUP BY cm.club_id
            ORDER BY total_score DESC
        ", [$startDate, $endDate]);

        // Find rank for this club
        $rank = null;
        foreach ($allClubScores as $index => $score) {
            if ($score->club_id == $club->id) {
                $rank = $index + 1;
                break;
            }
        }

        return $rank;
    }

    /**
     * Bảng xếp hạng all-time của câu lạc bộ.
     *
     * OPTIMIZED: Single query for all members' overview stats (was N separate UNION ALL queries).
     */
    public function getLeaderboard(Club $club): Collection
    {
        $members = $club->joinedMembers()
            ->with(['user'])
            ->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $sport = Sport::where('slug', 'pickleball')->first();
        $sportId = $sport?->id ?? 1;

        // Eager load all vndupr histories for all members in single query
        $memberUserIds = $members->pluck('user_id')->toArray();
        $allHistories = VnduprHistory::whereIn('user_id', $memberUserIds)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('user_id');

        // Pre-load sports and scores for all users
        $userSports = DB::table('user_sport')
            ->whereIn('user_id', $memberUserIds)
            ->pluck('sport_id', 'user_id');

        $sportScores = [];
        if ($userSports->isNotEmpty()) {
            $scores = DB::table('user_sport_scores')
                ->whereIn('user_sport_id', function ($q) use ($memberUserIds) {
                    $q->select('id')->from('user_sport')->whereIn('user_id', $memberUserIds);
                })
                ->where('score_type', 'vndupr_score')
                ->get()
                ->groupBy('user_sport_id');

            foreach ($scores as $usId => $scoreCollection) {
                $sportScores[$usId] = $scoreCollection->sortByDesc('created_at')->first();
            }
        }

        // OPTIMIZED: Fetch all overview stats in 3 queries instead of N queries (one per member).
        // Split users into batches to keep query plans efficient.
        $batches = array_chunk($memberUserIds, 100);
        $allStatsMap = [];

        foreach ($batches as $batchUserIds) {
            $batchStats = $this->batchFetchOverviewStats($batchUserIds, $sportId);
            $allStatsMap = array_merge($allStatsMap, $batchStats);
        }

        $leaderboardData = $members->map(function ($member) use ($allHistories, $sportId, $userSports, $sportScores, $allStatsMap) {
            return $this->calculateMemberStats($member, $allHistories, $sportId, $userSports, $sportScores, $allStatsMap);
        });

        $sorted = $leaderboardData->sortByDesc('vndupr_score')->values();
        $verified = $sorted->filter(fn($item) => ($item['all_time_stats']['matches_played'] ?? 0) >= 10);
        $unverified = $sorted->filter(fn($item) => ($item['all_time_stats']['matches_played'] ?? 0) < 10);

        $topThree = $verified->take(3)->values();
        $rest = $verified->skip(3)
            ->concat($unverified)
            ->sortByDesc('vndupr_score')
            ->values();

        return $topThree
            ->concat($rest)
            ->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });
    }

    /**
     * Batch fetch overview stats for multiple users in a single UNION ALL query.
     * BEFORE: N separate queries (one per member).
     * AFTER: 1 query per 100 users.
     *
     * @param  array<int>  $userIds
     * @return array<int, array>  keyed by user_id
     */
    private function batchFetchOverviewStats(array $userIds, int $sportId): array
    {
        if (empty($userIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));

        $result = DB::select("
            SELECT
                user_id,
                SUM(tournament_matches) AS total_tournament_matches,
                SUM(tournament_wins) AS tournament_wins,
                SUM(mini_matches) AS total_mini_matches,
                SUM(mini_wins) AS mini_wins,
                SUM(qm_matches) AS total_qm_matches,
                SUM(qm_wins) AS qm_wins
            FROM (
                -- Tournament home matches
                SELECT
                    tm.user_id,
                    COUNT(DISTINCT m.id) as tournament_matches,
                    SUM(CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END) as tournament_wins,
                    0 as mini_matches, 0 as mini_wins,
                    0 as qm_matches, 0 as qm_wins
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id = m.home_team_id
                WHERE tm.user_id IN ({$placeholders}) AND t.sport_id = ? AND m.status = 'completed'
                GROUP BY tm.user_id

                UNION ALL

                -- Tournament away matches
                SELECT
                    tm.user_id,
                    COUNT(DISTINCT m.id) as tournament_matches,
                    SUM(CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END) as tournament_wins,
                    0, 0, 0, 0
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id = m.away_team_id
                WHERE tm.user_id IN ({$placeholders}) AND t.sport_id = ? AND m.status = 'completed'
                GROUP BY tm.user_id

                UNION ALL

                -- Mini tournament team1 matches
                SELECT
                    mtm.user_id,
                    0, 0,
                    COUNT(DISTINCT mm.id) as mini_matches,
                    SUM(CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END) as mini_wins,
                    0, 0
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
                WHERE mtm.user_id IN ({$placeholders}) AND mnt.sport_id = ? AND mm.status = 'completed'
                GROUP BY mtm.user_id

                UNION ALL

                -- Mini tournament team2 matches
                SELECT
                    mtm.user_id,
                    0, 0,
                    COUNT(DISTINCT mm.id) as mini_matches,
                    SUM(CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END) as mini_wins,
                    0, 0
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team2_id
                WHERE mtm.user_id IN ({$placeholders}) AND mnt.sport_id = ? AND mm.status = 'completed'
                GROUP BY mtm.user_id

                UNION ALL

                -- Quick matches
                SELECT
                    mh.user_id,
                    0, 0, 0, 0,
                    COUNT(DISTINCT qm.id) as qm_matches,
                    SUM(CASE
                        WHEN qm.winner = 'team_a' AND JSON_CONTAINS(qm.team_a, CAST(mh.user_id AS CHAR)) THEN 1
                        WHEN qm.winner = 'team_b' AND JSON_CONTAINS(qm.team_b, CAST(mh.user_id AS CHAR)) THEN 1
                        ELSE 0
                    END) as qm_wins
                FROM quick_matches qm
                JOIN match_histories mh ON mh.quick_match_id = qm.id
                WHERE mh.user_id IN ({$placeholders}) AND qm.status = 'completed'
                GROUP BY mh.user_id
            ) combined
            GROUP BY user_id
        ", $this->buildLeaderboardBindings($userIds, $sportId));

        $map = [];
        foreach ($result as $row) {
            $totalMatches = (int) ($row->total_tournament_matches ?? 0)
                + (int) ($row->total_mini_matches ?? 0)
                + (int) ($row->total_qm_matches ?? 0);
            $totalWins = (int) ($row->tournament_wins ?? 0)
                + (int) ($row->mini_wins ?? 0)
                + (int) ($row->qm_wins ?? 0);

            $map[(int) $row->user_id] = [
                'matches_played' => $totalMatches,
                'wins' => $totalWins,
                'losses' => $totalMatches - $totalWins,
                'win_rate' => $totalMatches > 0 ? round(($totalWins / $totalMatches) * 100, 2) : 0,
            ];
        }

        return $map;
    }

    /**
     * Build bindings array for batch leaderboard query.
     * Pattern: [userId1, ..., userIdN, sportId, userId1, ..., userIdN, sportId, userId1, ..., userIdN, sportId, userId1, ..., userIdN, jsonId, jsonId, userId1, ..., userIdN]
     */
    private function buildLeaderboardBindings(array $userIds, int $sportId): array
    {
        $bindings = [];
        // 4 UNION ALL blocks × user IDs + sport ID
        for ($i = 0; $i < 4; $i++) {
            $bindings = array_merge($bindings, $userIds, [$sportId]);
        }
        // Quick match block: user IDs only (JSON_CONTAINS references mh.user_id column)
        $bindings = array_merge($bindings, $userIds);
        return $bindings;
    }

    private function calculateMemberStats(
        ClubMember $member,
        Collection $allHistories,
        int $sportId,
        Collection $userSports,
        array $sportScores,
        array $allStatsMap = []
    ): array {
        $userId = $member->user_id;

        // Get pre-fetched stats (from batch query) or compute inline
        if (!empty($allStatsMap) && isset($allStatsMap[$userId])) {
            $stats = $allStatsMap[$userId];
        } else {
            $stats = $this->calculateOverviewStats($userId, $sportId, $allHistories);
        }

        // Score change from history (always computed from allHistories to cover both paths)
        $userHistories = $allHistories->get($userId, collect());
        $firstHistory = $userHistories->sortBy('created_at')->first();
        $lastHistory = $userHistories->sortByDesc('created_at')->first();
        $scoreBefore = $firstHistory ? (float) $firstHistory->score_before : 0;
        $scoreAfter = $lastHistory ? (float) $lastHistory->score_after : 0;
        $stats['score_change'] = round($scoreAfter - $scoreBefore, 3);

        // Compute VNDRUP score
        $userHistories = $allHistories->get($userId, collect());
        $finalScore = 0;
        if ($userHistories->isNotEmpty()) {
            $finalScore = $userHistories->last()->score_after;
        } else {
            $userSportId = $userSports->get($userId);
            if ($userSportId && isset($sportScores[$userSportId])) {
                $finalScore = $sportScores[$userSportId]->score_value;
            }
        }

        return [
            'member_id' => $member->id,
            'user_id' => $userId,
            'user' => $member->user,
            'vndupr_score' => round($finalScore, 3),
            'all_time_stats' => $stats,
        ];
    }

    /**
     * Tính overview stats giống hệt matchesBySportId() trong UserMatchStatsController.
     * OPTIMIZED: Sử dụng single SQL query thay vì N+1 loops
     */
    private function calculateOverviewStats(int $userId, int $sportId, Collection $allHistories): array
    {
        // Use optimized single query with UNION ALL
        $statsResult = DB::selectOne("
            SELECT 
                SUM(t_matches) as total_tournament_matches,
                SUM(t_wins) as tournament_wins,
                SUM(mini_matches) as total_mini_matches,
                SUM(mini_wins) as mini_wins,
                SUM(qm_matches) as total_qm_matches,
                SUM(qm_wins) as qm_wins
            FROM (
                -- Tournament home matches
                SELECT 
                    COUNT(DISTINCT m.id) as t_matches, 
                    SUM(CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END) as t_wins,
                    0 as mini_matches, 0 as mini_wins,
                    0 as qm_matches, 0 as qm_wins
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id = m.home_team_id
                WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed'

                UNION ALL

                -- Tournament away matches
                SELECT 
                    COUNT(DISTINCT m.id) as t_matches,
                    SUM(CASE WHEN m.winner_id = tm.team_id THEN 1 ELSE 0 END) as t_wins,
                    0, 0, 0, 0
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id = m.away_team_id
                WHERE tm.user_id = ? AND t.sport_id = ? AND m.status = 'completed'

                UNION ALL

                -- Mini tournament team1 matches
                SELECT 
                    0, 0,
                    COUNT(DISTINCT mm.id) as mini_matches,
                    SUM(CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END) as mini_wins,
                    0, 0
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
                WHERE mtm.user_id = ? AND mnt.sport_id = ? AND mm.status = 'completed'

                UNION ALL

                -- Mini tournament team2 matches
                SELECT 
                    0, 0,
                    COUNT(DISTINCT mm.id) as mini_matches,
                    SUM(CASE WHEN mm.team_win_id = mtm.mini_team_id THEN 1 ELSE 0 END) as mini_wins,
                    0, 0
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team2_id
                WHERE mtm.user_id = ? AND mnt.sport_id = ? AND mm.status = 'completed'

                UNION ALL

                -- Quick matches (simplified - counts all matches user participated in)
                SELECT 
                    0, 0, 0, 0,
                    COUNT(DISTINCT qm.id) as qm_matches,
                    SUM(CASE 
                        WHEN qm.winner = 'team_a' AND JSON_CONTAINS(qm.team_a, ?) THEN 1
                        WHEN qm.winner = 'team_b' AND JSON_CONTAINS(qm.team_b, ?) THEN 1
                        ELSE 0 
                    END) as qm_wins
                FROM quick_matches qm
                JOIN match_histories mh ON mh.quick_match_id = qm.id AND mh.user_id = ?
                WHERE qm.status = 'completed'
            ) as combined
        ", [
            $userId, $sportId,
            $userId, $sportId,
            $userId, $sportId,
            $userId, $sportId,
            json_encode($userId),
            json_encode($userId),
            $userId,
        ]);

        $totalMatches = (int) ($statsResult->total_tournament_matches ?? 0)
                      + (int) ($statsResult->total_mini_matches ?? 0)
                      + (int) ($statsResult->total_qm_matches ?? 0);
        $totalWins = (int) ($statsResult->tournament_wins ?? 0)
                    + (int) ($statsResult->mini_wins ?? 0)
                    + (int) ($statsResult->qm_wins ?? 0);
        $totalLose = $totalMatches - $totalWins;
        $winRate = $totalMatches > 0 ? round(($totalWins / $totalMatches) * 100, 2) : 0;

        // Score change from history
        $userHistories = $allHistories->get($userId, collect());
        $firstHistory = $userHistories->sortBy('created_at')->first();
        $lastHistory = $userHistories->sortByDesc('created_at')->first();
        $scoreBefore = $firstHistory ? (float) $firstHistory->score_before : 0;
        $scoreAfter = $lastHistory ? (float) $lastHistory->score_after : 0;
        $scoreChange = round($scoreAfter - $scoreBefore, 3);

        return [
            'matches_played' => $totalMatches,
            'wins' => $totalWins,
            'losses' => $totalLose,
            'win_rate' => $winRate,
            'score_change' => $scoreChange,
        ];
    }
}
