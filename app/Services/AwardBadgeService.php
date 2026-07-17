<?php

namespace App\Services;

use App\Models\UserBadge;
use App\Models\Badge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AwardBadgeService
{
    public function awardAnchorBadge(int $userId): ?UserBadge
    {
        $badge = Badge::where('name', 'Anchor')->first();

        if (!$badge) {
            Log::warning('AwardBadgeService: Anchor badge not found in database. Creating it.');
            $badge = Badge::create([
                'name' => 'Anchor',
                'description' => 'Badge awarded for score verification',
                'icon_url' => null,
            ]);
        }

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
