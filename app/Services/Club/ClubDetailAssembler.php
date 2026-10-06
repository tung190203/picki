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
use App\Models\Follow;
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

        // 1a. Member count = user thật (joined/active) + thành viên ảo (club_virtual_members).
        // `_real_members_count` giữ raw count để assemble() gọi lại không cộng dồn VM nhiều lần.
        // Nếu controller đã withCount('activeMembers') thì tái dùng `active_members_count` cho khỏi query.
        if (!isset($club->_real_members_count)) {
            $club->_real_members_count = isset($club->active_members_count)
                ? (int) $club->active_members_count
                : $club->activeMembers()->count();
        }
        $club->setAttribute(
            'active_members_count',
            (int) $club->_real_members_count + $this->countVirtualMembers($club)
        );
        // Cờ báo: active_members_count đã bao gồm thành viên ảo.
        // Resource dùng cờ này để không cộng thêm lần nữa.
        $club->setAttribute('_virtual_members_counted', true);

        // 2. Calculate rank (cached, ~0ms)
        $club->rank = $this->leaderboardService->calculateClubRank($club);

        // 3. Load members only if user is a member (and option allows)
        if ($loadMembers && ($club->is_member ?? false)) {
            $this->loadMembers($club, $options);
            // 4. Calculate skill level from loaded members (in-memory)
            $club->_skill_level = $this->calculateSkillLevel($club);
        }

        return $club;
    }

    /**
     * Số thành viên ảo (club_virtual_members) của CLB.
     * Dùng `virtual_members_count` từ withCount nếu có, để không query thêm.
     */
    protected function countVirtualMembers(Club $club): int
    {
        if (isset($club->virtual_members_count)) {
            return (int) $club->virtual_members_count;
        }

        if ($club->relationLoaded('virtualMembers')) {
            return $club->virtualMembers->count();
        }

        return $club->virtualMembers()->count();
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
     *  - followers_count_excluding_members: số follower KHÔNG phải thành viên CLB
     */
    public function attachFollowStatus(Club $club, int $userId): void
    {
        $club->is_following = $club->isFollowedBy($userId);

        // Đếm follower không phải member (joined + active) bằng 1 query
        $club->followers_count_excluding_members = (int) Follow::where('followable_id', $club->id)
            ->where('followable_type', Club::class)
            ->whereNotExists(function ($q) use ($club) {
                $q->select(DB::raw(1))
                    ->from('club_members')
                    ->whereColumn('club_members.user_id', 'follows.user_id')
                    ->where('club_members.club_id', $club->id)
                    ->where('membership_status', ClubMembershipStatus::Joined->value)
                    ->where('status', ClubMemberStatus::Active->value);
            })
            ->count();
    }

    /**
     * Attach 4 admin stats (kèo hôm nay, chưa trả tiền, % khách quay lại, tổng khách).
     * Chỉ gọi khi user có quyền canManage (admin/manager/secretary).
     *
     * ponytail: 6 query, không cache. Đủ nhanh cho 1 club detail request.
     * Khi mở rộng sang CLB list (nhiều CLB cùng lúc) → cache 180s theo club_id.
     */
    public function attachAdminStats(Club $club): void
    {
        $oneMonthAgo = now()->subDays(30)->toDateString();
        $today = now()->toDateString();
        $memberUserIds = $club->activeMembers()->pluck('user_id')->all();
        $tournamentIds = $club->tournaments()->pluck('id');
        $miniIds = $club->miniTournaments()->pluck('id');

        // 1. mini_tournaments_today — tổng event (mini + tournament) diễn ra hôm nay
        $club->mini_tournaments_today =
            $club->miniTournaments()->whereDate('start_time', $today)->count()
            + $club->tournaments()->whereDate('start_date', $today)->count();

        // 2. unpaid_members_count — tổng lượt participant + fund contribution chưa confirmed
        $unpaidUserIds = collect();
        if (!empty($miniIds) || !empty($tournamentIds)) {
            $activeCollectionIds = $club->fundCollections()
                ->where('status', ClubFundCollectionStatus::Active->value)
                ->pluck('id');
            if ($activeCollectionIds->isNotEmpty()) {
                $unpaidUserIds = $unpaidUserIds->merge(
                    ClubFundContribution::whereIn('club_fund_collection_id', $activeCollectionIds)
                        ->where('status', ClubFundContributionStatus::Pending->value)
                        ->pluck('user_id')
                );
            }
            $unpaidUserIds = $unpaidUserIds->merge(
                Participant::whereIn('tournament_id', $tournamentIds)
                    ->where('payment_status', '!=', 'confirmed')
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
            );
            $unpaidUserIds = $unpaidUserIds->merge(
                MiniParticipant::whereIn('mini_tournament_id', $miniIds)
                    ->where('payment_status', '!=', 'confirmed')
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
            );
        }
        $club->unpaid_members_count = $unpaidUserIds->unique()->count();

        // 3. returning_guests_percent — dựa trên club_guests (bảng định danh khách).
        //    Mẫu số = user trong club_guests có last_played_at trong 30 ngày gần,
        //    không phải member hiện tại. Tử số = trong nhóm đó, user có play_count > 1
        //    (đã từng chơi TRƯỚC 30 ngày, tức "quay lại").
        $recentGuestIds = ClubGuest::where('club_id', $club->id)
            ->whereNotIn('user_id', $memberUserIds)
            ->where('last_played_at', '>=', $oneMonthAgo)
            ->pluck('user_id');

        if ($recentGuestIds->isEmpty()) {
            $club->returning_guests_percent = 0;
        } else {
            $returning = ClubGuest::where('club_id', $club->id)
                ->whereIn('user_id', $recentGuestIds)
                ->where('play_count', '>', 1)
                ->count();
            $club->returning_guests_percent = (int) round($returning / $recentGuestIds->count() * 100);
        }

        // 4. guests_count — tổng user trong club_guests chưa là member
        $club->guests_count = ClubGuest::where('club_id', $club->id)
            ->whereNotIn('user_id', $memberUserIds)
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
}
