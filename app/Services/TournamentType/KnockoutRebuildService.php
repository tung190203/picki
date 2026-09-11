<?php

namespace App\Services\TournamentType;

use App\Models\Matches;
use App\Models\PoolAdvancementRule;
use App\Models\TournamentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service xử lý luồng ghép cặp vòng sau khi vòng bảng đã kết thúc (rebuild pairing flow).
 *
 * KHÁC VỚI TeamPairingService:
 * - TeamPairingService: xử lý pairing khi tạo tournament type, khi groups chưa có kết quả → dùng placeholder.
 * - KnockoutRebuildService: xử lý REBUILD pairing SAU khi vòng bảng đã hoàn thành, khi đã biết được
 *   đội thật từ standings → gán trực tiếp vào round=2 main bracket.
 *
 * Constraints:
 * - CHỈ làm việc với FORMAT_MIXED.
 * - CHỈ xử lý main bracket (bracket_type='main'). Resurrection bracket ('sub') bỏ qua.
 * - Chỉ chạy khi vòng bảng đã hoàn thành 100% (round=1 tất cả status=completed).
 * - CHỈ reassign home_team_id/away_team_id cho round=2, KHÔNG động vào round≥3, KHÔNG động vào
 *   next_match_id / next_position / PoolAdvancementRule của real groups.
 */
class KnockoutRebuildService
{
    public function __construct(
        private CrossGroupComparisonService $crossGroupComparisonService,
        private CrossGroupRankingService $crossGroupRankingService,
        private BracketService $bracketService
    ) {}

