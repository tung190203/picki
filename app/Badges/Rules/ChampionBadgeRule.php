<?php

namespace App\Badges\Rules;

use App\Events\TournamentCompleted;
use App\Models\Team;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\TournamentType\TournamentRankService;

class ChampionBadgeRule implements BadgeRuleInterface
{
    public function badgeCode(): string
    {
        return 'CHAMPION';
    }

    public function condition($event): bool
    {
        return $event instanceof TournamentCompleted;
    }

    public function handle($event): void
    {
        /** @var TournamentCompleted $event */
        $tournament = $event->tournament;

        $tournamentType = $tournament->tournamentTypes->first();
        if (!$tournamentType) {
            return;
        }

        /** @var TournamentRankService $rankService */
        $rankService = app(TournamentRankService::class);
        $labels = $rankService->rankLabelsByTeam((int) $tournamentType->id);

        $championTeamId = null;
        foreach ($labels as $teamId => $info) {
            if (!empty($info['is_champion'])) {
                $championTeamId = (int) $teamId;
                break;
            }
        }

        if (!$championTeamId) {
            return;
        }

        $winnerTeam = Team::with('members')->find($championTeamId);
        if (!$winnerTeam) {
            return;
        }

        /** @var BadgeService $badgeService */
        $badgeService = app(BadgeService::class);
        
        $creatorExists = User::withTrashed()->find($tournament->created_by);
        $createdBy = $creatorExists ? $tournament->created_by : null;

        foreach ($winnerTeam->members as $member) {
            if (!$member->id) continue;
            
            $user = User::withTrashed()->find($member->id);
            if (!$user) continue;

            $badgeService->awardBadge((int) $member->id, $this->badgeCode(), $createdBy);
        }
    }
}
