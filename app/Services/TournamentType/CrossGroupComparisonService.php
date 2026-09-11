<?php

namespace App\Services\TournamentType;

use App\Models\Group;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\Team;
use App\Models\TournamentType;
use App\Services\TournamentService;
use App\Services\TournamentType\GroupStandingRanker;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service tính toán CROSS-GROUP COMPARISON RANKING (read-only).
 *
 * Mục đích:
 * - Xét các đội Nhì/Ba giữa các bảng khi số đội trong các bảng không đều.
 * - Loại các trận gặp đội xếp cuối bảng khỏi thành tích dùng để so sánh Nhì/Ba.
 * - KHÔNG thay đổi Matches / MatchResult / Group standings — chỉ tính toán layer riêng.
 *
 * Phụ thuộc:
 * - CrossGroupRankingService: kiểm tra applicability runtime (format MIXED, >=2 bảng,
 *   số đội không đều, enabled=true).
 * - TournamentService::calculateGroupStandings: tính BXH thật của từng bảng
 *   (tái sử dụng — không duplicate logic).
 */
class CrossGroupComparisonService
{
    public const CANDIDATE_TYPE_RUNNER_UP = 'runner_up';
    public const CANDIDATE_TYPE_THIRD_PLACE = 'third_place';

    /** Một trận đã hoàn thành có tỷ số đầy đủ → counted cho comparison. */
    protected const STATUS_COMPLETED = 'completed';

    public function __construct(
        private readonly CrossGroupRankingService $crossGroupRankingService
    ) {}

    /**
     * Trả về cấu trúc chuẩn khi rule không áp dụng.
     */
    public function notAppliedResponse(): array
    {
        return [
            'enabled' => false,
            'applied' => false,
            'comparison_rule' => [
                'minimum_group_size' => null,
                'description' => null,
            ],
            'knockout_calculation' => [
                'num_advancing_per_group' => 0,
                'number_of_groups' => 0,
                'total_from_pool_stage' => 0,
                'is_power_of_two' => false,
                'knockout_slots' => 0,
                'additional_slots' => 0,
            ],
            'qualification' => [
                'number_of_groups' => 0,
                'knockout_slots' => 0,
                'additional_slots' => 0,
                'runner_up_candidates' => 0,
                'third_place_candidates' => 0,
            ],
            'candidates' => [],
        ];
    }

    /**
     * Evaluate applicability + build toàn bộ payload cho API 1.
     *
     * @return array{enabled:bool, applied:bool, comparison_rule:array, qualification:array, candidates:array}
     */
    public function buildComparisonPayload(TournamentType $type): array
    {
        $rawConfig = $this->extractCrossGroupRankingConfig($type);
        $evaluation = $this->crossGroupRankingService->evaluate($type, $rawConfig);

        if (!$evaluation['applied']) {
            $payload = $this->notAppliedResponse();
            // Vẫn truyền enabled flag để FE biết có đang bật setting hay không.
            $payload['enabled'] = $evaluation['enabled'];
            return $payload;
        }

        $minimumGroupSize = (int) $evaluation['minimum_group_size'];
        $rankedCandidates = $this->buildAndRankCandidates($type, $minimumGroupSize);

        // Qualification info
        $groupTeamCounts = $evaluation['group_team_counts'];
        $numberOfGroups = count($groupTeamCounts);
        $numAdvancing = $this->getNumAdvancingPerGroup($type);
        $knockoutSlots = $this->getKnockoutSlots($numAdvancing, $numberOfGroups);
        $totalFromPool = $numAdvancing * $numberOfGroups;
        $isPowerOfTwo = $totalFromPool > 0 && (($totalFromPool & ($totalFromPool - 1)) === 0);
        // ✅ Công thức đúng với mọi numAdvancing:
        // - additional = knockoutSlots - max(totalFromPool, numberOfGroups)
        // - max() đảm bảo additionalSlots không âm khi totalFromPool >= knockoutSlots (vd: total=12, slots=16).
        $additionalSlots = max(0, $knockoutSlots - max($totalFromPool, $numberOfGroups));
        $applyTo = $this->extractApplyTo($rawConfig);

        // Gắn qualified flag dựa trên knockoutSlots (= 2^n gần nhất)
        $rankedCandidates = $this->assignQualifiedStatus(
            $rankedCandidates,
            $knockoutSlots,
            $numberOfGroups,
            $totalFromPool,
            $applyTo
        );

        // ✅ Đếm candidate theo status (chỉ tính qualified/not_qualified, bỏ not_applicable).
        // Nếu additionalSlots <= numberOfGroups (chỉ cần Nhì), Ba = not_applicable → không đếm.
        $runnerUpCount = $rankedCandidates
            ->where('candidate_type', self::CANDIDATE_TYPE_RUNNER_UP)
            ->where('status', '!=', 'not_applicable')
            ->count();
        $thirdPlaceCount = $rankedCandidates
            ->where('candidate_type', self::CANDIDATE_TYPE_THIRD_PLACE)
            ->where('status', '!=', 'not_applicable')
            ->count();

        $rankingRules = $this->extractRankingRules($type);

        return [
            'enabled' => $evaluation['enabled'],
            'applied' => true,
            'comparison_rule' => [
                'minimum_group_size' => $minimumGroupSize,
                'description' => $this->buildRuleDescription($groupTeamCounts),
                'ranking_rules' => $rankingRules, // thứ tự ưu tiên thực tế được áp dụng
            ],
            'knockout_calculation' => [
                'num_advancing_per_group' => $numAdvancing,
                'number_of_groups' => $numberOfGroups,
                'total_from_pool_stage' => $totalFromPool,
                'is_power_of_two' => $isPowerOfTwo,
                'knockout_slots' => $knockoutSlots,
                'additional_slots' => $additionalSlots,
            ],
            'qualification' => [
                'number_of_groups' => $numberOfGroups,
                'knockout_slots' => $knockoutSlots,
                'additional_slots' => $additionalSlots,
                'runner_up_candidates' => $runnerUpCount,
                'third_place_candidates' => $thirdPlaceCount,
            ],
            'candidates' => $rankedCandidates
                ->filter(fn($c) => ($c['status'] ?? null) !== 'not_applicable')
                ->map(fn($c) => $this->formatCandidate($c))
                ->values()
                ->all(),
        ];
    }

