<?php

namespace App\Http\Resources\Search;

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
        $invitedBy = null;

        if ($userId && $this->relationLoaded('members')) {
            $membership = $this->members
                ->where('user_id', $userId)
                ->first();

            if ($membership) {
                $status = $membership->membership_status ?? null;
                $role = $membership->role ?? null;

                $isMember = $status === \App\Enums\ClubMembershipStatus::Joined->value
                    && $membership->status !== \App\Enums\ClubMemberStatus::Suspended->value;
                $isAdmin = in_array($role, [\App\Enums\ClubMemberRole::Admin->value, \App\Enums\ClubMemberRole::Manager->value, \App\Enums\ClubMemberRole::Secretary->value]);
                $hasPendingRequest = $status === \App\Enums\ClubMembershipStatus::Pending->value;
            }
        }

        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'address'          => $this->address ?? $this->whenLoaded('profile', fn() => $this->profile?->address),
            'latitude'         => $this->latitude,
            'longitude'        => $this->longitude,
            'logo_url'         => $this->logo_url,
            'status'           => (int) $this->status,
            'is_verified'      => (bool) $this->is_verified,
            'is_public'        => (bool) ($this->is_public ?? true),
            'created_by'       => new \App\Http\Resources\UserResource($this->whenLoaded('creator')),
            'quantity_members' => (int) ($this->activeMembers_count ?? $this->activeMembers?->count() ?? 0),
            'is_admin'         => $isAdmin,
            'is_member'        => $isMember,
            'has_pending_request' => $hasPendingRequest,
            'has_invitation'   => false,
            'invited_by'       => $invitedBy,
            'profile'          => $this->whenLoaded('profile', fn() => [
                'description'     => $this->profile?->description,
                'cover_image_url' => $this->profile?->cover_image_url,
            ]),
            'distance'         => $this->when(isset($this->distance), round($this->distance, 1)),
            'marker_type'      => 'club',
        ];
    }
}
