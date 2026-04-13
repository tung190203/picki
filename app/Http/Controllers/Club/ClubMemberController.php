<?php

namespace App\Http\Controllers\Club;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Club\GetMembersRequest;
use App\Http\Requests\Club\InviteMemberRequest;
use App\Http\Requests\Club\UpdateMemberRequest;
use App\Http\Resources\Club\ClubMemberResource;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use App\Services\Club\ClubMemberManagementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClubMemberController extends Controller
{
    public function __construct(
        protected ClubMemberManagementService $memberManagementService
    ) {
    }

    public function index(GetMembersRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);

        $members = $this->memberManagementService->getMembers($club, $request->validated());
        $statistics = $this->memberManagementService->getMemberStatistics($club);

        $data = [
            'members' => ClubMemberResource::collection($members),
            'statistics' => $statistics,
        ];
        $meta = [
            'current_page' => $members->currentPage(),
            'per_page' => $members->perPage(),
            'total' => $members->total(),
            'last_page' => $members->lastPage(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách thành viên thành công', 200, $meta);
    }

    public function store(InviteMemberRequest $request, $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = auth()->id();

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền thêm thành viên', 403);
        }

        try {
            $member = $this->memberManagementService->inviteMember($club, $request->validated(), $userId);
            $member->load(['user' => User::FULL_RELATIONS, 'club', 'inviter', 'reviewer']);

            return ResponseHelper::success(
                new ClubMemberResource($member),
                $member->status->value === \App\Enums\ClubMemberStatus::Active->value
                    ? 'Đã thêm thành viên vào CLB thành công'
                    : 'Đã gửi lời mời tham gia CLB, chờ user đồng ý',
                201
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        }
    }

    public function show($clubId, $memberId)
    {
        $member = ClubMember::where('club_id', $clubId)
            ->with(['user' => User::FULL_RELATIONS, 'club', 'reviewer'])
            ->find($memberId);

        if (!$member) {
            return ResponseHelper::error('Không tìm thấy thành viên trong CLB này', 404);
        }

        return ResponseHelper::success(new ClubMemberResource($member), 'Lấy thông tin thành viên thành công');
    }

    public function update(UpdateMemberRequest $request, $clubId, $memberId)
    {
        $member = ClubMember::where('club_id', $clubId)->find($memberId);

        if (!$member) {
            return ResponseHelper::error('Không tìm thấy thành viên trong CLB này', 404);
        }
        $userId = auth()->id();
        $club = $member->club;

        $isSelfUpdate = $member->user_id === $userId;
        $canManage = $club->canManage($userId);

        if (!$canManage && !$isSelfUpdate) {
            return ResponseHelper::error('Không có quyền thực hiện thao tác này', 403);
        }

        try {
            $member = $this->memberManagementService->updateMember($member, $request->validated(), $userId, $club);
            $member->load(['user' => User::FULL_RELATIONS, 'reviewer']);

            return ResponseHelper::success(new ClubMemberResource($member), 'Cập nhật thành viên thành công');
        } catch (\Exception $e) {
            if ($e->getMessage() === 'DELETED') {
                return ResponseHelper::success([], 'Đã từ chối thành viên');
            }
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    public function destroy($clubId, $memberId)
    {
        $member = ClubMember::where('club_id', $clubId)->find($memberId);

        if (!$member) {
            return ResponseHelper::error('Không tìm thấy thành viên trong CLB này', 404);
        }
        $userId = auth()->id();
        $club = $member->club;

        if (!$club->canManage($userId)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền thực hiện', 403);
        }

        try {
            if ($member->membership_status === \App\Enums\ClubMembershipStatus::Pending && $member->invited_by === $userId) {
                $this->memberManagementService->cancelInvitation($member, $userId);
                return ResponseHelper::success([], 'Đã hủy lời mời tham gia CLB');
            }

            if ($member->membership_status !== \App\Enums\ClubMembershipStatus::Joined || $member->status !== \App\Enums\ClubMemberStatus::Active) {
                return ResponseHelper::error('Chỉ có thể đuổi thành viên đang tham gia hoặc hủy lời mời do chính bạn gửi', 400);
            }

            $this->memberManagementService->kickMember($member, $userId);
            return ResponseHelper::success([], 'Đã đuổi thành viên khỏi CLB');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        }
    }

    public function statistics($clubId)
    {
        $club = Club::findOrFail($clubId);
        $statistics = $this->memberManagementService->getMemberStatistics($club);

        return ResponseHelper::success($statistics, 'Lấy thống kê thành viên thành công');
    }

    public function getCandidates(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'scope' => 'required|in:club,friends,area,all',
            'club_id' => 'required|exists:clubs,id',
            'source_club_id' => 'sometimes|nullable|exists:clubs,id',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:200',
            'lat' => 'required_if:scope,area|numeric',
            'lng' => 'required_if:scope,area|numeric',
            'radius' => 'required_if:scope,area|numeric|min:0.1|max:200',
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $scope = $validated['scope'];

        // Initial query
        $query = User::withFullRelations();

        // 🎯 Tùy theo phạm vi (scope)
        switch ($scope) {
            case 'club':
                $query->whereHas('clubs', fn($q) => $q->where('clubs.id', $validated['source_club_id']));
                break;

            case 'friends':
                $query->whereExists(function ($q) use ($user) {
                    $q->select(DB::raw(1))
                        ->from('follows as f1')
                        ->whereColumn('f1.followable_id', 'users.id')
                        ->where('f1.user_id', $user->id)
                        ->where('f1.followable_type', User::class);
                })
                    ->whereExists(function ($q) use ($user) {
                        $q->select(DB::raw(1))
                            ->from('follows as f2')
                            ->whereColumn('f2.user_id', 'users.id')
                            ->where('f2.followable_id', $user->id)
                            ->where('f2.followable_type', User::class);
                    });
                break;

            case 'area':
                $lat = $validated['lat'];
                $lng = $validated['lng'];
                $radius = $validated['radius'];

                $haversine = "(6371 * acos(
                        cos(radians(?))
                        * cos(radians(users.latitude))
                        * cos(radians(users.longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(users.latitude))
                    ))";

                $query->whereNotNull('users.latitude')
                    ->whereNotNull('users.longitude')
                    ->whereRaw("$haversine <= ?", [
                        $lat,
                        $lng,
                        $lat,
                        $radius
                    ])
                    ->orderByRaw("$haversine asc", [
                        $lat,
                        $lng,
                        $lat
                    ]);
                break;
            case 'all':
                break;
        }

        // 🔐 Visibility filter (trừ scope 'all' hoặc tương tự như tournament logic)
        if ($scope !== 'all') {
            $query->whereIn('users.visibility', [
                User::VISIBILITY_PUBLIC,
                User::VISIBILITY_FRIEND_ONLY
            ]);
        } else {
            $query->whereIn('users.visibility', [User::VISIBILITY_PUBLIC]);
        }

        $club = Club::findOrFail($validated['club_id']);
        $memberUserIds = $club->members()->pluck('user_id')->toArray();
        $query->whereNotIn('users.id', array_merge([$user->id], $memberUserIds));

        // 🔍 Tìm kiếm tên người dùng (áp dụng cho tất cả scope)
        if (!empty($validated['search'])) {
            $query->where('users.full_name', 'like', '%' . $validated['search'] . '%');
        }

        // 🧮 Phân trang
        $paginated = $query->paginate($perPage);
        $candidates = $paginated->getCollection()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->full_name,
                'visibility' => $u->visibility,
                'avatar_url' => $u->avatar_url,
                'thumbnail' => $u->thumbnail,
                'gender' => $u->gender,
                'gender_text' => $u->gender_text,
                'sports' => $u->sports->map(function ($userSport) {
                    $scores = $userSport->scores()
                        ->pluck('score_value', 'score_type')
                        ->toArray();

                    return [
                        'sport_id' => $userSport->sport_id,
                        'sport_icon' => $userSport->sport?->icon,
                        'sport_name' => $userSport->sport?->name,
                        'scores' => [
                            'personal_score' => $scores['personal_score'] ?? '0.000',
                            'dupr_score'     => $scores['dupr_score'] ?? '0.000',
                            'vndupr_score'   => $scores['vndupr_score'] ?? '0.000',
                        ],
                    ];
                }),
                'invited' => false, // Default to false, frontend will handle this based on response from sending invite
            ];
        });

        return ResponseHelper::success([
            'result' => $candidates,
        ], 'Danh sách ứng viên', 200, [
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
        ]);
    }
}
