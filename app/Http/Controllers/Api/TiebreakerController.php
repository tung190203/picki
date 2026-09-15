<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessException;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\ManualTiebreakerRank;
use App\Models\TournamentType;
use App\Services\Permission\TournamentPermission;
use App\Services\TournamentType\GroupStandingRanker;
use App\Services\TournamentType\ManualTiebreakerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API xử lý manual tiebreaker (bốc thăm / kéo-thả thủ công).
 *
 * Endpoints:
 *  - GET    /api/tournament-types/{type}/groups/{group}/pending-ties
 *      → Trả các cụm team đồng hạng cần bốc thăm.
 *  - POST   /api/tournament-types/{type}/groups/{group}/manual-tiebreaker
 *      Body: { rankings: [{ team_id, manual_rank }] }
 *      → Lưu manual ranks cho group (intra-group).
 *  - DELETE /api/tournament-types/{type}/groups/{group}/manual-tiebreaker
 *      → Reset manual ranks cho group.
 *  - POST   /api/tournament-types/{type}/groups/{group}/manual-tiebreaker/cross
 *      Body: { candidate_type: 'runner_up'|'third_place', rankings: [...] }
 *      → Lưu manual ranks cho cross-group candidates.
 *  - DELETE /api/tournament-types/{type}/groups/{group}/manual-tiebreaker/cross
 *      Body: { candidate_type: ... }
 *      → Reset cross-group manual ranks cho 1 candidate_type.
 */
class TiebreakerController extends Controller
{
    public function __construct(
        private readonly ManualTiebreakerService $manualService,
    ) {}

    /**
     * GET .../pending-ties
     * Trả các cụm team đang đồng hạng trên TẤT CẢ ranking keys (bỏ qua H2H + RANDOM).
     */
    public function pendingTies(Request $request, TournamentType $type, Group $group)
    {
        $this->ensureCanEdit($type, $request);

        $rankingRules = $this->extractRankingRules($type);
        $standings = GroupStandingRanker::rank($group, $rankingRules);

        // Lấy các cụm đồng hạng (>=2 team cùng stats trên các key thực sự)
        $clusters = $this->manualService->findTiedClusters($standings, $rankingRules);

        // Kiểm tra BTC đã set manual chưa
        $existingManual = ManualTiebreakerRank::where('tournament_type_id', $type->id)
            ->where('group_id', $group->id)
            ->whereNull('candidate_type')
            ->get()
            ->keyBy('team_id');

        return ResponseHelper::success([
            'group_id' => $group->id,
            'pending_ties' => $clusters->values(),
            'has_manual_rank' => $existingManual->isNotEmpty(),
            'manual_ranks' => $existingManual->map(fn($r) => [
                'team_id' => (int) $r->team_id,
                'manual_rank' => (int) $r->manual_rank,
            ])->values(),
        ]);
    }

    /**
     * POST .../manual-tiebreaker (intra-group)
     */
    public function store(Request $request, TournamentType $type, Group $group)
    {
        $this->ensureCanEdit($type, $request);

        $data = $request->validate([
            'rankings' => 'required|array|min:2',
            'rankings.*.team_id' => 'required|integer|min:1',
            'rankings.*.manual_rank' => 'required|integer|min:1',
        ]);

        $rankingRules = $this->extractRankingRules($type);
        $standings = GroupStandingRanker::rank($group, $rankingRules);

        // Validate: các team trong rankings phải nằm trong cùng 1 cụm đồng hạng
        $this->validateClusterForGroup($standings, $data['rankings'], $rankingRules);

        $this->manualService->storeForGroup(
            $type,
            $group,
            $data['rankings'],
            (int) $request->user()->id
        );

        return ResponseHelper::success([
            'message' => 'Đã lưu thứ hạng thủ công',
            'group_id' => $group->id,
            'tournament_type_id' => $type->id,
        ]);
    }

    /**
     * DELETE .../manual-tiebreaker (reset intra-group + cross-group)
     */
    public function destroy(Request $request, TournamentType $type, Group $group)
    {
        $this->ensureCanEdit($type, $request);
        $this->manualService->resetForGroup($type, $group);

        return ResponseHelper::success([
            'message' => 'Đã reset manual ranks',
            'group_id' => $group->id,
        ]);
    }

