<?php

namespace App\Observers;

use App\Models\User;
use App\Services\BadgeService;
use App\Enums\BadgeType;

class UserObserver
{
    public const AUTO_VERIFY_THRESHOLD = 10;

    /**
     * Handle the User "updated" event.
     * Auto-verify user when total_matches_has_anchor crosses the threshold (>= 10).
     * Awards VERIFIED badge via BadgeService instead of updating is_verified field directly.
     */
    public function updated(User $user): void
    {
        // Clear role cache when role or is_super_admin changes
        if ($user->wasChanged('role') || $user->wasChanged('is_super_admin')) {
            User::clearRoleCache($user->id);
        }

        // Auto-verify logic using BadgeService
        if (!$user->wasChanged('total_matches_has_anchor')) {
            return;
        }

        if ($user->is_verified) {
            return;
        }

        if ($user->is_guest) {
            return;
        }

        if ((int) $user->total_matches_has_anchor >= self::AUTO_VERIFY_THRESHOLD) {
            app(BadgeService::class)->awardBadge($user->id, BadgeType::VERIFIED);
        }
    }
}
