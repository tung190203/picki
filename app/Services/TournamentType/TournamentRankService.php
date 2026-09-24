<?php

namespace App\Services\TournamentType;

use App\Models\Matches;
use App\Models\PoolAdvancementRule;
use App\Models\TournamentType;
use Illuminate\Support\Collection;

/**
 * Service tính rank tổng (overall_rank + rank_label) cho teams trong tournament.
 *
 * Mirror logic với TournamentTypeController::getRank TH2 (Mixed format có vòng bảng + playoff).
 *
 * Trả về data theo cấu trúc:
 *   [
 *     'group_rankings' => [
 *       ['group_id' => int, 'group_name' => string, 'rankings' => [...]],
 *       ...
 *     ],
 *     'overall_rankings' => [
 *       ['team_id' => int, 'team_name' => string, 'team_avatar' => string,
 *        'overall_rank' => int, 'rank_label' => string,
 *        // các fields khác: played, wins, ..., _group_rank, _is_champion
 *       ],
 *       ...
 *     ],
 *   ]
 */
class TournamentRankService
{
    public function __construct(
        private \App\Services\TournamentType\BracketService $bracketService,
        private \App\Services\TournamentType\StandingsService $standingsService,
        private \App\Services\TournamentType\ManualTiebreakerService $manualTiebreakerService,
    ) {}

    /**
     * Tính toàn bộ rankings cho 1 TournamentType.
     * Áp dụng cho cả TH 1 (không có group) và TH 2 (có group).
     *
     * @return array{group_rankings: array, overall_rankings: array}
     */
    public function compute(int $tournamentTypeId): array
    {
        $type = TournamentType::find($tournamentTypeId);
        if (!$type) {
            return ['group_rankings' => [], 'overall_rankings' => []];
        }

        $groups = $type->groups()->get();

        // TH 1: không có group
        if ($groups->isEmpty()) {
            $rankings = $this->buildRankingsNoGroups($type);
            return [
                'group_rankings' => [],
                'overall_rankings' => $rankings,
            ];
        }

        // TH 2: có group
        $rankingRules = $this->extractRankingRules($type);
        $allMatches = Matches::where('tournament_type_id', $type->id)
            ->where('status', 'completed')
            ->get();

        $groupRankings = $this->buildGroupRankings($type, $groups, $rankingRules, $allMatches);
        $overallRankings = $this->buildOverallRankings($type, $groupRankings);

        return [
            'group_rankings' => $groupRankings,
            'overall_rankings' => $overallRankings,
        ];
    }

    /**
     * Lấy nhanh rank + label cho từng team trong tournament (dùng cho LeaderboardController).
     *
     * @return array<int, array{overall_rank: int, rank_label: string, is_champion: bool}>
     *         key = team_id
     */
    public function rankLabelsByTeam(int $tournamentTypeId): array
    {
        $result = $this->compute($tournamentTypeId);
        $out = [];
        $prevRank = 0;
        // overall_rankings đã sorted đúng thứ tự - dùng rank đã tính sẵn
        // (rankPos + 1 chỉ là position, không tính đồng hạng skip)
        foreach ($result['overall_rankings'] as $rankPos => $r) {
            $actualRank = (int) ($r['overall_rank'] ?? ($rankPos + 1));
            $out[(int) $r['team_id']] = [
                'overall_rank' => $actualRank,
                'rank_label' => $r['rank_label'] ?? "Hạng {$actualRank}",
                'is_champion' => !empty($r['_is_champion']) || ($rankPos === 0),
            ];
        }
        return $out;
    }

