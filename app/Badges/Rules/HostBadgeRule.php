<?php

namespace App\Badges\Rules;

use App\Events\SuperAdmin\MiniTournamentCreated;
use App\Models\Badge;
use App\Models\MiniTournament;
use App\Models\UserBadge;
use App\Services\BadgeService;

class HostBadgeRule implements BadgeRuleInterface
{
    protected array $tiers = [
        1 => 'HOST_1',
        10 => 'HOST_10',
        50 => 'HOST_50',
        200 => 'HOST_200',
    ];

    public function badgeCode(): string
    {
        return 'HOST_TIERED';
    }

    public function condition($event): bool
    {
        return $event instanceof MiniTournamentCreated;
    }

    public function handle($event): void
    {
        /** @var MiniTournamentCreated $event */
        $miniTournament = $event->miniTournament;
        $userId = (int) $miniTournament->created_by;

        if (!$userId) {
            return;
        }

        $createdCount = MiniTournament::where('created_by', $userId)
            ->where('status', '!=', MiniTournament::STATUS_CANCELLED)
            ->count();

        $badgeService = app(BadgeService::class);

        foreach ($this->tiers as $threshold => $code) {
            if ($createdCount >= $threshold) {
                $badge = Badge::where('code', $code)->first();
                if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                    $badgeService->awardBadge($userId, $code);
                }
            }
        }
    }
}
