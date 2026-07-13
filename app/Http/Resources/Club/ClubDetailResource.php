<?php

namespace App\Http\Resources\Club;

use App\Services\Club\ClubDetailAssembler;

/**
 * ClubDetailResource — full club detail response.
 *
 * No computation, no queries. Reads pre-set attributes from ClubDetailAssembler.
 * GET /clubs/{id} continues to use this throughout the rollout.
 */
class ClubDetailResource extends ClubBaseResource
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
            'is_banned' => (bool) ($club->is_banned ?? false),
            'rank' => $club->rank ?? null,
            'created_by' => $club->created_by,

            // Membership flags — pre-set by ClubDetailAssembler
            'is_member' => (bool) ($club->is_member ?? false),
            'is_admin' => (bool) ($club->is_admin ?? false),
            'has_pending_request' => (bool) ($club->has_pending_request ?? false),
            'has_invitation' => (bool) ($club->has_invitation ?? false),

            // Invited by — pre-set by ClubDetailAssembler
            'invited_by' => $club->_invited_by_user ?? null,

            // Stats — pre-set by Assembler or withCount
            'quantity_members' => (int) (
                $club->active_members_count
                ?? $club->activeMembers_count
                ?? ($this->relationLoaded('activeMembers') ? $this->activeMembers->count() : 0)
            ),

            // Skill level — computed by Assembler if members loaded
            'skill_level' => $club->_skill_level ?? null,

            // Members — only included if explicitly loaded
            'members' => $this->when($this->relationLoaded('members') && $this->members, function () {
                return ClubMemberResource::collection($this->members);
            }),

            // Profile
            'profile' => $this->whenLoaded('profile', function () {
                return $this->formatProfile($this->profile);
            }, $this->getDefaultProfile()),

            // Fund QR (if wallet loaded)
            'fund_qr' => $this->whenLoaded('mainWallet', function () {
                return [
                    'qr_code_url' => $this->mainWallet?->qr_code_url,
                    'qr_note' => $this->mainWallet?->qr_note,
                ];
            }),

            // Timestamps
            'created_at' => $club->created_at?->toISOString(),
            'updated_at' => $club->updated_at?->toISOString(),

            // Unread notifications — pre-set by ClubDetailAssembler
            'unread_notification_count' => $this->when(
                isset($club->unread_notification_count),
                fn () => (int) $club->unread_notification_count
            ),
        ];
    }
}
