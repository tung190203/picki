<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Tournament;
use App\Models\TournamentCourt;
use App\Services\Tournament\CourtBoardService;
use App\Services\Tournament\TournamentCourtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TournamentCourtController extends Controller
{
    public function __construct(
        private TournamentCourtService $courtService,
        private CourtBoardService $boardService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        $tournament = Tournament::with('staff')->findOrFail($id);
        if (!$tournament->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền quản lý sân của giải đấu này.', 403);
        }

        return ResponseHelper::success($this->courtService->listCourts($tournament));
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $tournament = Tournament::with('staff')->findOrFail($id);
        if (!$tournament->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền quản lý sân của giải đấu này.', 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:40',
        ]);

        $court = $this->courtService->createCourt($tournament, $validated['name'] ?? null);
        $court->load('referee:id,full_name,avatar_url');

        return ResponseHelper::success($this->courtService->formatCourt($court), 'Thêm sân thành công', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $court = TournamentCourt::with('tournament.staff')->findOrFail($id);
        if (!$court->tournament->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền sửa sân của giải đấu này.', 403);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:40',
            'referee_user_id' => 'nullable|integer|exists:users,id',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Preserve explicit null for referee_user_id (unassign)
        $payload = [];
        if ($request->has('name')) {
            $payload['name'] = $validated['name'];
        }
        if ($request->exists('referee_user_id')) {
            $payload['referee_user_id'] = $validated['referee_user_id'] ?? null;
        }
        if ($request->has('sort_order')) {
            $payload['sort_order'] = $validated['sort_order'];
        }

        $updated = $this->courtService->updateCourt($court, $payload, $request->user()->id);

        return ResponseHelper::success($this->courtService->formatCourt($updated), 'Cập nhật sân thành công');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $court = TournamentCourt::with('tournament.staff')->findOrFail($id);
        if (!$court->tournament->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền xoá sân của giải đấu này.', 403);
        }

        try {
            $this->courtService->deleteCourt($court, $request->user()->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 409) {
                return ResponseHelper::error('Không thể xoá sân đang có trận Đang đấu.', 409, [
                    'error_code' => 'COURT_HAS_LIVE_MATCH',
                ]);
            }
            throw $e;
        }

        return ResponseHelper::success(null, 'Xoá sân thành công');
    }

    public function copy(Request $request, int $id): JsonResponse
    {
        $target = Tournament::with('staff')->findOrFail($id);
        if (!$target->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền quản lý sân của giải đấu đích.', 403);
        }

        $validated = $request->validate([
            'from_tournament_id' => 'required|integer|exists:tournaments,id',
        ]);

        $source = Tournament::with('staff')->findOrFail((int) $validated['from_tournament_id']);
        if (!$source->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền Admin/BTC ở giải đấu nguồn.', 403);
        }

        try {
            $result = $this->courtService->copyFromTournament($target, $source, $request->user()->id);
        } catch (\RuntimeException $e) {
            if ($e->getCode() === 409) {
                return ResponseHelper::error('Không thể sao chép khi giải hiện tại đang có trận Đang đấu.', 409, [
                    'error_code' => 'COURT_HAS_LIVE_MATCH',
                ]);
            }
            throw $e;
        }

        return ResponseHelper::success($result, 'Sao chép sân & trọng tài thành công');
    }

    public function board(Request $request, int $id): JsonResponse
    {
        $tournament = Tournament::with(['staff', 'tournamentTypes'])->findOrFail($id);
        if (!$tournament->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền xem bảng sân của giải đấu này.', 403);
        }

        return ResponseHelper::success($this->boardService->getBoard($tournament));
    }

    public function saveUnit(Request $request, int $id): JsonResponse
    {
        $tournament = Tournament::with(['staff', 'tournamentTypes'])->findOrFail($id);
        if (!$tournament->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền chia sân cho giải đấu này.', 403);
        }

        $validated = $request->validate([
            'unit_type' => 'required|string|in:group,slot',
            'group_id' => 'required_if:unit_type,group|nullable|integer',
            'slot_round' => 'required_if:unit_type,slot|nullable|string|max:10',
            'slot_no' => 'required_if:unit_type,slot|nullable|integer|min:1',
            'court_ids' => 'present|array',
            'court_ids.*' => 'integer|exists:tournament_courts,id',
        ]);

        $board = $this->boardService->saveUnit($tournament, $validated, $request->user()->id);

        return ResponseHelper::success($board, 'Cập nhật phân sân thành công');
    }

    public function auto(Request $request, int $id): JsonResponse
    {
        $tournament = Tournament::with(['staff', 'tournamentTypes'])->findOrFail($id);
        if (!$tournament->hasOrganizerOrStaff($request->user()->id)) {
            return ResponseHelper::error('Bạn không có quyền chia sân tự động cho giải đấu này.', 403);
        }

        $board = $this->boardService->autoAssign($tournament, $request->user()->id);

        return ResponseHelper::success($board, 'Chia sân tự động thành công');
    }
}
