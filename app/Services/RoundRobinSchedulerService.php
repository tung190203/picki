<?php

namespace App\Services;

use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Services\Tournament\ByeResolver;
use App\Services\Tournament\Scheduler\PartnerRotationScheduler;
use App\Services\Tournament\Scheduler\MixedGenderScheduler;
use App\Services\Tournament\Scheduler\RankPairingScheduler;
use Illuminate\Support\Facades\DB;

/**
 * Round Robin Scheduler Service.
 *
 * All scheduling logic is delegated to focused scheduler classes:
 * - PartnerRotationScheduler  (partner_rotation format)
 * - MixedGenderScheduler      (mixed_gender format)
 * - RankPairingScheduler      (rank_pairing format)
 *
 * This class maintains backward-compatible public method signatures
 * and provides leaderboard calculation.
 */
class RoundRobinSchedulerService
{
    const MATCH_TYPE_SINGLE = 'single';
    const MATCH_TYPE_DOUBLE = 'double';

    /**
     * Generate partner_rotation schedule (minimum 4 players, doubles only).
     *
     * Spec: "Mỗi người ghép cặp với tất cả người còn lại đúng 1 lần"
     *
     * @param array $playerIds Array of MiniParticipant IDs
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generatePartnerRotationSchedule(array $playerIds, string $matchType = self::MATCH_TYPE_SINGLE): array
    {
        if ($matchType === self::MATCH_TYPE_SINGLE) {
            throw new \InvalidArgumentException('Round Robin chỉ hỗ trợ kèo đánh đôi.');
        }

        $result = (new PartnerRotationScheduler())->generate($playerIds);

        return [
            'rounds' => $result['rounds'],
            'summary' => $result['summary'],
            'teams' => [],
        ];
    }

    /**
     * Generate mixed_gender schedule (minimum 1 male + 1 female, doubles only).
     *
     * Spec: "Mỗi nam ghép với mỗi nữ đúng 1 lần"
     *
     * @param array $maleIds   Array of MiniParticipant IDs (male)
     * @param array $femaleIds Array of MiniParticipant IDs (female)
     * @param string $matchType 'single' or 'double'
     * @param bool $shuffle @unused Shuffle handled by controller; kept for API compatibility
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateMixedGenderSchedule(
        array $maleIds,
        array $femaleIds,
        string $matchType = self::MATCH_TYPE_SINGLE,
        bool $shuffle = true
    ): array {
        if ($matchType === self::MATCH_TYPE_SINGLE) {
            throw new \InvalidArgumentException('Round Robin chỉ hỗ trợ kèo đánh đôi.');
        }

        // Shuffle is handled by the controller before calling this method
        $result = (new MixedGenderScheduler())->generate($maleIds, $femaleIds);

        return [
            'rounds' => $result['rounds'],
            'summary' => $result['summary'],
            'teams' => [],
        ];
    }

    /**
     * Generate rank_pairing schedule (minimum 1 A + 1 B, doubles only).
     *
     * Spec: "Mỗi A ghép với mỗi B đúng 1 lần"
     *
     * @param array $aIds Array of MiniParticipant IDs (group A)
     * @param array $bIds Array of MiniParticipant IDs (group B)
     * @param string $matchType 'single' or 'double'
     * @param bool $shuffle @unused Shuffle handled by controller; kept for API compatibility
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateRankPairingSchedule(
        array $aIds,
        array $bIds,
        string $matchType = self::MATCH_TYPE_SINGLE,
        bool $shuffle = true
    ): array {
        if ($matchType === self::MATCH_TYPE_SINGLE) {
            throw new \InvalidArgumentException('Round Robin chỉ hỗ trợ kèo đánh đôi.');
        }

        // Shuffle is handled by the controller before calling this method
        $result = (new RankPairingScheduler())->generate($aIds, $bIds);

        return [
            'rounds' => $result['rounds'],
            'summary' => $result['summary'],
            'teams' => [],
        ];
    }

    /**
     * Calculate leaderboard for a mini tournament.
     * For rank_pairing format, returns separate A and B leaderboards.
     *
     * @param int $miniTournamentId
     * @return array{leaderboard: array, group_a_leaderboard: array|null, group_b_leaderboard: array|null}
     */
    public function calculateLeaderboard(int $miniTournamentId): array
    {
        $miniTournament = MiniTournament::with('participants')
            ->find($miniTournamentId);
        if (!$miniTournament) {
            return ['leaderboard' => []];
        }

        $isRankPairing = $miniTournament->match_format === MiniTournament::MATCH_FORMAT_RANK_PAIRING;

        $participants = MiniParticipant::with('user:id,full_name')
            ->where('mini_tournament_id', $miniTournamentId)
            ->get()
            ->keyBy('id');

        // Map user_id -> participant_id for quick lookup
        $userToParticipant = $participants->map(fn($p) => $p->user_id)->flip()->toArray();

        $matches = MiniMatch::where('mini_tournament_id', $miniTournamentId)
            ->whereNotNull('round_number')
            ->where('status', MiniMatch::STATUS_COMPLETED)
            ->with([
                'team1.members.user',
                'team2.members.user',
                'results',
            ])
            ->get();

        $stats = [];
        foreach ($participants as $p) {
            $stats[$p->id] = [
                'participant_id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user?->full_name ?? ($p->guest_name ?? 'Khách'),
                'player_group' => $p->player_group,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'total_matches' => 0,
                'total_point_diff' => 0,
            ];
        }

        foreach ($matches as $match) {
            if (ByeResolver::isBye($match)) {
                $byeStats = ByeResolver::getByeStats($match);
                $pids = ByeResolver::getByeParticipantIds($match, $miniTournament);
                foreach ($pids as $pid) {
                    if (isset($stats[$pid])) {
                        $stats[$pid]['total_matches'] += $byeStats['total_matches'];
                        $stats[$pid]['wins'] += $byeStats['wins'];
                        $stats[$pid]['total_point_diff'] += $byeStats['point_diff'];
                    }
                }
                continue;
            }

            // Normal match: compute scores from mini_match_results
            $s1 = 0;
            $s2 = 0;
            foreach ($match->results as $result) {
                if ((int) $result->won_set === 1) {
                    if ((int) $result->team_id === (int) $match->team1_id) {
                        $s1++;
                    } else {
                        $s2++;
                    }
                }
            }

            if ($s1 === 0 && $s2 === 0) {
                continue;
            }

            // Build participant_id lists from team members
            $p1List = [];
            $p2List = [];
            foreach ($match->team1->members as $m) {
                if ($m->user_id && isset($userToParticipant[$m->user_id])) {
                    $pid = $userToParticipant[$m->user_id];
                    if (isset($stats[$pid])) {
                        $p1List[] = $pid;
                    }
                }
            }
            foreach ($match->team2->members as $m) {
                if ($m->user_id && isset($userToParticipant[$m->user_id])) {
                    $pid = $userToParticipant[$m->user_id];
                    if (isset($stats[$pid])) {
                        $p2List[] = $pid;
                    }
                }
            }

            if (empty($p1List) || empty($p2List)) {
                continue;
            }

            foreach ($p1List as $pid) {
                $stats[$pid]['total_matches']++;
                $stats[$pid]['total_point_diff'] += $s1 - $s2;
            }
            foreach ($p2List as $pid) {
                $stats[$pid]['total_matches']++;
                $stats[$pid]['total_point_diff'] += $s2 - $s1;
            }

            if ($s1 > $s2) {
                foreach ($p1List as $pid) { $stats[$pid]['wins']++; }
                foreach ($p2List as $pid) { $stats[$pid]['losses']++; }
            } elseif ($s2 > $s1) {
                foreach ($p2List as $pid) { $stats[$pid]['wins']++; }
                foreach ($p1List as $pid) { $stats[$pid]['losses']++; }
            } else {
                foreach ($p1List as $pid) { $stats[$pid]['draws']++; }
                foreach ($p2List as $pid) { $stats[$pid]['draws']++; }
            }
        }

        $enrichStats = function (array $items): array {
            return collect($items)->map(function ($s) {
                $s['win_rate'] = $s['total_matches'] > 0
                    ? round($s['wins'] / $s['total_matches'] * 100, 1)
                    : 0;
                $s['avg_point_diff'] = $s['total_matches'] > 0
                    ? round($s['total_point_diff'] / $s['total_matches'], 1)
                    : 0;
                return $s;
            })->sort(function ($a, $b) {
                if ($b['wins'] !== $a['wins']) {
                    return $b['wins'] - $a['wins'];
                }
                if (abs($b['avg_point_diff'] - $a['avg_point_diff']) > 0.01) {
                    return $b['avg_point_diff'] <=> $a['avg_point_diff'];
                }
                return $b['total_matches'] - $a['total_matches'];
            })->values()->map(function ($s, $idx) {
                $s['rank'] = $idx + 1;
                return $s;
            })->all();
        };

        $allLeaderboard = $enrichStats($stats);

        if ($isRankPairing) {
            $groupAStats = array_filter($stats, fn($s) => $s['player_group'] === 'a');
            $groupBStats = array_filter($stats, fn($s) => $s['player_group'] === 'b');

            return [
                'leaderboard' => $allLeaderboard,
                'group_a_leaderboard' => $enrichStats($groupAStats),
                'group_b_leaderboard' => $enrichStats($groupBStats),
            ];
        }

        return [
            'leaderboard' => $allLeaderboard,
        ];
    }

    /**
     * Resolve participant ID from a MiniTeam and the user→participant lookup map.
     *
     * For single format: the team's only member → participant via user_id.
     * For double format: all team members → participants via user_id.
     *
     * @param  \App\Models\MiniTeam|null  $team
     * @param  array  $userToParticipant  [user_id => participant_id]
     * @return int|null
     */
    public function resolveParticipantId(?\App\Models\MiniTeam $team, array $userToParticipant): ?int
    {
        if (!$team || !$team->relationLoaded('members')) {
            return null;
        }
        foreach ($team->members as $member) {
            $userId = $member->user_id ?? ($member->user?->id ?? null);
            if ($userId !== null && isset($userToParticipant[$userId])) {
                return (int) $userToParticipant[$userId];
            }
        }
        return null;
    }
}