    /**
     * Build danh sách ứng viên vào vòng knockout (kèm team_label).
     *
     * Nếu vòng bảng chưa kết thúc → trả về ['pool_completed' => false, 'candidates' => []].
     * Nếu đã kết thúc → build candidates theo standings + resolve virtual từ cross-group comparison.
     *
     * Logic:
     *   - Với mỗi group, lấy top N đội theo standings (N = num_advancing_teams).
     *   - Label: "Nhất {group_name}", "Nhì {group_name}", "Ba {group_name}"...
     *   - Nếu cross_group_ranking.enabled && apply_to=runner_up && num_advancing=1
     *     → resolve virtual "Nhì tốt nhất" qua CrossGroupComparisonService.
     *     Label: "Nhì tốt nhất #1", "Nhì tốt nhất #2", ...
     *   - Nếu apply_to=third_place → tương tự "Ba tốt nhất #N".
     *
     * @return array{pool_completed: bool, candidates: array<int, array>}
     */
    public function buildCandidatesList(TournamentType $type): array
    {
        if ($type->format !== TournamentType::FORMAT_MIXED) {
            return [
                'pool_completed' => false,
                'candidates' => [],
            ];
        }

        // ✅ Strict completion: tất cả trận round=1 phải completed
        $hasPendingPool = $type->matches()
            ->where('round', 1)
            ->where('status', '!=', Matches::STATUS_COMPLETED)
            ->exists();

        if ($hasPendingPool) {
            return [
                'pool_completed' => false,
                'candidates' => [],
            ];
        }

        $config = $type->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : $config;
        $poolConfig = $mainConfig['pool_stage'] ?? [];
        $crossGroupRaw = $mainConfig['cross_group_ranking'] ?? [];

        $numAdvancing = max(1, (int) ($poolConfig['num_advancing_teams'] ?? 1));
        $crossGroupEnabled = filter_var($crossGroupRaw['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $applyTo = $crossGroupRaw['apply_to'] ?? [];

        $candidates = [];

        // ===== 1. REAL CANDIDATES TỪ CÁC BẢNG (Nhất/Nhì/Ba/...) =====
        $groups = $type->groups()->get();

        // ✅ FIX: Tính ranking rules đã chuẩn hóa (kèm fallback POINTS_WON + HEAD_TO_HEAD)
        // để phân biệt Nhì/Ba khi đồng hạng trên points/diff/WR.
        $rankingRules = $this->extractRankingRules($type);

        foreach ($groups as $group) {
            // ✅ FIX: Dùng GroupStandingRanker (có H2H + ranking rules) thay cho
            // TournamentService::calculateGroupStandings (thiếu H2H → chọn sai
            // Nhì/Ba khi nhiều đội đồng hạng).
            $standings = GroupStandingRanker::rank($group, $rankingRules);

            if ($standings->isEmpty()) {
                continue;
            }

            // Lấy top numAdvancing đội
            for ($rank = 1; $rank <= $numAdvancing; $rank++) {
                $teamAtRank = $standings->get($rank - 1);
                if (!$teamAtRank || empty($teamAtRank['team_id'])) {
                    continue;
                }

                $candidates[] = [
                    'team_id' => (int) $teamAtRank['team_id'],
                    'team_name' => $teamAtRank['team_name'] ?? null,
                    'team_avatar' => null, // GroupStandingRanker không attach avatar; FE lookup qua team_id nếu cần
                    'group_id' => (int) $group->id,
                    'group_name' => $group->name,
                    'group_position' => $rank,
                    'candidate_type' => $this->mapRankToCandidateType($rank),
                    'team_label' => sprintf(
                        '%s %s',
                        $this->bracketService->getRankText($rank),
                        $this->stripBangPrefix($group->name)
                    ),
                    'is_virtual' => false,
                ];
            }
        }

        // ===== 2. VIRTUAL CANDIDATES TỪ CROSS-GROUP COMPARISON =====
        // ✅ IMPORTANT: Không phụ thuộc vào cross_group_ranking.enabled flag.
        // Vì vòng sau vẫn có thể có runner_up/third_place (do knockout_slots > numberOfGroups)
        // ngay cả khi user không bật cross_group_ranking. Khi đó BE vẫn sinh round=2 với
        // runner_up virtual, nên API /knockout-candidates cũng phải trả để FE rebuild được.
        if ($numAdvancing === 1) {
            $numberOfGroups = $type->groups()->count();

            // ✅ Tính số slot knockout dựa trên quy tắc CEILING power-of-2 (giống BE ở
            // TournamentTypeController::PHASE 2.5 khi generate round 2).
            // total = numAdvancing × numberOfGroups; knockout_slots = next power-of-2.
            $totalFromPool = max(0, $numAdvancing * $numberOfGroups);
            $knockoutSlots = $this->computeKnockoutSlotsCeiling($totalFromPool);
            $additionalSlots = max(0, $knockoutSlots - $numberOfGroups);

            // Nhì tốt nhất (nếu additionalSlots >= 1, mỗi bảng chỉ pick được 1 Nhì).
            if ($additionalSlots >= 1) {
                $virtualRunners = $this->resolveVirtualCandidates(
                    $type,
                    CrossGroupComparisonService::CANDIDATE_TYPE_RUNNER_UP,
                    'Nhì tốt nhất'
                );
                foreach ($virtualRunners as $vc) {
                    $candidates[] = $vc;
                }
            }

            // Ba tốt nhất (chỉ pick nếu additionalSlots > numberOfGroups, tức vẫn thiếu sau khi đã pick Nhì).
            $runnerUpCount = min($additionalSlots, $numberOfGroups);
            $thirdPlaceNeeded = $additionalSlots - $runnerUpCount;
            if ($thirdPlaceNeeded > 0) {
                $virtualThirds = $this->resolveVirtualCandidates(
                    $type,
                    CrossGroupComparisonService::CANDIDATE_TYPE_THIRD_PLACE,
                    'Ba tốt nhất'
                );
                foreach ($virtualThirds as $vc) {
                    $candidates[] = $vc;
                }
            }
        }

        return [
            'pool_completed' => true,
            'candidates' => $candidates,
        ];
    }

    /**
     * Rebuild main bracket round=2 dựa trên manual_pairings (chỉ reassign home/away_team_id).
     *
     * Quy trình:
     *   1. Validate: format=MIXED, pool đã hoàn thành, manual_pairings hợp lệ, không có locked matches ở round≥2.
     *   2. Resolve team_id cho từng entry trong manual_pairings:
     *      - group_id > 0 → lookup từ standings của group đó tại rank tương ứng.
     *      - group_id = 0 → resolve qua cross-group virtual mapping (theo rank=2 hoặc rank=3).
     *   3. Normalize manual_pairings theo convention mới (giống TeamPairingService::arrangeManual).
     *   4. Lấy các matches round=2 main, chunk theo num_legs.
     *   5. Gán home_team_id / away_team_id cho từng cặp (cả legs theo return-leg swap).
     *   6. Update PoolAdvancementRule nếu cần để consistency.
     *
     * KHÔNG động vào:
     *   - Round ≥ 3 (giữ nguyên cấu trúc bracket đã có)
     *   - Resurrection bracket (bracket_type='sub')
     *   - next_match_id, next_position của matches round=2
     *
     * @param TournamentType $type
     * @param array<int, array{group_id: int|string, rank: int|string, position: int|string}> $manualPairings
     * @return array{reassigned_pairs: int, virtual_resolved: int}
     */
    public function rebuildKnockoutMainBracket(TournamentType $type, array $manualPairings): array
    {
        // ===== VALIDATION =====
        if ($type->format !== TournamentType::FORMAT_MIXED) {
            throw new \InvalidArgumentException('Chỉ áp dụng cho giải đấu hỗn hợp (Mixed).');
        }

        // Check pool đã hoàn thành
        $hasPendingPool = $type->matches()
            ->where('round', 1)
            ->where('status', '!=', Matches::STATUS_COMPLETED)
            ->exists();
        if ($hasPendingPool) {
            throw new \RuntimeException('Vòng bảng chưa hoàn thành. Không thể rebuild pairing.');
        }

        // Check locked matches ở round≥2
        $hasLockedKnockout = $type->matches()
            ->where('round', '>=', 2)
            ->where('status', Matches::STATUS_COMPLETED)
            ->whereHas('results', function ($q) {
                $q->where('confirmed', true);
            })
            ->exists();
        if ($hasLockedKnockout) {
            throw new \RuntimeException('Đã có trận knockout hoàn thành và có kết quả được xác nhận. Không thể rebuild.');
        }

        if (empty($manualPairings)) {
            throw new \InvalidArgumentException('manual_pairings không được rỗng.');
        }

        $config = $type->format_specific_config ?? [];
        $mainConfig = is_array($config) && isset($config[0]) ? $config[0] : $config;
        $poolConfig = $mainConfig['pool_stage'] ?? [];
        $numAdvancing = max(1, (int) ($poolConfig['num_advancing_teams'] ?? 1));
        $numLegs = max(1, (int) ($type->num_legs ?? 1));

        // ===== RESOLVE TEAM_ID TỪ MANUAL_PAIRINGS =====
        // Build map: real_group_id → standings[rank-1].team_id
        $realTeamByGroupRank = $this->buildRealTeamMap($type);

        // Build map cho virtual: rank=2 (Nhì tốt nhất) & rank=3 (Ba tốt nhất) → list team_id theo thứ tự rank từ payload
        $virtualTeamByRank = $this->buildVirtualTeamMap($type);

        // Normalize manual_pairings theo convention mới (giống TeamPairingService::arrangeManual)
        $normalizedPairings = $this->normalizeManualPairings($manualPairings, $realTeamByGroupRank, $virtualTeamByRank);

        // ===== LẤY MATCHES ROUND=2 MAIN, CHUNK THEO LEGS =====
        $round2Matches = Matches::where('tournament_type_id', $type->id)
            ->where('round', 2)
            ->where('bracket_type', 'main')
            ->orderBy('id', 'asc')
            ->get();

        if ($round2Matches->isEmpty()) {
            throw new \RuntimeException('Không tìm thấy trận vòng 2 (main bracket). Hãy đảm bảo vòng bảng đã tạo round=2.');
        }

        $matchPairs = $round2Matches->chunk($numLegs)->values();
        $totalPairs = $matchPairs->count();

        // ===== VALIDATE: không có team_id trùng lặp trong manual_pairings =====
        // Mỗi team chỉ được xuất hiện tối đa 1 lần trong round 2.
        // Nếu có duplicate → throw error để FE biết và không lưu.
        $assignedTeamIds = [];
        foreach ($normalizedPairings as $entry) {
            $tid = $entry['team_id'];
            if ($tid === null) continue;
            if (isset($assignedTeamIds[$tid])) {
                throw new \InvalidArgumentException(sprintf(
                    'Team ID %d (%s) được gán vào nhiều hơn 1 slot trong vòng 2. Mỗi đội chỉ có thể xuất hiện 1 lần. Hãy kiểm tra lại ghép cặp.',
                    $tid,
                    $entry['team_name'] ?? 'N/A'
                ));
            }
            $assignedTeamIds[$tid] = true;
        }

        // ===== GÁN HOME/AWAY TEAM_ID CHO TỪNG CẶP =====
        $reassignedPairs = 0;
        $virtualResolved = 0;
        $pairIndex = 0;
        $slotIndex = 0;

        // Pairings đã được sort theo (position, rank): nhất của cặp trước, nhì của cặp trước, ...
        // → mỗi 2 entries là 1 cặp (rank=1 → home, rank=2 → away)
        foreach ($normalizedPairings as $entry) {
            if ($pairIndex >= $totalPairs) {
                break;
            }

            $basePosition = ($slotIndex % 2 === 0) ? 'home' : 'away';
            $matchPair = $matchPairs->get($pairIndex);

            if (!$matchPair) {
                $slotIndex++;
                if ($slotIndex % 2 === 0) $pairIndex++;
                continue;
            }

            $teamId = $entry['team_id'];
            $isVirtual = $entry['is_virtual'] ?? false;
            if ($isVirtual) {
                $virtualResolved++;
            }

            if ($teamId === null) {
                // Không resolve được team → skip, để null
                $slotIndex++;
                if ($slotIndex % 2 === 0) $pairIndex++;
                continue;
            }

            foreach ($matchPair as $legMatch) {
                $isReturnLeg = ((int) $legMatch->leg % 2 === 0);
                // Đảo home/away cho lượt về
                $actualPosition = $isReturnLeg
                    ? ($basePosition === 'home' ? 'away' : 'home')
                    : $basePosition;

                $legMatch->update([
                    $actualPosition . '_team_id' => $teamId,
                    'status' => Matches::STATUS_PENDING,
                ]);
            }

            $reassignedPairs++;

            $slotIndex++;
            if ($slotIndex % 2 === 0) {
                $pairIndex++;
            }
        }

        return [
            'reassigned_pairs' => $reassignedPairs,
            'virtual_resolved' => $virtualResolved,
        ];
    }

    // ===========================================================
    // HELPER METHODS
    // ===========================================================

    /**
     * Map rank (1-based) sang candidate_type cho response candidates.
     */
    private function mapRankToCandidateType(int $rank): string
    {
        return match ($rank) {
            1 => 'winner',
            2 => 'runner_up',
            3 => 'third_place',
            default => 'rank_' . $rank,
        };
    }

    /**
     * Strip prefix "Bảng " để label đẹp hơn (VD: "Bảng A" → "A").
     */
    private function stripBangPrefix(?string $groupName): string
    {
        if (!$groupName) {
            return '';
        }
        // Nếu là "Bảng A" thì lấy phần sau "Bảng "
        if (preg_match('/^Bảng\s+(.+)$/u', $groupName, $m)) {
            return $m[1];
        }
        return $groupName;
    }

    /**
     * Resolve virtual candidates (Nhì/Ba tốt nhất) bằng cách TÁI SỬ DỤNG logic build/rank
     * từ CrossGroupComparisonService (đã được tách ra thành buildRankedCandidates).
     *
     * ✅ Đảm bảo kết quả LUÔN GIỐNG với API comparison chính:
     *   - Cùng buildCandidates (logic theo standings + minimumGroupSize).
     *   - Cùng buildComparisonStats (wins/losses/points/sets...).
     *   - Cùng rankCandidates (sort theo ranking_rules: head-to-head, points, sets_diff, ...).
     *
     * Caller chỉ cần tính pickLimit rồi chọn top N từ Collection đã được rank.
     *
     * @param string $candidateType 'runner_up' hoặc 'third_place'
     * @param string $labelPrefix VD: "Nhì tốt nhất" hoặc "Ba tốt nhất"
     * @return array<int, array>
     */
    private function resolveVirtualCandidates(TournamentType $type, string $candidateType, string $labelPrefix): array
    {
        // ✅ Tính pickLimit dựa trên knockoutSlots (giống CrossGroupComparisonService logic).
        $numberOfGroups = $type->groups()->count();
        if ($numberOfGroups <= 0) {
            return [];
        }
        $poolConfig = $type->format_specific_config[0]['pool_stage'] ?? [];
        $numAdvancing = max(1, (int) ($poolConfig['num_advancing_teams'] ?? 1));
        $totalFromPool = max(0, $numAdvancing * $numberOfGroups);

        // ⚠️ Lưu ý: BE khi generate round 2 dùng ceiling power-of-2 (xem
        // TournamentTypeController PHASE 2.5). Ở đây cũng dùng ceiling để khớp với
        // BE — nếu dùng getKnockoutSlots của CrossGroupComparisonService (làm tròn về
        // gần nhất) sẽ cho ra kết quả khác khi total=3 (3 → 2 thay vì 4).
        $knockoutSlots = $this->computeKnockoutSlotsCeiling($totalFromPool);
        $additionalSlots = max(0, $knockoutSlots - $numberOfGroups);

        // Xác định cần pick bao nhiêu candidate cho loại này:
        // - runner_up: pick tối đa `min(additionalSlots, numberOfGroups)` Nhì.
        // - third_place: pick `max(0, additionalSlots - numberOfGroups)` Ba.
        if ($candidateType === CrossGroupComparisonService::CANDIDATE_TYPE_RUNNER_UP) {
            $pickLimit = min($additionalSlots, $numberOfGroups);
        } else {
            $pickLimit = max(0, $additionalSlots - $numberOfGroups);
        }

        if ($pickLimit <= 0) {
            return [];
        }

        // ✅ TÁI SỬ DỤNG logic build + rank từ CrossGroupComparisonService.
        // minimumGroupSize: lấy từ CrossGroupRankingService::evaluate() — giá trị này phản ánh
        // spec thật (khi applied=true thì minimumGroupSize = min(group_team_counts)).
        // Pass giá trị này để kết quả LUÔN GIỐNG với API comparison chính khi applied=true.
        // Nếu applied=false (do enabled=false hoặc isUniform=true), fallback về 0.
        $crossGroupRaw = $type->format_specific_config[0]['cross_group_ranking'] ?? [];
        $evaluation = $this->crossGroupRankingService->evaluate($type, is_array($crossGroupRaw) ? $crossGroupRaw : []);
        $effectiveMin = $evaluation['applied']
            ? (int) ($evaluation['minimum_group_size'] ?? 0)
            : 0;

        $ranked = $this->crossGroupComparisonService->buildRankedCandidates($type, $effectiveMin);

        // Filter theo candidate_type, sort theo rank, take top pickLimit.
        $picked = $ranked
            ->where('candidate_type', $candidateType)
            ->sortBy('rank')
            ->take($pickLimit);

        $result = [];
        foreach ($picked as $candidate) {
            $result[] = [
                'team_id' => isset($candidate['team_id']) ? (int) $candidate['team_id'] : null,
                'team_name' => $candidate['team_name'] ?? null,
                'team_avatar' => null, // buildRankedCandidates không attach avatar; FE lookup qua team_id nếu cần
                'group_id' => isset($candidate['group_id']) ? (int) $candidate['group_id'] : null,
                'group_name' => $candidate['group_name'] ?? null,
                'group_position' => $candidate['group_position'] ?? null,
                'candidate_type' => $candidateType,
                'team_label' => sprintf('%s #%d', $labelPrefix, $candidate['rank'] ?? count($result) + 1),
                'is_virtual' => true,
            ];
        }

        return $result;
    }

    /**
     * Helper: Tính số slot knockout theo quy tắc CEILING power-of-2 (giống logic ở
     * TournamentTypeController::PHASE 2.5 khi generate round 2).
     *
     *   $total = numAdvancing * numberOfGroups;
     *   $nextPowerOfTwo = ceil(log2(max(2, $total)));
     *   return $nextPowerOfTwo;
     *
     * Khác với CrossGroupComparisonService::getKnockoutSlots (làm tròn về gần nhất),
     * logic ở controller LUÔN làm tròn LÊN — vì round 2 cần power-of-2 để có bracket hợp lệ.
     * Hai giá trị giống nhau khi total là power-of-2, khác nhau khi total không phải power-of-2.
     */
    private function computeKnockoutSlotsCeiling(int $totalFromPool): int
    {
        if ($totalFromPool <= 0) {
            return 0;
        }
        $normalized = max(2, $totalFromPool);
        return (int) pow(2, (int) ceil(log($normalized, 2)));
    }

    /**
     * Build map: real_group_id → list[rank-1] = team_id (từ standings).
     *
     * Return: ['{group_id}_{rank}' => ['team_id' => int|null, 'team_name' => string|null]]
     *
     * @return array<string, array{team_id: int|null, team_name: string|null}>
     */
    private function buildRealTeamMap(TournamentType $type): array
    {
        $map = [];
        $groups = $type->groups()->get();

        // ✅ FIX: Tính ranking rules đã chuẩn hóa (kèm fallback POINTS_WON + HEAD_TO_HEAD)
        // để GroupStandingRanker phân biệt Nhì/Ba khi đồng hạng trên points/diff/WR.
        $rankingRules = $this->extractRankingRules($type);

        foreach ($groups as $group) {
            // ✅ FIX: Dùng GroupStandingRanker (có H2H + ranking rules) thay cho
            // TournamentService::calculateGroupStandings (thiếu H2H → chọn sai
            // Nhì/Ba khi nhiều đội đồng hạng).
            $standings = GroupStandingRanker::rank($group, $rankingRules);

            foreach ($standings as $entry) {
                $rank = (int) ($entry['rank'] ?? 0);
                if ($rank <= 0) {
                    continue;
                }
                $key = $group->id . '_' . $rank;
                $teamId = isset($entry['team_id']) ? (int) $entry['team_id'] : null;
                $teamName = $entry['team_name'] ?? null;
                $map[$key] = [
                    'team_id' => $teamId,
                    'team_name' => $teamName,
                ];
            }
        }

        return $map;
    }

    /**
     * Trích xuất ranking rules từ format_specific_config (đã chuẩn hóa + fallback HEAD_TO_HEAD).
     * Dùng cho buildCandidatesList và buildRealTeamMap.
     *
     * @return int[]
     */
    private function extractRankingRules(TournamentType $type): array
    {
        $config = $type->format_specific_config ?? [];
        if (is_array($config) && isset($config[0])) {
            $config = $config[0];
        }

        $rules = collect($config['ranking'] ?? [1, 4, 5])
            ->map(fn($id) => (int) $id)
            ->toArray();

        // ✅ Fallback: Tự động thêm POINTS_WON (4) + HEAD_TO_HEAD (5) nếu thiếu
        // (giống getRank, CrossGroupComparisonService).
        if (!in_array(TournamentType::RANKING_POINTS_WON, $rules, true)) {
            $rules[] = TournamentType::RANKING_POINTS_WON;
        }
        if (!in_array(TournamentType::RANKING_HEAD_TO_HEAD, $rules, true)) {
            $rules[] = TournamentType::RANKING_HEAD_TO_HEAD;
        }

        return $rules;
    }

    /**
     * Build map cho virtual: rank=2 (Nhì tốt nhất) và rank=3 (Ba tốt nhất).
     *
     * Logic tương tự resolveVirtualCandidates():
     * - Tính pickLimit dựa trên knockoutSlots (CEILING power-of-2, khớp với logic generate round 2).
     * - Dùng buildRankedCandidates() để resolve team_id theo standings + minimumGroupSize.
     * - Không filter 'qualified' (vì qualified dựa trên applyTo config có thể bị tắt — nhưng
     *   khi user đã ghép cặp thủ công với group_id=0/rank=2 thì vẫn cần resolve được team).
     *
     * Sau đó sẽ được resolve theo vị trí xuất hiện trong manual_pairings.
     *
     * Trả về:
     *   - rank_2: list team_id của các "Nhì tốt nhất" theo thứ tự rank từ cross-group comparison
     *   - rank_3: list team_id của các "Ba tốt nhất" theo thứ tự rank
     *
     * @return array{rank_2: array<int, array{team_id: int|null, team_name: string|null}>, rank_3: array<int, array{team_id: int|null, team_name: string|null}>}
     */
    private function buildVirtualTeamMap(TournamentType $type): array
    {
        $result = [
            'rank_2' => [],
            'rank_3' => [],
        ];

        $numberOfGroups = $type->groups()->count();
        if ($numberOfGroups <= 0) {
            return $result;
        }

        $poolConfig = $type->format_specific_config[0]['pool_stage'] ?? [];
        $numAdvancing = max(1, (int) ($poolConfig['num_advancing_teams'] ?? 1));
        $totalFromPool = max(0, $numAdvancing * $numberOfGroups);

        // ✅ CEILING power-of-2 (khớp với computeKnockoutSlotsCeiling)
        $knockoutSlots = $this->computeKnockoutSlotsCeiling($totalFromPool);
        $additionalSlots = max(0, $knockoutSlots - $numberOfGroups);

        // pickLimit giống resolveVirtualCandidates:
        // - runner_up: min(additionalSlots, numberOfGroups)
        // - third_place: max(0, additionalSlots - numberOfGroups)
        $runnerUpPickLimit = min($additionalSlots, $numberOfGroups);
        $thirdPlacePickLimit = max(0, $additionalSlots - $numberOfGroups);

        if ($runnerUpPickLimit <= 0 && $thirdPlacePickLimit <= 0) {
            return $result;
        }

        // ✅ Tái sử dụng logic build + rank từ CrossGroupComparisonService.
        // minimumGroupSize: lấy từ CrossGroupRankingService::evaluate() — giá trị này phản ánh
        // spec thật. Fallback 0 nếu applied=false.
        $crossGroupRaw = $type->format_specific_config[0]['cross_group_ranking'] ?? [];
        $evaluation = $this->crossGroupRankingService->evaluate($type, is_array($crossGroupRaw) ? $crossGroupRaw : []);
        $effectiveMin = $evaluation['applied']
            ? (int) ($evaluation['minimum_group_size'] ?? 0)
            : 0;

        try {
            $ranked = $this->crossGroupComparisonService->buildRankedCandidates($type, $effectiveMin);
        } catch (\Throwable $e) {
            return $result;
        }

        if ($runnerUpPickLimit > 0) {
            $runners = $ranked
                ->where('candidate_type', CrossGroupComparisonService::CANDIDATE_TYPE_RUNNER_UP)
                ->sortBy('rank')
                ->take($runnerUpPickLimit)
                ->values();
            foreach ($runners as $i => $candidate) {
                $result['rank_2'][$i] = [
                    'team_id' => isset($candidate['team_id']) ? (int) $candidate['team_id'] : null,
                    'team_name' => $candidate['team_name'] ?? null,
                ];
            }
        }

        if ($thirdPlacePickLimit > 0) {
            $thirds = $ranked
                ->where('candidate_type', CrossGroupComparisonService::CANDIDATE_TYPE_THIRD_PLACE)
                ->sortBy('rank')
                ->take($thirdPlacePickLimit)
                ->values();
            foreach ($thirds as $i => $candidate) {
                $result['rank_3'][$i] = [
                    'team_id' => isset($candidate['team_id']) ? (int) $candidate['team_id'] : null,
                    'team_name' => $candidate['team_name'] ?? null,
                ];
            }
        }

        return $result;
    }

    /**
     * Normalize manual_pairings theo convention mới (giống TeamPairingService::arrangeManual).
     *
     * Logic:
     *   - Detect old convention vs new convention.
     *   - Old: position = slotIndex (0,0, 1,1, 2,2,...).
     *   - New: position = slotIndex*2 + subIndex (0,1, 2,3, 4,5,...).
     *   - Sort theo (position, rank): nhất (rank=1) trước nhì (rank=2) trong cùng slot.
     *
     * Sau đó resolve team_id cho mỗi entry:
     *   - group_id > 0 → lookup realTeamByGroupRank["{group_id}_{rank-1}"]
     *   - group_id = 0 + rank = 2 → lấy virtual runners theo thứ tự xuất hiện
     *   - group_id = 0 + rank = 3 → lấy virtual thirds theo thứ tự xuất hiện
     *
     * @param array $manualPairings
     * @param array<string, array{team_id: int|null, team_name: string|null}> $realTeamByGroupRank
     * @param array{rank_2: array<int, array{team_id: int|null, team_name: string|null}>, rank_3: array<int, array{team_id: int|null, team_name: string|null}>} $virtualTeamByRank
     * @return array<int, array{team_id: int|null, is_virtual: bool, team_name: string|null}>
     */
    private function normalizeManualPairings(array $manualPairings, array $realTeamByGroupRank, array $virtualTeamByRank): array
    {
        if (empty($manualPairings)) {
            return [];
        }

        // Detect convention
        $maxPos = 0;
        foreach ($manualPairings as $p) {
            $maxPos = max($maxPos, (int) ($p['position'] ?? 0));
        }
        $numEntries = count($manualPairings);
        $numSlots = $numEntries > 0 ? max(1, (int) ceil(sqrt(2 * $numEntries))) : 1;
        $isOldConvention = $maxPos < $numSlots && $maxPos > 0;

        // Normalize về new convention
        foreach ($manualPairings as &$p) {
            $pos = (int) ($p['position'] ?? 0);
            $rank = (int) ($p['rank'] ?? 1);
            if ($isOldConvention) {
                $p['position'] = $pos * 2 + ($rank - 1);
            }
        }
        unset($p);

        // Sort theo (position, rank)
        usort($manualPairings, function ($a, $b) {
            $posCmp = ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
            if ($posCmp !== 0) {
                return $posCmp;
            }
            return ($a['rank'] ?? 1) <=> ($b['rank'] ?? 1);
        });

        // Resolve team_id
        $virtualCursor = [
            'rank_2' => -1, // next index = 0 cho entry đầu tiên
            'rank_3' => -1,
        ];

        $result = [];
        foreach ($manualPairings as $p) {
            $groupId = (int) ($p['group_id'] ?? 0);
            $rank = (int) ($p['rank'] ?? 1);

            if ($groupId === 0) {
                // Virtual
                $virtualKey = $rank === 2 ? 'rank_2' : ($rank === 3 ? 'rank_3' : null);
                if ($virtualKey === null) {
                    $result[] = ['team_id' => null, 'is_virtual' => true, 'team_name' => null];
                    continue;
                }

                $virtualCursor[$virtualKey]++;
                $idx = $virtualCursor[$virtualKey];
                $virtualTeam = $virtualTeamByRank[$virtualKey][$idx] ?? null;
                $teamId = $virtualTeam['team_id'] ?? null;
                $teamName = $virtualTeam['team_name'] ?? null;

                $result[] = [
                    'team_id' => $teamId,
                    'is_virtual' => true,
                    'team_name' => $teamName,
                ];
            } else {
                // Real group
                $key = $groupId . '_' . $rank;
                $realTeam = $realTeamByGroupRank[$key] ?? null;
                $teamId = $realTeam['team_id'] ?? null;
                $teamName = $realTeam['team_name'] ?? null;

                $result[] = [
                    'team_id' => $teamId,
                    'is_virtual' => false,
                    'team_name' => $teamName,
                ];
            }
        }

        return $result;
    }
}