    /**
     * ✅ Build rankings khi tournament KHÔNG chia bảng (Round Robin / Elimination thuần).
     */
    private function buildRankingsNoGroups(TournamentType $type): array
    {
        $allTeams = $type->tournament->teams()->with('members')->get();
        $rankings = $allTeams->map(function ($team) use ($type) {
            $stats = $this->getTeamStats($team->id, $type->id);
            return array_merge([
                'team_id' => $team->id,
                'team_name' => $team->name ?? 'Unknown',
                'team_avatar' => $team->avatar ?? '',
                '_format' => (int) $type->format,
            ], $stats);
        });

        $isKnockoutFormat = in_array((int) $type->format, [
            TournamentType::FORMAT_ELIMINATION,
            TournamentType::FORMAT_MIXED,
        ], true);

        if ($isKnockoutFormat) {
            $results = $this->buildTournamentResults((int) $type->id);
            $rankIndex = 0;
            $items = $rankings->all();
            foreach ($items as &$item) {
                $rankIndex++;
                $item['overall_rank'] = $this->computeOverallRank($rankIndex, $item, $results) ?? $rankIndex;
                $item['rank_label'] = $this->getOverallRankLabel(
                    (int) $item['overall_rank'],
                    (int) $type->format,
                    $this->hasThirdPlace($type->id),
                    (int) $type->id,
                    true
                );
                $item['_rank'] = $item['overall_rank'];
                $item['_is_champion'] = ((int) $item['overall_rank']) === 1;
            }
            unset($item);
            $rankings = collect($items);
        } else {
            $rankIndex = 0;
            $rankings = $rankings->map(function ($item) use (&$rankIndex) {
                $rankIndex++;
                $item['overall_rank'] = $rankIndex;
                $item['_rank'] = $rankIndex;
                $item['rank_label'] = "Hạng {$rankIndex}";
                $item['_is_champion'] = $rankIndex === 1;
                return $item;
            });
        }

        // TH1: dùng `rank` thay vì `overall_rank` (backward compat)
        return $rankings->map(function ($item) {
            unset($item['_format']);
            $item['rank'] = $item['_rank'] ?? $item['overall_rank'];
            unset($item['_rank']);
            return $item;
        })->values()->all();
    }

