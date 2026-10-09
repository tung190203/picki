<?php

namespace App\Services;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\User;
use Illuminate\Support\Facades\Cache as SQLCacheKeyAlias;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "user / match qualifies for the leaderboard".
 *
 * Rule:
 *   - A match (matches / mini_matches) is qualified_for_ranking when COMPLETED
 *     and at least one of its participants already holds a badge.
 *   - A user is qualified for the leaderboard when:
 *       (a) they hold one of the anchor badges (ANCHOR/PICKI/CHAMPION/VERIFIED), OR
 *       (b) their qualified_for_ranking match count >= ranking_matches setting,
 *       (c) legacy fallback: total_matches_has_anchor >= ranking_matches (for
 *           historical matches that pre-date the new column).
 *
 * HomeController and LeaderboardController MUST go through this service so the
 * two endpoints stay in sync.
 */
class LeaderboardQualifierService
{
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Badges that count as "anchor" for leaderboard qualification. Only these
     * four codes qualify a user on their own — any other badge (achievements
     * like MATCH_10, TOUR_BRONZE, etc.) is decorative and does NOT auto-qualify.
     */
    private const ANCHOR_BADGE_CODES = ['ANCHOR', 'PICKI', 'CHAMPION', 'VERIFIED'];

    /**
     * True when the user holds at least one anchor badge (see ANCHOR_BADGE_CODES).
     * Single source of truth — both inspectUser() and qualifiedUserIds() route
     * through here so the two endpoints stay in sync.
     */
    private function userHasAnchorBadge(int $userId): bool
    {
        return DB::table('user_badges')
            ->join('badges', 'badges.id', '=', 'user_badges.badge_id')
            ->where('user_badges.user_id', $userId)
            ->whereIn('badges.code', self::ANCHOR_BADGE_CODES)
            ->exists();
    }

    /**
     * Inspect a match and decide whether it should count toward ranking.
     */
    public function qualifyMatch(Matches|MiniMatch $match): bool
    {
        $userIds = $this->extractUserIds($match);
        if (empty($userIds)) {
            return false;
        }

        return DB::table('user_badges')
            ->whereIn('user_id', $userIds)
            ->exists();
    }

    /**
     * Persist qualified_for_ranking on a match. Idempotent — safe to call from
     * match-completion flows without re-saving unrelated fields.
     */
    public function markQualified(Matches|MiniMatch $match): bool
    {
        $qualified = $this->qualifyMatch($match);

        $table = $match instanceof Matches ? 'matches' : 'mini_matches';
        DB::table($table)
            ->where('id', $match->id)
            ->update(['qualified_for_ranking' => $qualified]);

        $match->qualified_for_ranking = $qualified;

        $this->forgetUserIdsCache();

        return $qualified;
    }

    /**
     * Count matches marked qualified_for_ranking for one user (one sport).
     * Mirrors countMatchesForBatch but only counts qualified rows.
     */
    public function countQualifiedMatches(int $userId, int $sportId): int
    {
        $counts = $this->countQualifiedMatchesForBatch([$userId], $sportId);
        return (int) ($counts[$userId] ?? 0);
    }

