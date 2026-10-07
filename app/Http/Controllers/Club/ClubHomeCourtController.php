<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Club\Club;
use App\Services\Club\ClubHomeCourtService;
use App\Services\Club\ClubService;
use Illuminate\Http\Request;

class ClubHomeCourtController extends Controller
{
    public function __construct(
        protected ClubService $clubService,
        protected ClubHomeCourtService $homeCourtEnricher,
    ) {}

    /**
     * GET /api/clubs/{clubId}/home-courts
     * Trả về danh sách sân nhà + distance_km (tính sẵn) + events_hosted_count (đếm sẵn).
     * Ai cũng xem được.
     */
    public function index(Request $request, $clubId)
    {
        $club = Club::with('homeCourts')->findOrFail($clubId);
        $enriched = $this->homeCourtEnricher->enrichCollection($club->homeCourts, $club, $request);
        return ResponseHelper::success($enriched, 'Lấy danh sách sân nhà thành công');
    }

    /**
     * POST /api/clubs/{clubId}/home-courts
     * Body: { locations: [{ competition_location_id, position? }, ...] }
     * distance_km / events_hosted_count bị ignore (BE tự tính khi GET).
     */
    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền cập nhật sân nhà', 403);
        }

        $data = $request->validate([
            'locations' => 'required|array|max:10',
            'locations.*.competition_location_id' => 'required|integer|exists:competition_locations,id',
            'locations.*.position' => 'nullable|integer|min:0',
        ]);

        $club = $this->clubService->setHomeCourts($club, $data['locations']);

        $enriched = $this->homeCourtEnricher->enrichCollection($club->homeCourts, $club, $request);
        return ResponseHelper::success($enriched, 'Cập nhật sân nhà thành công');
    }

    /**
     * PATCH /api/clubs/{clubId}/home-courts/{homeCourtId}
     * Cập nhật position. distance_km / events_hosted_count bị ignore.
     */
    public function update(Request $request, $clubId, $homeCourtId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền cập nhật sân nhà', 403);
        }

        $data = $request->validate([
            'position' => 'nullable|integer|min:0',
        ]);

        $row = \Illuminate\Support\Facades\DB::table('club_competition_locations')
            ->where('club_id', $club->id)
            ->where('id', $homeCourtId)
            ->first();

        if (!$row) {
            return ResponseHelper::error('Sân nhà không tồn tại', 404);
        }

        \Illuminate\Support\Facades\DB::table('club_competition_locations')
            ->where('id', $homeCourtId)
            ->update(array_merge($data, ['updated_at' => now()]));

        return ResponseHelper::success(null, 'Cập nhật sân nhà thành công');
    }

    /**
     * DELETE /api/clubs/{clubId}/home-courts/{homeCourtId}
     */
    public function destroy(Request $request, $clubId, $homeCourtId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xóa sân nhà', 403);
        }

        $deleted = \Illuminate\Support\Facades\DB::table('club_competition_locations')
            ->where('club_id', $club->id)
            ->where('id', $homeCourtId)
            ->delete();

        if (!$deleted) {
            return ResponseHelper::error('Sân nhà không tồn tại', 404);
        }

        return ResponseHelper::success([], 'Đã xóa sân nhà');
    }
}
