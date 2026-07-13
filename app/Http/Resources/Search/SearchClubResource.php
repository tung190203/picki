<?php

namespace App\Http\Resources\Search;

use App\Enums\ClubMembershipStatus;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SearchClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = auth()->id();

        $isMember = false;
        $isAdmin = false;
        $hasPendingRequest = false;
        $hasInvitation = false;
        $invitedBy = null;

        if ($userId && $this->relationLoaded('members')) {
            $membership = $this->members->firstWhere('user_id', $userId);

            if ($membership) {
                $status = $membership->membership_status;
                $role = $membership->role;

                $isMember = $status === ClubMembershipStatus::Joined
                    && $membership->status !== ClubMemberStatus::Suspended;
                $isAdmin = $this->created_by === $userId
                    || in_array($role, [ClubMemberRole::Admin->value, ClubMemberRole::Manager->value, ClubMemberRole::Secretary->value]);
                $hasPendingRequest = $status === ClubMembershipStatus::Pending
                    && $membership->invited_by === null;
                $hasInvitation = $status === ClubMembershipStatus::Pending
                    && $membership->invited_by !== null;
            }
        }

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'address'          => $this->address ?? $this->whenLoaded('profile', fn() => $this->profile?->address),
            'latitude'         => $this->latitude ?? $this->whenLoaded('profile', fn() => $this->profile?->latitude),
            'longitude'        => $this->longitude ?? $this->whenLoaded('profile', fn() => $this->profile?->longitude),
            'logo_url'         => $this->logo_url,
            'status'           => $this->status->value,
            'is_verified'      => (bool) $this->is_verified,
            'is_public'        => (bool) ($this->is_public ?? true),
            'created_by'       => $this->creator?->id,
            'quantity_members' => (int) ($this->activeMembers_count ?? $this->activeMembers?->count() ?? 0),
            'is_admin'         => $isAdmin,
            'is_member'        => $isMember,
            'has_pending_request' => $hasPendingRequest,
            'has_invitation' => $hasInvitation,
            'invited_by'       => $invitedBy,
            'profile'          => $this->whenLoaded('profile', fn() => [
                'description'     => $this->profile?->description,
                'cover_image_url' => $this->profile?->cover_image_url,
            ]),
            'distance'         => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'      => 'club',
            'active_matches_count' => $this->active_matches_count ?? 0,
            'active_tournaments_count' => $this->active_tournaments_count ?? 0,
            'announcements_count' => $this->announcements_count ?? 0,
        ];
    }
}