    /**
     * ✅ Build group_rankings cho mỗi group (Mixed format).
     */
    private function buildGroupRankings(TournamentType $type, $groups, array $rankingRules, $allMatches): array
    {
        $realAdvancementByGroupId = PoolAdvancementRule::where('tournament_type_id', $type->id)
            ->whereIn('group_id', $groups->pluck('id'))
            ->real()
            ->get()
            ->groupBy('group_id');

        return $groups->map(function ($group) use ($type, $rankingRules, $allMatches, $realAdvancementByGroupId) {
            $teamsInGroup = $group->teams()->with('members')->get();
            $groupRules = $realAdvancementByGroupId->get($group->id, collect());
            $numAdvancingForGroup = $groupRules->isNotEmpty()
                ? (int) $groupRules->max('rank')
                : 2;

            if ($teamsInGroup->isEmpty()) {
                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'need_draw_lots' => false,
                    'advanced_team_ids' => [],
                    'rankings' => [],
                ];
            }

            $rankings = $teamsInGroup->map(function ($team) use ($type, $group) {
                $stats = $this->getTeamStatsInGroup($team->id, $type->id, $group->id);
                return array_merge([
                    'team_id' => $team->id,
                    'team_name' => $team->name ?? 'Unknown',
                    'team_avatar' => $team->avatar ?? '',
                ], $stats);
            });

            $groupMatches = $allMatches->where('group_id', $group->id)->values();

            $rankings = $rankings->sort(function ($a, $b) use ($rankingRules, $groupMatches) {
                if (($a['played'] ?? 0) == 0 && ($b['played'] ?? 0) > 0) return 1;
                if (($b['played'] ?? 0) == 0 && ($a['played'] ?? 0) > 0) return -1;

                foreach ($rankingRules as $ruleId) {
                    switch ($ruleId) {
                        case TournamentType::RANKING_WIN_DRAW_LOSE_POINTS:
                            if ($a['points'] !== $b['points']) return $b['points'] <=> $a['points'];
                            break;
                        case TournamentType::RANKING_WIN_RATE:
                            if (($a['win_rate'] ?? 0) !== ($b['win_rate'] ?? 0)) {
                                return ($b['win_rate'] ?? 0) <=> ($a['win_rate'] ?? 0);
                            }
                            break;
                        case TournamentType::RANKING_SETS_WON:
                            if (($a['sets_diff'] ?? 0) !== ($b['sets_diff'] ?? 0)) {
                                return ($b['sets_diff'] ?? 0) <=> ($a['sets_diff'] ?? 0);
                            }
                            break;
                        case TournamentType::RANKING_POINTS_WON:
                            if ($a['point_diff'] !== $b['point_diff']) {
                                return $b['point_diff'] <=> $a['point_diff'];
                            }
                            break;
                        case TournamentType::RANKING_HEAD_TO_HEAD:
                            $h2h = $this->getHeadToHeadResultForRank($a['team_id'], $b['team_id'], $groupMatches);
                            if ($h2h !== 0) return $h2h;
                            break;
                        case TournamentType::RANKING_RANDOM_DRAW:
                            return $a['team_id'] <=> $b['team_id'];
                        case TournamentType::RANKING_GOALS_SCORED:
                            if (($a['points_for'] ?? 0) !== ($b['points_for'] ?? 0)) {
                                return ($b['points_for'] ?? 0) <=> ($a['points_for'] ?? 0);
                            }
                            break;
                    }
                }
                if ($a['point_diff'] !== $b['point_diff']) {
                    return $b['point_diff'] <=> $a['point_diff'];
                }
                return $a['team_id'] <=> $b['team_id'];
            })->values();

            $rankings = $rankings->map(function ($item, $index) {
                $item['rank'] = $index + 1;
                return $item;
            });

            $totalMatchesInGroup = Matches::where('tournament_type_id', $type->id)
                ->where('group_id', $group->id)->count();
            $completedMatchesInGroup = $allMatches->where('group_id', $group->id)->count();
            $isGroupFinished = $totalMatchesInGroup > 0 && $totalMatchesInGroup === $completedMatchesInGroup;

            $tiedTeamIds = [];
            if ($isGroupFinished) {
                $clusters = $this->manualTiebreakerService->findTiedClusters($rankings, $rankingRules);
                foreach ($clusters as $cluster) {
                    foreach ($cluster['team_ids'] as $tid) {
                        $tiedTeamIds[$tid] = true;
                    }
                }
            }
            $rankings = $rankings->map(function ($item) use ($tiedTeamIds) {
                $item['pending_tie'] = isset($tiedTeamIds[(int) $item['team_id']]);
                return $item;
            });

            if ($isGroupFinished) {
                $rankings = $this->manualTiebreakerService->applyManualToGroup(
                    $rankings,
                    (int) $type->id,
                    (int) $group->id,
                    $rankingRules
                );
                $rankings = $rankings->values()->map(function ($item, $index) use ($tiedTeamIds) {
                    $item['rank'] = $index + 1;
                    $item['pending_tie'] = isset($tiedTeamIds[(int) $item['team_id']]);
                    return $item;
                });
            }

            if (! $isGroupFinished) {
                return [
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'need_draw_lots' => null,
                    'advanced_team_ids' => null,
                    'rankings' => $rankings,
                ];
            }

            return [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'need_draw_lots' => $this->computeNeedDrawLots($rankings, $numAdvancingForGroup, $rankingRules, (int) $type->id, (int) $group->id),
                'advanced_team_ids' => $this->computeAdvancedTeamIds($rankings, $numAdvancingForGroup),
                'rankings' => $rankings,
            ];
        })->all();
    }

    /**
     * ✅ Tính overall_rank + rank_label cho tất cả teams dựa trên group_rankings và bracket results.
     */
    private function buildOverallRankings(TournamentType $type, array $groupRankings): array
    {
        $hasThirdPlace = Matches::where('tournament_type_id', $type->id)
            ->where('is_third_place', true)
            ->where('status', 'completed')
            ->exists();

        $overallRankings = $type->tournament->teams()
            ->with('members')
            ->get()
            ->map(function ($team) use ($type, $groupRankings) {
                $stats = $this->getTeamStats($team->id, $type->id);
                $groupRank = null;
                foreach ($groupRankings as $group) {
                    foreach ($group['rankings'] as $ranking) {
                        if ((int) $ranking['team_id'] === (int) $team->id) {
                            $groupRank = (int) $ranking['rank'];
                            break 2;
                        }
                    }
                }
                return array_merge([
                    'team_id' => $team->id,
                    'team_name' => $team->name ?? 'Unknown',
                    'team_avatar' => $team->avatar ?? '',
                    '_format' => (int) $type->format,
                    '_group_rank' => $groupRank,
                ], $stats);
            });

        $isKnockoutFormat = in_array((int) $type->format, [
            TournamentType::FORMAT_ELIMINATION,
            TournamentType::FORMAT_MIXED,
        ], true);

        if ($isKnockoutFormat) {
            $results = $this->buildTournamentResults((int) $type->id);

            // Sort: bracket teams trước (theo kết quả tournament), non-bracket sau (theo group_rank)
            $overallRankings = $overallRankings->sort(function ($a, $b) use ($results) {
                $inA = $this->isTeamInBracketProgression($a, $results);
                $inB = $this->isTeamInBracketProgression($b, $results);

                if ($inA && $inB) {
                    $rankA = $this->computeOverallRank(0, $a, $results);
                    $rankB = $this->computeOverallRank(0, $b, $results);
                    if ($rankA !== $rankB) return $rankA <=> $rankB;
                    if (($a['points'] ?? 0) !== ($b['points'] ?? 0)) {
                        return ($b['points'] ?? 0) <=> ($a['points'] ?? 0);
                    }
                    return $a['team_id'] <=> $b['team_id'];
                }
                if ($inA) return -1;
                if ($inB) return 1;

                $grA = $a['_group_rank'] ?? 999;
                $grB = $b['_group_rank'] ?? 999;
                if ($grA !== $grB) return $grA <=> $grB;
                return $a['team_id'] <=> $b['team_id'];
            })->values();

            // Gán overall_rank: bracket teams dùng computeOverallRank, non-bracket gán sau
            $maxBracketRank = 0;
            $bracketRanks = []; // đếm đồng hạng: nếu có N teams cùng rank R thì next phải là R + N (Olympic convention)
            $nonBracketTeams = [];
            $rankedItems = [];

            foreach ($overallRankings as &$item) {
                $rank = $this->computeOverallRank(0, $item, $results, $item['_group_rank'] ?? null);
                if ($rank !== null) {
                    $item['overall_rank'] = $rank;
                    $maxBracketRank = max($maxBracketRank, $rank);
                    $bracketRanks[] = $rank;
                    $rankedItems[] = $item;
                } else {
                    $nonBracketTeams[] = [
                        'team_id' => (int) $item['team_id'],
                        'group_rank' => $item['_group_rank'] ?? null,
                        'points' => $item['points'] ?? 0,
                        'point_diff' => $item['point_diff'] ?? 0,
                        '_item' => $item,
                    ];
                }
            }
            unset($item);

            // ✅ Olympic convention: nếu 2 teams đồng hạng 3, next rank = 5 (skip 4)
            // Nếu chỉ 1 team ở maxBracketRank, next = maxBracketRank + 1
            $tieCount = count(array_filter($bracketRanks, fn($r) => $r === $maxBracketRank));
            $startRank = $maxBracketRank + $tieCount;
            $nonBracketAssignments = $this->assignRanksForNonBracketTeams($nonBracketTeams, $startRank);

            foreach ($nonBracketTeams as $nb) {
                $rank = $nonBracketAssignments[(int) $nb['team_id']] ?? 999;
                // Build final item directly (not via _item copy)
                $rankedItems[] = array_merge($nb['_item'], [
                    'overall_rank' => $rank,
                    'rank_label' => "Hạng {$rank}",
                    '_is_champion' => false,
                ]);
            }

            foreach ($rankedItems as &$item) {
                if (!isset($item['rank_label'])) {
                    $item['rank_label'] = $this->getOverallRankLabel(
                        (int) $item['overall_rank'],
                        (int) $type->format,
                        $hasThirdPlace,
                        (int) $type->id,
                        true
                    );
                }
                if (!isset($item['_is_champion'])) {
                    $item['_is_champion'] = ((int) $item['overall_rank']) === 1;
                }
            }
            unset($item);

            $overallRankings = collect($rankedItems);
        } else {
            // Round Robin: rank = index thứ tự sau sort
            $rankIndex = 0;
            $overallRankings = $overallRankings->map(function ($item) use (&$rankIndex) {
                $rankIndex++;
                $item['overall_rank'] = $rankIndex;
                $item['rank_label'] = "Hạng {$rankIndex}";
                $item['_is_champion'] = $rankIndex === 1;
                return $item;
            });
        }

        return $overallRankings->map(function ($item) {
            unset($item['_format'], $item['_group_rank']);
            return $item;
        })->values()->all();
    }

    // =========== Helper methods (mirror logic từ TournamentTypeController) ===========

    private function extractRankingRules(TournamentType $type): array
    {
        $config = $type->format_specific_config ?? [];
        if (is_array($config) && isset($config[0])) {
            $config = $config[0];
        }

        $rules = collect($config['ranking'] ?? [1, 4, 5])
            ->map(fn($id) => (int) $id)
            ->toArray();

        if (!in_array(TournamentType::RANKING_POINTS_WON, $rules)) {
            $rules[] = TournamentType::RANKING_POINTS_WON;
        }
        if (!in_array(TournamentType::RANKING_HEAD_TO_HEAD, $rules)) {
            $rules[] = TournamentType::RANKING_HEAD_TO_HEAD;
        }

        return $rules;
    }

    private function hasThirdPlace(int $tournamentTypeId): bool
    {
        return Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('is_third_place', true)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Xác định kết quả chung cuộc của tournament (champion, runner-up, 3rd, 4th, ...).
     * Mirror logic với getBracket endpoint.
     */
    private function buildTournamentResults(int $tournamentTypeId): array
    {
        $empty = [
            'has_third_place' => false,
            'final_round' => 0,
            'champion_team_id' => null,
            'runner_up_team_id' => null,
            'third_place_team_id' => null,
            'fourth_place_team_id' => null,
            'losers_by_round' => [],
        ];

        $type = TournamentType::find($tournamentTypeId);
        if (!$type || ! $this->isKnockoutFormat($tournamentTypeId)) {
            return $empty;
        }

        $allMatches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->with('results')->get();

        $hasPoolStage = $allMatches->where('round', 1)->whereNotNull('group_id')->isNotEmpty();
        $knockoutStartRound = $hasPoolStage ? 2 : 1;

        $knockoutMatches = $allMatches->where('round', '>=', $knockoutStartRound);
        if ($knockoutMatches->isEmpty()) return $empty;

        $finalRound = (int) $knockoutMatches->where('is_third_place', '!=', true)->max('round');
        if ($finalRound === 0) return $empty;

        $finalMatch = $knockoutMatches->first(function ($m) use ($finalRound) {
            return (int) $m->round === $finalRound && !($m->is_third_place ?? false);
        });

        $thirdPlaceMatch = $knockoutMatches->first(function ($m) {
            return ($m->is_third_place ?? false) === true;
        });
        $hasThirdPlace = $thirdPlaceMatch !== null && $thirdPlaceMatch->status === 'completed';

        $champion = null; $runnerUp = null;
        if ($finalMatch && $finalMatch->status === 'completed') {
            $winnerId = $this->resolveMatchWinnerTeamId($finalMatch);
            if ($winnerId !== null) {
                $champion = $winnerId;
                $runnerUp = ((int) $finalMatch->home_team_id === $winnerId)
                    ? (int) $finalMatch->away_team_id
                    : (int) $finalMatch->home_team_id;
            }
        }

        $third = null; $fourth = null;
        if ($thirdPlaceMatch && $thirdPlaceMatch->status === 'completed') {
            $winnerId = $this->resolveMatchWinnerTeamId($thirdPlaceMatch);
            if ($winnerId !== null) {
                $third = $winnerId;
                $fourth = ((int) $thirdPlaceMatch->home_team_id === $winnerId)
                    ? (int) $thirdPlaceMatch->away_team_id
                    : (int) $thirdPlaceMatch->home_team_id;
            }
        }

        $losersByRound = [];
        foreach ($knockoutMatches as $m) {
            if ($m->status !== 'completed') continue;
            if ($m->is_third_place ?? false) continue;
            if ((int) $m->round === $finalRound) continue;

            $winnerId = $this->resolveMatchWinnerTeamId($m);
            if ($winnerId === null) continue;

            $loserId = ((int) $m->home_team_id === $winnerId)
                ? (int) $m->away_team_id
                : (int) $m->home_team_id;
            if ($loserId === 0) continue;

            $losersByRound[(int) $m->round][] = $loserId;
        }

        return [
            'has_third_place' => $hasThirdPlace,
            'final_round' => $finalRound,
            'champion_team_id' => $champion,
            'runner_up_team_id' => $runnerUp,
            'third_place_team_id' => $third,
            'fourth_place_team_id' => $fourth,
            'losers_by_round' => $losersByRound,
        ];
    }

    private function isKnockoutFormat(int $tournamentTypeId): bool
    {
        $type = TournamentType::find($tournamentTypeId);
        if (!$type) return false;
        return in_array((int) $type->format, [
            TournamentType::FORMAT_ELIMINATION,
            TournamentType::FORMAT_MIXED,
        ], true);
    }

    private function resolveMatchWinnerTeamId(Matches $match): ?int
    {
        $details = $this->bracketService->calculateLegDetails($match);
        $winnerId = $details['winner_team_id'] ?? null;
        if ($winnerId === null && $match->winner_id !== null) {
            $winnerId = (int) $match->winner_id;
        }
        return $winnerId !== null ? (int) $winnerId : null;
    }

    private function isTeamInBracketProgression(array $item, array $results): bool
    {
        $teamId = (int) ($item['team_id'] ?? 0);
        if ($teamId === 0) return false;
        if ($results['champion_team_id'] === $teamId) return true;
        if ($results['runner_up_team_id'] === $teamId) return true;
        if ($results['third_place_team_id'] === $teamId) return true;
        if ($results['fourth_place_team_id'] === $teamId) return true;
        foreach (($results['losers_by_round'] ?? []) as $losers) {
            if (in_array($teamId, $losers, true)) return true;
        }
        return false;
    }

    private function computeOverallRank(int $rankIndex, array $item, array $results, ?int $groupRank = null): ?int
    {
        $teamId = (int) ($item['team_id'] ?? 0);
        $format = (int) ($item['_format'] ?? 0);

        if ($format === TournamentType::FORMAT_ROUND_ROBIN) {
            return $rankIndex;
        }

        if ($results['champion_team_id'] === $teamId) return 1;
        if ($results['runner_up_team_id'] === $teamId) return 2;

        if ($results['has_third_place']) {
            if ($results['third_place_team_id'] === $teamId) return 3;
            if ($results['fourth_place_team_id'] === $teamId) return 4;
        } else {
            $semiLosers = $results['losers_by_round'][$results['final_round'] - 1] ?? [];
            if (in_array($teamId, $semiLosers, true)) return 3;
        }

        $losersByRound = $results['losers_by_round'] ?? [];
        $knockoutRoundsDesc = array_keys($losersByRound);
        rsort($knockoutRoundsDesc);

        $currentRank = 5;
        foreach ($knockoutRoundsDesc as $round) {
            if (! $results['has_third_place'] && $round === $results['final_round'] - 1) {
                continue;
            }
            if ($round === $results['final_round']) continue;

            $losers = $losersByRound[$round] ?? [];
            if (in_array($teamId, $losers, true)) {
                return $currentRank;
            }
            $currentRank += count($losers);
        }

        return null;
    }

    private function assignRanksForNonBracketTeams(array $nonBracketTeams, int $startRank): array
    {
        $byGroupRank = [];
        foreach ($nonBracketTeams as $item) {
            $gr = (int) ($item['group_rank'] ?? 999);
            $byGroupRank[$gr][] = $item;
        }

        ksort($byGroupRank);
        $rank = $startRank;
        $result = [];
        foreach ($byGroupRank as $gr => $items) {
            usort($items, function ($a, $b) {
                $pa = (int) ($a['points'] ?? 0);
                $pb = (int) ($b['points'] ?? 0);
                if ($pa !== $pb) return $pb <=> $pa;
                $da = (int) ($a['point_diff'] ?? 0);
                $db = (int) ($b['point_diff'] ?? 0);
                if ($da !== $db) return $db <=> $da;
                return ($a['team_id'] ?? 0) <=> ($b['team_id'] ?? 0);
            });

            foreach ($items as $item) {
                $result[(int) $item['team_id']] = $rank;
            }
            $rank += count($items);
        }
        return $result;
    }

    private function getOverallRankLabel(
        int $overallRank,
        int $format,
        bool $hasThirdPlace,
        int $tournamentTypeId,
        bool $isInBracket = true
    ): string {
        if ($format === TournamentType::FORMAT_ROUND_ROBIN) {
            return "Hạng {$overallRank}";
        }

        if (! $isInBracket) {
            return "Hạng {$overallRank}";
        }

        if ($overallRank === 1) return 'Vô địch';
        if ($overallRank === 2) return 'Á quân';

        if ($hasThirdPlace) {
            if ($overallRank === 3) return 'Hạng 3';
            if ($overallRank === 4) return 'Hạng 4';
        } else {
            if ($overallRank === 3) return 'Đồng hạng 3';
        }

        $results = $this->buildTournamentResults($tournamentTypeId);
        $losersByRound = $results['losers_by_round'] ?? [];
        $finalRound = $results['final_round'] ?? 0;

        $knockoutRoundsDesc = array_keys($losersByRound);
        rsort($knockoutRoundsDesc);

        $currentRank = 5;
        foreach ($knockoutRoundsDesc as $round) {
            if ($round === $results['final_round']) continue;
            if (! $hasThirdPlace && $round === $results['final_round'] - 1) continue;

            $losers = $losersByRound[$round] ?? [];
            if ($losers === []) continue;

            if ($overallRank >= $currentRank && $overallRank < $currentRank + count($losers)) {
                return $this->roundLabel($finalRound, $round);
            }
            $currentRank += count($losers);
        }

        return "Hạng {$overallRank}";
    }

    private function roundLabel(int $finalRound, int $round): string
    {
        $distance = $finalRound - $round;
        return match ($distance) {
            0 => 'Chung kết',
            1 => 'Bán kết',
            2 => 'Tứ kết',
            3 => 'Vòng 1/8',
            4 => 'Vòng 1/16',
            default => "Vòng {$round}",
        };
    }

    // =========== Helper methods - computeStats ===========

    private function getTeamStatsInGroup($teamId, $tournamentTypeId, $groupId)
    {
        $matches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('group_id', $groupId)
            ->where('status', 'completed')
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            })
            ->with('results')
            ->get();

        return $this->calculateStatsFromMatches($matches, $teamId);
    }

    private function getTeamStats($teamId, $tournamentTypeId)
    {
        $matches = Matches::where('tournament_type_id', $tournamentTypeId)
            ->where('status', 'completed')
            ->where(function ($query) use ($teamId) {
                $query->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            })
            ->with('results')
            ->get();

        return $this->calculateStatsFromMatches($matches, $teamId);
    }

    private function calculateStatsFromMatches($matches, $teamId)
    {
        if ($matches->isEmpty()) {
            return [
                'team_id' => $teamId,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
                'points' => 0,
                'points_for' => 0,
                'points_against' => 0,
                'point_diff' => 0,
                'sets_won' => 0,
                'sets_lost' => 0,
                'sets_diff' => 0,
                'win_rate' => 0,
            ];
        }

        $totalPoints = 0;
        $wins = 0;
        $draws = 0;
        $losses = 0;
        $pWon = 0;
        $pLost = 0;
        $setsWon = 0;
        $setsLost = 0;

        foreach ($matches as $leg) {
            $homeSetWins = 0;
            $awaySetWins = 0;
            $sets = $leg->results->groupBy('set_number');
            foreach ($sets as $setGroup) {
                $home = $setGroup->firstWhere('team_id', $leg->home_team_id);
                $away = $setGroup->firstWhere('team_id', $leg->away_team_id);
                if ($home && $away) {
                    if ((int) $home->score > (int) $away->score) $homeSetWins++;
                    elseif ((int) $away->score > (int) $home->score) $awaySetWins++;
                }
            }
            $setsWon += $leg->home_team_id == $teamId ? $homeSetWins : $awaySetWins;
            $setsLost += $leg->home_team_id == $teamId ? $awaySetWins : $homeSetWins;

            $totalPoints += $leg->points;
            if ($leg->winner_id === null) {
                $draws++;
            } elseif ((int) $leg->winner_id === (int) $teamId) {
                $wins++;
                $totalPoints += 2;
            } else {
                // ponytail: thua = 0 điểm xếp hạng (giống GroupStandingRanker)
                $losses++;
            }
        }

        $played = $wins + $draws + $losses;
        $pWon = $matches->sum(function ($m) use ($teamId) {
            return $m->results->where('team_id', $teamId)->sum('score');
        });
        $pLost = $matches->sum(function ($m) use ($teamId) {
            $oppId = $m->home_team_id == $teamId ? $m->away_team_id : $m->home_team_id;
            return $m->results->where('team_id', $oppId)->sum('score');
        });

        return [
            'team_id' => $teamId,
            'played' => $played,
            'wins' => $wins,
            'draws' => $draws,
            'losses' => $losses,
            'points' => $totalPoints,
            'points_for' => (int) $pWon,
            'points_against' => (int) $pLost,
            'point_diff' => (int) ($pWon - $pLost),
            'sets_won' => $setsWon,
            'sets_lost' => $setsLost,
            'sets_diff' => $setsWon - $setsLost,
            'win_rate' => $played > 0 ? round(($wins / $played) * 100, 2) : 0,
        ];
    }

    private function computeNeedDrawLots($rankings, $numAdvancing, $rankingRules, $typeId, $groupId): bool
    {
        if ($numAdvancing >= $rankings->count()) return false;

        $tiedClusters = $this->manualTiebreakerService->findTiedClusters($rankings, $rankingRules);
        foreach ($tiedClusters as $cluster) {
            $crossBoundary = false;
            foreach ($cluster['team_ids'] as $tid) {
                foreach ($rankings as $r) {
                    if ((int) $r['team_id'] === (int) $tid && (int) $r['rank'] <= $numAdvancing + 1) {
                        $crossBoundary = true;
                        break 2;
                    }
                }
            }
            if ($crossBoundary) return true;
        }
        return false;
    }

    private function computeAdvancedTeamIds(Collection $rankings, int $numAdvancing): array
    {
        return $rankings->take($numAdvancing)->pluck('team_id')->map(fn($id) => (int) $id)->all();
    }

    private function getHeadToHeadResultForRank($teamA, $teamB, $matches): int
    {
        $h2hMatches = $matches->filter(function ($m) use ($teamA, $teamB) {
            return ($m->home_team_id == $teamA && $m->away_team_id == $teamB)
                || ($m->home_team_id == $teamB && $m->away_team_id == $teamA);
        });

        if ($h2hMatches->isEmpty()) return 0;

        $aWins = 0; $bWins = 0;
        foreach ($h2hMatches as $m) {
            if ($m->winner_id == $teamA) $aWins++;
            elseif ($m->winner_id == $teamB) $bWins++;
        }

        if ($aWins > $bWins) return -1;
        if ($bWins > $aWins) return 1;
        return 0;
    }
}
