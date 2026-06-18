<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public const AUTO_VERIFY_THRESHOLD = 10;

    /**
     * Handle the User "updated" event.
     * Auto-verify user when total_matches_has_anchor crosses the threshold (>= 10).
     * Only increments when the user played with an anchor/verified partner,
     * so this is the correct field to watch for verification.
     */
    public function updated(User $user): void
    {
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
            $user->updateQuietly(['is_verified' => true]);
        }
    }
}
