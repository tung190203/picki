<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveScoreVerificationRequest;
use App\Http\Requests\RejectScoreVerificationRequest;
use App\Repositories\ScoreVerificationRepository;
use App\Services\ScoreVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScoreVerificationManagementController extends Controller
{
    public function __construct(
        private ScoreVerificationService $service,
        private ScoreVerificationRepository $repository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'score_type', 'keyword', 'from_date', 'to_date']);
        $perPage = min((int) $request->get('per_page', 20), 100);

        $summary = $this->repository->getSummary();
        $list = $this->service->getList($filters, $perPage);

        return ResponseHelper::success([
            'summary' => $summary,
            'items' => $list['items'],
            'meta' => $list['meta'],
        ]);
    }

    public function show(int $verification): JsonResponse
    {
        $data = $this->service->getDetail($verification);

        return ResponseHelper::success($data);
    }

    public function approve(ApproveScoreVerificationRequest $request, int $verification): JsonResponse
    {
        $scoreRequest = $this->repository->findOrFail($verification);

        $result = $this->service->approveRequest(
            $scoreRequest,
            auth()->id(),
            $request->boolean('award_anchor_badge', false)
        );

        return ResponseHelper::success($result, 'Đã duyệt yêu cầu');
    }

    public function reject(RejectScoreVerificationRequest $request, int $verification): JsonResponse
    {
        $scoreRequest = $this->repository->findOrFail($verification);

        $result = $this->service->rejectRequest(
            $scoreRequest,
            auth()->id(),
            $request->validated('reason')
        );

        return ResponseHelper::success($result, 'Đã từ chối yêu cầu');
    }
}
