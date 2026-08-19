<?php

namespace App\Services;

use App\Enums\BadgeType;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\BadgeGrantedNotification;
use App\Notifications\BadgeRevokedNotification;
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
     * Batch get primary badges for multiple users.
     * Returns array of user_id => badge_type
     */
    public function getBatchPrimaryBadges(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $orderClause = $this->getBadgeOrderByClause();

        $rows = DB::table('user_badges as ub')
            ->join(DB::raw("(SELECT user_id, MIN({$orderClause}) as min_order FROM user_badges WHERE user_id IN (" . implode(',', $userIds) . ") GROUP BY user_id) as ranked"), function ($join) {
                $join->on('ub.user_id', '=', 'ranked.user_id');
            })
            ->whereRaw("({$orderClause}) = ranked.min_order")
            ->whereIn('ub.user_id', $userIds)
            ->select('ub.user_id', 'ub.badge_type')
            ->get();

        $result = [];
        foreach ($userIds as $userId) {
            $result[$userId] = null;
        }
        foreach ($rows as $row) {
            $result[$row->user_id] = $row->badge_type;
        }

        return $result;
    }

    /**
     * Batch get all badges for multiple users.
     * Returns array of user_id => ['badges' => [...], 'primary_badge' => ...]
     */
    public function getBatchUserBadges(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $orderClause = $this->getBadgeOrderByClause();

        $userBadges = UserBadge::whereIn('user_id', $userIds)
            ->orderByRaw($orderClause)
            ->get()
            ->groupBy('user_id');

        $primaryBadges = $this->getBatchPrimaryBadges($userIds);

        $result = [];
        foreach ($userIds as $userId) {
            $badges = $userBadges->get($userId, collect());
            $result[$userId] = [
                'badges' => $badges->map(fn($ub) => $ub->badge_type->value)->toArray(),
                'primary_badge' => $primaryBadges[$userId] ?? null,
            ];
        }

        return $result;
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
     * Create a badge for a user (idempotent, uses firstOrCreate).
     */
    private function _create_badge(int $userId, BadgeType $type, ?int $createdBy = null): ?UserBadge
    {
        $existingBadge = UserBadge::where('user_id', $userId)
            ->where('badge_type', $type->value)
            ->first();

        if ($existingBadge) {
            return null;
        }

        $userBadge = UserBadge::create([
            'user_id' => $userId,
            'badge_type' => $type->value,
            'created_by' => $createdBy,
            'created_at' => now(),
        ]);

        $user = User::find($userId);
        if ($user) {
            $user->notify(new BadgeGrantedNotification($type, $createdBy));
        }

        return $userBadge;
    }

    /**
     * Grant VERIFIED badge to a user.
     */
    public function grant_verified(int $userId, ?int $createdBy = null): void
    {
        $this->_create_badge($userId, BadgeType::VERIFIED, $createdBy);
    }

    /**
     * Grant ANCHOR badge to a user.
     */
    public function grant_anchor(int $userId, ?int $createdBy = null): void
    {
        $this->_create_badge($userId, BadgeType::ANCHOR, $createdBy);
    }

    /**
     * Grant CHAMPION badge to a user. Automatically grants ANCHOR if not already present.
     */
    public function grant_champion(int $userId, ?int $createdBy = null): void
    {
        DB::transaction(function () use ($userId, $createdBy) {
            $this->_create_badge($userId, BadgeType::CHAMPION, $createdBy);
            $this->grant_anchor($userId, $createdBy);
        });
    }

    /**
     * Grant PICKI badge to a user.
     */
    public function grant_picki(int $userId, ?int $createdBy = null): void
    {
        $this->_create_badge($userId, BadgeType::PICKI, $createdBy);
    }

    /**
     * Award a badge to a user (backward compatibility wrapper).
     */
    public function awardBadge(int $userId, BadgeType $type, ?int $createdBy = null): UserBadge
    {
        return $this->_create_badge($userId, $type, $createdBy);
    }

    /**
     * Revoke a badge from a user.
     */
    public function revokeBadge(int $userId, BadgeType $type): bool
    {
        $user = User::find($userId);

        $deleted = UserBadge::where('user_id', $userId)
            ->where('badge_type', $type->value)
            ->delete() > 0;

        if ($deleted && $user) {
            $user->notify(new BadgeRevokedNotification($type));
        }

        return $deleted;
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

        asort($priority);

        return array_reverse(array_keys($priority));
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
                $this->grant_verified($user->id, $user->id);
            }

            if ($user->getRawOriginal('is_anchor')) {
                $this->grant_anchor($user->id, $user->id);
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
