<?php

namespace App\Repositories;

use App\Models\MiniMatch;
use App\Models\MiniTeamMember;
use Illuminate\Support\Collection;

class MatchHistoryRepository
{
    /**
     * Get signatures of all existing matches (pending, going_on, completed).
     * Used to prevent suggesting duplicate combinations.
     *
     * @return array<int, array<int>> Array of signatures (sorted user_id arrays)
     */
    public function getExistingMatchSignatures(int $miniTournamentId): array
    {
        $matches = MiniMatch::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('status', [
                MiniMatch::STATUS_PENDING,
                MiniMatch::STATUS_GOING_ON,
                MiniMatch::STATUS_WAITING_CONFIRM,
                MiniMatch::STATUS_COMPLETED,
            ])
            ->with(['team1.members', 'team2.members', 'participant1', 'participant2'])
            ->get();

        $signatures = [];

        foreach ($matches as $match) {
            $playerIds = $this->getMatchPlayerIds($match);

            if (count($playerIds) !== 4) {
                continue;
            }

            sort($playerIds);
            $signatures[] = $playerIds;
        }

        return $signatures;
    }

    public function getSessionMatches(int $miniTournamentId): Collection
    {
        return MiniMatch::where('mini_tournament_id', $miniTournamentId)
            ->with(['team1.members', 'team2.members', 'participant1', 'participant2'])
            ->get();
    }