    /**
     * Public helper: build + rank candidates cho cross-group comparison.
     *
     * ✅ LUÔN chạy — KHÔNG phụ thuộc vào `cross_group_ranking.enabled` hay `applied` flag.
     * Dùng để các service khác (như KnockoutRebuildService) tái sử dụng logic build/rank
     * đã có, đảm bảo kết quả luôn giống với API comparison chính.
     *
     * Luồng giống buildComparisonPayload (khi applied=true) nhưng:
     *   - Bỏ check `applied` (luôn build).
     *   - Bỏ `assignQualifiedStatus` (caller tự quyết định pick bao nhiêu).
     *   - minimumGroupSize fallback về 0 nếu không có.
     *
     * @return Collection<int, array> Ranked candidates (mỗi item có 'rank', 'candidate_type', 'team_id', 'group_id', ...)
     */
    public function buildRankedCandidates(TournamentType $type, ?int $minimumGroupSize = null): Collection
    {
        $effectiveMin = $minimumGroupSize ?? 0;
        return $this->buildAndRankCandidates($type, $effectiveMin);
    }

    /**
     * Private helper: chạy buildCandidates → buildComparisonStats → rankCandidates.
     * Được dùng chung bởi buildComparisonPayload (khi applied) và buildRankedCandidates.
     */
    private function buildAndRankCandidates(TournamentType $type, int $minimumGroupSize): Collection
    {
        $groups = $type->groups()->orderBy('id')->get();
        $rankingRules = $this->extractRankingRules($type);
        $candidates = $this->buildCandidates($groups, $type, $rankingRules);
        $candidates = $this->buildComparisonStats($candidates, $minimumGroupSize, $rankingRules);
        return $this->rankCandidates($candidates, $rankingRules);
    }

    /**
     * Build candidate detail cho một team cụ thể — dùng cho API 2.
     *
     * @return array|null  null nếu team không phải candidate (không ở top 2/3 group)
     */
    public function buildTeamComparison(TournamentType $type, Team $team): ?array
    {
        $rawConfig = $this->extractCrossGroupRankingConfig($type);
        $evaluation = $this->crossGroupRankingService->evaluate($type, $rawConfig);

        if (!$evaluation['applied']) {
            return null;
        }

        $minimumGroupSize = (int) $evaluation['minimum_group_size'];
        $groups = $type->groups()->orderBy('id')->get();
        $rankingRules = $this->extractRankingRules($type);
        $candidates = $this->buildCandidates($groups, $type, $rankingRules);

        $target = $candidates->firstWhere('team_id', $team->id);
        if (!$target) {
            return null;
        }

        $stats = $this->buildCandidateStats($target, $minimumGroupSize, $rankingRules);
        $matchList = $this->buildCandidateMatchList($target, $minimumGroupSize, $rankingRules);

        return [
            'team' => [
                'id' => (int) $team->id,
                'name' => $team->name,
            ],
            'group' => [
                'id' => (int) $target['group']->id,
                'name' => $target['group']->name,
                'team_count' => $target['group_team_count'],
            ],
            'group_position' => $target['group_position'],
            'candidate_type' => $target['candidate_type'],
            'comparison' => [
                'minimum_group_size' => $minimumGroupSize,
                'original_matches' => $stats['original'],
                'counted_matches' => $stats['counted'],
                'excluded_matches' => $stats['excluded'],
                'wins' => $stats['wins'],
                'losses' => $stats['losses'],
                'win_rate' => $stats['win_rate'],
                'points_for' => $stats['points_for'],
                'points_against' => $stats['points_against'],
                'point_diff' => $stats['point_diff'],
                'average_point_difference' => $stats['average_point_difference'],
            ],
            'matches' => $matchList,
        ];
    }

    // ====================================================================
    // INTERNAL HELPERS
    // ====================================================================

    /**
     * Lấy config cross_group_ranking từ tournament type.
     */
    protected function extractCrossGroupRankingConfig(TournamentType $type): array
    {
        $config = $type->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : (is_array($config) ? $config : []);
        return is_array($mainConfig['cross_group_ranking'] ?? null)
            ? $mainConfig['cross_group_ranking']
            : [];
    }

    /**
     * Lấy apply_to list từ config (đã normalize).
     */
    protected function extractApplyTo(array $rawConfig): array
    {
        $normalized = $this->crossGroupRankingService->normalizeConfig($rawConfig);
        return $normalized['apply_to'] ?? [];
    }

    /**
     * Lấy ranking rules từ format_specific_config[0].ranking.
     * Fallback về [1, 4, 5] giống TournamentTypeController::getRank.
     * Luôn thêm POINTS_WON (4) + HEAD_TO_HEAD (5) nếu thiếu (giống getRank).
     */
    protected function extractRankingRules(TournamentType $type): array
    {
        $config = $type->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : (is_array($config) ? $config : []);

        $rules = collect($mainConfig['ranking'] ?? [1, 2, 3, 4, 7])
            ->map(fn($id) => (int) $id)
            ->toArray();

        // ✅ Auto-fallback các rule thiết yếu cho cross-group comparison:
        // - H2H (5): không gây hại (luôn = 0 vì Nhì/Ba chéo bảng chưa từng gặp nhau),
        //   nhưng nếu user tự cấu hình thêm vẫn OK.
        // - GOALS_SCORED (7 = points_for): phân định khi points/win_rate/sets_diff/point_diff
        //   vẫn bằng nhau giữa các Nhì/Ba ở bảng khác nhau.
        if (!in_array(\App\Models\TournamentType::RANKING_HEAD_TO_HEAD, $rules, true)) {
            $rules[] = \App\Models\TournamentType::RANKING_HEAD_TO_HEAD;
        }
        if (!in_array(\App\Models\TournamentType::RANKING_GOALS_SCORED, $rules, true)) {
            $rules[] = \App\Models\TournamentType::RANKING_GOALS_SCORED;
        }

        return $rules;
    }

