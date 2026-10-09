<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\Follow;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * Batch load enrichment cho Search Club results.
 * Tránh N+1 bằng cách load tất cả data cần thiết trong 1-2 queries mỗi loại.
 */
class ClubSearchEnricher
{
    /**
     * Attach followers_count.
     * Followers = số user follow CLB mà KHÔNG phải member joined+active.
     * (member đã là 'thành viên' rồi, không tính là follower ngoài).
     */
    public static function attachFollowersCount(Collection|array $clubs): Collection|array
    {
        if (empty($clubs)) {
            return $clubs;
        }

        $clubIds = collect($clubs)->pluck('id')->toArray();
        if (empty($clubIds)) {
            return $clubs;
        }

        // Tập user_id đang joined+active theo từng club
        $memberIdsByClub = DB::table('club_members')
            ->whereIn('club_id', $clubIds)
            ->where('membership_status', 'joined')
            ->where('status', 'active')
            ->select('club_id', 'user_id')
            ->get()
            ->groupBy('club_id')
            ->map(fn($rows) => $rows->pluck('user_id')->all());

        // Follow theo từng (club, user) DISTINCT
        $followerRows = DB::table('follows')
            ->where('followable_type', Club::class)
            ->whereIn('followable_id', $clubIds)
            ->select('followable_id as club_id', 'user_id')
            ->distinct()
            ->get();

        $followersByClub = [];
        foreach ($followerRows as $row) {
            $followersByClub[$row->club_id][$row->user_id] = true;
        }

        foreach ($clubs as $club) {
            $followers = $followersByClub[$club->id] ?? [];
            $members = $memberIdsByClub->get($club->id, []) ?: [];
            $memberSet = array_flip($members);
            $count = 0;
            foreach ($followers as $uid => $_) {
                if (!isset($memberSet[$uid])) {
                    $count++;
                }
            }
            $club->followers_count = $count;
        }

        return $clubs;
    }

    /**
     * Attach is_following cho current user.
     */
    public static function attachIsFollowing(Collection|array $clubs, ?int $userId): Collection|array
    {
        if (empty($clubs) || !$userId) {
            foreach ($clubs as $club) {
                $club->is_following = false;
            }
            return $clubs;
        }

        $clubIds = collect($clubs)->pluck('id')->toArray();
        if (empty($clubIds)) {
            return $clubs;
        }

        $followingIds = Follow::where('followable_type', Club::class)
            ->where('user_id', $userId)
            ->whereIn('followable_id', $clubIds)
            ->pluck('followable_id')
            ->flip()
            ->toArray();

        foreach ($clubs as $club) {
            $club->is_following = isset($followingIds[$club->id]);
        }

        return $clubs;
    }

    /**
     * Attach primary_home_court (position = 0).
     * Sử dụng logic tương tự ClubHomeCourtService.
     */
    public static function attachPrimaryHomeCourt(Collection|array $clubs): Collection|array
    {
        if (empty($clubs)) {
            return $clubs;
        }

        $clubIds = collect($clubs)->pluck('id')->toArray();
        if (empty($clubIds)) {
            return $clubs;
        }

        // Lấy primary home court cho mỗi club (position = 0)
        $primaryCourts = DB::table('club_competition_locations as ccl')
            ->join('competition_locations as cl', 'cl.id', '=', 'ccl.competition_location_id')
            ->whereIn('ccl.club_id', $clubIds)
            ->where('ccl.position', 0)
            ->select([
                'ccl.club_id',
                'cl.id',
                'cl.name',
                'cl.address',
                'cl.latitude',
                'cl.longitude',
            ])
            ->get()
            ->groupBy('club_id');

        foreach ($clubs as $club) {
            $court = $primaryCourts->get($club->id)?->first();
            if ($court) {
                $club->primary_home_court = [
                    'id' => (int) $court->id,
                    'name' => $court->name,
                    'address' => $court->address,
                    'latitude' => (float) $court->latitude,
                    'longitude' => (float) $court->longitude,
                ];
            } else {
                $club->primary_home_court = null;
            }
        }

        return $clubs;
    }

