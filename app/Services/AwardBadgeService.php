<?php

namespace App\Services;

use App\Enums\BadgeType;
use App\Models\UserBadge;

class AwardBadgeService
{
    public function awardAnchorBadge(int $userId): ?UserBadge
    {
        return app(BadgeService::class)->awardBadge($userId, BadgeType::ANCHOR);
    }

    public function hasAnchorBadge(int $userId): bool
    {
        return app(BadgeService::class)->hasBadge($userId, BadgeType::ANCHOR);
    }
}
