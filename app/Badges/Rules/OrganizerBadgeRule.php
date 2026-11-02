<?php

namespace App\Badges\Rules;

use App\Events\SuperAdmin\TournamentCreated;
use App\Events\TournamentCompleted;
use App\Models\Badge;
use App\Models\Tournament;
use App\Models\UserBadge;
use App\Services\BadgeService;

class OrganizerBadgeRule implements BadgeRuleInterface
{
    protected array $tiers = [
        1 => 'ORGANIZER_1',
        5 => 'ORGANIZER_5',
        20 => 'ORGANIZER_20',
        50 => 'ORGANIZER_50',
    ];

    public function badgeCode(): string
    {
        return 'ORGANIZER_TIERED';
    }

    public function condition($event): bool
    {
        return $event instanceof TournamentCreated || $event instanceof TournamentCompleted;
    }

    public function handle($event): void
    {
        /** @var TournamentCreated|TournamentCompleted $event */
        $tournament = $event->tournament;
        $userId = (int) $tournament->created_by;

        if (!$userId) {
            return;
        }

        // Đếm tất cả giải đấu người dùng này đã tạo mà không bị huỷ
        $organizedCount = Tournament::where('created_by', $userId)
            ->where('status', '!=', Tournament::CANCELLED)
            ->count();

        $badgeService = app(BadgeService::class);

        foreach ($this->tiers as $threshold => $code) {
            if ($organizedCount >= $threshold) {
                $badge = Badge::where('code', $code)->first();
                if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                    $badgeService->awardBadge($userId, $code);
                }
            }
        }
    }
}