    /**
     * Attach leader info (adminMember với user + vndupr_score).
     */
    public static function attachLeaderInfo(Collection|array $clubs): Collection|array
    {
        if (empty($clubs)) {
            return $clubs;
        }

        $clubIds = collect($clubs)->pluck('id')->toArray();
        if (empty($clubIds)) {
            return $clubs;
        }

        // Lấy admin member cho mỗi club
        // Role priority: Admin > Manager > Secretary > Treasurer > Member
        $adminMembers = DB::table('club_members as cm')
            ->join('users as u', 'u.id', '=', 'cm.user_id')
            ->whereIn('cm.club_id', $clubIds)
            ->where('cm.membership_status', 'joined')
            ->where('cm.status', 'active')
            ->whereIn('cm.role', ['admin', 'manager', 'secretary'])
            ->select([
                'cm.club_id',
                'cm.user_id',
                'u.full_name',
                'u.avatar_url',
            ])
            // Order by role priority: admin=1, manager=2, secretary=3
            ->orderByRaw("FIELD(cm.role, 'admin', 'manager', 'secretary')")
            ->get()
            ->groupBy('club_id');

        // Lấy vndupr_score cho các user này
        $userIds = $adminMembers->flatten()->pluck('user_id')->unique()->toArray();
        if (empty($userIds)) {
            foreach ($clubs as $club) {
                $club->leader = null;
            }
            return $clubs;
        }

        $vnduprScores = DB::table('user_sport_scores as uss')
            ->join('user_sport as us', 'us.id', '=', 'uss.user_sport_id')
            ->whereIn('us.user_id', $userIds)
            ->where('uss.score_type', 'vndupr_score')
            ->whereNotNull('uss.score_value')
            ->groupBy('us.user_id')
            ->selectRaw('us.user_id, MAX(uss.score_value) as max_score')
            ->pluck('max_score', 'user_id');

        // Build leader info
        foreach ($clubs as $club) {
            $admin = $adminMembers->get($club->id)?->first();
            if ($admin) {
                $club->leader = [
                    'user_id' => (int) $admin->user_id,
                    'full_name' => $admin->full_name,
                    'avatar_url' => $admin->avatar_url,
                    'vndupr_score' => isset($vnduprScores[$admin->user_id])
                        ? number_format((float) $vnduprScores[$admin->user_id], 3, '.', '')
                        : null,
                ];
            } else {
                $club->leader = null;
            }
        }

        return $clubs;
    }

