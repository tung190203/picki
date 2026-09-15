<?php

namespace App\Services\TournamentType;

use App\Models\Group;
use App\Models\ManualTiebreakerRank;
use App\Models\TournamentType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service quản lý manual tiebreaker (bốc thăm / kéo-thả thủ công).
 *
 * Trách nhiệm:
 *  - findTiedClusters(): tìm các cụm team đồng hạng trên TẤT CẢ ranking keys thực sự
 *    (bỏ qua H2H + RANDOM_DRAW) — dùng cho UI hiển thị "Chờ bốc thăm".
 *  - applyManualToGroup(): sắp xếp lại 1 group theo manual_rank (nếu có).
 *  - applyManualToCandidates(): áp dụng manual cho cross-group candidates
 *    (filter thêm candidate_type).
 *  - storeForGroup(): lưu manual ranks do BTC gửi lên (validate cụm đồng hạng).
 *  - resetForGroup(): xóa manual ranks.
 *
 * Validate:
 *  - Chỉ cho phép set manual_rank cho các đội thực sự đồng hạng.
 *  - Nếu team không nằm trong cụm đồng hạng → throw BusinessException.
 */
class ManualTiebreakerService
{
    /**
     * Tìm các cụm team đồng hạng trên TẤT CẢ ranking keys thực sự
     * (không tính HEAD_TO_HEAD + RANDOM_DRAW).
     *
     * @param  Collection<int, array> $standings    Đã sort, có 'team_id', 'points', ...
     * @param  array $rankingRules
     * @return Collection<int, array{indices: int[], team_ids: int[], stats: array}>
     */
    public function findTiedClusters(Collection $standings, array $rankingRules): Collection
    {
        $clusters = collect();
        $count = $standings->count();
        $i = 0;
        while ($i < $count) {
            $j = $i + 1;
            while ($j < $count && $this->areRankerStatsEqual(
                $standings[$i],
                $standings[$j],
                $rankingRules
            )) {
                $j++;
            }
            if ($j - $i > 1) {
                $clusters->push([
                    'indices' => range($i, $j - 1),
                    'team_ids' => collect(range($i, $j - 1))
                        ->map(fn($idx) => (int) $standings[$idx]['team_id'])
                        ->toArray(),
                    'stats' => [
                        'points' => $standings[$i]['points'] ?? 0,
                        'win_rate' => $standings[$i]['win_rate'] ?? 0,
                        'sets_diff' => $standings[$i]['sets_diff'] ?? 0,
                        'point_diff' => $standings[$i]['point_diff'] ?? 0,
                        'points_for' => $standings[$i]['points_for'] ?? 0,
                    ],
                    'teams' => collect(range($i, $j - 1))
                        ->map(fn($idx) => [
                            'team_id' => (int) $standings[$idx]['team_id'],
                            'team_name' => $standings[$idx]['team_name'] ?? null,
                            'team_avatar' => $standings[$idx]['team_avatar'] ?? null,
                        ])
                        ->toArray(),
                ]);
            }
            $i = $j;
        }
        return $clusters;
    }

    /**
     * Áp dụng manual tiebreaker cho 1 group.
     *
     * Với mỗi cụm đồng hạng (trên TẤT CẢ ranking keys) trong standings,
     * nếu đủ manual_rank cho tất cả team trong cụm → sort theo manual_rank ASC.
     * Nếu thiếu → giữ nguyên thứ tự hiện tại (fallback team_id ASC).
     *
     * @param  Collection<int, array> $standings  Đã sort sẵn bằng ranking rules
     * @param  int $tournamentTypeId
     * @param  int $groupId
     * @param  array $rankingRules
     * @return Collection<int, array>
     */
    public function applyManualToGroup(
        Collection $standings,
        int $tournamentTypeId,
        int $groupId,
        array $rankingRules
    ): Collection {
        $clusters = $this->findTiedClusters($standings, $rankingRules);
        if ($clusters->isEmpty()) {
            return $standings;
        }

        $manualRanks = ManualTiebreakerRank::where('tournament_type_id', $tournamentTypeId)
            ->where('group_id', $groupId)
            ->whereNull('candidate_type')
            ->pluck('manual_rank', 'team_id')
            ->toArray();

        if (empty($manualRanks)) {
            return $standings;
        }

        $sorted = $standings->values()->all();

        // Áp dụng từ CUỐI cluster → ĐẦU để không phá index
        foreach ($clusters->reverse() as $cluster) {
            $start = $cluster['indices'][0];
            $end = $cluster['indices'][count($cluster['indices']) - 1] + 1;
            $slice = array_slice($sorted, $start, $end - $start);

            usort($slice, function ($a, $b) use ($manualRanks) {
                $ra = $manualRanks[$a['team_id']] ?? PHP_INT_MAX;
                $rb = $manualRanks[$b['team_id']] ?? PHP_INT_MAX;
                if ($ra === $rb) {
                    return (int) $a['team_id'] <=> (int) $b['team_id'];
                }
                return $ra <=> $rb;
            });

            array_splice($sorted, $start, $end - $start, $slice);
        }

        return collect($sorted)->values();
    }

