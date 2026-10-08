<?php

namespace App\Http\Resources\Map;

use App\Http\Resources\Concerns\ResolvesClubMemberCount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapClubResource extends JsonResource
{
    use ResolvesClubMemberCount;
    public function toArray(Request $request): array
    {
        $userId = auth()->id();

        $isMember = false;
        $isAdmin = false;
        $hasPendingRequest = false;

        if ($userId) {
            // Prefer the batch-preloaded flag from ClubService::attachMembershipStatus
            // so users with role=Admin are correctly flagged even when members isn't loaded.
            if (isset($this->is_admin)) {
                $isAdmin = (bool) $this->is_admin;
            }
            if (isset($this->is_member)) {
                $isMember = (bool) $this->is_member;
            }
        }

        if ($userId && $this->relationLoaded('members')) {
            $membership = $this->members
                ->where('user_id', $userId)
                ->first();

            if ($membership) {
                $status = $membership->membership_status;
                $role = $membership->role;

                $isMember = $isMember || ($status === \App\Enums\ClubMembershipStatus::Joined
                    && $membership->status !== \App\Enums\ClubMemberStatus::Suspended);
                // In DB, created_by is NOT NULL unsigned int (defaults to 0 when not set).
                // Treat both null and 0 as "no creator set" → fall through to role check.
                $isAdmin = $isAdmin
                    || (($this->created_by ?? 0) !== 0 && $this->created_by === $userId)
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
            'quantity_members' => $this->resolveClubQuantityMembers(),
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

            // Enricher-attached fields (same as SearchClubResource so map + list stay aligned)
            'followers_count'  => $this->followers_count ?? 0,
            'is_following'     => $this->is_following ?? false,
            'score_range'      => $this->skill_level,
            'score_range_text' => $this->skill_level
                ? $this->skill_level['min'] . '-' . $this->skill_level['max']
                : null,
            'recruitment_status' => $this->recruitment_status,
            'recruitment_status_text' => match ($this->recruitment_status) {
                'open' => 'Đang tuyển thành viên',
                'closed' => 'Đã đóng tuyển',
                'invite_only' => 'Chỉ mời',
                default => null,
            },
            'recurring_schedule_text' => $this->recurring_schedule_text
                ? \Illuminate\Support\Str::limit($this->recurring_schedule_text, 100)
                : null,
            'primary_home_court' => $this->primary_home_court,
            'admin' => $this->when(
                $this->relationLoaded('creator') || $this->relationLoaded('members'),
                fn() => $this->buildAdmin()
            ),
        ];
    }

    private function buildAdmin(): ?array
    {
        if ($this->creator) {
            $user = $this->creator;
            $score = $user->relationLoaded('vnduprScores')
                ? $user->vnduprScores->max('score_value')
                : null;
            return [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'avatar_url' => $user->avatar_url,
                'vndupr_score' => $score !== null ? round((float) $score, 3) : null,
            ];
        }
        return null;
    }
}
