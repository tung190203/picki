<?php

namespace App\Services;

use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\MiniTeam;
use App\Models\MiniTeamMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoundRobinSchedulerService
{
    const MATCH_TYPE_SINGLE = 'single';
    const MATCH_TYPE_DOUBLE = 'double';

    /**
     * Generate partner_rotation schedule (minimum 6 individual players).
     * Each player plays against every other player exactly once.
     * Distributed across rounds using available courts.
     *
     * @param array $playerIds Array of MiniParticipant IDs
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generatePartnerRotationSchedule(array $playerIds, string $matchType = self::MATCH_TYPE_SINGLE): array
    {
        $n = count($playerIds);
        if ($n < 6) {
            throw new \InvalidArgumentException('partner_rotation requires at least 6 players, got ' . $n);
        }

        if ($matchType === self::MATCH_TYPE_DOUBLE) {
            if ($n < 4) {
                throw new \InvalidArgumentException('double partner_rotation requires at least 4 players, got ' . $n);
            }
            return $this->generateDoublePartnerRotation($playerIds);
        }

        $allMatches = $this->generateAllPairs($playerIds);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $playerIds);

        $matchesPerPlayer = array_fill_keys($playerIds, 0);
        foreach ($allMatches as $match) {
            $matchesPerPlayer[$match['participant1_id']]++;
            $matchesPerPlayer[$match['participant2_id']]++;
        }

        $unbalancedNotice = null;
        if ($n === 6 || $n === 7) {
            $min = min($matchesPerPlayer);
            $max = max($matchesPerPlayer);
            if ($min !== $max) {
                $unbalancedNotice = "Số trận không đều: {$min}-{$max} trận/người.";
            }
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'matches_per_player' => $matchesPerPlayer,
                'unbalanced_notice' => $unbalancedNotice,
            ],
            'teams' => [],
        ];
    }

    /**
     * Generate double partner rotation schedule using circle method.
     *
     * Player 0 fixed as anchor. Remaining (n-1) players rotate left by 1 per round.
     * Each round: first half of rotated circle vs second half.
     * All players in team1 play all players in team2.
     *
     * Odd n: 1 player sits out per round (bye). n rounds total.
     * Even n: no bye. n-1 rounds total.
     *
     * @param array $playerIds Array of MiniParticipant IDs
     * @return array
     */
    private function generateDoublePartnerRotation(array $playerIds): array
    {
        $n = count($playerIds);
        $playerIds = array_values($playerIds);

        // Bước 1: 1-factorization — tạo tất cả C(n,2) pairs
        $allPairs = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $allPairs[] = [
                    'participant1_id' => $playerIds[$i],
                    'participant2_id' => $playerIds[$j],
                ];
            }
        }

        // Bước 2: Greedy coloring — pack pairs vào rounds (no player conflict)
        $rounds = $this->distributeMatchesIntoRounds($allPairs, $playerIds, true);

        // Bước 3: Nhóm mỗi 2 pairs liền kề thành match pair-vs-pair
        // Odd n: cặp cuối cùng → bye match
        foreach ($rounds as &$round) {
            $newMatches = [];
            $pairs = $round['matches'];
            for ($i = 0; $i < count($pairs); $i += 2) {
                $p1 = $pairs[$i];
                $team1 = array_filter([$p1['participant1_id'], $p1['participant2_id']]);

                if ($i + 1 < count($pairs)) {
                    $p2 = $pairs[$i + 1];
                    $team2 = array_filter([$p2['participant1_id'], $p2['participant2_id']]);
                    $isBye = false;
                    $byePlayer = null;
                } else {
                    // Odd: cặp cuối → bye (1 player ngồi ngoài)
                    $team2 = [];
                    $isBye = true;
                    $byePlayer = array_values($team1)[1] ?? null;
                }

                $newMatches[] = [
                    'team1_players' => array_values($team1),
                    'team2_players' => array_values($team2),
                    'team1_id' => null,
                    'team2_id' => null,
                    'is_bye' => $isBye,
                    'bye_player_id' => $byePlayer,
                ];
            }
            $round['matches'] = $newMatches;
        }
        unset($round);

        $matchesPerPlayer = array_fill_keys($playerIds, $n - 1);

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allPairs),
                'matches_per_player' => $matchesPerPlayer,
                'unbalanced_notice' => null,
            ],
            'teams' => [],
        ];
    }

    /**
     * Generate mixed_gender schedule.
     * Each male plays each female exactly once. Matches are distributed across rounds.
     *
     * @param array $maleIds Array of MiniParticipant IDs (male)
     * @param array $femaleIds Array of MiniParticipant IDs (female)
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateMixedGenderSchedule(array $maleIds, array $femaleIds, string $matchType = self::MATCH_TYPE_SINGLE, ?int $miniTournamentId = null): array
    {
        $m = count($maleIds);
        $f = count($femaleIds);

        if ($m < 1 || $f < 1) {
            throw new \InvalidArgumentException('mixed_gender requires at least 1 male and 1 female player');
        }

        $allMatches = [];
        $teams = [];

        if ($matchType === self::MATCH_TYPE_DOUBLE) {
            $maleTeams = $this->buildSameGenderTeams($maleIds, $miniTournamentId);
            $femaleTeams = $this->buildSameGenderTeams($femaleIds, $miniTournamentId);

            foreach ($maleTeams as $maleTeam) {
                foreach ($femaleTeams as $femaleTeam) {
                    $allMatches[] = [
                        'team1_players' => $maleTeam['member_ids'],
                        'team2_players' => $femaleTeam['member_ids'],
                        'team1_id' => null,
                        'team2_id' => null,
                        'is_bye' => false,
                    ];
                }
            }
            $teams = array_merge($maleTeams, $femaleTeams);
        } else {
            foreach ($maleIds as $maleId) {
                foreach ($femaleIds as $femaleId) {
                    $allMatches[] = [
                        'participant1_id' => $maleId,
                        'participant2_id' => $femaleId,
                        'is_bye' => false,
                    ];
                }
            }
        }

        $allPlayerIds = array_merge($maleIds, $femaleIds);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $allPlayerIds);

        $maleMatches = array_fill_keys($maleIds, 0);
        $femaleMatches = array_fill_keys($femaleIds, 0);
        foreach ($allMatches as $match) {
            $ids1 = $match['team1_players'] ?? [$match['participant1_id'] ?? null];
            $ids2 = $match['team2_players'] ?? [$match['participant2_id'] ?? null];
            foreach ($ids1 as $pid) {
                if (!$pid) continue;
                if (isset($maleMatches[$pid])) {
                    $maleMatches[$pid]++;
                } elseif (isset($femaleMatches[$pid])) {
                    $femaleMatches[$pid]++;
                }
            }
            foreach ($ids2 as $pid) {
                if (!$pid) continue;
                if (isset($maleMatches[$pid])) {
                    $maleMatches[$pid]++;
                } elseif (isset($femaleMatches[$pid])) {
                    $femaleMatches[$pid]++;
                }
            }
        }

        $unbalancedNotice = null;
        if ($m !== $f) {
            $unbalancedNotice = "Số trận chênh lệch: nam {$m}×{$f}=" . ($m * $f) . " trận, mỗi nam đánh {$f} trận, mỗi nữ đánh {$m} trận.";
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'male_matches' => $maleMatches,
                'female_matches' => $femaleMatches,
                'matches_per_male' => $f,
                'matches_per_female' => $m,
                'unbalanced_notice' => $unbalancedNotice,
            ],
            'teams' => $teams,
        ];
    }

    /**
     * Generate rank_pairing schedule.
     * Each A plays each B exactly once. Matches are distributed across rounds.
     *
     * @param array $aIds Array of MiniParticipant IDs (group A)
     * @param array $bIds Array of MiniParticipant IDs (group B)
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateRankPairingSchedule(array $aIds, array $bIds, string $matchType = self::MATCH_TYPE_SINGLE): array
    {
        $na = count($aIds);
        $nb = count($bIds);

        if ($na < 1 || $nb < 1) {
            throw new \InvalidArgumentException('rank_pairing requires at least 1 player in each group');
        }

        $allMatches = [];
        $teams = [];

        if ($matchType === self::MATCH_TYPE_DOUBLE) {
            $aTeams = $this->buildSameGenderTeams($aIds);
            $bTeams = $this->buildSameGenderTeams($bIds);

            foreach ($aTeams as $aTeam) {
                foreach ($bTeams as $bTeam) {
                    $allMatches[] = [
                        'team1_players' => $aTeam['member_ids'],
                        'team2_players' => $bTeam['member_ids'],
                        'team1_id' => null,
                        'team2_id' => null,
                        'is_bye' => false,
                    ];
                }
            }
            $teams = array_merge($aTeams, $bTeams);
        } else {
            foreach ($aIds as $aId) {
                foreach ($bIds as $bId) {
                    $allMatches[] = [
                        'participant1_id' => $aId,
                        'participant2_id' => $bId,
                        'is_bye' => false,
                    ];
                }
            }
        }

        $allPlayerIds = array_merge($aIds, $bIds);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $allPlayerIds);

        $aMatches = array_fill_keys($aIds, 0);
        $bMatches = array_fill_keys($bIds, 0);
        foreach ($allMatches as $match) {
            $ids1 = $match['team1_players'] ?? [$match['participant1_id'] ?? null];
            $ids2 = $match['team2_players'] ?? [$match['participant2_id'] ?? null];
            foreach ($ids1 as $pid) {
                if (!$pid) continue;
                if (isset($aMatches[$pid])) {
                    $aMatches[$pid]++;
                } elseif (isset($bMatches[$pid])) {
                    $bMatches[$pid]++;
                }
            }
            foreach ($ids2 as $pid) {
                if (!$pid) continue;
                if (isset($aMatches[$pid])) {
                    $aMatches[$pid]++;
                } elseif (isset($bMatches[$pid])) {
                    $bMatches[$pid]++;
                }
            }
        }

        $unbalancedNotice = null;
        if ($na !== $nb) {
            $unbalancedNotice = "Số trận chênh lệch: nhóm A × nhóm B = {$na}×{$nb}=" . ($na * $nb) . " trận, mỗi A đánh {$nb} trận, mỗi B đánh {$na} trận.";
        }

        return [
            'rounds' => $rounds,
            'summary' => [
                'total_rounds' => count($rounds),
                'total_matches' => count($allMatches),
                'a_matches' => $aMatches,
                'b_matches' => $bMatches,
                'matches_per_a' => $nb,
                'matches_per_b' => $na,
                'unbalanced_notice' => $unbalancedNotice,
            ],
            'teams' => $teams,
        ];
    }

    /**
     * Calculate leaderboard for a mini tournament.
     *
     * @param int $miniTournamentId
     * @return array{leaderboard: array, group_a_leaderboard: array|null, group_b_leaderboard: array|null}
     */
    public function calculateLeaderboard(int $miniTournamentId): array
    {
        $miniTournament = MiniTournament::find($miniTournamentId);
        if (!$miniTournament) {
            return ['leaderboard' => [], 'group_a_leaderboard' => null, 'group_b_leaderboard' => null];
        }

        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)->get()->keyBy('id');
        $matches = MiniMatch::where('mini_tournament_id', $miniTournamentId)
            ->whereNotNull('round_number')
            ->where('status', MiniMatch::STATUS_COMPLETED)
            ->get();

        $stats = [];
        foreach ($participants as $p) {
            $stats[$p->id] = [
                'participant_id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user?->name ?? ($p->guest_name ?? 'Khách'),
                'player_group' => $p->player_group,
                'wins' => 0,
                'losses' => 0,
                'draws' => 0,
                'total_matches' => 0,
                'total_point_diff' => 0,
            ];
        }

        foreach ($matches as $match) {
            if ($match->team_1_score === null || $match->team_2_score === null) {
                continue;
            }

            $s1 = $match->team_1_score;
            $s2 = $match->team_2_score;

            $p1 = $match->participant1_id;
            $p2 = $match->participant2_id;

            if (!isset($stats[$p1]) || !isset($stats[$p2])) {
                continue;
            }

            $stats[$p1]['total_matches']++;
            $stats[$p2]['total_matches']++;
            $stats[$p1]['total_point_diff'] += $s1 - $s2;
            $stats[$p2]['total_point_diff'] += $s2 - $s1;

            if ($s1 > $s2) {
                $stats[$p1]['wins']++;
                $stats[$p2]['losses']++;
            } elseif ($s2 > $s1) {
                $stats[$p2]['wins']++;
                $stats[$p1]['losses']++;
            } else {
                $stats[$p1]['draws']++;
                $stats[$p2]['draws']++;
            }
        }

        $leaderboard = collect($stats)->map(function ($s) {
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

        $groupALeaderboard = null;
        $groupBLeaderboard = null;

        if ($miniTournament->match_format === MiniTournament::MATCH_FORMAT_RANK_PAIRING) {
            $groupALeaderboard = collect($leaderboard)
                ->filter(fn($s) => $s['player_group'] === 'a')
                ->values()
                ->map(fn($s, $idx) => array_merge($s, ['rank' => $idx + 1]))
                ->values()
                ->all();

            $groupBLeaderboard = collect($leaderboard)
                ->filter(fn($s) => $s['player_group'] === 'b')
                ->values()
                ->map(fn($s, $idx) => array_merge($s, ['rank' => $idx + 1]))
                ->values()
                ->all();
        }

        return [
            'leaderboard' => $leaderboard,
            'group_a_leaderboard' => $groupALeaderboard,
            'group_b_leaderboard' => $groupBLeaderboard,
        ];
    }

    /**
     * Generate all unique pairs for a set of player IDs (Round Robin).
     *
     * @param array $playerIds
     * @return array
     */
    private function generateAllPairs(array $playerIds): array
    {
        $matches = [];
        $n = count($playerIds);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $matches[] = [
                    'participant1_id' => $playerIds[$i],
                    'participant2_id' => $playerIds[$j],
                    'is_bye' => false,
                ];
            }
        }
        return $matches;
    }

    /**
     * Distribute matches into rounds using greedy round-grouping.
     * Each round contains all matches that can be played simultaneously
     * (no player appears twice in the same round).
     * When a player has no match in a round, a bye match is added for them.
     *
     * @param array $allMatches
     * @param array $allPlayerIds All player IDs in this tournament
     * @return array
     */
    private function distributeMatchesIntoRounds(array $allMatches, array $allPlayerIds, bool $skipAutoBye = false): array
    {
        $totalMatches = count($allMatches);
        if ($totalMatches === 0) {
            return [];
        }

        $rounds = [];
        $remaining = $allMatches;
        $roundNumber = 1;

        while (!empty($remaining)) {
            $roundMatches = [];
            $playersInRound = [];

            // Greedy: add all non-conflicting matches to current round
            foreach ($remaining as $idx => $match) {
                $matchPlayers = $this->getMatchPlayerIds($match);

                $hasConflict = false;
                foreach ($matchPlayers as $pid) {
                    if (in_array($pid, $playersInRound, true)) {
                        $hasConflict = true;
                        break;
                    }
                }

                if (!$hasConflict) {
                    $roundMatches[] = $match;
                    foreach ($matchPlayers as $pid) {
                        $playersInRound[] = $pid;
                    }
                    unset($remaining[$idx]);
                }
            }

            // Deadlock: all remaining matches conflict → pick first to break
            if (empty($roundMatches) && !empty($remaining)) {
                $firstKey = array_key_first($remaining);
                $roundMatches[] = $remaining[$firstKey];
                $matchPlayers = $this->getMatchPlayerIds($remaining[$firstKey]);
                foreach ($matchPlayers as $pid) {
                    $playersInRound[] = $pid;
                }
                unset($remaining[$firstKey]);
            }

            // Add bye matches for unassigned players (skip if already handled by scheduler)
            if (!$skipAutoBye) {
                foreach ($allPlayerIds as $pid) {
                    if (!in_array($pid, $playersInRound, true)) {
                        $roundMatches[] = [
                            'participant1_id' => $pid,
                            'participant2_id' => null,
                            'is_bye' => true,
                        ];
                    }
                }
            }

            $rounds[] = [
                'round_number' => $roundNumber,
                'matches' => array_values($roundMatches),
            ];

            $roundNumber++;
        }

        return $rounds;
    }

    /**
     * Extract player IDs from a match structure (handles single, double, and team formats).
     *
     * @param array $match
     * @return array<int>
     */
    private function getMatchPlayerIds(array $match): array
    {
        $ids = [];

        if (isset($match['participant1_id'])) {
            $ids[] = $match['participant1_id'];
        }
        if (isset($match['participant2_id'])) {
            $ids[] = $match['participant2_id'];
        }
        if (isset($match['team1_players']) && is_array($match['team1_players'])) {
            foreach ($match['team1_players'] as $pid) {
                $ids[] = $pid;
            }
        }
        if (isset($match['team2_players']) && is_array($match['team2_players'])) {
            foreach ($match['team2_players'] as $pid) {
                $ids[] = $pid;
            }
        }

        return $ids;
    }

    /**
     * Build fixed same-gender pairs for mixed_gender / rank_pairing double format.
     * Pairs participants into teams of 2. Odd one out sits out.
     *
     * @param array $participantIds
     * @param int|null $miniTournamentId
     * @return array
     */
    public function buildSameGenderTeams(array $participantIds, ?int $miniTournamentId = null): array
    {
        $teams = [];
        $participants = MiniParticipant::with('user:id,full_name')->whereIn('id', $participantIds)->get()->keyBy('id');

        $shuffled = $participantIds;
        shuffle($shuffled);

        for ($i = 0; $i < count($shuffled); $i += 2) {
            if (!isset($shuffled[$i + 1])) break;

            $p1Id = $shuffled[$i];
            $p2Id = $shuffled[$i + 1];
            $p1 = $participants[$p1Id] ?? null;
            $p2 = $participants[$p2Id] ?? null;

            $name1 = $p1 && $p1->user ? $p1->user->name : ($p1->guest_name ?? 'TBD');
            $name2 = $p2 && $p2->user ? $p2->user->name : ($p2->guest_name ?? 'TBD');

            $team = MiniTeam::create([
                'name' => "{$name1} & {$name2}",
                'mini_tournament_id' => $miniTournamentId,
            ]);

            MiniTeamMember::create(['mini_team_id' => $team->id, 'user_id' => $p1Id, 'is_guest' => (bool) ($p1?->is_guest)]);
            MiniTeamMember::create(['mini_team_id' => $team->id, 'user_id' => $p2Id, 'is_guest' => (bool) ($p2?->is_guest)]);

            $teams[] = [
                'id' => $team->id,
                'name' => $team->name,
                'member_ids' => [$p1Id, $p2Id],
            ];
        }

        return $teams;
    }
}
