<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Club\Club;
use App\Models\Club\ClubRecurringSchedule;
use Illuminate\Http\Request;

class ClubRecurringScheduleController extends Controller
{
    /**
     * GET /api/clubs/{clubId}/recurring-schedules
     * Trả về lịch sinh hoạt định kỳ kèm danh sách sân nhà gắn với từng lịch.
     * Public data, hiển thị ở tab Giới thiệu.
     */
    public function index(Request $request, $clubId)
    {
        $club = Club::with(['recurringSchedules.homeCourts'])->findOrFail($clubId);
        $payload = $club->recurringSchedules->map(fn ($s) => $this->serialize($s))->values();
        return ResponseHelper::success($payload, 'Lấy lịch sinh hoạt thành công');
    }

    /**
     * POST /api/clubs/{clubId}/recurring-schedules
     * Body: { day_of_week, start_time, end_time, note?, position?, competition_location_ids? (array<int>) }
     * Nếu competition_location_ids rỗng / không gửi → lịch áp dụng cho toàn bộ sân nhà.
     */
    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền cập nhật lịch sinh hoạt', 403);
        }

        $data = $this->validatePayload($request);
        $locationIds = $this->resolveLocationIds($club, $data['competition_location_ids'] ?? null);
        unset($data['competition_location_ids']);

        $schedule = $club->recurringSchedules()->create($data);
        if (!empty($locationIds)) {
            $schedule->homeCourts()->sync($locationIds);
        }
        $schedule->load('homeCourts');

        return ResponseHelper::success($this->serialize($schedule), 'Tạo lịch sinh hoạt thành công', 201);
    }

    /**
     * PUT/PATCH /api/clubs/{clubId}/recurring-schedules/{scheduleId}
     */
    public function update(Request $request, $clubId, $scheduleId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền cập nhật lịch sinh hoạt', 403);
        }

        $schedule = $club->recurringSchedules()->find($scheduleId);
        if (!$schedule) {
            return ResponseHelper::error('Lịch sinh hoạt không tồn tại', 404);
        }

        $data = $this->validatePayload($request, true);
        $locationIds = $this->resolveLocationIds($club, $data['competition_location_ids'] ?? null);
        unset($data['competition_location_ids']);

        $schedule->update($data);
        // sync (kể cả rỗng) — rỗng nghĩa là lịch áp dụng cho toàn bộ sân nhà
        $schedule->homeCourts()->sync($locationIds);
        $schedule->load('homeCourts');

        return ResponseHelper::success($this->serialize($schedule), 'Cập nhật lịch sinh hoạt thành công');
    }

    /**
     * DELETE /api/clubs/{clubId}/recurring-schedules/{scheduleId}
     */
    public function destroy(Request $request, $clubId, $scheduleId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xóa lịch sinh hoạt', 403);
        }

        $deleted = $club->recurringSchedules()->where('id', $scheduleId)->delete();
        if (!$deleted) {
            return ResponseHelper::error('Lịch sinh hoạt không tồn tại', 404);
        }

        return ResponseHelper::success([], 'Đã xóa lịch sinh hoạt');
    }

    protected function validatePayload(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'day_of_week' => "$required|integer|min:0|max:6",
            'start_time' => "$required|date_format:H:i",
            'end_time' => "$required|date_format:H:i|after:start_time",
            'note' => 'nullable|string|max:255',
            'position' => 'nullable|integer|min:0',
            'competition_location_ids' => 'nullable|array',
            'competition_location_ids.*' => 'integer|exists:competition_locations,id',
        ]);
    }

    /**
     * Lọc location_ids: chỉ giữ những id thuộc sân nhà của CLB. Trả về mảng unique int.
     */
    protected function resolveLocationIds(Club $club, ?array $ids): array
    {
        if (empty($ids)) return [];
        $valid = $club->homeCourts()->pluck('competition_locations.id')->all();
        return array_values(array_unique(array_intersect(array_map('intval', $ids), $valid)));
    }

    protected function serialize(ClubRecurringSchedule $schedule): array
    {
        return [
            'id' => (int) $schedule->id,
            'club_id' => (int) $schedule->club_id,
            'day_of_week' => (int) $schedule->day_of_week,
            'start_time' => substr((string) $schedule->start_time, 0, 5),
            'end_time' => substr((string) $schedule->end_time, 0, 5),
            'note' => $schedule->note,
            'position' => (int) ($schedule->position ?? 0),
            'competition_location_ids' => $schedule->homeCourts->pluck('id')->map(fn ($v) => (int) $v)->values()->all(),
            'home_courts' => $schedule->homeCourts->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => $c->name,
                'address' => $c->address,
            ])->values()->all(),
        ];
    }
}
