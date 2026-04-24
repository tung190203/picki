<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubReportReasonType;
use App\Enums\ClubReportStatus;
use App\Events\SuperAdmin\ReportCreated;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\StoreClubReportRequest;
use App\Http\Resources\Club\ClubReportResource;
use App\Models\Club\Club;
use App\Services\Club\ClubReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubReportController extends Controller
{
    public function __construct(
        protected ClubReportService $reportService
    ) {
    }

    /**
     * Báo cáo CLB (user đăng nhập, gửi báo cáo gọn - không cần lý do)
     */
    public function store(StoreClubReportRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập để báo cáo', 401);
        }

        if ($this->reportService->hasUserReportedClub($club, $userId)) {
            return ResponseHelper::error('Bạn đã báo cáo CLB này và đang chờ xử lý', 422);
        }

        $report = $this->reportService->createReport($club, $userId, $request->validated());
        $report->load(['reporter', 'club']);

        ReportCreated::dispatch(
            $report->id,
            $report->report_type ?? 'general',
            $report->content ?? '',
            $club->id
        );

        return ResponseHelper::success(
            new ClubReportResource($report),
            'Gửi báo cáo thành công. Chúng tôi sẽ xem xét và xử lý sớm nhất.',
            201
        );
    }

    /**
     * Danh sách báo cáo của CLB (admin/manager/secretary)
     */
    public function index(Request $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền xem báo cáo', 403);
        }

        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(ClubReportStatus::class)],
            'reason_type' => ['sometimes', Rule::enum(ClubReportReasonType::class)],
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $reports = $this->reportService->getReports($club, $validated);

        $data = ['reports' => ClubReportResource::collection($reports)];
        $meta = [
            'current_page' => $reports->currentPage(),
            'per_page' => $reports->perPage(),
            'total' => $reports->total(),
            'last_page' => $reports->lastPage(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách báo cáo thành công', 200, $meta);
    }
}
