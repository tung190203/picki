<?php

namespace App\Badges\Rules;

use App\Events\TournamentCompleted;
use App\Models\Badge;
use App\Models\UserBadge;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;

class TournamentParticipationBadgeRule implements BadgeRuleInterface
{
    // Cấu hình các mốc huy hiệu tương ứng với số lượng giải
    protected array $tiers = [
        5 => 'TOUR_BRONZE',
        10 => 'TOUR_SILVER',
        15 => 'TOUR_GOLD',
        20 => 'TOUR_DIAMOND',
    ];

    public function badgeCode(): string
    {
        return 'TOUR_PARTICIPATION_TIERED'; // Không thực sự dùng vì ta duyệt array $tiers bên dưới
    }

    public function condition($event): bool
    {
        return $event instanceof TournamentCompleted;
    }

    public function handle($event): void
    {
        /** @var TournamentCompleted $event */
        $tournament = $event->tournament;

        // Lấy danh sách tất cả các user có tham gia trong giải đấu này
        $userIds = DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->join('tournament_types', 'tournament_types.id', '=', 'teams.tournament_type_id')
            ->where('tournament_types.tournament_id', $tournament->id)
            ->pluck('team_members.user_id')
            ->toArray();

        $userIds = array_unique(array_filter($userIds));
        if (empty($userIds)) {
            return;
        }

        $badgeService = app(BadgeService::class);

        foreach ($userIds as $userId) {
            // Đếm tổng số "Giải Đấu" (Tournaments) mà user này đã tham gia (chỉ tính giải đã closed)
            $totalParticipated = $this->countParticipatedTournaments((int) $userId);

            // Kiểm tra từng mốc huy hiệu
            foreach ($this->tiers as $threshold => $code) {
                if ($totalParticipated >= $threshold) {
                    $badge = Badge::where('code', $code)->first();
                    if ($badge && !UserBadge::where('user_id', $userId)->where('badge_id', $badge->id)->exists()) {
                        $badgeService->awardBadge((int) $userId, $code);
                    }
                }
            }
        }
    }

    /**
     * Đếm số giải đấu (Tournaments) đã tham gia và hoàn thành
     */
    private function countParticipatedTournaments(int $userId): int
    {
        return DB::table('tournaments')
            ->join('tournament_types', 'tournament_types.tournament_id', '=', 'tournaments.id')
            ->join('teams', 'teams.tournament_type_id', '=', 'tournament_types.id')
            ->join('team_members', 'team_members.team_id', '=', 'teams.id')
            ->where('team_members.user_id', $userId)
            ->where('tournaments.status', 'closed')
            ->distinct()
            ->count('tournaments.id');
    }
}