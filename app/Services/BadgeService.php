<?php

namespace App\Services;

use App\Enums\BadgeType;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    /**
     * Get all badges for a user.
     *
     * @return array{badges: array<string>, primary_badge: string|null}
     */
    public function getUserBadges(int $userId): array
    {
        $userBadges = UserBadge::where('user_id', $userId)
            ->orderByRaw($this->getBadgeOrderByClause())
            ->get();

        $badges = $userBadges->map(fn(UserBadge $userBadge) => $userBadge->badge_type->value)->toArray();
        $primaryBadge = $this->getPrimaryBadge($userId);

        return [
            'badges' => $badges,
            'primary_badge' => $primaryBadge,
        ];
    }

    /**
     * Get all badges for a user (alias).
     */
    public function get_badges(int $userId): array
    {
        return $this->getUserBadges($userId);
    }

    /**
     * Get the primary badge type for a user (badge with highest priority).
     */
    public function getPrimaryBadge(int $userId): ?string
    {
        $userBadge = UserBadge::where('user_id', $userId)
            ->orderByRaw($this->getBadgeOrderByClause())
            ->first();

        return $userBadge?->badge_type->value;
    }

    /**
     * Get the primary badge for a user (alias).
     */
    public function get_primary_badge(int $userId): ?string
    {
        return $this->getPrimaryBadge($userId);
    }

    /**
     * Check if a user has any badge.
     */
    public function has_any_badge(int $userId): bool
    {
        return UserBadge::where('user_id', $userId)->exists();
    }

    /**
     * Check if a user has a specific badge type.
     */
    public function has_badge(int $userId, BadgeType $type): bool
    {
        return UserBadge::where('user_id', $userId)
            ->where('badge_type', $type->value)
            ->exists();
    }

    /**
     * Check if a user has a specific badge (alias).
     */
    public function hasBadge(int $userId, BadgeType $type): bool
    {
        return $this->has_badge($userId, $type);
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
     * Get badge priority order from config (highest priority first).
     * Config: VERIFIED=1 (lowest), ANCHOR=2, CHAMPION=3, PICKI=4 (highest)
     */
    private function getBadgePriorityOrder(): array
    {
        $priority = config('badges.priority', [
            BadgeType::VERIFIED->value => 1,
            BadgeType::ANCHOR->value => 2,
            BadgeType::CHAMPION->value => 3,
            BadgeType::PICKI->value => 4,
        ]);

        arsort($priority);

        return array_keys($priority);
    }

    /**
     * Get ORDER BY clause for MySQL FIELD() function based on config priority.
     */
    private function getBadgeOrderByClause(): string
    {
        $order = $this->getBadgePriorityOrder();
        $fieldValues = implode("', '", $order);

        return "FIELD(badge_type, '{$fieldValues}')";
    }

    /**
     * Sync badges from legacy is_verified/is_anchor fields.
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
