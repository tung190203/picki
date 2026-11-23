<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Matches;
use App\Services\Tournament\RefereeMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefereeController extends Controller
{
    public function __construct(
        private RefereeMatchService $refereeService,
    ) {}

    public function tournaments(Request $request): JsonResponse
    {
        $tournaments = $this->refereeService->getRefereeTournaments($request->user());

        return ResponseHelper::success($tournaments);
    }

    public function matches(Request $request): JsonResponse
    {
        $raw = $request->query('tournament_ids', '');
        $ids = is_array($raw)
            ? $raw
            : array_filter(explode(',', (string) $raw), fn ($v) => $v !== '');

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            return ResponseHelper::error('Vui lòng chọn ít nhất 1 giải đấu.', 422);
        }

        $data = $this->refereeService->getMergedMatches($request->user(), $ids);

        return ResponseHelper::success($data);
    }

    public function claim(Request $request, int $id): JsonResponse
    {
        try {
            $match = $this->refereeService->claimMatch($id, $request->user());
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'MATCH_NOT_FOUND' => ResponseHelper::error('Không tìm thấy trận đấu.', 404),
                'ALREADY_CLAIMED' => ResponseHelper::error('Trận đã có người bắt.', 409, ['error_code' => 'ALREADY_CLAIMED']),
                'NOT_YOUR_COURT' => ResponseHelper::error('Bạn không phụ trách sân của trận này.', 403, ['error_code' => 'NOT_YOUR_COURT']),
                'PLAYER_BUSY' => ResponseHelper::error('VĐV của trận này đang thi đấu ở sân khác.', 409, ['error_code' => 'PLAYER_BUSY']),
                default => throw $e,
            };
        }

        return ResponseHelper::success([
            'id' => $match->id,
            'live_status' => $match->live_status,
            'referee_id' => $match->referee_id,
            'started_at' => $match->started_at?->toISOString(),
        ], 'Bắt trận thành công');
    }

    public function move(Request $request, int $id): JsonResponse
    {
        $match = Matches::with('tournamentType.tournament')->findOrFail($id);

        $validated = $request->validate([
            'court_id' => 'nullable|integer|exists:tournament_courts,id',
            'to_top' => 'sometimes|boolean',
        ]);

        try {
            $updated = $this->refereeService->moveMatch(
                $match,
                $request->user(),
                $validated['court_id'] ?? null,
                (bool) ($validated['to_top'] ?? false),
            );
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'MATCH_NOT_WAITING' => ResponseHelper::error('Chỉ chuyển được trận đang ở trạng thái chờ.', 409),
                'FORBIDDEN' => ResponseHelper::error('Bạn không có quyền chuyển trận này.', 403),
                'TOURNAMENT_NOT_FOUND' => ResponseHelper::error('Không tìm thấy giải đấu.', 404),
                default => throw $e,
            };
        }

        return ResponseHelper::success([
            'id' => $updated->id,
            'court_id' => $updated->court_id,
            'referee_id' => $updated->referee_id,
            'priority_at' => $updated->priority_at?->toISOString(),
        ], 'Chuyển trận thành công');
    }

    public function reassign(Request $request, int $id): JsonResponse
    {
        $match = Matches::with('tournamentType.tournament')->findOrFail($id);

        $validated = $request->validate([
            'referee_user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $updated = $this->refereeService->reassignMatch(
                $match,
                $request->user(),
                (int) $validated['referee_user_id'],
            );
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'MATCH_NOT_LIVE' => ResponseHelper::error('Chỉ đổi trọng tài cho trận đang diễn ra.', 409),
                'FORBIDDEN' => ResponseHelper::error('Bạn không có quyền đổi trọng tài.', 403),
                'TOURNAMENT_NOT_FOUND' => ResponseHelper::error('Không tìm thấy giải đấu.', 404),
                default => throw $e,
            };
        }

        return ResponseHelper::success([
            'id' => $updated->id,
            'referee_id' => $updated->referee_id,
        ], 'Đổi trọng tài thành công');
    }
}
