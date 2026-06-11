<?php

namespace App\Services;

use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoundRobinSchedulerService
{
    /**
     * Generate partner_rotation schedule (minimum 6 individual players).
     * Each player plays against every other player exactly once.
     * Distributed across rounds using available courts.
     *
     * @param array $playerIds Array of MiniParticipant IDs
     * @param int $courtCount Number of courts to use simultaneously
     * @return array{rounds: array, summary: array}
     */
    public function generatePartnerRotationSchedule(array $playerIds, int $courtCount = 2): array
    {
        $n = count($playerIds);
        if ($n < 6) {
            throw new \InvalidArgumentException('partner_rotation requires at least 6 players, got ' . $n);
        }

        $allMatches = $this->generateAllPairs($playerIds);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $courtCount, $n);

        $matchesPerPlayer = [];
        foreach ($playerIds as $id) {
            $matchesPerPlayer[$id] = 0;
        }
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
        ];
    }

    /**
     * Generate mixed_gender schedule.
     * Each male plays each female exactly once. Matches are distributed across rounds.
     *
     * @param array $maleIds Array of MiniParticipant IDs (male)
     * @param array $femaleIds Array of MiniParticipant IDs (female)
     * @param int $courtCount Number of courts to use simultaneously
     * @return array{rounds: array, summary: array}
     */
    public function generateMixedGenderSchedule(array $maleIds, array $femaleIds, int $courtCount = 2): array
    {
        $m = count($maleIds);
        $f = count($femaleIds);

        if ($m < 1 || $f < 1) {
            throw new \InvalidArgumentException('mixed_gender requires at least 1 male and 1 female player');
        }

        $allMatches = [];
        foreach ($maleIds as $maleId) {
            foreach ($femaleIds as $femaleId) {
                $allMatches[] = [
                    'participant1_id' => $maleId,
                    'participant2_id' => $femaleId,
                    'is_bye' => false,
                ];
            }
        }

        $maxPerGroup = max($m, $f);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $courtCount, $maxPerGroup);

        $maleMatches = [];
        $femaleMatches = [];
        foreach ($maleIds as $id) {
            $maleMatches[$id] = 0;
        }
        foreach ($femaleIds as $id) {
            $femaleMatches[$id] = 0;
        }
        foreach ($allMatches as $match) {
            if (in_array($match['participant1_id'], $maleIds)) {
                $maleMatches[$match['participant1_id']]++;
            } else {
                $femaleMatches[$match['participant1_id']]++;
            }
            if (in_array($match['participant2_id'], $maleIds)) {
                $maleMatches[$match['participant2_id']]++;
            } else {
                $femaleMatches[$match['participant2_id']]++;
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
        ];
    }

    /**
     * Generate rank_pairing schedule.
     * Each A plays each B exactly once. Matches are distributed across rounds.
     *
     * @param array $aIds Array of MiniParticipant IDs (group A)
     * @param array $bIds Array of MiniParticipant IDs (group B)
     * @param int $courtCount Number of courts to use simultaneously
     * @return array{rounds: array, summary: array}
     */
    public function generateRankPairingSchedule(array $aIds, array $bIds, int $courtCount = 2): array
    {
        $na = count($aIds);
        $nb = count($bIds);

        if ($na < 1 || $nb < 1) {
            throw new \InvalidArgumentException('rank_pairing requires at least 1 player in each group');
        }

        $allMatches = [];
        foreach ($aIds as $aId) {
            foreach ($bIds as $bId) {
                $allMatches[] = [
                    'participant1_id' => $aId,
                    'participant2_id' => $bId,
                    'is_bye' => false,
                ];
            }
        }

        $maxPerGroup = max($na, $nb);
        $rounds = $this->distributeMatchesIntoRounds($allMatches, $courtCount, $maxPerGroup);

        $aMatches = [];
        $bMatches = [];
        foreach ($aIds as $id) {
            $aMatches[$id] = 0;
        }
        foreach ($bIds as $id) {
            $bMatches[$id] = 0;
        }
        foreach ($allMatches as $match) {
            if (in_array($match['participant1_id'], $aIds)) {
                $aMatches[$match['participant1_id']]++;
            } else {
                $bMatches[$match['participant1_id']]++;
            }
            if (in_array($match['participant2_id'], $aIds)) {
                $aMatches[$match['participant2_id']]++;
            } else {
                $bMatches[$match['participant2_id']]++;
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
}