    /**
     * Attach organized_count cho leaders.
     * Tổng hợp từ: mini_tournaments.created_by, mini_tournament_staff (role=1),
     * tournaments.created_by, tournament_staff (role=1,2).
     */
    public static function attachOrganizedCount(Collection|array $clubs): Collection|array
    {
        if (empty($clubs)) {
            return $clubs;
        }

        // Collect unique leader user IDs
        $leaderUserIds = collect($clubs)
            ->map(fn($club) => $club->leader['user_id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($leaderUserIds)) {
            return $clubs;
        }

        // Count organized mini_tournaments (created_by)
        $miniCreated = DB::table('mini_tournaments')
            ->whereIn('created_by', $leaderUserIds)
            ->whereIn('status', [3, 4]) // draft, open - có thể thay đổi tùy business
            ->groupBy('created_by')
            ->selectRaw('created_by, COUNT(*) as cnt')
            ->pluck('cnt', 'created_by');

        // Count organized mini_tournaments (staff role=1/organizer)
        $miniStaff = DB::table('mini_tournament_staff as mts')
            ->join('mini_tournaments as mnt', 'mnt.id', '=', 'mts.mini_tournament_id')
            ->whereIn('mts.user_id', $leaderUserIds)
            ->where('mts.role', 1)
            ->whereIn('mnt.status', [3, 4])
            ->groupBy('mts.user_id')
            ->selectRaw('mts.user_id, COUNT(DISTINCT mts.mini_tournament_id) as cnt')
            ->pluck('cnt', 'mts.user_id');

        // Count organized tournaments (created_by)
        $tourCreated = DB::table('tournaments')
            ->whereIn('created_by', $leaderUserIds)
            ->whereIn('status', [0, 1]) // draft, open
            ->groupBy('created_by')
            ->selectRaw('created_by, COUNT(*) as cnt')
            ->pluck('cnt', 'created_by');

        // Count organized tournaments (staff role=1,2)
        $tourStaff = DB::table('tournament_staff as ts')
            ->join('tournaments as t', 't.id', '=', 'ts.tournament_id')
            ->whereIn('ts.user_id', $leaderUserIds)
            ->whereIn('ts.role', [1, 2])
            ->whereIn('t.status', [0, 1])
            ->groupBy('ts.user_id')
            ->selectRaw('ts.user_id, COUNT(DISTINCT ts.tournament_id) as cnt')
            ->pluck('cnt', 'ts.user_id');

        // Attach to each club's leader
        foreach ($clubs as $club) {
            if (isset($club->leader['user_id'])) {
                $userId = $club->leader['user_id'];
                $count = (int) ($miniCreated[$userId] ?? 0)
                    + (int) ($miniStaff[$userId] ?? 0)
                    + (int) ($tourCreated[$userId] ?? 0)
                    + (int) ($tourStaff[$userId] ?? 0);
                // Assign the full array — `$club->leader[...] = $count` would indirectly
                // modify the overloaded Eloquent attribute and trigger a PHP warning.
                $leader = $club->leader;
                $leader['organized_count'] = $count;
                $club->leader = $leader;
            }
        }

        return $clubs;
    }

    /**
     * Attach total_mini_tournaments_count và total_tournaments_count cho mỗi club.
     * 1 grouped query mỗi bảng. Indexes có sẵn (idx_mini_tournaments_club_status, idx_tournaments_club_status).
     */
    public static function attachTotalCounts(Collection|array $clubs): Collection|array
    {
        if (empty($clubs)) {
            return $clubs;
        }

        $clubIds = collect($clubs)->pluck('id')->toArray();
        if (empty($clubIds)) {
            return $clubs;
        }

        $mini = DB::table('mini_tournaments')
            ->whereIn('club_id', $clubIds)
            ->groupBy('club_id')
            ->selectRaw('club_id, COUNT(*) as cnt')
            ->pluck('cnt', 'club_id');

        $tours = DB::table('tournaments')
            ->whereIn('club_id', $clubIds)
            ->groupBy('club_id')
            ->selectRaw('club_id, COUNT(*) as cnt')
            ->pluck('cnt', 'club_id');

        foreach ($clubs as $club) {
            $club->total_mini_tournaments_count = (int) ($mini[$club->id] ?? 0);
            $club->total_tournaments_count = (int) ($tours[$club->id] ?? 0);
        }

        return $clubs;
    }

    /**
     * Tính score_match_score = |midpoint(user, club) - userScore|.
     * Lower = better fit. Dùng cho sort ASC khi sub_tab=suit_level.
     */
    public static function attachScoreMatch(Collection|array $clubs, float $userScore): Collection|array
    {
        foreach ($clubs as $club) {
            if (isset($club->skill_level) && is_array($club->skill_level)) {
                $min = (float) ($club->skill_level['min'] ?? 0);
                $max = (float) ($club->skill_level['max'] ?? 0);
                $mid = ($min + $max) / 2.0;
                $club->score_match_score = abs($mid - $userScore);
                $club->user_vndupr_score = $userScore;
            } else {
                $club->score_match_score = null;
                $club->user_vndupr_score = $userScore;
            }
        }

        return $clubs;
    }

    /**
     * Sort clubs theo score_match_score ASC (tốt nhất trước).
     * Dùng cho sub_tab=suit_level.
     */
    public static function sortByScoreMatch(Collection $clubs): Collection
    {
        return $clubs->sortBy(function ($club) {
            return $club->score_match_score ?? PHP_FLOAT_MAX;
        })->values();
    }

    /**
     * Enrich tất cả data cho search club results.
     */
    public static function enrich(Collection|array $clubs, ?int $userId, ?float $suitLevelUserScore = null): Collection|array
    {
        if (empty($clubs)) {
            return $clubs;
        }

        // Batch load tất cả
        self::attachFollowersCount($clubs);
        self::attachIsFollowing($clubs, $userId);
        if ($userId !== null) {
            app(ClubService::class)->attachMembershipStatus($clubs, $userId);
        }
        app(ClubService::class)->attachSkillLevel($clubs);
        self::attachPrimaryHomeCourt($clubs);
        self::attachLeaderInfo($clubs);
        self::attachOrganizedCount($clubs);
        self::attachTotalCounts($clubs);

        // Suit level enrichment
        if ($suitLevelUserScore !== null) {
            self::attachScoreMatch($clubs, $suitLevelUserScore);
        }

        return $clubs;
    }
}
