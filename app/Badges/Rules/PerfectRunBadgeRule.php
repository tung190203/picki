<?php

namespace App\Badges\Rules;

use App\Events\TournamentCompleted;
use App\Models\Team;
use App\Models\User;
use App\Services\BadgeService;
use App\Services\TournamentType\TournamentRankService;

class PerfectRunBadgeRule implements BadgeRuleInterface
{
    public function badgeCode(): string
    {
        return 'PERFECT_RUN';
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
        $rankings = $rankService->compute((int) $tournamentType->id);
        $overallRankings = $rankings['overall_rankings'] ?? [];

        $championTeamInfo = null;
        foreach ($overallRankings as $teamInfo) {
            if (!empty($teamInfo['_is_champion'])) {
                $championTeamInfo = $teamInfo;
                break;
            }
        }

        if (!$championTeamInfo || ($championTeamInfo['losses'] ?? 0) > 0) {
            return;
        }

        $winnerTeam = Team::with('members')->find($championTeamInfo['team_id']);
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