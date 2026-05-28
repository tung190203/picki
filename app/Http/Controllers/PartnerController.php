<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\User\UserPartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function __construct(
        private UserPartnerService $partnerService
    ) {}

    public function topPartners(Request $request): JsonResponse
    {
        $userId = $request->query('user_id') ? (int) $request->query('user_id') : auth()->id();

        $stats = $this->partnerService->getTopPartners($userId);

        if (empty($stats)) {
            return ResponseHelper::success(['partners' => []], 'Không có partner nào');
        }

        $userIds = collect($stats)->pluck('user_id')->all();
        $users = User::with(['sports.sport', 'sports.scores', 'clubs'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $result = collect($stats)->map(function ($stat) use ($users) {
            $user = $users->get($stat['user_id']);
            return [
                'user'          => $user ? (new UserResource($user))->resolve() : null,
                'total_matches' => $stat['total_matches'],
                'wins'          => $stat['wins'],
                'losses'        => $stat['losses'],
                'win_rate'      => $stat['win_rate'],
            ];
        })->filter(fn($item) => $item['user'] !== null)->values();

        return ResponseHelper::success(['partners' => $result], 'Lấy top partner thành công');
    }

    public function topOpponents(Request $request): JsonResponse
    {
        $userId = $request->query('user_id') ? (int) $request->query('user_id') : auth()->id();

        $stats = $this->partnerService->getTopOpponents($userId);

        if (empty($stats)) {
            return ResponseHelper::success(['opponents' => []], 'Không có opponent nào');
        }

        $userIds = collect($stats)->pluck('user_id')->all();
        $users = User::with(['sports.sport', 'sports.scores', 'clubs'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $result = collect($stats)->map(function ($stat) use ($users) {
            $user = $users->get($stat['user_id']);
            return [
                'user'          => $user ? (new UserResource($user))->resolve() : null,
                'total_matches' => $stat['total_matches'],
                'wins'          => $stat['wins'],
                'losses'        => $stat['losses'],
                'win_rate'      => $stat['win_rate'],
            ];
        })->filter(fn($item) => $item['user'] !== null)->values();

        return ResponseHelper::success(['opponents' => $result], 'Lấy top opponent thành công');
    }
}
