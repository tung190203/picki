<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubStatus;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Club\GetClubsRequest;
use App\Http\Requests\Club\GetMonthlyLeaderboardRequest;
use App\Http\Requests\Club\LeaveClubRequest;
use App\Http\Requests\Club\StoreClubRequest;
use App\Http\Requests\Club\UpdateClubFundRequest;
use App\Http\Requests\Club\UpdateClubRequest;
use App\Http\Requests\Club\VerifyClubRequest;
use App\Http\Resources\Club\ClubLeaderboardResource;
use App\Http\Resources\Club\ClubListResource;
use App\Http\Resources\ClubResource;
use App\Models\Club\Club;
use App\Models\User;
use App\Services\Club\ClubLeaderboardService;
use App\Services\Club\ClubService;
use App\Services\GeocodingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubController extends Controller
{
    public function __construct(
        protected ClubService $clubService,
        protected ClubLeaderboardService $leaderboardService,
        protected GeocodingService $geocodingService
    ) {
    }

    public function index(GetClubsRequest $request)
    {
        $userId = auth()->id();
        $clubs = $this->clubService->searchClubs($request->validated(), $userId);

        $data = [
            'clubs' => ClubListResource::collection($clubs),
        ];

        $meta = [
            'current_page' => $clubs->currentPage(),
            'last_page'    => $clubs->lastPage(),
            'per_page'     => $clubs->perPage(),
            'total'        => $clubs->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách câu lạc bộ thành công', 200, $meta);
    }

    public function store(StoreClubRequest $request)
    {
        $userId = auth()->id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập để tạo CLB', 401);
        }

        try {
            $club = $this->clubService->createClub($request->validated(), $userId);

            $message = $club->status === ClubStatus::Draft
                ? 'Lưu bản nháp CLB thành công'
                : 'Tạo câu lạc bộ thành công';

            return ResponseHelper::success(new ClubResource($club), $message);
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function show($clubId)
    {
        try {
            // Chỉ load relations cần thiết; members được load trong getClubDetail khi user là member
            $club = Club::with(['creator', 'profile', 'mainWallet'])->findOrFail($clubId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ResponseHelper::error('Câu lạc bộ không còn tồn tại trong hệ thống', 404);
        }

        $userId = auth()->id();

        try {
            $club = $this->clubService->getClubDetail($club, $userId);
            $club->rank = $this->leaderboardService->calculateClubRank($club);
            return ResponseHelper::success(new ClubResource($club), 'Lấy thông tin câu lạc bộ thành công');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'đăng nhập') ? 401 : 403;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function update(UpdateClubRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $updatableFields = [
            'name', 'address', 'latitude', 'longitude', 'logo_url', 'status', 'is_public',
            'cover_image_url', 'description', 'phone', 'email', 'website', 'city', 'province', 'country', 'footer',
            'zalo_link', 'zalo_link_enabled', 'qr_zalo', 'qr_zalo_enabled', 'remove_qr_zalo', 'qr_code_enabled'
        ];

        $hasAnyField = $request->hasAny($updatableFields) ||
                       $request->hasFile('logo_url') ||
                       $request->hasFile('cover_image_url') ||
                       $request->hasFile('qr_code_image_url') ||
                       $request->hasFile('qr_zalo');

        if (!$hasAnyField) {
            return ResponseHelper::error('Không có trường nào được gửi lên để cập nhật', 400);
        }

        try {
            $club = $this->clubService->updateClub($club, $request->validated(), $userId);
            return ResponseHelper::success(new ClubResource($club), 'Cập nhật câu lạc bộ thành công');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'quyền') ? 403 : 400;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function destroy($clubId)
    {
        $club = Club::with('profile')->findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $this->clubService->deleteClub($club, $userId);
            return ResponseHelper::success([], 'Xóa câu lạc bộ thành công');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function restore($clubId)
    {
        $club = Club::onlyTrashed()->findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $club = $this->clubService->restoreClub($club, $userId);
            return ResponseHelper::success(
                new ClubResource($club),
                'Khôi phục câu lạc bộ thành công. Lưu ý: Tên CLB đã được thay đổi để tránh trùng lặp. Bạn có thể cập nhật lại tên nếu cần.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function leave(LeaveClubRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $result = $this->clubService->leaveClub(
                $club,
                $userId,
                $request->input('transfer_to_user_id')
            );

            if (!empty($result)) {
                return ResponseHelper::success($result, 'Bạn đã nhượng quyền quản lý và rời CLB thành công');
            }

            return ResponseHelper::success([], 'Bạn đã rời CLB');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'không phải thành viên') ? 404 : 400;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function myClubs(Request $request)
    {
        $userId = auth()->id();
        $validated = $request->validate([
            'role' => ['sometimes', 'array'],
            'role.*' => [Rule::enum(ClubMemberRole::class)],
        ]);

        // Luôn bao gồm: clubs user tạo + clubs user là Admin
        $query = Club::where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
              ->orWhereHas('members', function ($q2) use ($userId) {
                  $q2->where('user_id', $userId)
                     ->where('membership_status', ClubMembershipStatus::Joined)
                     ->where('status', \App\Enums\ClubMemberStatus::Active)
                     ->where('role', ClubMemberRole::Admin);
              });
        });

        // Nếu filter theo role cụ thể (không phải Admin) -> thêm clubs đó vào kết quả
        if (!empty($validated['role'])) {
            $rolesToAdd = array_filter($validated['role'], fn($r) => $r !== ClubMemberRole::Admin);
            if (!empty($rolesToAdd)) {
                $query->orWhereHas('members', function ($q) use ($userId, $rolesToAdd) {
                    $q->where('user_id', $userId)
                      ->where('membership_status', ClubMembershipStatus::Joined)
                      ->where('status', \App\Enums\ClubMemberStatus::Active)
                      ->whereIn('role', $rolesToAdd);
                });
            }
        }

        $clubs = $query->withFullRelations()->get();

        return ResponseHelper::success(ClubResource::collection($clubs), 'Lấy danh sách câu lạc bộ của tôi thành công');
    }

    public function getProfile($clubId)
    {
        $club = Club::with(['profile', 'creator'])->findOrFail($clubId);

        return ResponseHelper::success([
            'club_id' => $club->id,
            'name' => $club->name,
            'address' => $club->address,
            'latitude' => $club->latitude,
            'longitude' => $club->longitude,
            'logo_url' => $club->logo_url,
            'status' => $club->status,
            'profile' => ClubResource::formatProfile($club->profile),
        ], 'Lấy thông tin profile CLB thành công');
    }

    public function getFund($clubId)
    {
        $club = Club::with(['mainWallet'])->findOrFail($clubId);
        $mainWallet = $club->mainWallet;

        $fund = [
            'club_id' => $club->id,
            'main_wallet_id' => $mainWallet?->id,
            'balance' => $mainWallet?->balance ?? 0,
            'currency' => $mainWallet?->currency ?? 'VND',
            'qr_code_url' => $mainWallet?->qr_code_url,
            'total_wallets' => $club->mainWallet ? 1 : 0,
        ];

        return ResponseHelper::success($fund, 'Lấy thông tin quỹ CLB thành công');
    }

    public function updateFund(UpdateClubFundRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $result = $this->clubService->updateFund($club, $request->input('qr_code_url'), $userId);
            return ResponseHelper::success($result, 'Cập nhật thông tin quỹ CLB thành công');
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'quyền') ? 403 : 404;
            return ResponseHelper::error($e->getMessage(), $statusCode);
        }
    }

    public function verify(VerifyClubRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $user = auth()->user();

        if (!$user || $user->role !== User::ADMIN) {
            return ResponseHelper::error('Chỉ admin hệ thống mới có quyền verify CLB', 403);
        }

        $club = $this->clubService->verifyClub($club, $request->input('is_verified'));

        $message = $request->input('is_verified')
            ? 'Xác minh CLB thành công'
            : 'Hủy xác minh CLB thành công';

        return ResponseHelper::success(
            new ClubResource($club),
            $message
        );
    }

    public function searchLocation(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $results = $this->geocodingService->search($validated['query']);

        return ResponseHelper::success($results, 'Tìm kiếm địa điểm thành công');
    }

    public function detailGooglePlace(Request $request)
    {
        $validated = $request->validate([
            'place_id' => 'required|string|max:255',
        ]);

        $result = $this->geocodingService->getGooglePlaceDetail($validated['place_id']);

        return ResponseHelper::success($result, 'Lấy chi tiết địa điểm thành công');
    }

    public function getMonthlyLeaderboard(GetMonthlyLeaderboardRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);
        $perPage = $request->input('per_page', 50);

        $requestedDate = Carbon::create($year, $month, 1);
        if ($requestedDate->isFuture() && !$requestedDate->isCurrentMonth()) {
            return ResponseHelper::error('Không thể xem bảng xếp hạng của tháng trong tương lai', 400);
        }

        $rankedLeaderboard = $this->leaderboardService->getMonthlyLeaderboard($club, $month, $year);

        if ($rankedLeaderboard->isEmpty()) {
            return ResponseHelper::success([
                'club_info' => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'member_count' => 0,
                ],
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'label' => "Tháng {$month}/{$year}",
                ],
                'updated_at' => now()->toISOString(),
                'leaderboard' => [],
            ], 'Bảng xếp hạng câu lạc bộ');
        }

        // Paginate manually
        $total = $rankedLeaderboard->count();
        $currentPage = max(1, (int) $request->query('page', 1));
        $lastPage = ceil($total / $perPage);
        $offset = ($currentPage - 1) * $perPage;
        $paginatedData = $rankedLeaderboard->slice($offset, $perPage)->values();

        $response = [
            'club_info' => [
                'id' => $club->id,
                'name' => $club->name,
                'member_count' => $total,
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'label' => "Tháng {$month}/{$year}",
            ],
            'updated_at' => now()->toISOString(),
            'leaderboard' => ClubLeaderboardResource::collection($paginatedData),
        ];

        $meta = [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total,
        ];

        return ResponseHelper::success($response, 'Lấy bảng xếp hạng thành công', 200, $meta);
    }
}