    /**
     * Batch variant. Returns [$userId => qualifiedMatchCount].
     * Covers: tournament matches, mini matches (team + solo participant).
     */
    public function countQualifiedMatchesForBatch(array $userIds, int $sportId): array
    {
        $counts = array_fill_keys($userIds, 0);
        if (empty($userIds)) {
            return $counts;
        }

        $userIdsCsv = implode(',', array_map('intval', $userIds));

        $tSql = "
            SELECT tm.user_id, COUNT(DISTINCT m.id) AS cnt
            FROM matches m
            JOIN tournament_types tt ON m.tournament_type_id = tt.id
            JOIN tournaments t ON tt.tournament_id = t.id
            JOIN team_members tm ON tm.team_id = m.home_team_id
            WHERE tm.user_id IN ({$userIdsCsv})
              AND t.sport_id = ?
              AND m.status = 'completed'
              AND m.is_bye = 0
              AND m.qualified_for_ranking = 1
            GROUP BY tm.user_id
        ";
        $tAwaySql = "
            SELECT tm.user_id, COUNT(DISTINCT m.id) AS cnt
            FROM matches m
            JOIN tournament_types tt ON m.tournament_type_id = tt.id
            JOIN tournaments t ON tt.tournament_id = t.id
            JOIN team_members tm ON tm.team_id = m.away_team_id
            WHERE tm.user_id IN ({$userIdsCsv})
              AND t.sport_id = ?
              AND m.status = 'completed'
              AND m.is_bye = 0
              AND m.qualified_for_ranking = 1
            GROUP BY tm.user_id
        ";
        foreach (DB::select($tSql, [$sportId]) as $r) {
            $counts[(int) $r->user_id] += (int) $r->cnt;
        }
        foreach (DB::select($tAwaySql, [$sportId]) as $r) {
            $counts[(int) $r->user_id] += (int) $r->cnt;
        }

        $mSql = "
            SELECT mtm.user_id, COUNT(DISTINCT mm.id) AS cnt
            FROM mini_matches mm
            JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
            JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team1_id
            WHERE mtm.user_id IN ({$userIdsCsv})
              AND mnt.sport_id = ?
              AND mm.status = 'completed'
              AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
              AND mm.qualified_for_ranking = 1
            GROUP BY mtm.user_id
        ";
        $mAwaySql = "
            SELECT mtm.user_id, COUNT(DISTINCT mm.id) AS cnt
            FROM mini_matches mm
            JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
            JOIN mini_team_members mtm ON mtm.mini_team_id = mm.team2_id
            WHERE mtm.user_id IN ({$userIdsCsv})
              AND mnt.sport_id = ?
              AND mm.status = 'completed'
              AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
              AND mm.qualified_for_ranking = 1
            GROUP BY mtm.user_id
        ";
        $mSoloSql = "
            SELECT mp.user_id, COUNT(DISTINCT mm.id) AS cnt
            FROM mini_matches mm
            JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
            JOIN mini_participants mp ON mp.mini_tournament_id = mnt.id
            WHERE mp.user_id IN ({$userIdsCsv})
              AND (mm.participant1_id = mp.id OR mm.participant2_id = mp.id)
              AND mnt.sport_id = ?
              AND mm.status = 'completed'
              AND mm.team1_id IS NULL AND mm.team2_id IS NULL
              AND mm.qualified_for_ranking = 1
            GROUP BY mp.user_id
        ";
        foreach (DB::select($mSql, [$sportId]) as $r) {
            $counts[(int) $r->user_id] += (int) $r->cnt;
        }
        foreach (DB::select($mAwaySql, [$sportId]) as $r) {
            $counts[(int) $r->user_id] += (int) $r->cnt;
        }
        foreach (DB::select($mSoloSql, [$sportId]) as $r) {
            $counts[(int) $r->user_id] += (int) $r->cnt;
        }

        return $counts;
    }

    /**
     * The single "should this user appear on the leaderboard?" predicate.
     * Both /api/home and /api/leaderboard call this — keep them in sync.
     *
     * @return array{qualified: bool, has_badge: bool, qualified_match_count: int, ranking_matches: int}
     */
    public function inspectUser(int $userId, int $sportId): array
    {
        $rankingMatches = User::getRankingMatches();

        $hasBadge = $this->userHasAnchorBadge($userId);
        $qualifiedMatchCount = $this->countQualifiedMatches($userId, $sportId);

        $legacyMatches = (int) (DB::table('users')
            ->where('id', $userId)
            ->value('total_matches_has_anchor') ?? 0);

        $qualified = $hasBadge
            || $qualifiedMatchCount >= $rankingMatches
            || $legacyMatches >= $rankingMatches;

        return [
            'qualified' => $qualified,
            'has_badge' => $hasBadge,
            'qualified_match_count' => $qualifiedMatchCount,
            'ranking_matches' => $rankingMatches,
        ];
    }

    /**
     * Cached set of qualified user_ids for a sport — for use as a WHERE IN clause.
     * 60s TTL is fine because match completion updates invalidate the cache
     * (see markQualified → forgetUserIdsCache).
     *
     * @return int[]
     */
    public function qualifiedUserIds(int $sportId): array
    {
        return SQLCacheKeyAlias::remember(
            "leaderboard_qualified_user_ids:{$sportId}",
            self::CACHE_TTL_SECONDS,
            function () use ($sportId) {
                $rankingMatches = User::getRankingMatches();

                $badgeUserIds = DB::table('user_badges')
                    ->join('badges', 'badges.id', '=', 'user_badges.badge_id')
                    ->whereIn('badges.code', self::ANCHOR_BADGE_CODES)
                    ->distinct()
                    ->pluck('user_badges.user_id')
                    ->all();

                $legacyUserIds = DB::table('users')
                    ->where('total_matches_has_anchor', '>=', $rankingMatches)
                    ->pluck('id')
                    ->all();

                $qualifiedFromMatches = $this->userIdsWithEnoughQualifiedMatches($sportId, $rankingMatches);

                $ids = array_unique(array_merge($badgeUserIds, $legacyUserIds, $qualifiedFromMatches));
                return array_map('intval', $ids);
            }
        );
    }

