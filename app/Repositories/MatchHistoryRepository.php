<?php

namespace App\Repositories;

use App\Models\MiniMatch;
use App\Models\MiniTeamMember;
use Illuminate\Support\Collection;

class MatchHistoryRepository
{
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

    public function getConsecutiveCounts(int $miniTournamentId): array
    {
        $matches = $this->getSessionMatches($miniTournamentId);
        $counts = [];
        $lastMatchIndex = [];

        foreach ($matches as $index => $match) {
            $playerIds = $this->getMatchPlayerIds($match);
            
            foreach ($playerIds as $userId) {
                if (isset($lastMatchIndex[$userId])) {
                    if ($index - $lastMatchIndex[$userId] === 1) {
                        $counts[$userId] = ($counts[$userId] ?? 0) + 1;
                    } else {
                        $counts[$userId] = 1;
                    }
                } else {
                    $counts[$userId] = 1;
                }
                $lastMatchIndex[$userId] = $index;
            }
        }

        return $counts;
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
            ->whereIn('status', [MiniMatch::STATUS_GOING_ON, MiniMatch::STATUS_WAITING_CONFIRM])
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
