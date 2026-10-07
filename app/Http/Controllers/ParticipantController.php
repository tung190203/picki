<?php

namespace App\Http\Controllers;

use App\Events\SuperAdmin\TournamentMemberAdded;
use App\Helpers\ResponseHelper;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\TournamentParticipantResource;
use App\Http\Requests\ModifyParticipantScoreRequest;
use App\Services\ParticipantScoreService;
use App\Models\Club\Club;
use App\Enums\ClubMemberRole;
use App\Jobs\SendPushJob;
use App\Models\Participant;
use App\Models\SuperAdminDraft;
use App\Models\Tournament;
use App\Models\TournamentParticipantPayment;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Notifications\TournamentGuestAddedNotification;
use App\Notifications\TournamentInvitationNotification;
use App\Notifications\TournamentJoinConfirmedNotification;
use App\Notifications\TournamentJoinRequestNotification;
use App\Notifications\TournamentRemovedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParticipantController extends Controller
{
    public function index(Request $request, $tournamentId)
    {
        $validated = $request->validate([
            'is_confirmed' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = Participant::where('tournament_id', $tournamentId)
            ->with(['user', 'tournament']);

        if (isset($validated['is_confirmed'])) {
            $query->where('is_confirmed', $validated['is_confirmed']);
        }

        $participants = $query->paginate($validated['per_page'] ?? Participant::PER_PAGE);

        $data = [
            'participants' => ParticipantResource::collection($participants),
        ];

        $meta = [
            'current_page'   => $participants->currentPage(),
            'last_page'      => $participants->lastPage(),
            'per_page'       => $participants->perPage(),
            'total'          => $participants->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách người tham gia thành công', 200, $meta);
    }

    /* Lấy danh sách người được mời tham gia giải đấu */
    public function listInvite(Request $request, $tournamentId){
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        $tournament = Tournament::findOrFail($tournamentId);

        $participantsIds = Participant::where('tournament_id', $tournamentId)->pluck('user_id');
        $tournamentStaffIds = $tournament->staff()->pluck('user_id');
        $createId = $tournament->created_by;

        $listIds = array_values(
            array_diff(
                $participantsIds->merge($tournamentStaffIds)->unique()->toArray(),
                [$createId]
            )
        );

        $participants = Participant::where('tournament_id', $tournamentId)
            ->whereIn('user_id', $listIds)
            ->get(['id', 'user_id', 'is_confirmed']);

        $participantMap = $participants->keyBy('user_id');
        $staffIdMap = array_flip($tournamentStaffIds->toArray());

        $listInviteQuery = User::whereIn('id', $listIds)
            ->select('users.*');

        $listInvite = $listInviteQuery->paginate($validated['per_page'] ?? Participant::PER_PAGE);

        $inviteList = $listInvite->getCollection()->map(function ($user) use ($participantMap, $staffIdMap) {
            $isConfirmed = 0;
            $participantId = null;
            if (isset($staffIdMap[$user->id])) {
                $isConfirmed = 1;
            } elseif ($participantMap->has($user->id)) {
                $isConfirmed = (int) $participantMap[$user->id]->is_confirmed;
                $participantId = $participantMap[$user->id]->id;
            }
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'avatar' => $user->avatar_url,
                'gender' => $user->gender,
                'gender_text' => $user->gender_text,
                'is_confirmed' => $isConfirmed,
                'visibility' => $user->visibility,
                'participant_id' => $participantId,
            ];
        });

        $data = [
            'invitations' => $inviteList,
        ];
        $meta = [
            'current_page' => $listInvite->currentPage(),
            'last_page' => $listInvite->lastPage(),
            'per_page' => $listInvite->perPage(),
            'total' => $listInvite->total(),
        ];
        return ResponseHelper::success($data, 'Lấy danh sách lời mời người tham gia thành công', 200, $meta);
    }


    public function join(Request $request, $tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);
        $user = Auth::user();
        if (!$user) {
            return ResponseHelper::error('Bạn cần đăng nhập để thực hiện thao tác này.', 401);
        }
        if ($tournament->start_date < now()) {
            return ResponseHelper::error('Thời gian đăng ký đã kết thúc', 400);
        }
        $userSport = $user->sports()
            ->where('sport_id', $tournament->sport_id)
            ->first();
        if (!$userSport) {
            return ResponseHelper::error('Bạn không đạt yêu cầu tham gia giải đấu này', 400);
        }
        $score = $userSport->scores()
            ->where('score_type', 'vndupr_score')
            ->value('score_value');
        if (is_null($score)) {
            return ResponseHelper::error('Bạn chưa có điểm số cho môn thể thao này.', 422);
        }

        $min = $tournament->min_level;
        $max = $tournament->max_level;
        if ($min == 0 && $max == 0) {
        } else {
            if ($min > 0 && $score < $min) {
                return ResponseHelper::error('Điểm của bạn thấp hơn mức yêu cầu', 422);
            }
            if ($max > 0 && $score > $max) {
                return ResponseHelper::error('Điểm của bạn vượt quá mức cho phép', 422);
            }
        }
        $age = Carbon::parse($user->date_of_birth)->age;
        switch ($tournament->age_group) {
            case Tournament::ALL_AGES:
                break;
            case Tournament::YOUTH:
                if ($age >= 18) {
                    return ResponseHelper::error('Giải đấu chỉ dành cho người dưới 18 tuổi.', 422);
                }
                break;
            case Tournament::ADULT:
                if ($age < 18 || $age > 55) {
                    return ResponseHelper::error('Giải đấu chỉ dành cho người từ 18 đến 55 tuổi.', 422);
                }
                break;
            case Tournament::SENIOR:
                if ($age <= 55) {
                    return ResponseHelper::error('Giải đấu chỉ dành cho người trên 55 tuổi.', 422);
                }
                break;
        }
        if ($user->gender === null) {
            switch ($tournament->gender_policy) {
                case Tournament::MALE:
                    return ResponseHelper::error('Giải này chỉ dành cho Nam. Bạn chưa có thông tin giới tính trong hồ sơ.', 422);
                case Tournament::FEMALE:
                    return ResponseHelper::error('Giải này chỉ dành cho Nữ. Bạn chưa có thông tin giới tính trong hồ sơ.', 422);
                case Tournament::MIXED:
                    break;
            }
        } else {
            switch ($tournament->gender_policy) {
                case Tournament::MALE:
                    if ($user->gender != Tournament::MALE) {
                        return ResponseHelper::error('Giải này chỉ dành cho Nam.', 422);
                    }
                    break;

                case Tournament::FEMALE:
                    if ($user->gender != Tournament::FEMALE) {
                        return ResponseHelper::error('Giải này chỉ dành cho Nữ.', 422);
                    }
                    break;

                case Tournament::MIXED:
                    break;
            }
        }
        if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->player_per_team * $tournament->max_team)) {
            return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
        }

        $exists = Participant::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Bạn đã tham gia giải này.'], 422);
        }

        if (!$user) {
            return ResponseHelper::error('Bạn cần đăng nhập để thực hiện thao tác này.', 401);
        }

        $rank = $user->getVNRank($tournament->sport_id);

        // Xác định payment_status dựa trên tournament financial settings
        $paymentStatus = TournamentParticipantPayment::STATUS_CONFIRMED;
        if ($tournament->has_financial_management && $tournament->has_fee && !$tournament->use_club_fund && !$tournament->auto_split_fee) {
            $paymentStatus = TournamentParticipantPayment::STATUS_PENDING;
        }

        $participant = Participant::create([
            'tournament_id' => $tournamentId,
            'user_id' => $user->id,
            'is_confirmed' => $tournament->auto_approve && !$tournament->is_private,
            'rating_before' => $score,
            'rank_before' => $rank,
            'payment_status' => $paymentStatus,
            'self_registered' => true,
        ]);

        // Tạo TournamentParticipantPayment record nếu có phí cố định mỗi người
        if ($tournament->has_financial_management && $tournament->has_fee && !$tournament->use_club_fund && !$tournament->auto_split_fee) {
            $feePerPerson = $tournament->fee_amount;

            TournamentParticipantPayment::firstOrCreate(
                [
                    'tournament_id' => $tournament->id,
                    'participant_id' => $participant->id,
                ],
                [
                    'user_id' => $user->id,
                    'amount' => $feePerPerson,
                    'status' => TournamentParticipantPayment::STATUS_PENDING,
                ]
            );
        }

        $organizers = $tournament->staff()->wherePivot('role', TournamentStaff::ROLE_ORGANIZER)->get();
        if(!$participant->is_confirmed){
            foreach ($organizers as $organizer) {
                if ($organizer->id === Auth::id()) {
                    continue;
                }

                // 📩 Notification DB
                $organizer->notify(
                    new TournamentJoinRequestNotification($participant)
                );
            }
        }else {
            foreach ($organizers as $organizer) {
                if ($organizer->id === Auth::id()) {
                    continue;
                }

                $this->pushToUsers(
                    [$organizer->id],
                    'Người tham gia mới',
                    $user->full_name . ' đã tham gia giải "' . $tournament->name . '"',
                    [
                        'type' => 'TOURNAMENT_JOINED',
                        'tournament_id' => $tournament->id,
                        'participant_id' => $participant->id,
                    ]
                );
            }
        }

        $participant->load('user');
        TournamentMemberAdded::dispatch(
            $tournament->id,
            $tournament->name,
            [
                'id' => $participant->id,
                'user' => [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'avatar_url' => $user->avatar_url,
                ],
            ],
            'participant'
        );

        return ResponseHelper::success(new ParticipantResource($participant),'Tham gia giải đấu thành công',201);
    }

    public function confirm($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        $tournamentWithStaff = $participant->tournament->load('staff');

        if ($tournamentWithStaff->start_date < now()) {
            return ResponseHelper::error('Giải đấu đã bắt đầu hoặc đã kết thúc. Không thể duyệt VĐV.', 400);
        }
        if (in_array($tournamentWithStaff->status, ['closed', 'cancelled'])) {
            return ResponseHelper::error('Giải đấu đã đóng hoặc bị hủy. Không thể duyệt VĐV.', 400);
        }

        $isOrganizer = $tournamentWithStaff->hasOrganizer(Auth::id());

        // Guest đang chờ BTC duyệt (VĐV bảo lãnh)
        if ($participant->is_guest && $participant->is_pending_confirmation) {
            if (!$isOrganizer) {
                return ResponseHelper::error('Bạn không có quyền xác nhận guest này', 403);
            }
            if ($participant->is_confirmed) {
                return ResponseHelper::error('Guest đã được xác nhận', 400);
            }

            $participant->update([
                'is_confirmed' => true,
                'is_pending_confirmation' => false,
            ]);

            if ($participant->guarantor_user_id) {
                User::find($participant->guarantor_user_id)?->notify(
                    new TournamentGuestAddedNotification($participant->tournament, $participant)
                );
            }

            // Đảm bảo TournamentParticipantPayment tồn tại khi confirm guest
            if ($participant->tournament->has_financial_management
                && $participant->tournament->has_fee
                && !$participant->tournament->auto_split_fee
                && !$participant->tournament->use_club_fund
            ) {
                $feePerPerson = $participant->tournament->fee_amount;
                TournamentParticipantPayment::firstOrCreate(
                    [
                        'tournament_id' => $participant->tournament_id,
                        'participant_id' => $participant->id,
                    ],
                    [
                        'user_id' => $participant->guarantor_user_id,
                        'amount' => $feePerPerson,
                        'status' => TournamentParticipantPayment::STATUS_PENDING,
                    ]
                );
            }

            $participant->load(['user', 'guarantor']);
            return ResponseHelper::success(
                new ParticipantResource($participant),
                'Xác nhận guest thành công'
            );
        }

        // VĐV bình thường (luồng hiện tại)
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xác nhận người tham gia này', 403);
        }
        if ($participant->is_confirmed) {
            return ResponseHelper::error('Người tham gia đã được xác nhận', 400);
        }
        if ($participant->tournament->participants()->where('is_confirmed', true)->count()
            >= ($participant->tournament->max_team * $participant->tournament->player_per_team)) {
            return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
        }

        // Đảm bảo TournamentParticipantPayment tồn tại khi confirm (phòng trường hợp user được duyệt mà chưa có payment record)
        if ($participant->tournament->has_financial_management
            && $participant->tournament->has_fee
            && !$participant->tournament->auto_split_fee
            && !$participant->tournament->use_club_fund
        ) {
            $feePerPerson = $participant->tournament->fee_amount;
            TournamentParticipantPayment::firstOrCreate(
                [
                    'tournament_id' => $participant->tournament_id,
                    'participant_id' => $participant->id,
                ],
                [
                    'user_id' => $participant->user_id,
                    'amount' => $feePerPerson,
                    'status' => TournamentParticipantPayment::STATUS_PENDING,
                ]
            );
        }

        $participant->update(['is_confirmed' => true]);
        $participant->user->notify(new TournamentJoinConfirmedNotification($participant));

        return ResponseHelper::success(new ParticipantResource($participant), 'Xác nhận người tham gia thành công');
    }

    /**
     * SuperAdmin xác nhận thay user
     */
    public function adminConfirm($tournamentId, $participantId)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $participant = Participant::with('tournament')->findOrFail($participantId);

        if ($participant->tournament_id != $tournamentId) {
            return ResponseHelper::error('Participant không thuộc giải đấu này.', 400);
        }

        if ($participant->is_confirmed) {
            return ResponseHelper::success(
                new ParticipantResource($participant),
                'Người tham gia đã được xác nhận trước đó'
            );
        }

        $tournament = $participant->tournament;

        if ($err = $this->authorizeAdminConfirm($tournament, $userId)) {
            return $err;
        }

        if ($tournament->start_date < now()) {
            return ResponseHelper::error('Giải đấu đã bắt đầu hoặc đã kết thúc. Không thể xác nhận.', 400);
        }
        if (in_array($tournament->status, ['closed', 'cancelled'])) {
            return ResponseHelper::error('Giải đấu đã đóng hoặc bị hủy. Không thể xác nhận.', 400);
        }

        $participantType = $tournament->participant;
        if ($participantType === 'user') {
            if ($tournament->participants()->where('is_confirmed', true)->count()
                >= ($tournament->player_per_team * $tournament->max_team)) {
                return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
            }
        } else {
            if ($tournament->participants()->where('is_confirmed', true)->count()
                >= ($tournament->max_team * $tournament->player_per_team)) {
                return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
            }
        }

        if ($tournament->has_financial_management
            && $tournament->has_fee
            && !$tournament->auto_split_fee
            && !$tournament->use_club_fund
        ) {
            $feePerPerson = $tournament->fee_amount;
            TournamentParticipantPayment::firstOrCreate(
                [
                    'tournament_id' => $participant->tournament_id,
                    'participant_id' => $participant->id,
                ],
                [
                    'user_id' => $participant->user_id,
                    'amount' => $feePerPerson,
                    'status' => TournamentParticipantPayment::STATUS_PENDING,
                ]
            );
        }

        $participant->update([
            'is_confirmed' => true,
            'self_confirmed' => false,
        ]);
        $participant->user->notify(new TournamentJoinConfirmedNotification($participant));

        return ResponseHelper::success(new ParticipantResource($participant), 'Đã xác nhận người tham gia thay user thành công');
    }

    /**
     * SuperAdmin xác nhận thay nhiều user cùng lúc.
     * Body: { participant_ids: int[] }
     */
    public function adminConfirmAll(Request $request, int $tournamentId)
    {
        $validated = $request->validate([
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'integer',
        ]);

        $tournament = Tournament::with('staff')->findOrFail($tournamentId);

        if ($err = $this->authorizeAdminConfirm($tournament, Auth::id())) {
            return $err;
        }

        if ($tournament->start_date < now()) {
            return ResponseHelper::error('Giải đấu đã bắt đầu hoặc đã kết thúc. Không thể xác nhận.', 400);
        }
        if (in_array($tournament->status, ['closed', 'cancelled'])) {
            return ResponseHelper::error('Giải đấu đã đóng hoặc bị hủy. Không thể xác nhận.', 400);
        }

        $participants = $tournament->participants()
            ->whereIn('id', $validated['participant_ids'])
            ->get();

        if ($participants->isEmpty()) {
            return ResponseHelper::error('Không tìm thấy thành viên nào trong danh sách', 404);
        }

        $participantType = $tournament->participant;
        $capacity = $participantType === 'user'
            ? ($tournament->player_per_team * $tournament->max_team)
            : ($tournament->max_team * $tournament->player_per_team);
        $currentConfirmed = $tournament->participants()->where('is_confirmed', true)->count();
        $remainingSlots = max(0, $capacity - $currentConfirmed);

        $confirmed = [];
        $skipped = [];

        DB::transaction(function () use ($participants, $tournament, &$confirmed, &$skipped, &$remainingSlots) {
            foreach ($participants as $participant) {
                if ($participant->is_confirmed) {
                    $skipped[] = ['participant_id' => $participant->id, 'reason' => 'already_confirmed'];
                    continue;
                }
                if ($remainingSlots <= 0) {
                    $skipped[] = ['participant_id' => $participant->id, 'reason' => 'capacity_full'];
                    continue;
                }

                $participant->update([
                    'is_confirmed' => true,
                    'self_confirmed' => false,
                ]);

                if ($tournament->has_financial_management
                    && $tournament->has_fee
                    && !$tournament->auto_split_fee
                    && !$tournament->use_club_fund
                ) {
                    TournamentParticipantPayment::firstOrCreate(
                        [
                            'tournament_id' => $participant->tournament_id,
                            'participant_id' => $participant->id,
                        ],
                        [
                            'user_id' => $participant->user_id,
                            'amount' => $tournament->fee_amount,
                            'status' => TournamentParticipantPayment::STATUS_PENDING,
                        ]
                    );
                }

                $confirmed[] = $participant;
                $remainingSlots--;
            }
        });

        foreach ($confirmed as $participant) {
            $participant->user?->notify(new TournamentJoinConfirmedNotification($participant));
        }

        return ResponseHelper::success([
            'confirmed_count' => count($confirmed),
            'skipped_count' => count($skipped),
            'skipped' => $skipped,
        ], 'Đã xác nhận ' . count($confirmed) . ' người tham gia');
    }

    public function acceptInvite($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        if ($participant->user_id !== Auth::id()) {
            return ResponseHelper::error('Bạn không có quyền xác nhận lời mời tham gia này', 403);
        }
        if ($participant && $participant->is_confirmed) {
            return ResponseHelper::error('Lời mời tham gia đã được chấp nhận', 400);
        }
        $tournament = Tournament::findOrFail($participant->tournament_id);
        if ($tournament->start_date < now()) {
            return ResponseHelper::error('Thời gian tham gia đã kết thúc', 400);
        }
        $participantType = $tournament->participant;
        if ($participantType === 'user') {
            if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->player_per_team * $tournament->max_team)) {
                return ResponseHelper::error('Số lượng người chơi đã đạt giới hạn.', 422);
            }
        } else {
            if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->max_team * $tournament->player_per_team)) {
                return ResponseHelper::error('Số lượng người chơi đã đạt giới hạn.', 422);
            }
        }
        $participant->is_confirmed = true;
        $participant->self_registered = false;
        $participant->save();

        return ResponseHelper::success(new ParticipantResource($participant), 'Chấp nhận lời mời tham gia thành công');
    }

    public function declineInvite($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        if ($participant->user_id !== Auth::id()) {
            return ResponseHelper::error('Bạn không có quyền từ chối lời mời tham gia này', 403);
        }
        if ($participant->is_confirmed) {
            return ResponseHelper::error('Người tham gia đã được xác nhận, không thể từ chối', 400);
        }
        $participant->delete();

        return ResponseHelper::success(null, 'Từ chối lời mời tham gia thành công', 200);
    }

    public function inviteUsers(Request $request, $tournamentId)
    {
        $validated = $request->validate([
            'user_ids' => 'sometimes|nullable|array',
            'user_ids.*' => 'nullable|integer',
            'club_guest_profile_ids' => 'sometimes|nullable|array',
            'club_guest_profile_ids.*' => 'nullable|integer|exists:club_guest_profiles,id',
        ]);

        // Lọc ra các user_id thực (không null, không rỗng) và check exists
        $rawUserIds = array_values(array_filter($validated['user_ids'] ?? [], fn($id) => $id !== null && $id !== ''));
        if (!empty($rawUserIds)) {
            $existing = \App\Models\User::whereIn('id', $rawUserIds)->pluck('id')->all();
            $invalid = array_diff($rawUserIds, $existing);
            if (!empty($invalid)) {
                return ResponseHelper::error('user_id không tồn tại: ' . implode(', ', $invalid), 422);
            }
        }

        // Lọc club_guest_profile_ids (bỏ null/rỗng)
        $rawClubGuestProfileIds = array_values(array_filter($validated['club_guest_profile_ids'] ?? [], fn($id) => $id !== null && $id !== ''));

        if (empty($rawUserIds) && empty($rawClubGuestProfileIds)) {
            return ResponseHelper::error('Cần chọn ít nhất 1 người chơi hoặc CLB guest để mời.', 422);
        }

        $tournament = Tournament::findOrFail($tournamentId);
        $organizer = Auth::user();
        if (!$organizer) {
            return ResponseHelper::error('Bạn cần đăng nhập để thực hiện thao tác này.', 401);
        }

        $isSuperAdmin = $organizer->is_super_admin;

        if (!$tournament->hasOrganizer($organizer->id)) {
            return ResponseHelper::error('Bạn không có quyền mời người chơi.', 403);
        }

        $participantType = $tournament->participant;
        $currentConfirmed = $tournament->participants()->where('is_confirmed', true)->count();
        $maxSlots = $participantType === 'user'
            ? ($tournament->player_per_team * $tournament->max_team)
            : ($tournament->max_team * $tournament->player_per_team);

        // Resolve club_guest_profile_ids → user_ids + snapshot estimated_level
        $profileUserMap = []; // [user_id => ClubGuestProfile]
        $profileUserIds = [];
        if (!empty($rawClubGuestProfileIds)) {
            $profiles = \App\Models\Club\ClubGuestProfile::whereIn('id', $rawClubGuestProfileIds)->get();
            $profileUserIds = $profiles->pluck('user_id')->all();
            foreach ($profiles as $p) {
                $profileUserMap[$p->user_id] = $p;
            }
        }
        $allRealUserIds = array_values(array_unique(array_merge($rawUserIds, $profileUserIds)));
        $allRequestedIds = $allRealUserIds;
        $totalRequested = count($allRequestedIds);
        if ($currentConfirmed + $totalRequested > $maxSlots) {
            return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
        }

        // ──────── Handle real users ────────
        $invitedResources = [];
        $realUserIds = $allRealUserIds;

        if (!empty($realUserIds)) {
            $existingMemberIds = Participant::where('tournament_id', $tournament->id)
                ->whereIn('user_id', $realUserIds)
                ->pluck('user_id')
                ->toArray();

            $newUserIds = array_diff($realUserIds, $existingMemberIds);

            if (!empty($newUserIds)) {
                $paymentStatus = TournamentParticipantPayment::STATUS_CONFIRMED;
                if ($tournament->has_financial_management && $tournament->has_fee && !$tournament->use_club_fund && !$tournament->auto_split_fee) {
                    $paymentStatus = TournamentParticipantPayment::STATUS_PENDING;
                }

                // Lấy info user.is_guest để set is_guest + snapshot name/avatar/phone cho CLB guest
                $usersById = User::whereIn('id', $newUserIds)->get()->keyBy('id');

                $insertData = array_map(function ($invitedUserId) use ($tournament, $isSuperAdmin, $paymentStatus, $organizer, $usersById, $profileUserMap) {
                    $u = $usersById[$invitedUserId] ?? null;
                    $isGuest = $u && (bool) $u->is_guest;
                    // Snapshot estimated_level từ ClubGuestProfile (chỉ áp dụng cho CLB guest)
                    $estimatedLevel = ($isGuest && isset($profileUserMap[$invitedUserId]))
                        ? $profileUserMap[$invitedUserId]->estimated_level
                        : null;
                    return [
                        'tournament_id' => $tournament->id,
                        'user_id' => $invitedUserId,
                        'is_confirmed' => $isSuperAdmin,
                        'self_confirmed' => !$isSuperAdmin,
                        'self_registered' => false,
                        'is_guest' => $isGuest,
                        'guest_name' => $isGuest ? $u->full_name : null,
                        'guest_avatar' => $isGuest ? $u->avatar_url : null,
                        'guest_phone' => $isGuest ? $u->phone : null,
                        'guarantor_user_id' => $isGuest ? $organizer->id : null,
                        'estimated_level' => $estimatedLevel,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'payment_status' => $paymentStatus,
                    ];
                }, $newUserIds);

                $tournament->participants()->insert($insertData);

                if ($tournament->has_financial_management && $tournament->has_fee && !$tournament->use_club_fund && !$tournament->auto_split_fee) {
                    $feePerPerson = $tournament->fee_amount;
                    $newParticipants = Participant::where('tournament_id', $tournament->id)
                        ->whereIn('user_id', $newUserIds)
                        ->get();

                    foreach ($newParticipants as $participant) {
                        TournamentParticipantPayment::firstOrCreate(
                            [
                                'tournament_id' => $tournament->id,
                                'participant_id' => $participant->id,
                            ],
                            [
                                'user_id' => $participant->user_id,
                                'amount' => $feePerPerson,
                                'status' => TournamentParticipantPayment::STATUS_PENDING,
                            ]
                        );
                    }
                }

                $invitedUsers = User::whereIn('id', $newUserIds)->get();

                foreach ($invitedUsers as $user) {
                    $user->notify(new TournamentInvitationNotification($tournament));
                }

                $participants = Participant::where('tournament_id', $tournament->id)
                    ->whereIn('user_id', $newUserIds)
                    ->get();

                foreach ($participants as $p) {
                    $invitedResources[] = new ParticipantResource($p);
                }
            }
        }

        // (Đã bỏ block xử lý virtualMembers cũ — club_guest_profile_ids đã được resolve
        //  sang user_ids ở phần trên, các user này được insert qua loop $realUserIds)

        if (empty($invitedResources)) {
            return ResponseHelper::error('Tất cả người chơi đã được mời hoặc đã tham gia.', 422);
        }

        return ResponseHelper::success(
            $invitedResources,
            'Đã gửi lời mời thành công cho ' . count($invitedResources) . ' người chơi.'
        );
    }

    public function delete($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        $tournamentId = $participant->tournament_id;
        $tournamentWithStaff = $participant->tournament->load('staff');
        $isOrganizer = $tournamentWithStaff->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xoá người tham gia này', 403);
        }
        $userNeedRemove = $participant->user_id;

        // Không cho xóa nếu team đã được xếp vào trận đấu (dù trận đang pending, đang đấu hay đã kết thúc)
        $teamIdsInTournament = DB::table('teams')
            ->where('tournament_id', $tournamentId)
            ->pluck('id');
        $userTeamIds = DB::table('team_members')
            ->where('user_id', $userNeedRemove)
            ->whereIn('team_id', $teamIdsInTournament)
            ->pluck('team_id');

        if ($userTeamIds->isNotEmpty()) {
            $hasScheduledMatch = DB::table('matches')
                ->where(function ($q) use ($userTeamIds) {
                    $q->whereIn('home_team_id', $userTeamIds)
                      ->orWhereIn('away_team_id', $userTeamIds);
                })
                ->exists();
            if ($hasScheduledMatch) {
                return ResponseHelper::error(
                    'Không thể xóa người tham gia. Đội đã được xếp vào trận đấu.',
                    400
                );
            }
        }

        DB::transaction(function () use ($userNeedRemove, $teamIdsInTournament, $participant, $tournamentId) {
            DB::table('team_members')
                ->where('user_id', $userNeedRemove)
                ->whereIn('team_id', $teamIdsInTournament)
                ->delete();

            TournamentParticipantPayment::where('tournament_id', $tournamentId)
                ->where('user_id', $userNeedRemove)
                ->delete();

            $participant->delete();
        });

        $participant->user?->notify(new TournamentRemovedNotification($participant));

        return ResponseHelper::success(null, 'Xoá người tham gia thành công', 200);
    }

    public function getParticipantsNonTeam(Request $request, $tournamentId)
    {
        $user_ids_in_teams = DB::table('team_members')
            ->join('teams', 'team_members.team_id', '=', 'teams.id')
            ->where('teams.tournament_id', $tournamentId)
            ->whereNotNull('team_members.user_id')
            ->pluck('team_members.user_id')
            ->unique()
            ->values();

        // Include confirmed real users not in any team. Exclude guests already
        // assigned to any team in this tournament — they're not selectable.
        $nonTeamParticipants = Participant::withFullRelations()
            ->where('tournament_id', $tournamentId)
            ->where('is_confirmed', 1)
            ->whereNotIn('id', function ($sub) use ($tournamentId) {
                $sub->select('participant_id')->from('team_members')
                    ->join('teams', 'team_members.team_id', '=', 'teams.id')
                    ->where('teams.tournament_id', $tournamentId)
                    ->whereNotNull('participant_id');
            })
            ->where(function ($q) use ($user_ids_in_teams) {
                $q->where(function ($q2) use ($user_ids_in_teams) {
                    $q2->where('is_guest', false)
                        ->whereNotIn('user_id', $user_ids_in_teams);
                })->orWhere('is_guest', true);
            })
            ->get();

        // Also surface club guest profiles (ẩn) whose user chưa có trong participant của giải này.
        $tournament = Tournament::find($tournamentId);
        $virtualArrays = [];
        $userIdsInGuests = [];
        $userNameToProfileId = [];
        if ($tournament && $tournament->club_id) {
            $profiles = \App\Models\Club\ClubGuestProfile::with('user')
                ->where('club_id', $tournament->club_id)
                ->get();

            // Lấy TẤT CẢ guest participants đã nằm trong team của giải đấu,
            // không chỉ trong $nonTeamParticipants (đã bị filter ra).
            $existingGuestUserIds = Participant::where('tournament_id', $tournamentId)
                ->where('is_guest', true)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all();

            foreach ($profiles as $profile) {
                $user = $profile->user;
                if (!$user) {
                    continue;
                }
                $userIdsInGuests[$user->id] = $profile->id;
                $userNameToProfileId[$user->full_name] = $profile->id;
                if (in_array($user->id, $existingGuestUserIds, true)) {
                    continue;
                }
                $virtualArrays[] = [
                    'id'                       => null,
                    'is_confirmed'             => false,
                    'is_guest'                 => true,
                    'guest_name'               => $user->full_name,
                    'guest_avatar'             => $user->avatar_url,
                    'guarantor_user_id'        => null,
                    'estimated_level'          => null,
                    'is_pending_confirmation'  => false,
                    'checked_in_at'            => null,
                    'is_absent'                => false,
                    'is_virtual'               => true,
                    'virtual_member_id'        => $profile->id,
                    'club_guest_profile_id'    => $profile->id,
                ];
            }
        }

        $participants = TournamentParticipantResource::collection($nonTeamParticipants)->toArray($request);
        foreach ($participants as &$p) {
            if (!empty($p['is_guest']) && isset($userNameToProfileId[$p['guest_name'] ?? ''])) {
                $p['is_virtual'] = true;
                $p['virtual_member_id'] = $userNameToProfileId[$p['guest_name']];
                $p['club_guest_profile_id'] = $userNameToProfileId[$p['guest_name']];
            }
        }
        unset($p);

        $data = [
            'participants' => array_merge($participants, $virtualArrays),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách người chơi thành công');
    }
    public function authorizeAdminConfirm(Tournament $tournament, int $userId): ?\Illuminate\Http\JsonResponse
    {
        if ($tournament->club_id) {
            $club = Club::find($tournament->club_id);
            if (!$club) {
                return ResponseHelper::error('CLB không tồn tại', 404);
            }

            $clubMember = $club->activeMembers()->where('user_id', $userId)->first();
            $isClubStaff = $clubMember && in_array(
                $clubMember->role,
                [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary],
                true
            );

            if (!$isClubStaff && !$tournament->hasAttendancePermission($userId)) {
                return ResponseHelper::error('Bạn không có quyền xác nhận VĐV trong giải đấu này', 403);
            }
        } else {
            if (!$tournament->hasAttendancePermission($userId)) {
                return ResponseHelper::error('Bạn không có quyền xác nhận VĐV trong giải đấu này', 403);
            }
        }

        return null;
    }

    public function modifyScore(ModifyParticipantScoreRequest $request, int $tournamentId, int $participantId)
    {
        $userId = Auth::id();
        $tournament = Tournament::findOrFail($tournamentId);

        if ($error = $this->authorizeAdminConfirm($tournament, $userId)) {
            return $error;
        }

        $participant = Participant::where('id', $participantId)
            ->where('tournament_id', $tournamentId)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Participant không tồn tại trong giải đấu này', 404);
        }

        $scoreService = new ParticipantScoreService();
        $score = isset($request->validated()['score']) ? (float) $request->validated()['score'] : null;
        $participant = $scoreService->modifyScore($participant, $score);

        return ResponseHelper::success([
            'participant_id' => $participant->id,
            'modified_score' => $participant->modified_score,
        ], 'Score đã được cập nhật', 200);
    }

    private function pushToUsers(array $userIds, string $title, string $body, array $data = [])
    {
        foreach ($userIds as $userId) {
            SendPushJob::dispatch($userId, $title, $body, $data);
        }
    }
}