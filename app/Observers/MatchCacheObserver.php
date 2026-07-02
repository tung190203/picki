<?php

namespace App\Observers;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Services\UserSportMatchCounter;
use Illuminate\Support\Facades\Cache;

/**
 * Clears the /me endpoint cache when any match is completed.
 * Also increments total_matches on user_sport for all participating users.
 */
class MatchCacheObserver
{
    public function __construct(
        protected UserSportMatchCounter $matchCounter
    ) {}

    public function updated($match): void
    {
        if (! $this->statusChangedToCompleted($match)) {
            return;
        }

        $userIds = $this->extractUserIds($match);
        $sportId = $this->getSportId($match);

        // Clear cache
        foreach (array_unique($userIds) as $userId) {
            Cache::forget("user:{$userId}:me_extras");
        }

        // Increment total_matches counter
        if ($sportId) {
            $this->incrementCounter($match, $sportId);
        }
    }

    protected function statusChangedToCompleted($model): bool
    {
        if (! $model->isDirty('status')) {
            return false;
        }

        $status = $model->getAttribute('status');

        if ($model instanceof MiniMatch) {
            return $status === MiniMatch::STATUS_COMPLETED;
        }
        if ($model instanceof QuickMatch) {
            return $status === QuickMatch::STATUS_COMPLETED;
        }
        if ($model instanceof Matches) {
            return $status === Matches::STATUS_COMPLETED;
        }

        return $status === 'completed';
    }

    protected function getSportId($match): ?int
    {
        if ($match instanceof MiniMatch) {
            return $match->mini_tournament?->sport_id;
        }
        if ($match instanceof QuickMatch) {
            // QuickMatch: sport from its sport_id
            return $match->sport_id;
        }
        if ($match instanceof Matches) {
            return $match->group?->tournamentType?->tournament?->sport_id;
        }
        return null;
    }

    protected function incrementCounter($match, int $sportId): void
    {
        if ($match instanceof MiniMatch) {
            if ($match->team1_id) {
                $this->matchCounter->incrementForMiniTeam($match->team1_id, $sportId);
            }
            if ($match->team2_id) {
                $this->matchCounter->incrementForMiniTeam($match->team2_id, $sportId);
            }
            // Individual participants
            if ($match->participant1_id) {
                $userId = $match->participant1?->user_id;
                if ($userId) {
                    $this->matchCounter->incrementForQuickMatchUser($userId, $sportId);
                }
            }
            if ($match->participant2_id) {
                $userId = $match->participant2?->user_id;
                if ($userId) {
                    $this->matchCounter->incrementForQuickMatchUser($userId, $sportId);
                }
            }
            return;
        }

        if ($match instanceof QuickMatch) {
            foreach ($match->team_a ?? [] as $userId) {
                $this->matchCounter->incrementForQuickMatchUser($userId, $sportId);
            }
            foreach ($match->team_b ?? [] as $userId) {
                $this->matchCounter->incrementForQuickMatchUser($userId, $sportId);
            }
            return;
        }

        if ($match instanceof Matches) {
            if ($match->home_team_id) {
                $this->matchCounter->incrementForTeam($match->home_team_id, $sportId);
            }
            if ($match->away_team_id) {
                $this->matchCounter->incrementForTeam($match->away_team_id, $sportId);
            }
        }
    }

    protected function extractUserIds($match): array
    {
        if ($match instanceof MiniMatch) {
            return $this->extractMiniMatchUserIds($match);
        }
        if ($match instanceof QuickMatch) {
            return $this->extractQuickMatchUserIds($match);
        }
        if ($match instanceof Matches) {
            return $this->extractTournamentMatchUserIds($match);
        }
        return [];
    }

    protected function extractMiniMatchUserIds(MiniMatch $miniMatch): array
    {
        $userIds = [];

        if ($miniMatch->team1_id && $miniMatch->team1) {
            $userIds = array_merge(
                $userIds,
                $miniMatch->team1->members->pluck('user_id')->filter()->toArray()
            );
        }
        if ($miniMatch->team2_id && $miniMatch->team2) {
            $userIds = array_merge(
                $userIds,
                $miniMatch->team2->members->pluck('user_id')->filter()->toArray()
            );
        }
        if ($miniMatch->participant1_id && $miniMatch->participant1?->user_id) {
            $userIds[] = $miniMatch->participant1->user_id;
        }
        if ($miniMatch->participant2_id && $miniMatch->participant2?->user_id) {
            $userIds[] = $miniMatch->participant2->user_id;
        }

        return $userIds;
    }

    protected function extractQuickMatchUserIds(QuickMatch $quickMatch): array
    {
        return array_merge($quickMatch->team_a ?? [], $quickMatch->team_b ?? []);
    }

    protected function extractTournamentMatchUserIds(Matches $match): array
    {
        $userIds = [];

        if ($match->home_team_id && $match->homeTeam) {
            $userIds = array_merge(
                $userIds,
                $match->homeTeam->members->pluck('user_id')->filter()->toArray()
            );
        }
        if ($match->away_team_id && $match->awayTeam) {
            $userIds = array_merge(
                $userIds,
                $match->awayTeam->members->pluck('user_id')->filter()->toArray()
            );
        }

        return $userIds;
    }
}