    /**
     * Lấy số đội đi tiếp / bảng từ format_specific_config[0].pool_stage.num_advancing_teams.
     */
    protected function getNumAdvancingPerGroup(TournamentType $type): int
    {
        $config = $type->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : (is_array($config) ? $config : []);
        $pool = $mainConfig['pool_stage'] ?? [];
        return max(0, (int) ($pool['num_advancing_teams'] ?? 0));
    }

    /**
     * Tính số slot knockout theo quy tắc CEILING power-of-2.
     *
     * QUY TẮC NÀY PHẢI KHỚP với logic ở TournamentTypeController::PHASE 2.5 (khi generate round 2)
     * và với KnockoutRebuildService::computeKnockoutSlotsCeiling.
     * - total = numAdvancing * numberOfGroups
     * - knockout_slots = ceil power-of-2 >= total
     *
     * Ví dụ:
     *   total=1  → 2   (đảm bảo ít nhất 2 đội)
     *   total=2  → 2
     *   total=3  → 4   (KHÔNG làm tròn về 2 như code cũ)
     *   total=4  → 4
     *   total=5  → 8
     *
     * Lưu ý: Kết quả LUÔN được đảm bảo >= numberOfGroups để mỗi bảng có ít nhất 1 Nhất vào round 2.
     *
     * @param int $numAdvancing   Số đội đi tiếp / bảng (pool_stage.num_advancing_teams)
     * @param int $numberOfGroups Số bảng
     */
    protected function getKnockoutSlots(int $numAdvancing, int $numberOfGroups): int
    {
        $total = max(0, $numAdvancing * $numberOfGroups);

        if ($total <= 0) {
            return max(0, $numberOfGroups);
        }

        // Edge case: tổng = 1 đội thì knockout không hợp lệ, ép về 2
        if ($total < 2) {
            return max(2, $numberOfGroups);
        }

        // ✅ Ceiling power-of-2 (KHỚP với KnockoutRebuildService::computeKnockoutSlotsCeiling)
        // → round 2 cần power-of-2 để có bracket hợp lệ.
        $logVal = log($total, 2);
        $ceilingPower = (int) pow(2, (int) ceil($logVal));

        // Đảm bảo >= numberOfGroups (luôn có Nhất mỗi bảng đi tiếp)
        return max((int) $numberOfGroups, (int) $ceilingPower);
    }

    /**
     * Build danh sách candidate từ các group.
     * Mỗi group: lấy top 2 (Nhì) + top 3 (Ba) theo BXH nội bộ đã áp dụng ranking rules + HEAD_TO_HEAD.
     *
     * QUAN TRỌNG:
     * - Source of truth để xác định Nhì/Ba là `format_specific_config[0].ranking`
     *   (đã bao gồm fallback POINTS_WON + HEAD_TO_HEAD).
     * - Dùng `GroupStandingRanker::rank()` thay cho `TournamentService::calculateGroupStandings()`
     *   vì method cũ thiếu HEAD_TO_HEAD → xếp sai khi 2 đội bằng điểm + hiệu số.
     * - `exclude_bottom_team_matches` KHÔNG ảnh hưởng thứ hạng nội bộ —
     *   chỉ ảnh hưởng stats dùng cho cross-group comparison (xử lý ở buildCandidateStats).
     *
     * @param Collection<int,Group> $groups
     * @param TournamentType $type
     * @param array $rankingRules  Ranking rules đã chuẩn hóa
     */
    protected function buildCandidates(Collection $groups, TournamentType $type, array $rankingRules): Collection
    {
        $candidates = collect();

        foreach ($groups as $group) {
            // ✅ Dùng helper có áp dụng HEAD_TO_HEAD + ranking rules đã config
            $standings = GroupStandingRanker::rank($group, $rankingRules);

            $groupTeamCount = $group->teams()->count();

            // position 2 = Nhì, position 3 = Ba
            foreach ([2, 3] as $position) {
                $standing = $standings->get($position - 1);
                if (!$standing) {
                    continue;
                }

                $candidates->push([
                    'team_id' => $standing['team_id'],
                    'team_name' => $standing['team_name'] ?? 'Unknown',
                    'group' => $group,
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'group_team_count' => $groupTeamCount,
                    'group_position' => $position,
                    'candidate_type' => $position === 2
                        ? self::CANDIDATE_TYPE_RUNNER_UP
                        : self::CANDIDATE_TYPE_THIRD_PLACE,
                ]);
            }
        }

        return $candidates;
    }

    /**
     * Build counted stats cho từng candidate.
     */
    protected function buildComparisonStats(Collection $candidates, int $minimumGroupSize, array $rankingRules = []): Collection
    {
        return $candidates->map(function (array $candidate) use ($minimumGroupSize, $rankingRules) {
            $stats = $this->buildCandidateStats($candidate, $minimumGroupSize, $rankingRules);
            // attach stats back to candidate
            return array_merge($candidate, $stats);
        });
    }

