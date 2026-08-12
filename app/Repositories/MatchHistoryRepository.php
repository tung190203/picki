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
            if ($match->team1) {
                foreach ($match->team1->members as $member) {
                    $counts[$member->user_id] = ($counts[$member->user_id] ?? 0) + 1;
                }
            }
            if ($match->team2) {
                foreach ($match->team2->members as $member) {
                    $counts[$member->user_id] = ($counts[$member->user_id] ?? 0) + 1;
                }
            }
            if ($match->participant1) {
                $counts[$match->participant1->user_id] = ($counts[$match->participant1->user_id] ?? 0) + 1;
            }
            if ($match->participant2) {
                $counts[$match->participant2->user_id] = ($counts[$match->participant2->user_id] ?? 0) + 1;
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

            $playerIds = $this->getMatchPlayerIds($match);

            foreach ($playerIds as $userId) {
                if (isset($lastRound[$userId])) {
                    // Check if this match is immediately after the last match (consecutive round)
                    if ($roundNumber - $lastRound[$userId] === 1) {
                        $counts[$userId] = ($counts[$userId] ?? 0) + 1;
                    } else {
                        // Gap in rounds - reset consecutive count
                        $counts[$userId] = 1;
                    }
                } else {
                    // First match for this player
                    $counts[$userId] = 1;
                }
                $lastRound[$userId] = $roundNumber;
            }
        }

        return $counts;
    }

    /**
     * Get waiting rounds for each player.
     * A "waiting round" is a round where the player was eligible but didn't play.
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

        // Track which rounds each player played
        $playedRounds = [];
        foreach ($matches as $match) {
            $playerIds = $this->getMatchPlayerIds($match);
            $roundNumber = $match->round_number ?? 0;
            
            if ($roundNumber <= 0) {
                continue;
            }

            foreach ($playerIds as $userId) {
                if (!isset($playedRounds[$userId])) {
                    $playedRounds[$userId] = [];
                }
                if (!in_array($roundNumber, $playedRounds[$userId])) {
                    $playedRounds[$userId][] = $roundNumber;
                }
            }
        }

        // Calculate waiting rounds for each player
        $waitingRounds = [];
        $allRounds = range(1, $totalRounds);
        
        foreach ($playedRounds as $userId => $rounds) {
            $missed = array_diff($allRounds, $rounds);
            $waitingRounds[$userId] = count($missed);
        }

        return $waitingRounds;
    }

    /**
     * Last round number each player participated in.
     *
     * @return array<int, int> user_id => max(round_number)
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

            foreach ($this->getMatchPlayerIds($match) as $userId) {
                if (!isset($lastPlayed[$userId]) || $roundNumber > $lastPlayed[$userId]) {
                    $lastPlayed[$userId] = $roundNumber;
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
            $teamAPartners = [];
            $teamBPartners = [];

            if ($match->team1) {
                $memberIds = $match->team1->members->pluck('user_id')->toArray();
                foreach ($memberIds as $id) {
                    $teamAPartners = array_merge($teamAPartners, array_filter($memberIds, fn($pid) => $pid !== $id));
                }
            }
            if ($match->team2) {
                $memberIds = $match->team2->members->pluck('user_id')->toArray();
                foreach ($memberIds as $id) {
                    $teamBPartners = array_merge($teamBPartners, array_filter($memberIds, fn($pid) => $pid !== $id));
                }
            }

            $allPartners = array_merge($teamAPartners, $teamBPartners);
            foreach (array_unique($allPartners) as $partnerId) {
                $partnerMap[$partnerId] = $partnerMap[$partnerId] ?? [];
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
            ->flatMap(function ($match) {
                $ids = [];
                if ($match->team1) {
                    $ids = array_merge($ids, $match->team1->members->pluck('user_id')->toArray());
                }
                if ($match->team2) {
                    $ids = array_merge($ids, $match->team2->members->pluck('user_id')->toArray());
                }
                if ($match->participant1) {
                    $ids[] = $match->participant1->user_id;
                }
                if ($match->participant2) {
                    $ids[] = $match->participant2->user_id;
                }
                return $ids;
            })
            ->unique()
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

    private function getMatchPlayerIds(MiniMatch $match): array
    {
        $ids = [];

        if ($match->team1) {
            $ids = array_merge($ids, $match->team1->members->pluck('user_id')->toArray());
        }
        if ($match->team2) {
            $ids = array_merge($ids, $match->team2->members->pluck('user_id')->toArray());
        }
        if ($match->participant1) {
            $ids[] = $match->participant1->user_id;
        }
        if ($match->participant2) {
            $ids[] = $match->participant2->user_id;
        }

        return $ids;
    }
}