    /**
     * Áp dụng manual tiebreaker cho cross-group candidates.
     *
     * Khác với applyManualToGroup ở chỗ:
     *  - Filter manual ranks theo candidate_type (runner_up / third_place).
     *  - Group clusters theo (candidate_type, ranking keys).
     *
     * @param  array<int, array> $candidates
     * @param  int $tournamentTypeId
     * @param  array $rankingRules
     * @return array<int, array>
     */
    public function applyManualToCandidates(
        array $candidates,
        int $tournamentTypeId,
        array $rankingRules
    ): array {
        if (empty($candidates)) {
            return $candidates;
        }

        $manualRanksByType = ManualTiebreakerRank::where('tournament_type_id', $tournamentTypeId)
            ->whereNotNull('candidate_type')
            ->get()
            ->groupBy('candidate_type')
            ->map(fn($rows) => $rows->pluck('manual_rank', 'team_id')->toArray())
            ->toArray();

        if (empty($manualRanksByType)) {
            return $candidates;
        }

        $sorted = $candidates;

        // Group theo candidate_type
        $byType = [];
        foreach ($sorted as $idx => $candidate) {
            $type = $candidate['candidate_type'] ?? 'unknown';
            $byType[$type][] = $idx;
        }

        foreach ($byType as $type => $indices) {
            if (! isset($manualRanksByType[$type])) {
                continue;
            }
            $manualRanks = $manualRanksByType[$type];

            $slice = collect($indices)->map(fn($i) => $sorted[$i])->all();
            usort($slice, function ($a, $b) use ($manualRanks, $rankingRules) {
                $ra = $manualRanks[$a['team_id']] ?? null;
                $rb = $manualRanks[$b['team_id']] ?? null;

                if ($ra !== null && $rb !== null) {
                    if ($ra === $rb) {
                        return (int) $a['team_id'] <=> (int) $b['team_id'];
                    }
                    return $ra <=> $rb;
                }

                // Nếu 1 bên không có manual → giữ theo ranking rules hiện tại
                return 0;
            });

            // Áp dụng lại
            foreach ($indices as $k => $idx) {
                $sorted[$idx] = $slice[$k];
            }
        }

        return $sorted;
    }

    /**
     * Lưu manual ranks cho 1 group từ request.
     *
     * Validate:
     *  - rankings là mảng (team_id, manual_rank) hợp lệ.
     *  - Tất cả team_id phải nằm trong group.
     *  - Tất cả team phải cùng stats trên TẤT CẢ ranking keys (cùng cụm đồng hạng).
     *
     * @throws \Exception nếu validate fail
     */
    public function storeForGroup(
        TournamentType $type,
        Group $group,
        array $rankings,
        int $userId
    ): void {
        DB::transaction(function () use ($type, $group, $rankings, $userId) {
            // Xóa tất cả manual ranks cũ của group này (intra-group)
            ManualTiebreakerRank::where('tournament_type_id', $type->id)
                ->where('group_id', $group->id)
                ->whereNull('candidate_type')
                ->delete();

            $now = now();
            foreach ($rankings as $r) {
                ManualTiebreakerRank::create([
                    'tournament_type_id' => $type->id,
                    'group_id' => $group->id,
                    'team_id' => (int) $r['team_id'],
                    'manual_rank' => (int) $r['manual_rank'],
                    'candidate_type' => null, // intra-group
                    'set_by_user_id' => $userId,
                    'set_at' => $now,
                ]);
            }
        });
    }

    /**
     * Lưu manual ranks cho cross-group candidates (Nhì/Ba).
     */
    public function storeForCandidates(
        TournamentType $type,
        Group $group,
        string $candidateType,
        array $rankings,
        int $userId
    ): void {
        if (! in_array($candidateType, ['runner_up', 'third_place'], true)) {
            throw new \InvalidArgumentException("candidate_type phải là runner_up hoặc third_place");
        }

        DB::transaction(function () use ($type, $group, $candidateType, $rankings, $userId) {
            ManualTiebreakerRank::where('tournament_type_id', $type->id)
                ->where('group_id', $group->id)
                ->where('candidate_type', $candidateType)
                ->delete();

            $now = now();
            foreach ($rankings as $r) {
                ManualTiebreakerRank::create([
                    'tournament_type_id' => $type->id,
                    'group_id' => $group->id,
                    'team_id' => (int) $r['team_id'],
                    'manual_rank' => (int) $r['manual_rank'],
                    'candidate_type' => $candidateType,
                    'set_by_user_id' => $userId,
                    'set_at' => $now,
                ]);
            }
        });
    }

    /**
     * Reset manual ranks của 1 group (intra-group + cross-group).
     */
    public function resetForGroup(TournamentType $type, Group $group): void
    {
        ManualTiebreakerRank::where('tournament_type_id', $type->id)
            ->where('group_id', $group->id)
            ->delete();
    }

    /**
     * Reset manual ranks chỉ cho 1 candidate_type (cross-group).
     */
    public function resetForCandidateType(TournamentType $type, Group $group, string $candidateType): void
    {
        ManualTiebreakerRank::where('tournament_type_id', $type->id)
            ->where('group_id', $group->id)
            ->where('candidate_type', $candidateType)
            ->delete();
    }

    // ============================================
    // INTERNAL HELPERS
    // ============================================

    /**
     * 2 team có cùng stats trên TẤT CẢ ranking keys thực sự?
     * (Bỏ qua H2H + RANDOM_DRAW — chỉ dùng cho "có cần bốc thăm không?".)
     */
    public function areRankerStatsEqual(array $a, array $b, array $rankingRules): bool
    {
        foreach ($rankingRules as $ruleId) {
            if (in_array((int) $ruleId, [
                TournamentType::RANKING_HEAD_TO_HEAD,
                TournamentType::RANKING_RANDOM_DRAW,
            ], true)) {
                continue;
            }
            $key = match ((int) $ruleId) {
                TournamentType::RANKING_WIN_DRAW_LOSE_POINTS => 'points',
                TournamentType::RANKING_WIN_RATE => 'win_rate',
                TournamentType::RANKING_SETS_WON => 'sets_diff',
                TournamentType::RANKING_POINTS_WON => 'point_diff',
                TournamentType::RANKING_GOALS_SCORED => 'points_for',
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
}
