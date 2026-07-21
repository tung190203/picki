<?php

namespace App\Services;

use App\Enums\BadgeType;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * Get all badges for a user with primary badge info.
     *
     * @return array{badges: array, primary_badge: array|null}
     */
    public function getUserBadges(int $userId): array
    {
        $userBadges = UserBadge::where('user_id', $userId)
            ->orderByRaw("FIELD(badge_type, 'PICKI', 'CHAMPION', 'ANCHOR', 'VERIFIED')")
            ->get();

        $badges = $userBadges->map(function (UserBadge $userBadge) {
            return [
                'type' => $userBadge->badge_type->value,
                'label' => $userBadge->badge_type->label(),
                'description' => $userBadge->badge_type->description(),
                'created_at' => $userBadge->created_at?->toISOString(),
            ];
        })->toArray();

        $primaryBadge = $this->getPrimaryBadge($userId);

        return [
            'badges' => $badges,
            'primary_badge' => $primaryBadge,
        ];
    }

    /**
     * Get the primary badge for a user based on priority config.
     */
    public function getPrimaryBadge(int $userId): ?array
    {
        $userBadge = UserBadge::where('user_id', $userId)
            ->orderByRaw("FIELD(badge_type, 'PICKI', 'CHAMPION', 'ANCHOR', 'VERIFIED')")
            ->first();

        if (!$userBadge) {
            return null;
        }

        return [
            'type' => $userBadge->badge_type->value,
            'label' => $userBadge->badge_type->label(),
            'description' => $userBadge->badge_type->description(),
            'created_at' => $userBadge->created_at?->toISOString(),
        ];
    }

    /**
     * Award a badge to a user.
     */
    public function awardBadge(int $userId, BadgeType $type, ?int $createdBy = null): UserBadge
    {
        return UserBadge::firstOrCreate(
            [
                'user_id' => $userId,
                'badge_type' => $type->value,
            ],
            [
                'created_by' => $createdBy,
                'created_at' => now(),
            ]
        );
    }

    /**
     * Revoke a badge from a user.
     */
    public function revokeBadge(int $userId, BadgeType $type): bool
    {
        return UserBadge::where('user_id', $userId)
            ->where('badge_type', $type->value)
            ->delete() > 0;
    }

    /**
     * Check if a user has a specific badge.
     */
    public function hasBadge(int $userId, BadgeType $type): bool
    {
        return UserBadge::where('user_id', $userId)
            ->where('badge_type', $type->value)
            ->exists();
    }

    /**
     * Sync badges from legacy is_verified/is_anchor fields.
     * Call this once during migration to migrate existing data.
     */
    public function syncFromLegacyFields(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($user->getRawOriginal('is_verified')) {
                $this->awardBadge($user->id, BadgeType::VERIFIED, $user->id);
            }

            if ($user->getRawOriginal('is_anchor')) {
                $this->awardBadge($user->id, BadgeType::ANCHOR, $user->id);
            }
        });
    }

    /**
     * Batch sync badges from legacy fields for all users.
     */
    public function syncAllFromLegacyFields(): int
    {
        $count = 0;

        User::where('is_verified', true)
            ->orWhere('is_anchor', true)
            ->chunk(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $this->syncFromLegacyFields($user);
                    $count++;
                }
            });

        return $count;
    }
}