    public function getPlayedCounts(int $miniTournamentId): array
    {
        $matches = $this->getSessionMatches($miniTournamentId);
        $counts = [];

        foreach ($matches as $match) {
            $playerIds = $this->getMatchPlayerIds($match, $miniTournamentId);
            foreach ($playerIds as $miniParticipantId) {
                $counts[$miniParticipantId] = ($counts[$miniParticipantId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Get consecutive match count for each player.
     * A player has consecutive_count = N if they played in N consecutive rounds.
     * Players who skip a round reset their consecutive count.
     *
     * Uses round_number for proper ordering (not collection index).
     * Keyed by mini_participant_id.
     */
    public function getConsecutiveCounts(int $miniTournamentId): array
    {
        $matches = $this->getSessionMatches($miniTournamentId);

        // Sort by round_number to ensure proper ordering
        $sortedMatches = $matches->sortBy(fn($m) => $m->round_number ?? 0);

        $counts = [];
        $lastRound = [];

        foreach ($sortedMatches as $match) {
            $roundNumber = (int) ($match->round_number ?? 0);
            if ($roundNumber <= 0) {
                continue;
            }

            $playerIds = $this->getMatchPlayerIds($match, $miniTournamentId);

            foreach ($playerIds as $miniParticipantId) {
                if (isset($lastRound[$miniParticipantId])) {
                    if ($roundNumber - $lastRound[$miniParticipantId] === 1) {
                        $counts[$miniParticipantId] = ($counts[$miniParticipantId] ?? 0) + 1;
                    } else {
                        $counts[$miniParticipantId] = 1;
                    }
                } else {
                    $counts[$miniParticipantId] = 1;
                }
                $lastRound[$miniParticipantId] = $roundNumber;
            }
        }

        return $counts;
    }

    /**
     * Get waiting rounds for each player.
     * A "waiting round" is a round where the player was eligible but didn't play.
     * Keyed by mini_participant_id.
     */
    public function getWaitingRounds(int $miniTournamentId): array
    {
        $matches = $this->getSessionMatches($miniTournamentId);

        if ($matches->isEmpty()) {
            return [];
        }

        $totalRounds = $matches->max('round_number') ?? 0;

        if ($totalRounds <= 1) {
            return [];
        }

        $playedRounds = [];
        foreach ($matches as $match) {
            $playerIds = $this->getMatchPlayerIds($match, $miniTournamentId);
            $roundNumber = $match->round_number ?? 0;

            if ($roundNumber <= 0) {
                continue;
            }

            foreach ($playerIds as $miniParticipantId) {
                if (!isset($playedRounds[$miniParticipantId])) {
                    $playedRounds[$miniParticipantId] = [];
                }
                if (!in_array($roundNumber, $playedRounds[$miniParticipantId])) {
                    $playedRounds[$miniParticipantId][] = $roundNumber;
                }
            }
        }

        $waitingRounds = [];
        $allRounds = range(1, $totalRounds);

        foreach ($playedRounds as $miniParticipantId => $rounds) {
            $missed = array_diff($allRounds, $rounds);
            $waitingRounds[$miniParticipantId] = count($missed);
        }

        return $waitingRounds;
    }

    /**
     * Last round number each player participated in.
     * Keyed by mini_participant_id.
     *
     * @return array<int, int> mini_participant_id => max(round_number)
     */
    public function getLastPlayedRounds(int $miniTournamentId): array
    {
        $matches = $this->getSessionMatches($miniTournamentId);
        $lastPlayed = [];

        foreach ($matches as $match) {
            $roundNumber = (int) ($match->round_number ?? 0);
            if ($roundNumber <= 0) {
                continue;
            }

            foreach ($this->getMatchPlayerIds($match, $miniTournamentId) as $miniParticipantId) {
                if (!isset($lastPlayed[$miniParticipantId]) || $roundNumber > $lastPlayed[$miniParticipantId]) {
                    $lastPlayed[$miniParticipantId] = $roundNumber;
                }
            }
        }

        return $lastPlayed;
    }

    /**
     * Round number being scheduled next (max existing round + 1).
     */
    public function getCurrentRoundNumber(int $miniTournamentId): int
    {
        $matches = $this->getSessionMatches($miniTournamentId);

        if ($matches->isEmpty()) {
            return 1;
        }

        $maxRound = (int) ($matches->max('round_number') ?? 0);

        return max(1, $maxRound + 1);
    }

    public function getPartnerHistory(int $miniTournamentId): array
    {
        $matches = $this->getSessionMatches($miniTournamentId);
        $partnerMap = [];

        foreach ($matches as $match) {
            $playerIds = $this->getMatchPlayerIds($match, $miniTournamentId);

            foreach ($playerIds as $id) {
                $others = array_values(array_filter($playerIds, fn($pid) => $pid !== $id));
                $partnerMap[$id] = array_values(array_unique(array_merge($partnerMap[$id] ?? [], $others)));
            }
        }

        return $partnerMap;
    }

    public function getPlayingParticipants(int $miniTournamentId): array
    {
        return MiniMatch::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('status', [
                MiniMatch::STATUS_PENDING,
                MiniMatch::STATUS_GOING_ON,
                MiniMatch::STATUS_WAITING_CONFIRM,
            ])
            ->with(['team1.members', 'team2.members', 'participant1', 'participant2'])
            ->get()
            ->flatMap(function ($match) use ($miniTournamentId) {
                return $this->getMatchPlayerIds($match, $miniTournamentId);
            })
            ->unique()
            ->values()
            ->toArray();
    }

    public function getLastMatch(int $miniParticipantId): ?MiniMatch
    {
        return MiniMatch::where(function ($query) use ($miniParticipantId) {
                $query->where('participant1_id', $miniParticipantId)
                    ->orWhere('participant2_id', $miniParticipantId);
            })
            ->where('status', MiniMatch::STATUS_COMPLETED)
            ->orderBy('updated_at', 'desc')
            ->first();
    }

    public function getRecentMatches(int $miniTournamentId, int $limit = 5): Collection
    {
        return MiniMatch::where('mini_tournament_id', $miniTournamentId)
            ->with(['team1.members.user', 'team2.members.user'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getMatchPlayerIds(MiniMatch $match, ?int $miniTournamentId = null): array
    {
        $ids = [];

        if ($match->team1) {
            $memberIds = $match->team1->members->pluck('user_id')->toArray();
            $ids = array_merge($ids, $this->resolveMiniParticipantIds($memberIds, $miniTournamentId));
        }
        if ($match->team2) {
            $memberIds = $match->team2->members->pluck('user_id')->toArray();
            $ids = array_merge($ids, $this->resolveMiniParticipantIds($memberIds, $miniTournamentId));
        }
        if ($match->participant1) {
            $ids[] = $match->participant1->id;
        }
        if ($match->participant2) {
            $ids[] = $match->participant2->id;
        }

        return array_values(array_filter($ids, fn($v) => $v !== null));
    }

    /**
     * Resolve user_ids → mini_participant_ids for the current tournament.
     * Required because MiniTeamMember only stores user_id, not mini_participant_id.
     */
    private function resolveMiniParticipantIds(array $userIds, ?int $miniTournamentId = null): array
    {
        if (empty($userIds)) {
            return [];
        }

        // Filter out null (guests whose user_id is null) - they are matched by participant1/participant2 directly
        $userIds = array_values(array_filter($userIds, fn($id) => $id !== null));
        if (empty($userIds)) {
            return [];
        }

        $query = \App\Models\MiniParticipant::whereIn('user_id', $userIds);
        if ($miniTournamentId !== null) {
            $query->where('mini_tournament_id', $miniTournamentId);
        }
        return $query->pluck('id')->toArray();
    }
}
