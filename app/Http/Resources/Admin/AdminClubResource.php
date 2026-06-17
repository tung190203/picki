<?php

namespace App\Http\Resources\Admin;

use App\Enums\ClubMembershipStatus;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubNotificationStatus;
use App\Enums\ClubMemberRole;
use App\Models\Club\ClubMember;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeTournaments = $this->tournaments
            ? $this->tournaments->filter(fn($t) => in_array($t->status, [Tournament::OPEN, 5]))
            : collect();

        $activeMiniTournaments = $this->miniTournaments
            ? $this->miniTournaments->filter(fn($t) => in_array($t->status, [MiniTournament::STATUS_OPEN, MiniTournament::STATUS_CLOSED]))
            : collect();

        $activeMatchesCount = $activeTournaments->sum(fn($t) =>
            $t->relationLoaded('tournamentTypes')
                ? $t->tournamentTypes->sum(fn($tt) =>
                    $tt->relationLoaded('groups')
                        ? $tt->groups->sum(fn($g) =>
                            $g->relationLoaded('matches')
                                ? $g->matches->where('status', 'pending')->count()
                                : 0
                        )
                        : 0
                )
                : 0
        ) + $activeMiniTournaments->sum(fn($t) =>
            $t->relationLoaded('matches')
                ? $t->matches->whereIn('status', ['pending', 'going_on', 'waiting_confirm'])->count()
                : 0
        );

        $activeTournamentsCount = $activeTournaments->count() + $activeMiniTournaments->count();

        $announcementsCount = $this->relationLoaded('notifications')
            ? $this->notifications->where('status', '!=', ClubNotificationStatus::Cancelled)->count()
            : 0;

        $adminMember = $this->adminMember;

        $summary = sprintf(
            'Đang có %d kèo - %d giải đấu - %d thông báo',
            $activeMatchesCount,
            $activeTournamentsCount,
            $announcementsCount
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo_url' => $this->logo_url,
            'status' => $this->status?->value ?? $this->status,
            'is_public' => (bool) ($this->is_public ?? true),
            'is_verified' => (bool) $this->is_verified,
            'rank' => $this->rank ?? null,
            'created_at' => $this->created_at?->toISOString(),

            'admin' => $adminMember && $adminMember->user ? [
                'id' => $adminMember->user->id,
                'full_name' => $adminMember->user->full_name,
                'avatar_url' => $adminMember->user->avatar_url,
            ] : null,

            'members_count' => $this->when(
                $this->relationLoaded('activeMembers'),
                fn() => $this->activeMembers->where('membership_status', ClubMembershipStatus::Joined)->count(),
                0
            ),

            'active_matches_count' => $activeMatchesCount,
            'active_tournaments_count' => $activeTournamentsCount,
            'announcements_count' => $announcementsCount,
            'summary' => $summary,
        ];
    }
}
