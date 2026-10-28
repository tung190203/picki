<?php

namespace App\Services\Club;

use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubFundContributionStatus;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\Club\ClubGuest;
use App\Models\Club\ClubMember;
use App\Models\MiniParticipant;
use App\Models\Participant;
use App\Models\User;
use App\Models\UserSportScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ClubDetailAssembler — assembles club data for detail view.
 *
 * Logic layer: reads Club, queries DB for membership/unread/rank/skill,
 * then sets attributes directly on the Club model for the Resource to read.
 * Resource only serializes — never queries.
 *
 * Usage:
 *   $assembler = app(ClubDetailAssembler::class);
 *   $club = $assembler->assemble($club, $userId, $options);
 *   return new ClubDetailResource($club);
 */
class ClubDetailAssembler
{
    public function __construct(
        protected ClubLeaderboardService $leaderboardService,
        protected ClubService $clubService,
    ) {
    }

    /**
     * Assemble full club detail data.
     *
     * @param  Club  $club  Club model (should have creator, profile, mainWallet eager-loaded)
     * @param  int|null  $userId
     * @param  array  $options  Additional options:
     *                            - load_members: bool (default: true if user is member)
     *                            - include_members: bool (force include/exclude members array)
     * @return Club
     */
    public function assemble(Club $club, ?int $userId, array $options = []): Club
    {
        $loadMembers = $options['include_members']
            ?? ($userId && ($club->is_member ?? false));

        // 1. Attach membership status (already done by ClubService::getClubDetail — skip to avoid duplicate)
        // NOTE: If assemble() is called without going through ClubService, uncomment the line below:
        // if ($userId) { $this->attachMembershipStatus($club, $userId); }

        if ($userId && !isset($club->is_member)) {
            $this->attachMembershipStatus($club, $userId);
        }

        if ($userId) {
            $this->attachUnreadNotificationCount($club, $userId);
            $this->attachFollowStatus($club, $userId);
        }

        // 0b. Admin/BTC stats — chỉ attach khi user có quyền manage CLB.
        if ($userId && $club->canManage($userId)) {
            $this->attachAdminStats($club);
        }

        // 1a. Member count = user thật (joined/active) + CLB guest (club_guest_profiles).
        // `_real_members_count` giữ raw count để assemble() gọi lại không cộng dồn VM nhiều lần.
        // Nếu controller đã withCount('activeMembers') thì tái dùng `active_members_count` cho khỏi query.
        if (!isset($club->_real_members_count)) {
            $club->_real_members_count = isset($club->active_members_count)
                ? (int) $club->active_members_count
                : $club->activeMembers()->count();
        }
        $club->setAttribute(
            'active_members_count',
            (int) $club->_real_members_count + $this->countGuestProfiles($club)
        );
        // Cờ báo: active_members_count đã bao gồm CLB guest.
        // Resource dùng cờ này để không cộng thêm lần nữa.
        $club->setAttribute('_virtual_members_counted', true);

        // 2. Calculate rank (cached, ~0ms)
        $club->rank = $this->leaderboardService->calculateClubRank($club);

        // 2c. Score range (min/max vndupr) — luôn tính, dùng cho FE bất kể user là member hay không
        $club->score_range = $this->calculateScoreRange($club);

        // 3. Load members only if user is a member (and option allows)
        if ($loadMembers && ($club->is_member ?? false)) {
            $this->loadMembers($club, $options);
            // 4. Calculate skill level from loaded members (in-memory)
            $club->_skill_level = $this->calculateSkillLevel($club);
        }

        return $club;
    }

    /**
     * Số CLB guest (club_guest_profiles) của CLB.
     * Dùng `guest_profiles_count` từ withCount nếu có, để không query thêm.
     */
    protected function countGuestProfiles(Club $club): int
    {
        if (isset($club->guest_profiles_count)) {
            return (int) $club->guest_profiles_count;
        }

        if ($club->relationLoaded('guestProfiles')) {
            return $club->guestProfiles->count();
        }

        return $club->guestProfiles()->count();
    }

