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
     * @param int $courtCount Number of courts to use simultaneously
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generatePartnerRotationSchedule(array $playerIds, int $courtCount = 2, string $matchType = self::MATCH_TYPE_SINGLE): array
    {
        $n = count($playerIds);
        if ($n < 6) {
            throw new \InvalidArgumentException('partner_rotation requires at least 6 players, got ' . $n);
        }

        if ($matchType === self::MATCH_TYPE_DOUBLE) {
            if ($n < 4) {
                throw new \InvalidArgumentException('double partner_rotation requires at least 4 players, got ' . $n);
            }
            return $this->generateDoublePartnerRotation($playerIds, $courtCount);
        }

        $allMatches = $this->generateAllPairs($playerIds);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $courtCount, $n);

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
     * Generate double partner rotation schedule.
     * Players are paired into teams using circular rotation per round,
     * then each round-robin step creates matches between teams.
     *
     * Algorithm:
     * - Each round: pair players using circle rotation → teams for that round
     * - Between rounds: rotate partners and opponents together
     * - Result: each player partners with every other player exactly once
     *
     * @param array $playerIds
     * @param int $courtCount
     * @return array
     */
    private function generateDoublePartnerRotation(array $playerIds, int $courtCount): array
    {
        $n = count($playerIds);
        $playerIds = array_values($playerIds);

        $allMatches = [];
        $matchesPerPlayer = array_fill_keys($playerIds, 0);

        // Generate all unique player pairs to ensure each player meets every other player
        $targetPairs = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $targetPairs[] = [$playerIds[$i], $playerIds[$j]];
            }
        }

        // Track which partner pair is active in each round
        // Round i: partner pair at positions (i, i+1), (i+2, i+3), ...
        $numRounds = $n - 1;
        for ($round = 0; $round < $numRounds; $round++) {
            // Build teams for this round using circle rotation
            // Position r's partner in round r is at position (r + round + 1) % n
            $roundTeams = [];
            $usedInRound = [];

            for ($i = 0; $i < $n; $i++) {
                if (in_array($i, $usedInRound)) continue;
                $partnerPos = ($i + $round + 1) % $n;
                $roundTeams[] = [$playerIds[$i], $playerIds[$partnerPos]];
                $usedInRound[] = $i;
                $usedInRound[] = $partnerPos;
            }

            // Each team plays against every other team in this round
            $numTeams = count($roundTeams);
            for ($t1 = 0; $t1 < $numTeams; $t1++) {
                for ($t2 = $t1 + 1; $t2 < $numTeams; $t2++) {
                    $allMatches[] = [
                        'team1_players' => $roundTeams[$t1],
                        'team2_players' => $roundTeams[$t2],
                        'team1_id' => null,
                        'team2_id' => null,
                        'round_in_partner_rotation' => $round + 1,
                        'is_bye' => false,
                    ];
                    foreach ($roundTeams[$t1] as $pid) {
                        $matchesPerPlayer[$pid]++;
                    }
                    foreach ($roundTeams[$t2] as $pid) {
                        $matchesPerPlayer[$pid]++;
                    }
                }
            }
        }

        $rounds = $this->distributeMatchesIntoRounds($allMatches, $courtCount, $n);

        $unbalancedNotice = null;
        if ($n === 4 || $n === 5) {
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
     * Generate mixed_gender schedule.
     * Each male plays each female exactly once. Matches are distributed across rounds.
     *
     * @param array $maleIds Array of MiniParticipant IDs (male)
     * @param array $femaleIds Array of MiniParticipant IDs (female)
     * @param int $courtCount Number of courts to use simultaneously
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateMixedGenderSchedule(array $maleIds, array $femaleIds, int $courtCount = 2, string $matchType = self::MATCH_TYPE_SINGLE): array
    {
        $m = count($maleIds);
        $f = count($femaleIds);

        if ($m < 1 || $f < 1) {
            throw new \InvalidArgumentException('mixed_gender requires at least 1 male and 1 female player');
        }

        $allMatches = [];
        $teams = [];

        if ($matchType === self::MATCH_TYPE_DOUBLE) {
            $maleTeams = $this->buildSameGenderTeams($maleIds);
            $femaleTeams = $this->buildSameGenderTeams($femaleIds);

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

        $maxPerGroup = max($m, $f);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $courtCount, $maxPerGroup);

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
     * @param int $courtCount Number of courts to use simultaneously
     * @param string $matchType 'single' or 'double'
     * @return array{rounds: array, summary: array, teams: array}
     */
    public function generateRankPairingSchedule(array $aIds, array $bIds, int $courtCount = 2, string $matchType = self::MATCH_TYPE_SINGLE): array
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

        $maxPerGroup = max($na, $nb);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $courtCount, $maxPerGroup);

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
     * Distribute matches into rounds, respecting court count.
     * Adds bye placeholder matches when needed to fill rounds.
     *
     * @param array $allMatches
     * @param int $courtCount
     * @param int $playerCount
     * @return array
     */
    private function distributeMatchesIntoRounds(array $allMatches, int $courtCount, int $playerCount): array
    {
        $matchesPerRound = $courtCount;
        $totalMatches = count($allMatches);
        $totalRounds = (int) ceil($totalMatches / $matchesPerRound);

        $rounds = [];
        $idx = 0;

        for ($round = 1; $round <= $totalRounds; $round++) {
            $roundMatches = [];

            for ($c = 0; $c < $matchesPerRound && $idx < $totalMatches; $c++) {
                $roundMatches[] = $allMatches[$idx];
                $idx++;
            }

            $rounds[] = [
                'round_number' => $round,
                'matches' => $roundMatches,
            ];
        }

        return $rounds;
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
        $participants = MiniParticipant::with('user:id,name')->whereIn('id', $participantIds)->get()->keyBy('id');

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