    /**
     * Tính counted stats cho 1 candidate dựa trên minimumGroupSize.
     *
     * @return array{original:int, counted:int, excluded:int, wins:int, losses:int, draws:int,
     *               win_rate:float, points_for:int, points_against:int, point_diff:int,
     *               average_point_difference:float, excluded_opponent_ids:array<int>}
     */
    protected function buildCandidateStats(array $candidate, int $minimumGroupSize, array $rankingRules = []): array
    {
        /** @var Group $group */
        $group = $candidate['group'];
        $teamId = $candidate['team_id'];
        $groupTeamCount = $candidate['group_team_count'];

        // Identify excluded opponents: bottom N teams theo final group standings
        // Dùng GroupStandingRanker (có H2H) để ĐỒNG BỘ với buildCandidates —
        // nếu dùng method cũ calculateGroupStandings (thiếu H2H), team bị xếp
        // "rank cuối" sẽ khác → loại trận sai.
        $excludedOpponentIds = $this->getExcludedOpponentIds($group, $groupTeamCount, $minimumGroupSize, $rankingRules);

        // Lấy toàn bộ match completed của team trong group này
        $matches = Matches::where('group_id', $group->id)
            ->where('status', self::STATUS_COMPLETED)
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            })
            ->with('results')
            ->get();

        $original = $matches->count();
        $countedMatches = $matches->reject(function (Matches $m) use ($teamId, $excludedOpponentIds) {
            $opponentId = $m->home_team_id === $teamId ? $m->away_team_id : $m->home_team_id;
            return in_array($opponentId, $excludedOpponentIds, true);
        });

        $counted = $countedMatches->count();
        $excluded = $original - $counted;

        // Tính stats chỉ trên counted matches
        $wins = 0;
        $losses = 0;
        $draws = 0;
        $pointsFor = 0;
        $pointsAgainst = 0;
        $setsWon = 0;
        $setsLost = 0;
        $points = 0; // điểm xếp hạng (Thắng=3, Hòa=1, Thua=0)

        foreach ($countedMatches as $match) {
            $isHome = $match->home_team_id === $teamId;
            $homeScore = (int) $match->results->where('team_id', $match->home_team_id)->sum('score');
            $awayScore = (int) $match->results->where('team_id', $match->away_team_id)->sum('score');

            // Số hiệp (set) thắng của từng đội
            $homeSetsWon = (int) $match->results->where('team_id', $match->home_team_id)->where('won_match', true)->count();
            $awaySetsWon = (int) $match->results->where('team_id', $match->away_team_id)->where('won_match', true)->count();

            if ($isHome) {
                $pointsFor += $homeScore;
                $pointsAgainst += $awayScore;
                $setsWon += $homeSetsWon;
                $setsLost += $awaySetsWon;
                if ($match->winner_id === $teamId) {
                    $wins++;
                    $points += 3;
                } elseif ($match->winner_id && $match->winner_id !== $teamId) {
                    $losses++;
                } else {
                    $draws++;
                    $points += 1;
                }
            } else {
                $pointsFor += $awayScore;
                $pointsAgainst += $homeScore;
                $setsWon += $awaySetsWon;
                $setsLost += $homeSetsWon;
                if ($match->winner_id === $teamId) {
                    $wins++;
                    $points += 3;
                } elseif ($match->winner_id && $match->winner_id !== $teamId) {
                    $losses++;
                } else {
                    $draws++;
                    $points += 1;
                }
            }
        }

        $pointDiff = $pointsFor - $pointsAgainst;
        $winRate = $counted > 0 ? round(($wins / $counted) * 100, 2) : 0.0;
        $avgPointDiff = $counted > 0 ? round($pointDiff / $counted, 2) : 0.0;

        return [
            'original' => $original,
            'counted' => $counted,
            'excluded' => $excluded,
            'wins' => $wins,
            'losses' => $losses,
            'draws' => $draws,
            'points' => $points,
            'sets_won' => $setsWon,
            'sets_lost' => $setsLost,
            'sets_diff' => $setsWon - $setsLost,
            'win_rate' => $winRate,
            'points_for' => $pointsFor,
            'points_against' => $pointsAgainst,
            'point_diff' => $pointDiff,
            'average_point_difference' => $avgPointDiff,
            'excluded_opponent_ids' => $excludedOpponentIds,
        ];
    }

    /**
     * Xác định các đội bị loại khỏi comparison ở group này.
     *
     * QUY TẮC SO SÁNH CÔNG BẰNG:
     * - Với bảng đủ (k > minimumGroupSize): loại trận gặp **đội cuối bảng (Ba)** để so sánh Nhì công bằng.
     *   Lý do: Ba có thể thua nhiều trận hơn, tạo handicap không công bằng cho Nhì.
     *   → Chỉ tính trận Nhì gặp Nhất.
     *
     * - Với bảng thiếu (k <= minimumGroupSize): không loại gì, tính đầy đủ.
     *
     * Ví dụ:
     * - Bảng 3 đội (k=3, m=2): loại rank 3 (Ba) → Nhì được so sánh qua trận gặp Nhất.
     * - Bảng 2 đội (k=2, m=2): không loại gì → Nhì chỉ gặp Nhất, tính đầy đủ.
     *
     * ⚠️ QUAN TRỌNG: Dùng `GroupStandingRanker::rank()` (CÓ HEAD_TO_HEAD + ranking rules)
     * thay cho `TournamentService::calculateGroupStandings()` (THIẾU HEAD_TO_HEAD).
     * Lý do: buildCandidates cũng dùng GroupStandingRanker để xác định Nhì/Ba.
     * Nếu 2 method xếp hạng khác nhau (do H2H phân biệt), team bị xếp "rank cuối" ở
     * đây sẽ KHÁC với team "Ba" thực sự → loại trận sai.
     *
     * @param int $groupTeamCount     Số đội trong bảng
     * @param int $minimumGroupSize   Minimum group size để comparison được apply
     * @param array $rankingRules     Ranking rules đã chuẩn hóa (optional, fallback mặc định)
     * @return int[]                  Danh sách opponent_id bị loại
     */
    protected function getExcludedOpponentIds(Group $group, int $groupTeamCount, int $minimumGroupSize, array $rankingRules = []): array
    {
        if ($groupTeamCount <= $minimumGroupSize) {
            return [];
        }

        // ✅ Dùng GroupStandingRanker (đồng bộ với buildCandidates) để khi H2H
        // phân biệt thứ hạng, đội "rank cuối" được tính đúng theo đúng rule.
        $standings = GroupStandingRanker::rank($group, $rankingRules);

        $excludedCount = $groupTeamCount - $minimumGroupSize;
        $excludedIds = [];

        // ✅ Loại từ CUỐI BXH (Ba) lên → so sánh Nhì qua trận gặp Nhất.
        for ($i = 0; $i < $excludedCount; $i++) {
            $standing = $standings->get(($groupTeamCount - 1) - $i);
            if ($standing && isset($standing['team_id'])) {
                $excludedIds[] = (int) $standing['team_id'];
            }
        }

        return $excludedIds;
    }

    /**
     * Rank candidates theo ranking rules đã được config trong format_specific_config[0].ranking.
     *
     * Hỗ trợ các rule constants (giống TournamentTypeController::getRank):
     *  - RANKING_WIN_DRAW_LOSE_POINTS (1): điểm xếp hạng (Thắng=3, Hòa=1, Thua=0)
     *  - RANKING_WIN_RATE (2): % thắng
     *  - RANKING_SETS_WON (3): số hiệp thắng
     *  - RANKING_POINTS_WON (4): hiệu số điểm
     *  - RANKING_HEAD_TO_HEAD (5): đối đầu trực tiếp (vô hiệu với cross-group vì Nhì/Ba ở bảng khác nhau chưa từng gặp nhau)
     *  - RANKING_RANDOM_DRAW (6): stable theo team_id
     *  - RANKING_GOALS_SCORED (7): tổng số bàn/điểm ghi được (points_for) — tie-breaker quan trọng cho cross-group.
     *
     * Default cho cross-group: [1, 2, 3, 4, 7] (points → win_rate → sets_diff → point_diff → goals_scored)
     * + auto-append RANKING_HEAD_TO_HEAD (5).
     *
     * pending_draw = true khi CÙNG candidate_type + cùng tất cả ranking keys đang xét.
     */
    protected function rankCandidates(Collection $candidates, array $rankingRules): Collection
    {
        // Tính head-to-head trước (nếu cần)
        $h2hMatrix = in_array(\App\Models\TournamentType::RANKING_HEAD_TO_HEAD, $rankingRules, true)
            ? $this->buildHeadToHeadMatrix($candidates)
            : [];

        $sorted = $candidates->sort(function ($a, $b) use ($rankingRules, $h2hMatrix) {
            // Ưu tiên runner_up trước third_place khi bằng nhau
            if ($a['candidate_type'] !== $b['candidate_type']) {
                return $a['candidate_type'] === self::CANDIDATE_TYPE_RUNNER_UP ? -1 : 1;
            }

            foreach ($rankingRules as $ruleId) {
                $cmp = match ($ruleId) {
                    \App\Models\TournamentType::RANKING_WIN_DRAW_LOSE_POINTS =>
                        $this->compareScalar($a, $b, 'points'),
                    \App\Models\TournamentType::RANKING_WIN_RATE =>
                        $this->compareScalar($a, $b, 'win_rate'),
                    \App\Models\TournamentType::RANKING_SETS_WON =>
                        $this->compareScalar($a, $b, 'sets_diff'),
                    \App\Models\TournamentType::RANKING_POINTS_WON =>
                        $this->compareScalar($a, $b, 'point_diff'),
                    \App\Models\TournamentType::RANKING_GOALS_SCORED =>
                        // Tổng số bàn/điểm ghi được (DESC). Tie-breaker quan trọng
                        // vì Nhì/Ba chéo bảng không thể so H2H.
                        $this->compareScalar($a, $b, 'points_for'),
                    \App\Models\TournamentType::RANKING_HEAD_TO_HEAD =>
                        $this->compareHeadToHead($a, $b, $h2hMatrix),
                    \App\Models\TournamentType::RANKING_RANDOM_DRAW =>
                        $a['team_id'] <=> $b['team_id'],
                    default => 0,
                };

                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            // Fallback cuối: stable theo team_id
            return $a['team_id'] <=> $b['team_id'];
        })->values();

        // Gắn rank + pending_draw
        // pending_draw = true chỉ khi CẢ HAI đội cùng candidate_type
        // (cùng loại: runner_up hoặc third_place) và cùng tất cả ranking keys.
        $withRank = $sorted->map(function (array $c, int $idx) use ($sorted, $rankingRules) {
            $c['rank'] = $idx + 1;

            if ($idx > 0) {
                $prev = $sorted->get($idx - 1);
                $isSameType = $prev['candidate_type'] === $c['candidate_type'];
                $c['pending_draw'] = $isSameType && $this->isSameStats($prev, $c, $rankingRules);
            } else {
                $c['pending_draw'] = false;
            }

            return $c;
        });

        return $withRank;
    }

    /**
     * So sánh hai candidate theo một scalar ranking key (DESC).
     * Trả về -1/0/1 để dùng với usort.
     */
    protected function compareScalar(array $a, array $b, string $key): int
    {
        $av = (float) ($a[$key] ?? 0);
        $bv = (float) ($b[$key] ?? 0);
        if ($av === $bv) {
            return 0;
        }
        return $bv <=> $av; // DESC
    }

    /**
     * So sánh head-to-head giữa 2 đội (DESC ai thắng H2H).
     * Trả 0 nếu chưa gặp nhau hoặc tỷ số cân.
     */
    protected function compareHeadToHead(array $a, array $b, array $h2hMatrix): int
    {
        if (empty($h2hMatrix)) {
            return 0;
        }
        $keyA = $a['team_id'];
        $keyB = $b['team_id'];
        $pairKey = $keyA <= $keyB ? "{$keyA}|{$keyB}" : "{$keyB}|{$keyA}";
        $pair = $h2hMatrix[$pairKey] ?? null;

        if (!$pair) {
            return 0;
        }

        $aWins = ($pair['wins'][$keyA] ?? 0);
        $bWins = ($pair['wins'][$keyB] ?? 0);

        if ($aWins === $bWins) {
            return 0;
        }
        return $aWins < $bWins ? 1 : -1; // người thắng nhiều hơn xếp trên
    }

    /**
     * Kiểm tra 2 candidate có cùng stats trên các ranking keys không.
     */
    protected function isSameStats(array $a, array $b, array $rankingRules): bool
    {
        foreach ($rankingRules as $ruleId) {
            $key = match ($ruleId) {
                \App\Models\TournamentType::RANKING_WIN_DRAW_LOSE_POINTS => 'points',
                \App\Models\TournamentType::RANKING_WIN_RATE => 'win_rate',
                \App\Models\TournamentType::RANKING_SETS_WON => 'sets_diff',
                \App\Models\TournamentType::RANKING_POINTS_WON => 'point_diff',
                \App\Models\TournamentType::RANKING_GOALS_SCORED => 'points_for',
                default => null,
            };
            if ($key === null) {
                continue;
            }
            if ((float) ($a[$key] ?? 0) !== (float) ($b[$key] ?? 0)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Build head-to-head matrix cho tất cả cặp candidate.
     * Key = "minId|maxId", value = { wins: [teamId => count], ... }
     */
    protected function buildHeadToHeadMatrix(Collection $candidates): array
    {
        $teamIds = $candidates->pluck('team_id')->all();
        if (count($teamIds) < 2) {
            return [];
        }

        $matches = Matches::where('status', self::STATUS_COMPLETED)
            ->whereIn('home_team_id', $teamIds)
            ->whereIn('away_team_id', $teamIds)
            ->get();

        $matrix = [];
        foreach ($matches as $match) {
            $homeId = (int) $match->home_team_id;
            $awayId = (int) $match->away_team_id;
            if (!in_array($homeId, $teamIds, true) || !in_array($awayId, $teamIds, true)) {
                continue;
            }
            $pairKey = $homeId <= $awayId ? "{$homeId}|{$awayId}" : "{$awayId}|{$homeId}";
            if (!isset($matrix[$pairKey])) {
                $matrix[$pairKey] = ['wins' => []];
            }
            if ($match->winner_id && in_array($match->winner_id, [$homeId, $awayId], true)) {
                $winner = (int) $match->winner_id;
                $matrix[$pairKey]['wins'][$winner] = ($matrix[$pairKey]['wins'][$winner] ?? 0) + 1;
            }
        }
        return $matrix;
    }

    /**
     * Gắn `status = qualified/not_qualified/not_applicable` cho từng candidate.
     *
     * Quy tắc đúng theo spec:
     * - Số slot knockout = 2^n gần nhất với numAdvancing × numGroups (tính từ getKnockoutSlots).
     * - Số slot Nhất "mặc định" = numberOfGroups (mỗi bảng 1 Nhất).
     * - Số slot Ba "mặc định" = numberOfGroups (mỗi bảng 1 Ba, nếu numAdvancing >= 2).
     * - additionalSlots = max(0, knockoutSlots - totalFromPool)
     *   (KHÔNG dùng `knockoutSlots - numberOfGroups` vì sai khi numAdvancing > 1).
     *
     * Logic qualified:
     * - Khi additionalSlots == 0: tất cả candidate = not_applicable (FE ẩn hết).
     * - Khi additionalSlots > 0:
     *   + Ưu tiên Nhì trước: pick tối đa runnerUpMaxApply = numberOfGroups Nhì.
     *   + Nếu additionalSlots > numberOfGroups → pick thêm Ba (additionalSlots - numberOfGroups Ba tốt nhất).
     *   + Nếu additionalSlots <= numberOfGroups → CHỈ pick Nhì, Ba = not_applicable.
     *
     * Display logic cho Ba:
     * - Nếu additionalSlots <= numberOfGroups: Ba không cần → status = 'not_applicable' (FE ẩn).
     * - Nếu additionalSlots > numberOfGroups: Ba có thể được pick → status = 'qualified' hoặc 'not_qualified'.
     *
     * Status gắn in-place giữ nguyên order từ rankCandidates.
     *
     * @param int $knockoutSlots   Số slot knockout (= 2^n gần nhất)
     * @param int $numberOfGroups  Số bảng (= số Nhất tự nhiên)
     * @param int $totalFromPool   Tổng số đội từ pool stage (numAdvancing × numberOfGroups)
     */
    protected function assignQualifiedStatus(
        Collection $candidates,
        int $knockoutSlots,
        int $numberOfGroups,
        int $totalFromPool,
        array $applyTo
    ): Collection {
        // ✅ Số slot cần pick thêm SAU các đội đã được resolve từ pool stage.
        // Công thức đúng với mọi numAdvancing: additionalSlots = knockoutSlots - totalFromPool.
        $additionalSlots = max(0, $knockoutSlots - max($totalFromPool, $numberOfGroups));

        // Số Nhì tối đa được apply (mỗi bảng 1 Nhì)
        $runnerUpMaxApply = $numberOfGroups;

        // Nếu additionalSlots <= numberOfGroups → chỉ cần Nhì, Ba không cần pick.
        // Ba sẽ được mark 'not_applicable' để FE ẩn, dù `apply_to` có chứa 'third_place'.
        $needThirdPlace = $additionalSlots > $numberOfGroups;

        // ✅ Nếu additionalSlots = 0 (đã đủ đội từ pool, totalFromPool đã là power-of-2)
        // → không cần xét Nhì/Ba tốt nhất giữa các bảng. Tất cả Nhì/Ba sẽ là 'not_applicable'
        // để FE ẩn (kể cả khi apply_to có chứa 'runner_up'/'third_place').
        $needRunnerUp = $additionalSlots > 0;

        // Sort candidates: runner_up trước third_place, theo rank
        $sorted = $candidates->sortBy([
            ['candidate_type', 'asc'],
            ['rank', 'asc'],
        ])->values();

        $qualifiedTeamIds = [];
        $needed = $additionalSlots;

        // Round 1: lấy Nhì (ưu tiên) — chỉ pick nếu additionalSlots > 0 và apply_to cho phép.
        if ($needed > 0 && in_array(self::CANDIDATE_TYPE_RUNNER_UP, $applyTo, true)) {
            $runnerUps = $sorted->where('candidate_type', self::CANDIDATE_TYPE_RUNNER_UP);
            $picked = 0;
            foreach ($runnerUps as $c) {
                if ($needed <= 0 || $picked >= $runnerUpMaxApply) {
                    break;
                }
                $qualifiedTeamIds[$c['team_id']] = true;
                $needed--;
                $picked++;
            }
        }

        // Round 2: nếu vẫn thiếu (additionalSlots > numberOfGroups), lấy Ba
        if ($needed > 0 && in_array(self::CANDIDATE_TYPE_THIRD_PLACE, $applyTo, true)) {
            $thirdPlaces = $sorted->where('candidate_type', self::CANDIDATE_TYPE_THIRD_PLACE);
            foreach ($thirdPlaces as $c) {
                if ($needed <= 0) {
                    break;
                }
                $qualifiedTeamIds[$c['team_id']] = true;
                $needed--;
            }
        }

        // Bước 2: gắn status
        return $candidates->map(function (array $candidate) use ($qualifiedTeamIds, $applyTo, $needThirdPlace, $needRunnerUp) {
            $type = $candidate['candidate_type'];

            // Nhì khi không cần pick (additionalSlots=0) → not_applicable (FE ẩn),
            // kể cả khi apply_to có 'runner_up'.
            if ($type === self::CANDIDATE_TYPE_RUNNER_UP && !$needRunnerUp) {
                $candidate['status'] = 'not_applicable';
            }
            // Ba khi không cần pick → not_applicable (FE ẩn), kể cả khi apply_to có third_place.
            elseif ($type === self::CANDIDATE_TYPE_THIRD_PLACE && !$needThirdPlace) {
                $candidate['status'] = 'not_applicable';
            } elseif (!in_array($type, $applyTo, true)) {
                $candidate['status'] = 'not_applicable';
            } elseif (isset($qualifiedTeamIds[$candidate['team_id']])) {
                $candidate['status'] = 'qualified';
            } else {
                $candidate['status'] = 'not_qualified';
            }
            return $candidate;
        });
    }

    /**
     * Format candidate row thành JSON-friendly shape cho response.
     */
    protected function formatCandidate(array $c): array
    {
        return [
            'rank' => $c['rank'] ?? null,
            'team' => [
                'id' => (int) $c['team_id'],
                'name' => $c['team_name'] ?? 'Unknown',
            ],
            'group' => [
                'id' => (int) $c['group_id'],
                'name' => $c['group_name'] ?? '',
                'team_count' => (int) ($c['group_team_count'] ?? 0),
            ],
            'group_position' => $c['group_position'] ?? null,
            'candidate_type' => $c['candidate_type'] ?? null,
            'matches' => [
                'original' => (int) ($c['original'] ?? 0),
                'counted' => (int) ($c['counted'] ?? 0),
                'excluded' => (int) ($c['excluded'] ?? 0),
            ],
            'statistics' => [
                'wins' => (int) ($c['wins'] ?? 0),
                'losses' => (int) ($c['losses'] ?? 0),
                'draws' => (int) ($c['draws'] ?? 0),
                'points' => (int) ($c['points'] ?? 0), // điểm xếp hạng (Thắng=3, Hòa=1, Thua=0)
                'sets_won' => (int) ($c['sets_won'] ?? 0),
                'sets_lost' => (int) ($c['sets_lost'] ?? 0),
                'sets_diff' => (int) ($c['sets_diff'] ?? 0),
                'win_rate' => (float) ($c['win_rate'] ?? 0),
                'points_for' => (int) ($c['points_for'] ?? 0),
                'points_against' => (int) ($c['points_against'] ?? 0),
                'point_diff' => (int) ($c['point_diff'] ?? 0),
                'average_point_difference' => (float) ($c['average_point_difference'] ?? 0),
            ],
            'status' => $c['status'] ?? 'not_qualified',
            'pending_draw' => (bool) ($c['pending_draw'] ?? false),
            'has_excluded_matches' => (bool) (($c['excluded'] ?? 0) > 0),
        ];
    }

    /**
     * Build danh sách matches cho API 2 — bao gồm cả included và excluded.
     *
     * @return array<int,array>
     */
    protected function buildCandidateMatchList(array $candidate, int $minimumGroupSize, array $rankingRules = []): array
    {
        /** @var Group $group */
        $group = $candidate['group'];
        $teamId = $candidate['team_id'];
        $groupTeamCount = $candidate['group_team_count'];

        $excludedIds = $this->getExcludedOpponentIds($group, $groupTeamCount, $minimumGroupSize, $rankingRules);

        // Standings cho opponent_group_position — Dùng GroupStandingRanker (đồng bộ với buildCandidates)
        $standings = GroupStandingRanker::rank($group, $rankingRules);
        $rankByTeamId = [];
        foreach ($standings as $standing) {
            if (isset($standing['team_id'])) {
                $rankByTeamId[$standing['team_id']] = $standing['rank'];
            }
        }

        $teamMatches = Matches::where('group_id', $group->id)
            ->where('status', self::STATUS_COMPLETED)
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)->orWhere('away_team_id', $teamId);
            })
            ->with(['homeTeam', 'awayTeam', 'results'])
            ->orderBy('id')
            ->get();

        $matchList = [];
        foreach ($teamMatches as $match) {
            $isHome = $match->home_team_id === $teamId;
            $opponent = $isHome ? $match->awayTeam : $match->homeTeam;
            $opponentId = $isHome ? $match->away_team_id : $match->home_team_id;

            $homeScore = (int) $match->results->where('team_id', $match->home_team_id)->sum('score');
            $awayScore = (int) $match->results->where('team_id', $match->away_team_id)->sum('score');

            $myScore = $isHome ? $homeScore : $awayScore;
            $opponentScore = $isHome ? $awayScore : $homeScore;

            // Determine result
            $result = 'draw';
            if ($match->winner_id === $teamId) {
                $result = 'win';
            } elseif ($match->winner_id && $match->winner_id !== $teamId) {
                $result = 'loss';
            }

            $included = !in_array($opponentId, $excludedIds, true);
            $reason = null;
            if (!$included) {
                $oppRank = $rankByTeamId[$opponentId] ?? null;
                $reason = $oppRank
                    ? "Đối thủ xếp hạng {$oppRank} trong bảng"
                    : 'Đối thủ xếp cuối bảng';
            }

            $matchList[] = [
                'id' => (int) $match->id,
                'opponent' => [
                    'id' => (int) ($opponent->id ?? $opponentId),
                    'name' => $opponent->name ?? 'Unknown',
                ],
                'opponent_group_position' => $rankByTeamId[$opponentId] ?? null,
                'score' => "{$myScore} - {$opponentScore}",
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'result' => $result,
                'included' => $included,
                'exclusion_reason' => $reason,
            ];
        }

        return $matchList;
    }

    /**
     * Resolve đội Nhì tốt nhất (qualified) vào các bảng ảo trong knockout bracket.
     *
     * Được gọi trong applyPoolAdvancement sau khi đã resolve đội từ các bảng thật.
     * Tìm các trận round 2 đang trống (không có PoolAdvancementRule) và fill
     * bằng đội Nhì tốt nhất theo thứ tự rank từ cross-group comparison.
     *
     * @return array<int, array{next_match_id:int, next_position:string, team_id:int}>
     */
    public function resolveVirtualGroupAdvancing(TournamentType $type): array
    {
        $payload = $this->buildComparisonPayload($type);
        if (!$payload['applied']) {
            return [];
        }

        // Lấy các candidate Nhì tốt nhất (qualified), sort theo rank
        $qualifiedRunners = collect($payload['candidates'])
            ->where('candidate_type', self::CANDIDATE_TYPE_RUNNER_UP)
            ->where('status', 'qualified')
            ->sortBy('rank')
            ->values();

        if ($qualifiedRunners->isEmpty()) {
            return [];
        }

        // Lấy các trận knockout round 2 đang trống (home/away chưa gán team) mà
        // slot đó KHÔNG có real rule chiếm chỗ. Virtual rule (is_virtual=true)
        // chiếm đúng position home/away tương ứng → vẫn match được 2 rule trên
        // cùng 1 match (1 real home + 1 virtual away) mà không xung đột.
        $virtualSlots = Matches::where('tournament_type_id', $type->id)
            ->where('round', 2)
            ->where('bracket_type', 'main')
            ->whereNotExists(function ($q) {
                // Loại slot nào đã có REAL rule (is_virtual=false) cùng position.
                $q->select(DB::raw(1))
                  ->from('pool_advancement_rules as par')
                  ->whereColumn('par.next_match_id', 'matches.id')
                  ->where(function ($qq) {
                      $qq->whereNull('par.is_virtual')->orWhere('par.is_virtual', false);
                  });
            })
            ->where(function ($q) {
                $q->whereNull('home_team_id')->orWhereNull('away_team_id');
            })
            ->orderBy('id')
            ->get();

        if ($virtualSlots->isEmpty()) {
            return [];
        }

        $result = [];
        foreach ($virtualSlots as $i => $match) {
            $candidate = $qualifiedRunners->get($i);
            if (!$candidate) {
                break;
            }

            // Xác định position: mỗi trận có 2 slots (home, away)
            // Pair index: slot 0→home, slot 1→away, slot 2→home, slot 3→away, ...
            $slotInPair = $i % 2;
            $nextPosition = $slotInPair === 0 ? 'home' : 'away';

            $result[] = [
                'next_match_id' => $match->id,
                'next_position' => $nextPosition,
                'team_id' => (int) $candidate['team']['id'],
            ];
        }

        return $result;
    }

    /**
     * Build description string cho rule (dùng trong response FE nếu cần).
     */
    protected function buildRuleDescription(array $groupTeamCounts): string
    {
        $min = min(array_filter($groupTeamCounts));
        $sizes = implode(', ', $groupTeamCounts);
        return "Các bảng có số đội: {$sizes}. Bảng nhỏ nhất có {$min} đội. "
            . 'Các trận gặp đội xếp cuối bảng ở các bảng lớn hơn được loại để so sánh công bằng.';
    }

}