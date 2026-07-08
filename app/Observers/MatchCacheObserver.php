<?php

namespace App\Observers;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\QuickMatch;
use App\Services\UserSportMatchCounter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        if ($this->statusChangedToCompleted($match)) {
            Log::info('[MatchCacheObserver] Match completed', [
                'class' => get_class($match),
                'id' => $match->id,
                'status' => $match->status,
            ]);
            $this->handleCompleted($match);
        } elseif ($this->statusRevertedFromCompleted($match)) {
            Log::info('[MatchCacheObserver] Match reverted from completed', [
                'class' => get_class($match),
                'id' => $match->id,
            ]);
            $this->handleReverted($match);
        }
    }

    protected function handleCompleted($match): void
    {
        $userIds = $this->extractUserIds($match);
        $sportId = $this->getSportId($match);

        Log::info('[MatchCacheObserver] handleCompleted', [
            'match_id' => $match->id,
            'sport_id' => $sportId,
            'user_ids' => $userIds,
            'user_count' => count($userIds),
        ]);

        foreach (array_unique($userIds) as $userId) {
            Cache::forget("user:{$userId}:me_extras");
        }

        if ($sportId) {
            $this->incrementCounter($match, $sportId);
        } else {
            Log::warning('[MatchCacheObserver] sportId is null, skipping increment', [
                'match_id' => $match->id,
                'class' => get_class($match),
            ]);
        }

        // Auto-complete tournament when all matches are completed
        $this->checkAndCompleteTournament($match);
    }

    protected function handleReverted($match): void
    {
        $userIds = $this->extractUserIds($match);
        $sportId = $this->getSportId($match);

        foreach (array_unique($userIds) as $userId) {
            Cache::forget("user:{$userId}:me_extras");
        }

        if ($sportId) {
            $this->decrementCounter($match, $sportId);
        }

        // Revert tournament completion status
        $this->revertTournamentCompletion($match);
    }

    protected function checkAndCompleteTournament($match): void
    {
        if ($match instanceof Matches) {
            $this->completeTournamentIfAllMatchesDone($match);
        } elseif ($match instanceof MiniMatch) {
            $this->completeMiniTournamentIfAllMatchesDone($match);
        }
    }

    protected function completeTournamentIfAllMatchesDone(Matches $match): void
    {
        $tournament = $match->group?->tournamentType?->tournament;
        if (!$tournament) {
            return;
        }

        if ($tournament->status === \App\Models\Tournament::CLOSED) {
            return;
        }

        $tournamentType = $tournament->tournamentTypes->first();
        if (!$tournamentType) {
            return;
        }

        $allMatches = $tournamentType->matches()->get();
        if ($allMatches->isEmpty()) {
            return;
        }

        $allCompleted = $allMatches->every(fn($m) => $m->status === 'completed');
        if ($allCompleted) {
            Log::info('[MatchCacheObserver] All matches completed, auto-completing tournament', [
                'tournament_id' => $tournament->id,
                'match_id' => $match->id,
            ]);
            $tournament->update(['status' => \App\Models\Tournament::CLOSED]);
        }
    }

    protected function completeMiniTournamentIfAllMatchesDone(MiniMatch $match): void
    {
        $miniTournament = $match->miniTournament;
        if (!$miniTournament) {
            return;
        }

        if ($miniTournament->status === MiniTournament::STATUS_CLOSED) {
            return;
        }

        $allMatches = $miniTournament->matches()->get();
        if ($allMatches->isEmpty()) {
            return;
        }

        $allCompleted = $allMatches->every(fn($m) => $m->status === 'completed');
        if ($allCompleted) {
            Log::info('[MatchCacheObserver] All mini matches completed, auto-completing mini tournament', [
                'mini_tournament_id' => $miniTournament->id,
                'match_id' => $match->id,
            ]);
            $miniTournament->update(['status' => MiniTournament::STATUS_CLOSED]);
        }
    }

    protected function revertTournamentCompletion($match): void
    {
        if ($match instanceof Matches) {
            $this->revertTournamentStatus($match);
        } elseif ($match instanceof MiniMatch) {
            $this->revertMiniTournamentStatus($match);
        }
    }

    protected function revertTournamentStatus(Matches $match): void
    {
        $tournament = $match->group?->tournamentType?->tournament;
        if (!$tournament) {
            return;
        }

        if ($tournament->status !== \App\Models\Tournament::CLOSED) {
            return;
        }

        $tournamentType = $tournament->tournamentTypes->first();
        if (!$tournamentType) {
            return;
        }

        $hasIncomplete = $tournamentType->matches()
            ->where('status', '!=', 'completed')
            ->exists();

        if ($hasIncomplete) {
            Log::info('[MatchCacheObserver] Match reverted, reverting tournament status', [
                'tournament_id' => $tournament->id,
                'match_id' => $match->id,
            ]);
            $tournament->update(['status' => \App\Models\Tournament::OPEN]);
        }
    }

    protected function revertMiniTournamentStatus(MiniMatch $match): void
    {
        $miniTournament = $match->miniTournament;
        if (!$miniTournament) {
            return;
        }

        if ($miniTournament->status !== MiniTournament::STATUS_CLOSED) {
            return;
        }

        $hasIncomplete = $miniTournament->matches()
            ->where('status', '!=', 'completed')
            ->exists();

        if ($hasIncomplete) {
            Log::info('[MatchCacheObserver] Mini match reverted, reverting mini tournament status', [
                'mini_tournament_id' => $miniTournament->id,
                'match_id' => $match->id,
            ]);
            $miniTournament->update(['status' => MiniTournament::STATUS_OPEN]);
        }
    }

    public function deleted($match): void
    {
        if (! $this->wasCompleted($match)) {
            return;
        }

        $userIds = $this->extractUserIds($match);
        $sportId = $this->getSportId($match);

        foreach (array_unique($userIds) as $userId) {
            Cache::forget("user:{$userId}:me_extras");
        }

        if ($sportId) {
            $this->decrementCounter($match, $sportId);
        }
    }

    protected function wasCompleted($model): bool
    {
        if ($model instanceof MiniMatch) {
            return $model->getOriginal('status') === MiniMatch::STATUS_COMPLETED;
        }
        if ($model instanceof QuickMatch) {
            return $model->getOriginal('status') === QuickMatch::STATUS_COMPLETED;
        }
        if ($model instanceof Matches) {
            return $model->getOriginal('status') === Matches::STATUS_COMPLETED;
        }
        return $model->getOriginal('status') === 'completed';
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

    protected function statusRevertedFromCompleted($model): bool
    {
        if (! $model->isDirty('status')) {
            return false;
        }

        $original = $model->getOriginal('status');
        $current = $model->getAttribute('status');

        if ($model instanceof MiniMatch) {
            return $original === MiniMatch::STATUS_COMPLETED && $current !== MiniMatch::STATUS_COMPLETED;
        }
        if ($model instanceof QuickMatch) {
            return $original === QuickMatch::STATUS_COMPLETED && $current !== QuickMatch::STATUS_COMPLETED;
        }
        if ($model instanceof Matches) {
            return $original === Matches::STATUS_COMPLETED && $current !== Matches::STATUS_COMPLETED;
        }

        return $original === 'completed' && $current !== 'completed';
    }

    protected function getSportId($match): ?int
    {
        if ($match instanceof MiniMatch) {
            // miniTournament() is the relationship method — NOT mini_tournament (snake_case).
            $sportId = $match->miniTournament?->sport_id;
            Log::debug('[MatchCacheObserver] getSportId MiniMatch', [
                'match_id' => $match->id,
                'sport_id' => $sportId,
                'miniTournament_loaded' => $match->relationLoaded('miniTournament'),
                'mini_tournament_id' => $match->mini_tournament_id,
            ]);
            return $sportId;
        }
        if ($match instanceof QuickMatch) {
            return $match->sport_id;
        }
        if ($match instanceof Matches) {
            $groupLoaded = $match->relationLoaded('group');
            $tournamentTypeLoaded = $groupLoaded && $match->group?->relationLoaded('tournamentType');
            $tournamentLoaded = $tournamentTypeLoaded && $match->group?->tournamentType?->relationLoaded('tournament');
            $sportId = $match->group?->tournamentType?->tournament?->sport_id;
            Log::debug('[MatchCacheObserver] getSportId Matches', [
                'match_id' => $match->id,
                'sport_id' => $sportId,
                'group_loaded' => $groupLoaded,
                'tournament_type_loaded' => $tournamentTypeLoaded,
                'tournament_loaded' => $tournamentLoaded,
                'group_id' => $match->group_id,
                'group' => $match->group ? $match->group->id : 'null',
            ]);
            return $sportId;
        }
        return null;
    }

    protected function incrementCounter($match, int $sportId): void
    {
        if ($match instanceof MiniMatch) {
            Log::info('[MatchCacheObserver] increment MiniMatch', [
                'match_id' => $match->id,
                'team1_id' => $match->team1_id,
                'team2_id' => $match->team2_id,
                'participant1_id' => $match->participant1_id,
                'participant2_id' => $match->participant2_id,
            ]);
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
            Log::info('[MatchCacheObserver] increment Matches', [
                'match_id' => $match->id,
                'home_team_id' => $match->home_team_id,
                'away_team_id' => $match->away_team_id,
            ]);
            if ($match->home_team_id) {
                $this->matchCounter->incrementForTeam($match->home_team_id, $sportId);
            }
            if ($match->away_team_id) {
                $this->matchCounter->incrementForTeam($match->away_team_id, $sportId);
            }
        }
    }

    protected function decrementCounter($match, int $sportId): void
    {
        if ($match instanceof MiniMatch) {
            if ($match->team1_id) {
                $this->matchCounter->decrementForMiniTeam($match->team1_id, $sportId);
            }
            if ($match->team2_id) {
                $this->matchCounter->decrementForMiniTeam($match->team2_id, $sportId);
            }
            if ($match->participant1_id) {
                $userId = $match->participant1?->user_id;
                if ($userId) {
                    $this->matchCounter->decrementForQuickMatchUser($userId, $sportId);
                }
            }
            if ($match->participant2_id) {
                $userId = $match->participant2?->user_id;
                if ($userId) {
                    $this->matchCounter->decrementForQuickMatchUser($userId, $sportId);
                }
            }
            return;
        }

        if ($match instanceof QuickMatch) {
            foreach ($match->team_a ?? [] as $userId) {
                $this->matchCounter->decrementForQuickMatchUser($userId, $sportId);
            }
            foreach ($match->team_b ?? [] as $userId) {
                $this->matchCounter->decrementForQuickMatchUser($userId, $sportId);
            }
            return;
        }

        if ($match instanceof Matches) {
            if ($match->home_team_id) {
                $this->matchCounter->decrementForTeam($match->home_team_id, $sportId);
            }
            if ($match->away_team_id) {
                $this->matchCounter->decrementForTeam($match->away_team_id, $sportId);
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
