<?php

namespace App\Http\Resources\Map;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $userId = auth()->id();

        $isMember = false;
        $isAdmin = false;
        $hasPendingRequest = false;

        if ($userId && $this->relationLoaded('members')) {
            $membership = $this->members
                ->where('user_id', $userId)
                ->first();

            if ($membership) {
                $status = $membership->membership_status;
                $role = $membership->role;

                $isMember = $status === \App\Enums\ClubMembershipStatus::Joined
                    && $membership->status !== \App\Enums\ClubMemberStatus::Suspended;
                $isAdmin = $this->created_by === $userId
                    || in_array($role, [\App\Enums\ClubMemberRole::Admin->value, \App\Enums\ClubMemberRole::Manager->value, \App\Enums\ClubMemberRole::Secretary->value]);
                $hasPendingRequest = $status === \App\Enums\ClubMembershipStatus::Pending;
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
            'has_invitation'   => false,
            'invited_by'       => null,
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
