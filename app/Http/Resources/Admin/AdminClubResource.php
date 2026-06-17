<?php

namespace App\Http\Resources\Admin;

use App\Enums\ClubMembershipStatus;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubNotificationStatus;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = Carbon::now();

        $activeMiniTournaments = $this->miniTournaments
            ? $this->miniTournaments->filter(fn($t) =>
                in_array($t->status, [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN])
                && ($t->end_time === null || $t->end_time->gte($now))
            )
            : collect();

        $activeTournaments = $this->tournaments
            ? $this->tournaments->filter(fn($t) =>
                in_array($t->status, [Tournament::DRAFT, Tournament::OPEN])
                && ($t->end_date === null || Carbon::parse($t->end_date)->endOfDay()->gte($now))
            )
            : collect();

        $announcementsCount = $this->relationLoaded('notifications')
            ? $this->notifications->where('status', '!=', ClubNotificationStatus::Cancelled)->count()
            : 0;

        $adminMember = $this->adminMember;

        $summary = sprintf(
            'Đang có %d kèo - %d giải đấu - %d thông báo',
            $activeMiniTournaments->count(),
            $activeTournaments->count(),
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
            'is_banned' => (bool) ($this->is_banned ?? false),
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

            'active_matches_count' => $activeMiniTournaments->count(),
            'active_tournaments_count' => $activeTournaments->count(),
            'announcements_count' => $announcementsCount,
            'summary' => $summary,
        ];
    }
}