    public function forgetUserIdsCache(?int $sportId = null): void
    {
        if ($sportId !== null) {
            SQLCacheKeyAlias::forget("leaderboard_qualified_user_ids:{$sportId}");
            return;
        }
        // Forget pickleball (default). Other sports reuse via TTL anyway.
        SQLCacheKeyAlias::forget('leaderboard_qualified_user_ids:1');
    }

    /**
     * Pull distinct user_ids with qualified match count >= threshold for a sport.
     *
     * @return int[]
     */
    private function userIdsWithEnoughQualifiedMatches(int $sportId, int $threshold): array
    {
        $sql = "
            SELECT user_id, SUM(cnt) AS total FROM (
                SELECT tm.user_id AS user_id, COUNT(DISTINCT m.id) AS cnt
                FROM matches m
                JOIN tournament_types tt ON m.tournament_type_id = tt.id
                JOIN tournaments t ON tt.tournament_id = t.id
                JOIN team_members tm ON tm.team_id IN (m.home_team_id, m.away_team_id)
                WHERE t.sport_id = ?
                  AND m.status = 'completed' AND m.is_bye = 0
                  AND m.qualified_for_ranking = 1
                GROUP BY tm.user_id

                UNION ALL

                SELECT user_id, COUNT(*) AS cnt FROM (
                    SELECT mtm.user_id
                    FROM mini_matches mm
                    JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                    JOIN mini_team_members mtm ON mtm.mini_team_id IN (mm.team1_id, mm.team2_id)
                    WHERE mnt.sport_id = ? AND mm.status = 'completed'
                      AND mm.team1_id IS NOT NULL AND mm.team2_id IS NOT NULL
                      AND mm.qualified_for_ranking = 1
                    GROUP BY mtm.user_id, mm.id
                ) AS mini_team GROUP BY user_id

                UNION ALL

                SELECT mp.user_id, COUNT(DISTINCT mm.id) AS cnt
                FROM mini_matches mm
                JOIN mini_tournaments mnt ON mm.mini_tournament_id = mnt.id
                JOIN mini_participants mp ON mp.mini_tournament_id = mnt.id
                WHERE (mm.participant1_id = mp.id OR mm.participant2_id = mp.id)
                  AND mnt.sport_id = ? AND mm.status = 'completed'
                  AND mm.team1_id IS NULL AND mm.team2_id IS NULL
                  AND mm.qualified_for_ranking = 1
                GROUP BY mp.user_id
            ) AS combined
            GROUP BY user_id
            HAVING total >= ?
        ";

        return array_map(
            'intval',
            DB::select($sql, [$sportId, $sportId, $sportId, $threshold])
                ? collect(DB::select($sql, [$sportId, $sportId, $sportId, $threshold]))->pluck('user_id')->all()
                : []
        );
    }

    /**
     * Extract all user_ids participating in a match (team members + solo participants).
     *
     * @return int[]
     */
    private function extractUserIds(Matches|MiniMatch $match): array
    {
        if ($match instanceof Matches) {
            return DB::table('team_members')
                ->whereIn('team_id', array_filter([$match->home_team_id, $match->away_team_id]))
                ->pluck('user_id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        // MiniMatch — covers team-based + solo participant
        $ids = [];

        if ($match->team1_id || $match->team2_id) {
            $ids = DB::table('mini_team_members')
                ->whereIn('mini_team_id', array_filter([$match->team1_id, $match->team2_id]))
                ->pluck('user_id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        if ($match->participant1_id || $match->participant2_id) {
            $participantIds = DB::table('mini_participants')
                ->whereIn('id', array_filter([$match->participant1_id, $match->participant2_id]))
                ->pluck('user_id')
                ->map(fn($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $participantIds);
        }

        return array_values(array_unique(array_filter($ids)));
    }
}