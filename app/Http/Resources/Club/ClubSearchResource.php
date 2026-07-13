<?php

namespace App\Http\Resources\Club;

/**
 * ClubSearchResource — for club search results.
 *
 * Reads pre-set attributes from ClubService (attachMembershipStatus, attachUnreadNotificationCount).
 * No queries, no computation.
 */
class ClubSearchResource extends ClubBaseResource
{
    public function toArray($request): array
    {
        $club = $this->resource;

        return [
            'id' => $club->id,
            'name' => $club->name,
            'address' => $club->address,
            'latitude' => $club->latitude,
            'longitude' => $club->longitude,
            'logo_url' => $club->logo_url,
            'status' => $club->status?->value ?? $club->status,
            'is_public' => (bool) ($club->is_public ?? true),
            'is_verified' => (bool) $club->is_verified,
            'created_by' => $club->created_by,

            // Pre-set by ClubService::attachMembershipStatus
            'is_member' => (bool) ($club->is_member ?? false),
            'is_admin' => (bool) ($club->is_admin ?? false),
            'has_pending_request' => (bool) ($club->has_pending_request ?? false),
            'has_invitation' => (bool) ($club->has_invitation ?? false),

            // Pre-set by ClubService::attachMembershipStatus
            'invited_by' => $club->_invited_by_user ?? null,

            // from withCount
            'quantity_members' => (int) (
                $club->active_members_count
                ?? $club->activeMembers_count
                ?? ($this->relationLoaded('activeMembers') ? $this->activeMembers->count() : 0)
            ),

            // from withCount + calculation (if activeMembers loaded)
            'skill_level' => $club->_skill_level ?? null,

            // Profile excerpt
            'profile' => $this->whenLoaded('profile', function () {
                $p = $this->profile;
                return [
                    'id' => $p?->id,
                    'description' => $p?->description,
                    'cover_image_url' => $p?->cover_image_url,
                    'footer' => $p?->footer,
                ];
            }),

            // Pre-set by ClubService::attachUnreadNotificationCount
            'unread_notification_count' => $this->when(
                isset($club->unread_notification_count),
                fn () => (int) $club->unread_notification_count
            ),

            // Distance (if nearBy scope used)
            'distance' => $this->when(
                isset($club->distance),
                fn () => $club->distance !== null ? round((float) $club->distance, 1) : null
            ),

            'created_at' => $club->created_at?->toISOString(),
        ];
    }
}
