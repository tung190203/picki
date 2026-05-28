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
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 3; // Luôn chỉ lấy top 3

        $result = $this->partnerService->getTopPartners($userId, $page, $perPage);

        if (empty($result['data'])) {
            return ResponseHelper::success([
                'partners' => [],
                'meta' => ['current_page' => $page, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
            ], 'Không có partner nào');
        }

        $userIds = collect($result['data'])->pluck('user_id')->all();
        $users = User::with(['sports.sport', 'sports.scores', 'clubs'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $partners = collect($result['data'])->map(function ($stat) use ($users) {
            $user = $users->get($stat['user_id']);
            return [
                'user'          => $user ? (new UserResource($user))->resolve() : null,
                'total_matches' => $stat['total_matches'],
                'wins'          => $stat['wins'],
                'losses'        => $stat['losses'],
                'win_rate'      => $stat['win_rate'],
            ];
        })->filter(fn($item) => $item['user'] !== null)->values();

        $total = $result['total'];
        $lastPage = (int) ceil($total / $perPage);

        return ResponseHelper::success([
            'partners' => $partners,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ], 'Lấy top 3 partner thành công');
    }

    public function topOpponents(Request $request): JsonResponse
    {
        $userId = $request->query('user_id') ? (int) $request->query('user_id') : auth()->id();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 3; // Luôn chỉ lấy top 3

        $result = $this->partnerService->getTopOpponents($userId, $page, $perPage);

        if (empty($result['data'])) {
            return ResponseHelper::success([
                'opponents' => [],
                'meta' => ['current_page' => $page, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0],
            ], 'Không có opponent nào');
        }

        $userIds = collect($result['data'])->pluck('user_id')->all();
        $users = User::with(['sports.sport', 'sports.scores', 'clubs'])
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        $opponents = collect($result['data'])->map(function ($stat) use ($users) {
            $user = $users->get($stat['user_id']);
            return [
                'user'          => $user ? (new UserResource($user))->resolve() : null,
                'total_matches' => $stat['total_matches'],
                'wins'          => $stat['wins'],
                'losses'        => $stat['losses'],
                'win_rate'      => $stat['win_rate'],
            ];
        })->filter(fn($item) => $item['user'] !== null)->values();

        $total = $result['total'];
        $lastPage = (int) ceil($total / $perPage);

        return ResponseHelper::success([
            'opponents' => $opponents,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ], 'Lấy top 3 opponent thành công');
    }
}
