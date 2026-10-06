<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Club\Club;
use App\Models\Club\ClubRecurringSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubRecurringScheduleController extends Controller
{
    /**
     * GET /api/clubs/{clubId}/recurring-schedules
     * Trả về lịch sinh hoạt định kỳ. Public data, hiển thị ở tab Giới thiệu.
     */
    public function index(Request $request, $clubId)
    {
        $club = Club::with('recurringSchedules')->findOrFail($clubId);
        return ResponseHelper::success($club->recurringSchedules, 'Lấy lịch sinh hoạt thành công');
    }

    /**
     * POST /api/clubs/{clubId}/recurring-schedules
     * Body: { day_of_week, start_time, end_time, note?, position? }
     */
    public function store(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền cập nhật lịch sinh hoạt', 403);
        }

        $data = $this->validatePayload($request);

        $schedule = $club->recurringSchedules()->create($data);
        return ResponseHelper::success($schedule, 'Tạo lịch sinh hoạt thành công', 201);
    }

    /**
     * PUT /api/clubs/{clubId}/recurring-schedules/{scheduleId}
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
        $schedule->update($data);
        return ResponseHelper::success($schedule, 'Cập nhật lịch sinh hoạt thành công');
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
        ]);
    }
}
