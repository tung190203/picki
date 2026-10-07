<?php

namespace App\Badges\Rules;

use App\Events\UserProfileCompleted;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Services\BadgeService;

class ProfileCompletedBadgeRule implements BadgeRuleInterface
{
    public function badgeCode(): string
    {
        return 'PROFILE_COMPLETED';
    }

    public function condition($event): bool
    {
        return $event instanceof UserProfileCompleted;
    }

    public function handle($event): void
    {
        /** @var UserProfileCompleted $event */
        $user = $event->user;

        $badgeCode = $this->badgeCode();
        $badge = Badge::where('code', $badgeCode)->first();

        if ($badge && !UserBadge::where('user_id', $user->id)->where('badge_id', $badge->id)->exists()) {
            $badgeService = app(BadgeService::class);
            $badgeService->awardBadge($user->id, $badgeCode);
        }
    }
}