    /**
     * Attach membership status flags to club (1 query).
     * Sets: is_member, is_admin, has_pending_request, has_invitation, _invited_by_user.
     */
    public function attachMembershipStatus(Club $club, int $userId): void
    {
        $memberships = ClubMember::whereIn('club_id', [$club->id])
            ->where('user_id', $userId)
            ->with('invitedBy')
            ->get()
            ->groupBy('club_id');

        $members = $memberships->get($club->id, collect());

        $activeMember = $members->first(fn ($m) =>
            $m->membership_status === ClubMembershipStatus::Joined
            && $m->status === ClubMemberStatus::Active
        );

        $club->is_member = $activeMember !== null;
        $club->is_admin = $activeMember !== null
            && $activeMember->role === ClubMemberRole::Admin;
        $club->has_pending_request = $members->contains(fn ($m) =>
            $m->membership_status === ClubMembershipStatus::Pending
            && $m->invited_by === null
        );
        $club->has_invitation = $members->contains(fn ($m) =>
            $m->membership_status === ClubMembershipStatus::Pending
            && $m->invited_by !== null
        );

        // Pre-set invited_by_user for Resource
        $pendingInvite = $members->first(fn ($m) =>
            $m->membership_status === ClubMembershipStatus::Pending
            && $m->invited_by !== null
        );
        if ($pendingInvite && $pendingInvite->relationLoaded('invitedBy') && $pendingInvite->invitedBy) {
            $inviter = $pendingInvite->invitedBy;
            $club->_invited_by_user = [
                'id' => $inviter->id,
                'full_name' => $inviter->full_name,
                'avatar_url' => $inviter->avatar_url,
            ];
        } else {
            $club->_invited_by_user = null;
        }
    }

    /**
     * Attach unread notification count (1-2 queries depending on current implementation).
     */
    public function attachUnreadNotificationCount(Club $club, int $userId): void
    {
        $this->clubService->attachUnreadNotificationCount(collect([$club]), $userId);
    }

    /**
     * Attach follow status to club:
     *  - is_following: user hiện tại có đang follow CLB không
     *  - followers_count: số follower KHÔNG phải thành viên CLB (joined + active)
     */
    public function attachFollowStatus(Club $club, int $userId): void
    {
        $club->is_following = $club->isFollowedBy($userId);

        // ponytail: whereNotExists thay vì pluck memberIds vào PHP rồi whereNotIn.
        $club->followers_count = (int) $club->followers()
            ->whereNotExists(function ($q) use ($club) {
                $q->select(DB::raw(1))
                    ->from('club_members')
                    ->whereColumn('club_members.user_id', 'follows.user_id')
                    ->where('club_members.club_id', $club->id)
                    ->where('club_members.membership_status', ClubMembershipStatus::Joined->value)
                    ->where('club_members.status', ClubMemberStatus::Active->value);
            })
            ->count();
    }

    /**
     * Attach admin stats — chỉ attach khi user có quyền manage CLB.
     */
    public function attachAdminStats(Club $club): void
    {
        $oneMonthAgo = now()->subDays(30)->toDateString();
        $today = now()->toDateString();

        // ponytail: gom về count SQL, không pluck id list vào PHP rồi whereIn/whereNotIn.
        // Trước đó: pluck tournamentIds/miniIds/memberUserIds + merge 3 pluck user_id rồi unique()->count().

        // activities_count: tổng hợp activities đang diễn ra
        $this->attachActivitiesCount($club);

        // 1. mini_tournaments_today — tổng event (mini + tournament) diễn ra hôm nay
        $club->mini_tournaments_today =
            $club->miniTournaments()->whereDate('start_time', $today)->count()
            + $club->tournaments()->whereDate('start_date', $today)->count();

        // 2. unpaid_members_count — distinct user chưa confirmed trên cả 3 nguồn (1 query UNION).
        $club->unpaid_members_count = (int) DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM (
                SELECT user_id FROM club_fund_contributions
                WHERE status = ? AND user_id IS NOT NULL
                  AND club_fund_collection_id IN (
                    SELECT id FROM club_fund_collections WHERE club_id = ? AND status = ?
                  )
                UNION
                SELECT user_id FROM participants
                WHERE payment_status != 'confirmed' AND user_id IS NOT NULL
                  AND tournament_id IN (SELECT id FROM tournaments WHERE club_id = ?)
                UNION
                SELECT user_id FROM mini_participants
                WHERE payment_status != 'confirmed' AND user_id IS NOT NULL
                  AND mini_tournament_id IN (SELECT id FROM mini_tournaments WHERE club_id = ?)
             ) AS u",
            [
                ClubFundContributionStatus::Pending->value,
                $club->id,
                ClubFundCollectionStatus::Active->value,
                $club->id,
                $club->id,
            ]
        )->cnt ?? 0;

