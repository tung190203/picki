<?php

namespace App\Services\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use App\Models\UserSportScore;
use Illuminate\Support\Collection;

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
        }

        // 1a. Active member count — use withCount from controller if available, else query
        if (isset($club->active_members_count)) {
            // already loaded via withCount('activeMembers') in controller
        } else {
            $club->active_members_count = $club->activeMembers()->count();
        }

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
