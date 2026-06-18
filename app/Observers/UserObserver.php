<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    public const AUTO_VERIFY_THRESHOLD = 10;

    /**
     * Handle the User "updated" event.
     * Auto-verify user when total_matches crosses the threshold (>= 10).
     */
    public function updated(User $user): void
    {
        if (!$user->wasChanged('total_matches')) {
            return;
        }

        if ($user->is_verified) {
            return;
        }

        if ($user->is_guest) {
            return;
        }

        if ((int) $user->total_matches >= self::AUTO_VERIFY_THRESHOLD) {
            $user->updateQuietly(['is_verified' => true]);
        }
    }
}