        // 3-4. returning_guests_percent + guests_count — whereNotExists thay vì whereNotIn(pluck).
        //    member = activeMembers (joined+active, có user) + guestProfiles (thành viên ảo).
        $memberExistsSql = "(
            EXISTS (SELECT 1 FROM club_members cm
                     JOIN users u ON u.id = cm.user_id
                     WHERE cm.club_id = club_guests.club_id
                       AND cm.user_id = club_guests.user_id
                       AND cm.membership_status = ? AND cm.status = ?)
            OR EXISTS (SELECT 1 FROM club_guest_profiles cgp
                        WHERE cgp.club_id = club_guests.club_id
                          AND cgp.user_id = club_guests.user_id)
        )";
        $bindings = [ClubMembershipStatus::Joined->value, ClubMemberStatus::Active->value];

        $recentTotal = (int) ClubGuest::where('club_id', $club->id)
            ->where('last_played_at', '>=', $oneMonthAgo)
            ->whereRaw("NOT {$memberExistsSql}", $bindings)
            ->count();

        if ($recentTotal === 0) {
            $club->returning_guests_percent = 0;
        } else {
            $returning = (int) ClubGuest::where('club_id', $club->id)
                ->where('last_played_at', '>=', $oneMonthAgo)
                ->where('play_count', '>', 1)
                ->whereRaw("NOT {$memberExistsSql}", $bindings)
                ->count();
            $club->returning_guests_percent = (int) round($returning / $recentTotal * 100);
        }

        $club->guests_count = (int) ClubGuest::where('club_id', $club->id)
            ->whereRaw("NOT {$memberExistsSql}", $bindings)
            ->count();
    }

    /**
     * Đếm tổng số hoạt động đang diễn ra của CLB:
     * - ClubActivity với status Scheduled hoặc Ongoing (không phân biệt có mini_tournament_id hay không)
     * - MiniTournament với status DRAFT hoặc OPEN
     * - Tournament với status DRAFT hoặc OPEN
     */
    protected function attachActivitiesCount(Club $club): void
    {
        $club->activities_count =
            $club->activities()
                ->whereIn('status', [
                    \App\Enums\ClubActivityStatus::Scheduled,
                    \App\Enums\ClubActivityStatus::Ongoing,
                ])
                ->count()
            + $club->miniTournaments()
                ->whereIn('status', [
                    \App\Models\MiniTournament::STATUS_DRAFT,
                    \App\Models\MiniTournament::STATUS_OPEN,
                ])
                ->count()
            + $club->tournaments()
                ->whereIn('status', [
                    \App\Models\Tournament::DRAFT,
                    \App\Models\Tournament::OPEN,
                ])
                ->count();
    }

    /**
     * Load members with inline projection (no FULL_RELATIONS).
     */
    protected function loadMembers(Club $club, array $options): void
    {
        // Load members + user (no nested relations yet — handle separately for clarity)
        $members = $club->activeMembers()
            ->with([
                'user' => function ($q) {
                    $q->select(['id', 'full_name', 'avatar_url'])
                        ->with('sports.sport');
                },
            ])
            ->get();

        if ($members->isEmpty()) {
            $club->setRelation('members', $members);
            return;
        }

        // Manually load scores for all user_sport ids in a single query
        $userSportIds = [];
        foreach ($members as $member) {
            if ($member->user && $member->user->relationLoaded('sports')) {
                foreach ($member->user->sports as $us) {
                    $userSportIds[] = $us->id;
                }
            }
        }

        if (!empty($userSportIds)) {
            $scores = UserSportScore::whereIn('user_sport_id', $userSportIds)
                ->whereIn('score_type', ['personal_score', 'dupr_score', 'vndupr_score'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy('user_sport_id');

            foreach ($members as $member) {
                if ($member->user && $member->user->relationLoaded('sports')) {
                    foreach ($member->user->sports as $us) {
                        $us->setRelation('scores', $scores->get($us->id, collect()));
                    }
                }
            }
        }

        // Canonical stats source — same as /me
        $memberUsers = $members->pluck('user')->filter();
        User::loadSportStatsOnUsers($memberUsers, 1);

        $club->setRelation('members', $members);
    }

    /**
     * Calculate skill level from pre-loaded members (in-memory, no query).
     */
    protected function calculateSkillLevel(Club $club): ?array
    {
        $members = $club->relationLoaded('members') ? $club->members : null;
        if (!$members || $members->isEmpty()) {
            return null;
        }

        $scores = collect();
        foreach ($members as $member) {
            $user = $member->user ?? null;
            if (!$user) {
                continue;
            }
            $score = $this->getMemberVnduprScore($user);
            if ($score !== null) {
                $scores->push($score);
            }
        }

        if ($scores->isEmpty()) {
            return null;
        }

        return [
            'min' => round($scores->min(), 1),
            'max' => round($scores->max(), 1),
        ];
    }

    /**
     * Get member's best VNDRUP score from pre-loaded relations (no query).
     */
    protected function getMemberVnduprScore($user): ?float
    {
        if ($user->relationLoaded('vnduprScores')) {
            $max = $user->vnduprScores->max('score_value');
            if ($max !== null) {
                return (float) $max;
            }
        }

        if ($user->relationLoaded('sports')) {
            foreach ($user->sports as $userSport) {
                if ($userSport->relationLoaded('scores')) {
                    $vndupr = $userSport->scores
                        ->where('score_type', 'vndupr_score')
                        ->sortByDesc('created_at')
                        ->first();
                    if ($vndupr) {
                        return (float) $vndupr->score_value;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Tính score_range (min/max vndupr) của các thành viên active trong CLB.
     * Trả về string "min-max" với 1 chữ số thập phân (vd: "1.2-2.5"), null nếu không có.
     * ponytail: O(active_members) user_sport join + 1 aggregate. Cần cache 300s nếu CLB list gọi nhiều.
     */
    protected function calculateScoreRange(Club $club): ?string
    {
        $rows = DB::select(
            "SELECT MIN(per_user.max_score) AS min_score, MAX(per_user.max_score) AS max_score
             FROM (
                 SELECT us.user_id, MAX(uss.score_value) AS max_score
                 FROM club_members cm
                 JOIN user_sport us ON us.user_id = cm.user_id
                 JOIN user_sport_scores uss ON uss.user_sport_id = us.id
                 WHERE cm.club_id = ?
                   AND cm.membership_status = ?
                   AND cm.status = ?
                   AND uss.score_type = 'vndupr_score'
                 GROUP BY us.user_id
             ) AS per_user",
            [
                $club->id,
                ClubMembershipStatus::Joined->value,
                ClubMemberStatus::Active->value,
            ]
        );

        if (empty($rows) || $rows[0]->min_score === null || $rows[0]->max_score === null) {
            return null;
        }

        $min = round((float) $rows[0]->min_score, 1);
        $max = round((float) $rows[0]->max_score, 1);

        // 1 member → trả về điểm luôn, không cần "min-max"
        if ($min === $max) {
            return (string) $min;
        }

        return "{$min}-{$max}";
    }
}
