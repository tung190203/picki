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
 * Clears the /me endpoint cache when any match result is confirmed.
 * Also increments total_matches on user_sport when winner_id is set.
 * Only counts matches that have confirmed results (winner_id/team_win_id/participant_win_id is set).
 */
class MatchCacheObserver
{
    public function __construct(
        protected UserSportMatchCounter $matchCounter
    ) {}

    public function updated($match): void
    {
        // Trigger khi winner_id được set (kết quả được xác nhận)
        if ($this->winnerIdSet($match)) {
            Log::info('[MatchCacheObserver] Result confirmed (winner_id set)', [
                'class' => get_class($match),
                'id' => $match->id,
                'winner_id' => $match->winner_id ?? $match->team_win_id ?? $match->participant_win_id,
            ]);
            $this->handleResultConfirmed($match);
        // Trigger khi winner_id bị xóa (kết quả bị hủy xác nhận)
        } elseif ($this->winnerIdRemoved($match)) {
            Log::info('[MatchCacheObserver] Result un-confirmed (winner_id removed)', [
                'class' => get_class($match),
                'id' => $match->id,
            ]);
            $this->handleResultRemoved($match);
        }

        // Auto-complete tournament khi tất cả trận có kết quả
        if ($this->winnerIdSet($match)) {
            $this->checkAndCompleteTournament($match);
        }

        // Revert tournament nếu kết quả bị hủy
        if ($this->winnerIdRemoved($match)) {
            $this->revertTournamentCompletion($match);
        }
    }

    /**
     * Kiểm tra winner_id được set (từ null -> not null)
     */
    protected function winnerIdSet($model): bool
    {
        if ($model instanceof MiniMatch) {
            $original = $model->getOriginal('team_win_id');
            $current = $model->getAttribute('team_win_id');
            if ($original === null && $current !== null) return true;
            $origParticipant = $model->getOriginal('participant_win_id');
            $currParticipant = $model->getAttribute('participant_win_id');
            return $origParticipant === null && $currParticipant !== null;
        }
        if ($model instanceof Matches) {
            $original = $model->getOriginal('winner_id');
            $current = $model->getAttribute('winner_id');
            return $original === null && $current !== null;
        }
        if ($model instanceof QuickMatch) {
            $original = $model->getOriginal('winner');
            $current = $model->getAttribute('winner');
            return $original === null && $current !== null;
        }
        return false;
    }

    /**
     * Kiểm tra winner_id bị xóa (từ not null -> null)
     */
    protected function winnerIdRemoved($model): bool
    {
        if ($model instanceof MiniMatch) {
            $original = $model->getOriginal('team_win_id');
            $current = $model->getAttribute('team_win_id');
            if ($original !== null && $current === null) return true;
            $origParticipant = $model->getOriginal('participant_win_id');
            $currParticipant = $model->getAttribute('participant_win_id');
            return $origParticipant !== null && $currParticipant === null;
        }
        if ($model instanceof Matches) {
            $original = $model->getOriginal('winner_id');
            $current = $model->getAttribute('winner_id');
            return $original !== null && $current === null;
        }
        if ($model instanceof QuickMatch) {
            $original = $model->getOriginal('winner');
            $current = $model->getAttribute('winner');
            return $original !== null && $current === null;
        }
        return false;
    }

    protected function handleResultConfirmed($match): void
    {
        $userIds = $this->extractUserIds($match);
        $sportId = $this->getSportId($match);

        Log::info('[MatchCacheObserver] handleResultConfirmed', [
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
    }

    protected function handleResultRemoved($match): void
    {
        $userIds = $this->extractUserIds($match);
        $sportId = $this->getSportId($match);

        foreach (array_unique($userIds) as $userId) {
            Cache::forget("user:{$userId}:me_extras");
        }

        if ($sportId) {
            $this->decrementCounter($match, $sportId);
        }
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

        $allHasResult = $allMatches->every(fn($m) => $m->winner_id !== null);
        if ($allHasResult) {
            Log::info('[MatchCacheObserver] All matches have results, auto-completing tournament', [
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

        $allHasResult = $allMatches->every(fn($m) => $m->winner_id !== null || $m->team_win_id !== null || $m->participant_win_id !== null);
        if ($allHasResult) {
            Log::info('[MatchCacheObserver] All mini matches have results, auto-completing mini tournament', [
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
            ->whereNull('winner_id')
            ->exists();

        if ($hasIncomplete) {
            Log::info('[MatchCacheObserver] Result removed, reverting tournament status', [
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
            ->where(function ($q) {
                $q->whereNull('team_win_id')
                  ->whereNull('participant_win_id');
            })
            ->exists();

        if ($hasIncomplete) {
            Log::info('[MatchCacheObserver] Result removed, reverting mini tournament status', [
                'mini_tournament_id' => $miniTournament->id,
                'match_id' => $match->id,
            ]);
            $miniTournament->update(['status' => MiniTournament::STATUS_OPEN]);
        }
    }

    public function deleted($match): void
    {
        if (! $this->hadWinner($match)) {
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

    /**
     * Kiểm tra match có winner trước khi bị xóa
     */
    protected function hadWinner($model): bool
    {
        if ($model instanceof MiniMatch) {
            return $model->getOriginal('team_win_id') !== null || $model->getOriginal('participant_win_id') !== null;
        }
        if ($model instanceof QuickMatch) {
            return $model->getOriginal('winner') !== null;
        }
        if ($model instanceof Matches) {
            return $model->getOriginal('winner_id') !== null;
        }
        return false;
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
