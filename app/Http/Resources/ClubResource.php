<?php

namespace App\Http\Resources;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Http\Resources\Club\ClubMemberResource;
use App\Models\Club\ClubMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{

    public function toArray(Request $request): array
    {
        $isMember = auth()->check() && ClubMember::where('club_id', $this->id)
            ->where('user_id', auth()->id())
            ->where('membership_status', ClubMembershipStatus::Joined)
            ->where('status', ClubMemberStatus::Active)
            ->exists();

        if (!$isMember) {
            return $this->toLimitedArray();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo_url' => $this->logo_url,
            'status' => $this->status,
            'is_public' => (bool) ($this->is_public ?? true),
            'is_verified' => (bool) $this->is_verified,
            'rank' => $this->rank ?? null,
            'created_by' => $this->created_by,
            'members' => ClubMemberResource::collection($this->whenLoaded('members')),
            'quantity_members' => $this->whenLoaded('members', fn() =>
                $this->members
                    ->filter(fn($m) => $m->user !== null)
                    ->where('membership_status', ClubMembershipStatus::Joined)
                    ->where('status', ClubMemberStatus::Active)
                    ->count(),
                0
            ),
            'skill_level' => $this->whenLoaded('members', function () {
                $scores = $this->members
                    ->filter(fn($m) => $m->user !== null)
                    ->where('membership_status', ClubMembershipStatus::Joined)
                    ->where('status', ClubMemberStatus::Active)
                    ->map(fn($member) => $this->getMemberVnduprScore($member))
                    ->filter(fn($score) => $score !== null);

                if ($scores->isEmpty()) {
                    return null;
                }

                return [
                    'min' => round($scores->min(), 1),
                    'max' => round($scores->max(), 1),
                ];
            }, null),
            'is_member' => true,
            'has_pending_request' => $this->when(auth()->check(), fn () =>
                ClubMember::where('club_id', $this->id)
                    ->where('user_id', auth()->id())
                    ->where('membership_status', ClubMembershipStatus::Pending)
                    ->whereNull('invited_by')
                    ->exists(),
                false
            ),
            'has_invitation' => $this->when(auth()->check(), fn () =>
                ClubMember::where('club_id', $this->id)
                    ->where('user_id', auth()->id())
                    ->where('membership_status', ClubMembershipStatus::Pending)
                    ->whereNotNull('invited_by')
                    ->exists(),
                false
            ),
            'invited_by' => auth()->check() ? $this->getInvitedByInfo() : null,
            'can_edit_footer' => $this->when(auth()->check(), fn () => $this->resource->canEditFooter(auth()->id()), false),
            'profile' => $this->whenLoaded('profile', fn () => static::formatProfile($this->profile), static::getDefaultProfile()),
            'fund_qr' => $this->whenLoaded('mainWallet', fn () => [
                'qr_code_url' => $this->mainWallet?->qr_code_url,
                'qr_note'     => $this->mainWallet?->qr_note,
            ]),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    protected function toLimitedArray(): array
    {
        $profile = $this->profile;
        $activeMembers = $this->resource->relationLoaded('activeMembers')
            ? $this->resource->activeMembers
            : $this->resource->activeMembers()->with(['user.vnduprScores', 'user.sports.scores'])->get();
        $scores = $activeMembers->map(fn ($m) => $this->getMemberVnduprScore($m))->filter(fn ($s) => $s !== null);
        $skillLevel = $scores->isEmpty() ? null : [
            'min' => round($scores->min(), 1),
            'max' => round($scores->max(), 1),
        ];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo_url' => $this->logo_url,
            'status' => $this->status,
            'is_public' => (bool) ($this->is_public ?? true),
            'is_verified' => (bool) $this->is_verified,
            'quantity_members' => $activeMembers->count(),
            'skill_level' => $skillLevel,
            'rank' => $this->rank ?? null,
            'is_member' => false,
            'has_pending_request' => auth()->check() && ClubMember::where('club_id', $this->id)
                ->where('user_id', auth()->id())
                ->where('membership_status', ClubMembershipStatus::Pending)
                ->whereNull('invited_by')
                ->exists(),
            'has_invitation' => auth()->check() && ClubMember::where('club_id', $this->id)
                ->where('user_id', auth()->id())
                ->where('membership_status', ClubMembershipStatus::Pending)
                ->whereNotNull('invited_by')
                ->exists(),
            'invited_by' => auth()->check() ? $this->getInvitedByInfo() : null,
            'profile' => [
                'id' => $profile?->id,
                'description' => $profile?->description,
                'cover_image_url' => $profile?->cover_image_url,
                'footer' => $profile?->footer,
                'zalo_link_enabled' => (bool) (($profile?->settings ?? [])['zalo_link_enabled'] ?? false),
                'qr_zalo_enabled' => (bool) (($profile?->settings ?? [])['qr_zalo_enabled'] ?? false),
            ],
        ];
    }

    public static function formatProfile(?\Illuminate\Database\Eloquent\Model $profile): array
    {
        if (!$profile) {
            return static::getDefaultProfile();
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

    protected static function getDefaultProfile(): array
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

    protected function getMemberVnduprScore($member): ?float
    {
        $user = $member->user ?? null;
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
                $scores = $userSport->relationLoaded('scores') ? $userSport->scores : collect();
                $vndupr = $scores->where('score_type', 'vndupr_score')->sortByDesc('created_at')->first();
                if ($vndupr) {
                    $score = (float) $vndupr->score_value;
                    break;
                }
            }
        }
        return $score;
    }

    protected function getInvitedByInfo(): ?array
    {
        $membership = ClubMember::where('club_id', $this->id)
            ->where('user_id', auth()->id())
            ->with('invitedBy')
            ->first();

        if (!$membership || !$membership->invitedBy) {
            return null;
        }

        return [
            'id' => $membership->invitedBy->id,
            'full_name' => $membership->invitedBy->full_name,
            'avatar_url' => $membership->invitedBy->avatar_url,
        ];
    }
}
