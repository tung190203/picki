<?php

namespace App\Http\Resources\Club;

/**
 * ClubBaseResource — shared base for all Club resources.
 * Provides common helper methods but does NOT fire queries.
 */
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ClubBaseResource extends JsonResource
{
    /**
     * Calculate skill_level from pre-loaded members (no query).
     * Call this AFTER ClubDetailAssembler has populated the data.
     */
    protected function calculateSkillLevel(): ?array
    {
        $members = $this->getMemberCollection();
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
     * Get the member collection from various possible relation names.
     * Checks both 'members' and 'activeMembers' in priority order.
     */
    protected function getMemberCollection(): ?\Illuminate\Support\Collection
    {
        foreach (['activeMembers', 'members'] as $rel) {
            if ($this->relationLoaded($rel)) {
                return $this->{$rel};
            }
        }
        return null;
    }

    /**
     * Get member's VNDRUP score from pre-loaded relations (no query).
     */
    protected function getMemberVnduprScore($member): ?float
    {
        $user = $member;
        if (!$user) {
            return null;
        }
        $score = null;

        if ($user->relationLoaded('vnduprScores')) {
            $max = $user->vnduprScores->max('score_value');
            $score = $max !== null ? (float) $max : null;
        }
        if ($score === null && $user->relationLoaded('sports')) {
            foreach ($user->sports ?? [] as $userSport) {
                $sportScores = $userSport->relationLoaded('scores')
                    ? $userSport->scores
                    : collect();
                $vndupr = $sportScores->where('score_type', 'vndupr_score')->sortByDesc('created_at')->first();
                if ($vndupr) {
                    $score = (float) $vndupr->score_value;
                    break;
                }
            }
        }
        return $score;
    }

    /**
     * Check if user has can_edit_footer based on pre-loaded membership (no query).
     */
    protected function canEditFooter(): bool
    {
        $members = $this->getMemberCollection();
        if (!$members) {
            return false;
        }
        $userId = auth()->id();
        if (!$userId) {
            return false;
        }
        $member = $members->first(fn ($m) =>
            (int) $m->user_id === (int) $userId
            && $m->membership_status === ClubMembershipStatus::Joined
            && $m->status === ClubMemberStatus::Active
        );
        return $member && in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Secretary]);
    }

    /**
     * Format profile settings (no query).
     */
    protected function formatProfile(?\Illuminate\Database\Eloquent\Model $profile): array
    {
        if (!$profile) {
            return $this->getDefaultProfile();
        }
        $settings = $profile->settings ?? [];
        return [
            'id' => $profile->id,
            'description' => $profile->description,
            'cover_image_url' => $profile->cover_image_url,
            'qr_code_image_url' => $profile->qr_code_image_url,
            'qr_code_enabled' => (bool) ($settings['qr_code_enabled'] ?? false),
            'phone' => $profile->phone,
            'email' => $profile->email,
            'website' => $profile->website,
            'address' => $profile->address,
            'city' => $profile->city,
            'province' => $profile->province,
            'country' => $profile->country,
            'footer' => $profile->footer,
            'latitude' => $profile->latitude,
            'longitude' => $profile->longitude,
            'zalo_link' => $profile->zalo_link,
            'zalo_link_enabled' => (bool) ($settings['zalo_link_enabled'] ?? false),
            'qr_zalo' => $profile->qr_zalo_url,
            'qr_zalo_enabled' => (bool) ($settings['qr_zalo_enabled'] ?? false),
        ];
    }

    protected function getDefaultProfile(): array
    {
        return [
            'id' => null,
            'description' => null,
            'cover_image_url' => null,
            'qr_code_image_url' => null,
            'qr_code_enabled' => false,
            'phone' => null,
            'email' => null,
            'website' => null,
            'address' => null,
            'city' => null,
            'province' => null,
            'country' => null,
            'footer' => null,
            'latitude' => null,
            'longitude' => null,
            'zalo_link' => null,
            'zalo_link_enabled' => false,
            'qr_zalo' => null,
            'qr_zalo_enabled' => false,
        ];
    }
}
