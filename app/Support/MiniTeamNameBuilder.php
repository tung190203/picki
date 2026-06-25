<?php

namespace App\Support;

use App\Models\MiniParticipant;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Build display names for MiniTeam based on player members.
 */
class MiniTeamNameBuilder
{
    /**
     * Build team name from an array of user IDs.
     * Falls back to participant IDs if user is not found.
     */
    public static function buildFromUserIds(array $userIds, int $miniTournamentId): string
    {
        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $names = [];
        foreach ($userIds as $userId) {
            $p = $participants->get($userId);
            if ($p && $p->is_guest) {
                $names[] = $p->guest_name ?? 'Khách';
            } else {
                $user = User::find($userId);
                $names[] = $user?->full_name ?? 'Người chơi';
            }
        }

        return implode(' - ', $names);
    }

    /**
     * Build team name from an array of participant IDs.
     */
    public static function buildFromParticipantIds(array $participantIds, int $miniTournamentId): string
    {
        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('id', $participantIds)
            ->get()
            ->keyBy('id');

        $names = [];
        foreach ($participantIds as $pid) {
            $p = $participants->get($pid);
            if ($p && $p->is_guest) {
                $names[] = $p->guest_name ?? 'Khách';
            } elseif ($p && $p->user_id) {
                $user = User::find($p->user_id);
                $names[] = $user?->full_name ?? 'Người chơi';
            } else {
                $names[] = 'Người chơi';
            }
        }

        return implode(' - ', $names);
    }
}
