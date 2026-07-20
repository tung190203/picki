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
        $validated = $request->validated();
        $scoreType = $validated['score_type'];

        if ($this->service->hasPendingRequest($user->id, $scoreType)) {
            return ResponseHelper::error(
                'Bạn đang có yêu cầu đang chờ duyệt cho loại điểm này',
                409,
                ['code' => 'PENDING_REQUEST_EXISTS']
            );
        }

        $data = $this->service->createRequest($user->id, $validated);

        return ResponseHelper::success($data, 'Yêu cầu xác minh đã được gửi', 201);
    }
}
