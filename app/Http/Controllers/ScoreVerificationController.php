<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScoreVerificationRequest;
use App\Services\ScoreVerificationService;
use Illuminate\Http\JsonResponse;

class ScoreVerificationController extends Controller
{
    public function __construct(
        private ScoreVerificationService $service
    ) {}

    public function store(StoreScoreVerificationRequest $request): JsonResponse
    {
        $user = auth()->user();

        if ($this->service->hasPendingRequest($user->id)) {
            return ResponseHelper::error(
                'Bạn đang có yêu cầu đang chờ duyệt',
                409,
                ['code' => 'PENDING_REQUEST_EXISTS']
            );
        }

        $data = $this->service->createRequest($user->id, $request->validated());

        return ResponseHelper::success($data, 'Yêu cầu xác minh đã được gửi', 201);
    }

    public function latest(): JsonResponse
    {
        $user = auth()->user();
        $data = $this->service->getLatestRequest($user->id);

        return ResponseHelper::success($data);
    }
}
