<?php

namespace App\Services\Tournament;

use App\Models\MiniMatch;
use App\Models\MiniTournament;

/**
 * Single source of truth for all BYE handling logic.
 *
 * All code that needs to determine anything about a BYE match
 * (who wins, what stats to apply, whether it's relevant, etc.)
 * MUST go through this class.
 */
class ByeResolver
{
    /**
     * Check if a match is a BYE match.
     */
    public static function isBye(MiniMatch $match): bool
    {
        return (bool) $match->is_bye;
    }

    /**
     * Resolve which team_id is the side that received the BYE.
     * Returns null if neither side has players (edge case).
     *
     * Double format: one team has members, the other is null.
     * Bye side = the team that has players.
     */
    public static function resolveByeTeamId(MiniMatch $match): ?int
    {
        if (!$match->is_bye) {
            return null;
        }

        // If team1 has members (team1_id set), it's the bye side.
        // If only team2 has members, team2 is the bye side.
        // Both null → bye with no players (shouldn't happen).
        return $match->team1_id ?? $match->team2_id;
    }

    /**
     * In a BYE match, the bye team sits out (no opponent).
     * Returns the team_id of the bye side (for scheduling identification only).
     *
     * @deprecated BYE no longer awards a win. Use this only for identification purposes.
6     */
    public static function getByeWinnerTeamId(MiniMatch $match): ?int
    {
        return self::resolveByeTeamId($match);
    }

    /**
     * Get all participant_ids that have the BYE in this match.
     *
     * For double format: all members of the bye team.
     * For single format: the single participant.
     *
     * @return int[]
     * @deprecated BYE no longer awards a win. This is only for identification.
     */
    public static function getByeParticipantIds(MiniMatch $match, MiniTournament $tournament): array
    {
        if (!$match->is_bye) {
            return [];
        }

        $pids = [];

        // Load members if not already loaded
        $byeTeamId = self::resolveByeTeamId($match);
        if ($byeTeamId === null) {
            return [];
        }

        // Determine which side is the bye side
        $isTeam1Bye = (int) $match->team1_id === (int) $byeTeamId;
        $byeTeam = $isTeam1Bye ? $match->team1 : $match->team2;

        if ($byeTeam && $byeTeam->relationLoaded('members')) {
            foreach ($byeTeam->members as $member) {
                if ($member->user_id) {
                    // Find participant_id from user_id
                    $participant = $tournament->participants
                        ->firstWhere('user_id', $member->user_id);
                    if ($participant) {
                        $pids[] = $participant->id;
                    }
                }
            }
        }

        // Single format fallback: use participant1_id / participant2_id
        if (empty($pids)) {
            $singlePid = $match->participant1_id ?? $match->participant2_id;
            if ($singlePid) {
                $pids[] = $singlePid;
            }
        }

        return $pids;
    }

    /**
     * Get the stats to apply for a BYE.
     * BYE = "nghỉ vòng" — no result, no stats.
     * BYE only affects scheduling (who sits out a round).
     *
     * @return array{wins: int, total_matches: int, point_diff: int}
     */
    public static function getByeStats(MiniMatch $match): array
    {
        if (!$match->is_bye) {
            return ['wins' => 0, 'total_matches' => 0, 'point_diff' => 0];
        }

        // BYE = no result. No wins, no matches counted, no point diff.
        return ['wins' => 0, 'total_matches' => 0, 'point_diff' => 0];
    }

    /**
     * Whether a match should be counted toward round completion.
     * BYE matches are NOT counted — they are automatic.
     */
    public static function isMatchRelevant(MiniMatch $match): bool
    {
        return !$match->is_bye;
    }

    /**
     * Whether a BYE match should be auto-marked as completed.
     * Returns true — BYE matches are always treated as completed for stats purposes.
     */
    public static function isByeMatchAutoComplete(): bool
    {
        return true;
    }
}
