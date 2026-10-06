<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\BadgeGrantedNotification;
use App\Notifications\BadgeRevokedNotification;
use Illuminate\Support\Facades\DB;

class BadgeService
{
    public function getUserBadges(int $userId): array
    {
        $userBadges = UserBadge::with('badge')
            ->where('user_id', $userId)
            ->get()
            ->sortByDesc(fn($ub) => $ub->badge->priority ?? 0);

        $badges = $userBadges->map(function ($ub) {
            if (!$ub->badge) return null;
            return ['type' => $ub->badge->code, 'icon_url' => $ub->badge->icon_url];
        })->filter()->values()->toArray();

        $featuredBadges = $userBadges->filter(fn($ub) => $ub->is_featured)->map(function ($ub) {
            if (!$ub->badge) return null;
            return ['type' => $ub->badge->code, 'icon_url' => $ub->badge->icon_url];
        })->filter()->values()->toArray();

        $primaryBadge = $badges[0] ?? null;

        return [
            'badges' => $badges,
            'featured_badges' => $featuredBadges,
            'primary_badge' => $primaryBadge,
        ];
    }

    public function get_badges(int $userId): array
    {
        return $this->getUserBadges($userId);
    }

    public function getPrimaryBadge(int $userId): ?array
    {
        $userBadge = UserBadge::with('badge')
            ->where('user_id', $userId)
            ->get()
            ->sortByDesc(fn($ub) => $ub->badge->priority ?? 0)
            ->first();

        if (!$userBadge?->badge) return null;
        return [
            'type' => $userBadge->badge->code,
            'icon_url' => $userBadge->badge->icon_url,
        ];
    }

    public function get_primary_badge(int $userId): ?array
    {
        return $this->getPrimaryBadge($userId);
    }

    public function getBatchPrimaryBadges(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userBadges = UserBadge::with('badge')
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $result = [];
        foreach ($userIds as $userId) {
            $badges = $userBadges->get($userId, collect());
            $primary = $badges->sortByDesc(fn($ub) => $ub->badge->priority ?? 0)->first();
            $result[$userId] = $primary?->badge ? [
                'type' => $primary->badge->code,
                'icon_url' => $primary->badge->icon_url,
            ] : null;
        }

        return $result;
    }

    public function getBatchUserBadges(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userBadges = UserBadge::with('badge')
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $result = [];
        foreach ($userIds as $userId) {
            $badges = $userBadges->get($userId, collect())->sortByDesc(fn($ub) => $ub->badge->priority ?? 0);
            $badgesArray = $badges->map(function ($ub) {
                if (!$ub->badge) return null;
                return ['type' => $ub->badge->code, 'icon_url' => $ub->badge->icon_url];
            })->filter()->values()->toArray();

            $featuredBadges = $badges->filter(fn($ub) => $ub->is_featured)->map(function ($ub) {
                if (!$ub->badge) return null;
                return ['type' => $ub->badge->code, 'icon_url' => $ub->badge->icon_url];
            })->filter()->values()->toArray();

            $result[$userId] = [
                'badges' => $badgesArray,
                'featured_badges' => $featuredBadges,
                'primary_badge' => $badgesArray[0] ?? null,
            ];
        }

        return $result;
    }

    public function has_any_badge(int $userId): bool
    {
        return UserBadge::where('user_id', $userId)->exists();
    }

    public function has_badge(int $userId, string $code): bool
    {
        return UserBadge::where('user_id', $userId)
            ->whereHas('badge', fn($q) => $q->where('code', $code))
            ->exists();
    }

    public function hasBadge(int $userId, string $code): bool
    {
        return $this->has_badge($userId, $code);
    }

    private function _create_badge(int $userId, string $code, ?int $createdBy = null): ?UserBadge
    {
        $badge = Badge::where('code', $code)->first();
        if (!$badge) {
            return null;
        }

        $existingBadge = UserBadge::where('user_id', $userId)
            ->where('badge_id', $badge->id)
            ->first();

        if ($existingBadge) {
            return null;
        }

        $userBadge = UserBadge::create([
            'user_id' => $userId,
            'badge_id' => $badge->id,
            'created_by' => $createdBy,
            'acquired_at' => now(),
        ]);

        if ($code === 'ANCHOR') {
            User::where('id', $userId)->update(['is_anchor' => true]);
        }

        $user = User::find($userId);
        if ($user) {
            $user->notify(new BadgeGrantedNotification($code, $createdBy));
        }

        return $userBadge;
    }

    public function grant_verified(int $userId, ?int $createdBy = null): void
    {
        $this->_create_badge($userId, 'VERIFIED', $createdBy);
    }

    public function grant_anchor(int $userId, ?int $createdBy = null): void
    {
        $this->_create_badge($userId, 'ANCHOR', $createdBy);
    }

    public function grant_champion(int $userId, ?int $createdBy = null): void
    {
        DB::transaction(function () use ($userId, $createdBy) {
            $this->_create_badge($userId, 'CHAMPION', $createdBy);
        });
    }

    public function grant_picki(int $userId, ?int $createdBy = null): void
    {
        $this->_create_badge($userId, 'PICKI', $createdBy);
    }

    public function awardBadge(int $userId, string $code, ?int $createdBy = null): ?UserBadge
    {
        return $this->_create_badge($userId, $code, $createdBy);
    }

    public function revokeBadge(int $userId, string $code): bool
    {
        $badge = Badge::where('code', $code)->first();
        if (!$badge) {
            return false;
        }

        $user = User::find($userId);

        $deleted = UserBadge::where('user_id', $userId)
            ->where('badge_id', $badge->id)
            ->delete() > 0;

        if ($deleted) {
            if ($code === 'ANCHOR') {
                User::where('id', $userId)->update(['is_anchor' => false]);
            }
            if ($user) {
                $user->notify(new BadgeRevokedNotification($code));
            }
        }

        return $deleted;
    }

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
