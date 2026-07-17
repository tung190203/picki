<?php

namespace App\Services;

use App\Models\UserBadge;
use App\Models\Badge;
use Illuminate\Support\Facades\DB;

class AwardBadgeService
{
    public function awardAnchorBadge(int $userId): UserBadge
    {
        $badge = Badge::where('name', 'Anchor')->firstOrFail();

        return UserBadge::firstOrCreate(
            ['user_id' => $userId, 'badge_id' => $badge->id],
            ['awarded_at' => now()]
        );
    }

    public function hasAnchorBadge(int $userId): bool
    {
        return UserBadge::where('user_id', $userId)
            ->whereHas('badge', fn($q) => $q->where('name', 'Anchor'))
            ->exists();
    }
}