    /**
     * POST .../manual-tiebreaker/cross
     * Lưu manual ranks cho cross-group candidates (Nhì/Ba).
     */
    public function storeCross(Request $request, TournamentType $type, Group $group)
    {
        $this->ensureCanEdit($type, $request);

        $data = $request->validate([
            'candidate_type' => 'required|in:runner_up,third_place',
            'rankings' => 'required|array|min:2',
            'rankings.*.team_id' => 'required|integer|min:1',
            'rankings.*.manual_rank' => 'required|integer|min:1',
        ]);

        $this->manualService->storeForCandidates(
            $type,
            $group,
            $data['candidate_type'],
            $data['rankings'],
            (int) $request->user()->id
        );

        return ResponseHelper::success([
            'message' => 'Đã lưu manual ranks cho cross-group',
            'group_id' => $group->id,
            'candidate_type' => $data['candidate_type'],
        ]);
    }

    /**
     * DELETE .../manual-tiebreaker/cross
     */
    public function destroyCross(Request $request, TournamentType $type, Group $group)
    {
        $this->ensureCanEdit($type, $request);

        $data = $request->validate([
            'candidate_type' => 'required|in:runner_up,third_place',
        ]);

        $this->manualService->resetForCandidateType(
            $type,
            $group,
            $data['candidate_type']
        );

        return ResponseHelper::success([
            'message' => 'Đã reset cross-group manual ranks',
            'group_id' => $group->id,
            'candidate_type' => $data['candidate_type'],
        ]);
    }

    // ============================================
    // INTERNAL HELPERS
    // ============================================

    private function ensureCanEdit(TournamentType $type, Request $request): void
    {
        $userId = (int) $request->user()->id;
        $tournament = $type->tournament;
        if (! $tournament) {
            throw new BusinessException('Không tìm thấy giải đấu', 404);
        }
        if (! TournamentPermission::canOperateBracket($tournament, $userId)) {
            throw new BusinessException('Bạn không có quyền chỉnh sửa BXH', 403);
        }
    }

    /**
     * Trích xuất ranking rules đã chuẩn hóa (đồng bộ với TournamentTypeController::extractRankingRules).
     */
    private function extractRankingRules(TournamentType $type): array
    {
        $config = $type->format_specific_config ?? [];
        if (is_array($config) && isset($config[0])) {
            $config = $config[0];
        }

        $rules = collect($config['ranking'] ?? [1, 2, 3, 4, 7])
            ->map(fn($id) => (int) $id)
            ->toArray();

        if (! in_array(TournamentType::RANKING_POINTS_WON, $rules, true)) {
            $rules[] = TournamentType::RANKING_POINTS_WON;
        }
        if (! in_array(TournamentType::RANKING_HEAD_TO_HEAD, $rules, true)) {
            $rules[] = TournamentType::RANKING_HEAD_TO_HEAD;
        }

        return $rules;
    }

    /**
     * Validate: tất cả team trong rankings phải nằm trong CÙNG 1 cụm đồng hạng.
     *
     * @param  Collection<int, array> $standings
     * @param  array $rankings
     * @param  array $rankingRules
     * @throws BusinessException
     */
    private function validateClusterForGroup($standings, array $rankings, array $rankingRules): void
    {
        $clusters = $this->manualService->findTiedClusters($standings, $rankingRules);

        $submittedTeamIds = collect($rankings)->pluck('team_id')->map(fn($id) => (int) $id)->toArray();

        $matchedCluster = null;
        foreach ($clusters as $cluster) {
            $clusterTeamIds = collect($cluster['team_ids'])->map(fn($id) => (int) $id)->all();
            // Cụm match nếu có chứa tất cả team được submit
            $intersect = array_intersect($submittedTeamIds, $clusterTeamIds);
            if (count($intersect) === count($submittedTeamIds)) {
                $matchedCluster = $cluster;
                break;
            }
        }

        if (! $matchedCluster) {
            throw new BusinessException(
                'Các đội được chọn không nằm trong cùng một cụm đồng hạng hoặc chưa đồng hạng. Vui lòng kiểm tra lại.',
                422
            );
        }

        // Đảm bảo số team submit == số team trong cụm (hoặc ít hơn — cho phép set từng phần)
        // Không bắt buộc full cluster; cho phép BTC set rank cho subset (vd: bốc 2/3 team thực sự đồng hạng).
        // Tuy nhiên manual_rank phải là duy nhất (1..N không trùng).
        $manualRanks = collect($rankings)->pluck('manual_rank')->map(fn($r) => (int) $r);
        if ($manualRanks->count() !== $manualRanks->unique()->count()) {
            throw new BusinessException('manual_rank phải là duy nhất', 422);
        }
    }
}
