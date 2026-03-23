<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatusEnum;
use App\Helpers\ResponseHelper;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\MiniParticipantPayment;
use App\Http\Resources\MiniParticipantResource;
use App\Jobs\SendPushJob;
use App\Models\MiniTournamentStaff;
use App\Models\SuperAdminDraft;
use App\Models\User;
use App\Notifications\MiniTournamentCreatorInvitationNotification;
use App\Notifications\MiniTournamentJoinConfirmedNotification;
use App\Notifications\MiniTournamentJoinRequestNotification;
use App\Notifications\MiniTournamentRemovedNotification;
use App\Notifications\MiniTournamentInvitationNotification;
use App\Notifications\MiniTournamentMemberJoinedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MiniParticipantController extends Controller
{
    /**
     * Danh sách người tham gia 1 mini tournament.
     * - Filter theo is_confirmed (0/1)
     * - Hỗ trợ phân trang
     */
    public function index(Request $request, $tournamentId)
    {
        $validated = $request->validate([
            'is_confirmed' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = MiniParticipant::where('mini_tournament_id', $tournamentId)->withFullRelations();

        if ($request->filled('is_confirmed')) {
            $query->where('is_confirmed', $validated['is_confirmed']);
        }

        $participants = $query->paginate($validated['per_page'] ?? MiniParticipant::PER_PAGE);

        $data = [
            'participants' => MiniParticipantResource::collection($participants),
        ];

        $meta = [
            'current_page' => $participants->currentPage(),
            'last_page' => $participants->lastPage(),
            'per_page' => $participants->perPage(),
            'total' => $participants->total(),
        ];

        return ResponseHelper::success($data, 'Danh sách người tham gia mini tournament.', 200, $meta);
    }

    /**
     * Người dùng (hoặc team) tự JOIN vào mini tournament.
     * - Check max_players
     * - Nếu auto_approve = true -> is_confirmed = true
     * - Nếu auto_approve = false hoặc is_private = true -> chờ duyệt
     */
    public function join($tournamentId)
    {
        $miniTournament = MiniTournament::with('staff')->findOrFail($tournamentId);

        $this->checkMaxPlayers($miniTournament);
        $organizerIds = $miniTournament->staff->where('role', MiniTournamentStaff::ROLE_ORGANIZER)->pluck('user_id')->unique()->toArray();

        $exists = MiniParticipant::where('mini_tournament_id', $tournamentId)
            ->where('user_id', Auth::id())
            ->exists();

        if ($exists) {
            return ResponseHelper::error('Bạn đã tham gia kèo đấu này.', 400);
        }

        $paymentStatus = PaymentStatusEnum::CONFIRMED;
        $isConfirmed = $miniTournament->auto_approve && !$miniTournament->is_private;
        
        // Set payment_status based on tournament fee settings
        if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
            $paymentStatus = PaymentStatusEnum::PENDING;
        }

        $participant = MiniParticipant::create([
            'mini_tournament_id' => $tournamentId,
            'user_id' => Auth::id(),
            'is_confirmed' => $isConfirmed,
            'is_invited' => false,
            'payment_status' => $paymentStatus,
        ]);

        $organizerIds = $miniTournament->staff
        ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
        ->pluck('user_id')
        ->toArray();
        if (!$participant->is_confirmed) {
            $this->notifyOrganizersJoinRequest($miniTournament, $participant);

            $this->pushToUsers(
                $organizerIds,
                'Yêu cầu tham gia mới',
                auth()->user()->full_name . ' vừa gửi yêu cầu tham gia kèo đấu "' . $miniTournament->name . '".',
                [
                    'type' => 'MINI_TOURNAMENT_JOIN_REQUEST',
                    'mini_tournament_id' => $miniTournament->id,
                    'participant_id' => $participant->id,
                ]
            );
        }

        if ($participant->is_confirmed) {
            $this->pushToUsers(
                $organizerIds,
                'Người tham gia mới',
                auth()->user()->full_name . ' đã tham gia kèo đấu "' . $miniTournament->name . '".',
                [
                    'type' => 'MINI_TOURNAMENT_JOINED',
                    'mini_tournament_id' => $miniTournament->id,
                    'participant_id' => $participant->id,
                ]
            );

            // Gửi notification cho user được auto-approve
            auth()->user()->notify(
                new MiniTournamentJoinConfirmedNotification($participant, Auth::id())
            );

            // Gửi push notification cho user
            $this->pushToUsers(
                [Auth::id()],
                'Đã được duyệt tham gia',
                'Bạn đã được duyệt tham gia kèo đấu "' . $miniTournament->name . '"',
                [
                    'type' => 'MINI_TOURNAMENT_JOIN_CONFIRMED',
                    'mini_tournament_id' => $miniTournament->id,
                    'participant_id' => $participant->id,
                ]
            );

            // Gửi notification cho chủ kèo
            foreach ($miniTournament->staff as $organizer) {
                $organizer->notify(
                    new MiniTournamentMemberJoinedNotification($participant)
                );
            }

            // Gắn thanh toán pending cho người chơi nếu kèo có thu phí VÀ KHÔNG phải auto_split_fee
            // auto_split_fee = true: KHÔNG tạo payment ở đây, sẽ tạo khi kèo kết thúc
            if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
                // Tiền cố định mỗi người
                $feePerPerson = $miniTournament->fee_amount;

                MiniParticipantPayment::firstOrCreate(
                    [
                        'mini_tournament_id' => $miniTournament->id,
                        'participant_id' => $participant->id,
                    ],
                    [
                        'user_id' => Auth::id(),
                        'amount' => $feePerPerson,
                        'status' => MiniParticipantPayment::STATUS_PENDING,
                    ]
                );
            }
        }

        return ResponseHelper::success(
            new MiniParticipantResource($participant->loadFullRelations()),
            'Tham gia kèo thành công',
            201
        );
    }

    /**
     * Organizer mời user
     */
    public function invite(Request $request, $tournamentId)
    {
        $miniTournament = MiniTournament::with('staff', 'participants')->findOrFail($tournamentId);

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền mời người tham gia.', 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $this->checkMaxPlayers($miniTournament);

        $exists = $miniTournament->participants()
            ->where('user_id', $validated['user_id'])
            ->exists();

        if ($exists) {
            return ResponseHelper::error('Người chơi này đã được mời hoặc đã tham gia.', 400);
        }

        $isSuperAdmin = SuperAdminDraft::where('user_id', Auth::id())->exists();

        $paymentStatus = PaymentStatusEnum::CONFIRMED;
        if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
            $paymentStatus = PaymentStatusEnum::PENDING;
        }

        $participant = $miniTournament->participants()->create([
            'user_id' => $validated['user_id'],
            'is_confirmed' => $isSuperAdmin,
            'is_invited' => true,
            'invited_by' => Auth::id(),
            'payment_status' => $paymentStatus,
        ]);

        $user = User::find($validated['user_id']);

        // 📩 Notification DB
        $user->notify(
            new MiniTournamentCreatorInvitationNotification($participant, Auth::id())
        );

        // 🔔 PUSH Notification
        $this->pushToUsers(
            [$user->id],
            'Lời mời tham gia kèo đấu',
            'Bạn được mời tham gia kèo đấu "' . $miniTournament->name . '"',
            [
                'type' => 'MINI_TOURNAMENT_INVITED',
                'mini_tournament_id' => $miniTournament->id,
                'participant_id' => $participant->id,
            ]
        );

        return ResponseHelper::success(
            new MiniParticipantResource($participant->loadFullRelations()),
            'Đã gửi lời mời tham gia kèo đấu',
            201
        );
    }

    /**
     * Organizer duyệt user
     */
    public function confirm($participantId)
    {
        $participant = MiniParticipant::with('miniTournament')->findOrFail($participantId);

        if (!$participant->miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Không có quyền duyệt', 403);
        }

        if ($participant->is_confirmed) {
            return ResponseHelper::success(
                new MiniParticipantResource($participant),
                'Người chơi đã được duyệt trước đó'
            );
        }

        $this->checkMaxPlayers($participant->miniTournament);

        $paymentStatus = PaymentStatusEnum::CONFIRMED;
        if ($participant->miniTournament->has_fee && !$participant->miniTournament->auto_split_fee) {
            $paymentStatus = PaymentStatusEnum::PENDING;
        }

        $participant->update([
            'is_confirmed' => true,
            'payment_status' => $paymentStatus,
        ]);

        $participant->user->notify(
            new MiniTournamentJoinConfirmedNotification($participant, Auth::id())
        );

        $this->pushToUsers(
            [$participant->user_id],
            'Đã được duyệt tham gia',
            'Bạn đã được duyệt tham gia kèo đấu "' . ($participant->miniTournament->name ?? '') . '".',
            [
                'type' => 'MINI_TOURNAMENT_JOIN_CONFIRMED',
                'mini_tournament_id' => $participant->mini_tournament_id,
                'participant_id' => $participant->id,
            ]
        );

        // Gắn thanh toán pending nếu kèo có thu phí VÀ KHÔNG phải auto_split_fee
        // auto_split_fee = true: KHÔNG tạo payment ở đây, sẽ tạo khi kèo kết thúc
        if ($participant->miniTournament->has_fee && !$participant->miniTournament->auto_split_fee) {
            // Tiền cố định mỗi người
            $feePerPerson = $participant->miniTournament->fee_amount;

            MiniParticipantPayment::firstOrCreate(
                [
                    'mini_tournament_id' => $participant->mini_tournament_id,
                    'participant_id' => $participant->id,
                ],
                [
                    'user_id' => $participant->user_id,
                    'amount' => $feePerPerson,
                    'status' => MiniParticipantPayment::STATUS_PENDING,
                ]
            );
        }

        return ResponseHelper::success(
            new MiniParticipantResource($participant->loadFullRelations()),
            'Đã duyệt người tham gia thành công'
        );
    }

    /**
     * User accept lời mời
     */
    public function acceptInvite($participantId)
    {
        $participant = MiniParticipant::with('miniTournament')->findOrFail($participantId);

        if ($participant->user_id !== Auth::id()) {
            return ResponseHelper::error('Không có quyền', 403);
        }

        if ($participant->is_confirmed) {
            return ResponseHelper::success(
                new MiniParticipantResource($participant),
                'Bạn đã chấp nhận trước đó'
            );
        }

        $this->checkMaxPlayers($participant->miniTournament);

        $paymentStatus = PaymentStatusEnum::CONFIRMED;
        if ($participant->miniTournament->has_fee && !$participant->miniTournament->auto_split_fee) {
            $paymentStatus = PaymentStatusEnum::PENDING;
        }

        $participant->update([
            'is_confirmed' => true,
            'payment_status' => $paymentStatus,
        ]);

        $organizerIds = $participant->miniTournament->staff
            ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->pluck('user_id')
            ->toArray();

        $this->pushToUsers(
            $organizerIds,
            'Lời mời được chấp nhận',
            auth()->user()->full_name . ' đã chấp nhận lời mời tham gia kèo đấu "' . ($participant->miniTournament->name ?? '') . '".',
            [
                'type' => 'MINI_TOURNAMENT_INVITE_ACCEPTED',
                'mini_tournament_id' => $participant->mini_tournament_id,
                'participant_id' => $participant->id,
            ]
        );

        foreach ($participant->miniTournament->staff as $organizer) {
            $organizer->notify(new MiniTournamentMemberJoinedNotification($participant));
        }

        // Gắn thanh toán pending nếu kèo có thu phí VÀ KHÔNG phải auto_split_fee
        // auto_split_fee = true: KHÔNG tạo payment ở đây, sẽ tạo khi kèo kết thúc
        if ($participant->miniTournament->has_fee && !$participant->miniTournament->auto_split_fee) {
            // Tiền cố định mỗi người
            $feePerPerson = $participant->miniTournament->fee_amount;

            MiniParticipantPayment::firstOrCreate(
                [
                    'mini_tournament_id' => $participant->mini_tournament_id,
                    'participant_id' => $participant->id,
                ],
                [
                    'user_id' => $participant->user_id,
                    'amount' => $feePerPerson,
                    'status' => MiniParticipantPayment::STATUS_PENDING,
                ]
            );
        }

        return ResponseHelper::success(
            new MiniParticipantResource($participant->loadFullRelations()),
            'Chấp nhận lời mời thành công'
        );
    }

    /**
     * User từ chối lời mời
     */
    public function declineInvite($participantId)
    {
        $participant = MiniParticipant::findOrFail($participantId);

        if ($participant->user_id !== Auth::id()) {
            return ResponseHelper::error('Không có quyền', 403);
        }

        $organizerIds = $participant->miniTournament->staff->where('role', MiniTournamentStaff::ROLE_ORGANIZER)->pluck('user_id')->toArray();

        $this->pushToUsers(
            $organizerIds,
            'Lời mời bị từ chối',
            auth()->user()->full_name . ' đã từ chối lời mời tham gia kèo đấu "' . ($participant->miniTournament->name ?? '') . '".',
            [
                'type' => 'MINI_TOURNAMENT_INVITE_DECLINED',
                'mini_tournament_id' => $participant->mini_tournament_id,
            ]
        );

        $participant->delete();

        return ResponseHelper::success(null, 'Bạn đã từ chối lời mời tham gia kèo đấu');
    }

    /**
     * Organizer xóa participant
     */
    public function delete($participantId)
    {
        $participant = MiniParticipant::with('miniTournament')->findOrFail($participantId);

        if (!$participant->miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Không có quyền', 403);
        }

        // Lưu thông tin để gửi notification
        $participantData = [
            'id' => $participant->id,
            'mini_tournament_id' => $participant->mini_tournament_id,
            'tournament_name' => $participant->miniTournament->name,
            'user_id' => $participant->user_id,
        ];

        DB::transaction(function () use ($participant) {
            // Xóa payment record của chính participant này
            MiniParticipantPayment::where('mini_tournament_id', $participant->mini_tournament_id)
                ->where('participant_id', $participant->id)
                ->delete();

            // Nếu là guest — xóa luôn payment mà guest tạo ra cho người bảo lãnh
            if ($participant->is_guest && $participant->guarantor_user_id) {
                MiniParticipantPayment::where('mini_tournament_id', $participant->mini_tournament_id)
                    ->where('user_id', $participant->guarantor_user_id)
                    ->where('participant_id', $participant->id)
                    ->delete();
            }

            $participant->delete();
        });

        $participant->user?->notify(
            new MiniTournamentRemovedNotification($participantData, Auth::id())
        );

        $this->pushToUsers(
            [$participant->user_id],
            'Bị xóa khỏi kèo đấu',
            'Bạn đã bị xóa khỏi kèo đấu',
            [
                'type' => 'MINI_TOURNAMENT_REMOVED',
                'mini_tournament_id' => $participant->mini_tournament_id,
            ]
        );

        return ResponseHelper::success(null, 'Đã xóa người tham gia khỏi kèo đấu');
    }

    public function deleteStaff(Request $request, $staffId)
    {
        $tournamentStaff = DB::table('mini_tournament_staff')->where('id', $staffId)->first();
        if (!$tournamentStaff) {
            return ResponseHelper::error('Nhân viên không tồn tại', 404);
        }
        $tournament = MiniTournament::with('staff')->findOrFail($tournamentStaff->mini_tournament_id);
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xoá nhân viên này', 403);
        }
        if ($tournamentStaff->role === MiniTournamentStaff::ROLE_ORGANIZER) {
            return ResponseHelper::error('Không thể xoá nhân viên với vai trò tổ chức', 400);
        }
        if ($tournamentStaff->user_id === Auth::id()) {
            return ResponseHelper::error('Bạn không thể tự xoá chính mình', 400);
        }

        // Bước 1: Lấy action từ request
        $action = $request->input('action');

        // Kiểm tra xem staff này có guest bảo lãnh không
        $guaranteedGuests = MiniParticipant::where('mini_tournament_id', $tournament->id)
            ->where('is_guest', true)
            ->where('guarantor_user_id', $tournamentStaff->user_id)
            ->with(['user', 'guarantor'])
            ->get();

        $hasGuaranteedGuests = $guaranteedGuests->isNotEmpty();

        // Nếu có guest bảo lãnh và chưa có action → trả về thông tin để FE hiển thị modal
        if ($hasGuaranteedGuests && !$action) {
            // Lấy candidate người bảo lãnh thay thế
            $organizers = collect($tournament->staff)
                ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
                ->map(fn($user) => [
                    'user_id' => $user->id,
                    'full_name' => $user->full_name,
                    'avatar_url' => $user->avatar_url,
                    'is_organizer' => true,
                ]);

            $paidParticipants = MiniParticipant::with('user')
                ->where('mini_tournament_id', $tournament->id)
                ->where('is_confirmed', true)
                ->where('is_guest', false)
                ->where('payment_status', PaymentStatusEnum::CONFIRMED)
                ->where('user_id', '!=', $tournamentStaff->user_id)
                ->get()
                ->map(fn($p) => [
                    'user_id' => $p->user_id,
                    'full_name' => $p->user?->full_name,
                    'avatar_url' => $p->user?->avatar_url,
                    'is_organizer' => false,
                ]);

            $guarantorCandidates = $organizers->concat($paidParticipants)
                ->unique('user_id')
                ->values();

            return ResponseHelper::success([
                'has_guaranteed_guests' => true,
                'guarantor_user_id' => $tournamentStaff->user_id,
                'guarantor_name' => DB::table('users')->where('id', $tournamentStaff->user_id)->value('full_name'),
                'guaranteed_guests' => MiniParticipantResource::collection($guaranteedGuests),
                'guarantor_candidates' => $guarantorCandidates,
            ], 'Staff có guest bảo lãnh, cần xác nhận hành động', 200);
        }

        // Bước 2: Xử lý theo action
        if ($action === 'delete_guests') {
            // Xóa toàn bộ guest do staff này bảo lãnh
            $guestIds = $guaranteedGuests->pluck('id')->toArray();
            if (!empty($guestIds)) {
                // Xóa payment của guest
                MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                    ->whereIn('participant_id', $guestIds)
                    ->delete();
                // Xóa payment mà guest tạo cho người bảo lãnh
                MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                    ->where('user_id', $tournamentStaff->user_id)
                    ->whereIn('participant_id', $guestIds)
                    ->delete();
                // Xóa guest
                MiniParticipant::whereIn('id', $guestIds)->delete();
            }
        } elseif ($action === 'transfer_guarantor') {
            // Chuyển guarantor sang user khác
            $newGuarantorId = $request->input('new_guarantor_user_id');
            if (!$newGuarantorId) {
                return ResponseHelper::error('Vui lòng chọn người bảo lãnh mới', 400);
            }

            // Validate new guarantor
            $isValidNewGuarantor = $tournament->staff()
                ->where('mini_tournament_staff.role', MiniTournamentStaff::ROLE_ORGANIZER)
                ->where('user_id', $newGuarantorId)
                ->exists()
                || MiniParticipant::where('mini_tournament_id', $tournament->id)
                    ->where('is_confirmed', true)
                    ->where('is_guest', false)
                    ->where('payment_status', PaymentStatusEnum::CONFIRMED)
                    ->where('user_id', $newGuarantorId)
                    ->exists();

            if (!$isValidNewGuarantor) {
                return ResponseHelper::error('Người bảo lãnh mới không hợp lệ', 400);
            }

            // Cập nhật guarantor cho các guest
            MiniParticipant::whereIn('id', $guaranteedGuests->pluck('id')->toArray())
                ->update(['guarantor_user_id' => $newGuarantorId]);

            // Cập nhật payment: chuyển user_id từ old guarantor → new guarantor
            MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
                ->where('user_id', $tournamentStaff->user_id)
                ->whereIn('participant_id', $guaranteedGuests->pluck('id')->toArray())
                ->update([
                    'user_id' => $newGuarantorId,
                    'confirmed_by' => $newGuarantorId,
                ]);
        }

        // Bước 3: Xóa staff
        DB::table('mini_tournament_staff')->where('id', $staffId)->delete();

        return ResponseHelper::success(null, 'Xoá nhân viên thành công', 200);
    }

    /**
     * Participant mời bạn bè tham gia mini tournament
     */
    public function inviteFriends(Request $request, $tournamentId)
    {
        $miniTournament = MiniTournament::with('staff')->findOrFail($tournamentId);

        // Check if allow_participant_add_friends is enabled
        if (!$miniTournament->allow_participant_add_friends) {
            return ResponseHelper::error('Tính năng mời bạn bè không được bật cho kèo đấu này', 403);
        }

        // Check if current user is a confirmed participant
        $participant = $miniTournament->participants()
            ->where('user_id', Auth::id())
            ->where('is_confirmed', true)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Bạn phải là thành viên được duyệt của kèo đấu để mời bạn bè', 403);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $invitedCount = 0;
        $errors = [];

        foreach ($validated['user_ids'] as $userId) {
            try {
                // Check if user already exists
                $exists = $miniTournament->participants()
                    ->where('user_id', $userId)
                    ->exists();

                if ($exists) {
                    $errors[] = "User ID {$userId} đã tham gia hoặc được mời";
                    continue;
                }

                // Check max players
                $this->checkMaxPlayers($miniTournament);

                // Determine payment_status based on tournament fee settings
                $paymentStatus = PaymentStatusEnum::CONFIRMED;
                if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
                    $paymentStatus = PaymentStatusEnum::PENDING;
                }

                // Create new participant
                $newParticipant = $miniTournament->participants()->create([
                    'user_id' => $userId,
                    'is_confirmed' => $miniTournament->auto_approve && !$miniTournament->is_private,
                    'is_invited' => true,
                    'invited_by' => Auth::id(),
                    'payment_status' => $paymentStatus,
                ]);

                // Send notification
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new MiniTournamentInvitationNotification($miniTournament));
                    $this->pushToUsers(
                        [$userId],
                        'Lời mời tham gia kèo đấu',
                        auth()->user()->full_name . ' mời bạn tham gia kèo đấu "' . $miniTournament->name . '"',
                        [
                            'type' => 'MINI_TOURNAMENT_INVITED',
                            'mini_tournament_id' => $miniTournament->id,
                            'participant_id' => $newParticipant->id,
                        ]
                    );
                }

                $invitedCount++;
            } catch (\Exception $e) {
                $errors[] = "Lỗi khi mời user {$userId}: " . $e->getMessage();
            }
        }

        $message = "Đã mời {$invitedCount} bạn bè tham gia kèo đấu";
        if (!empty($errors)) {
            return ResponseHelper::success(
                ['invited_count' => $invitedCount, 'errors' => $errors],
                $message . ' (có lỗi)',
                200
            );
        }

        return ResponseHelper::success(
            ['invited_count' => $invitedCount],
            $message,
            200
        );
    }

    /**
     * =====================
     * Helpers
     * =====================
     */
    private function checkMaxPlayers(MiniTournament $miniTournament)
    {
        if (!$miniTournament->max_players) {
            return;
        }

        $confirmed = $miniTournament->participants()
            ->where('is_confirmed', true)
            ->count();

        if ($confirmed >= $miniTournament->max_players) {
            abort(ResponseHelper::error('Kèo đã đủ số lượng người chơi.', 400));
        }
    }

    public function getCandidates(Request $request, $tournamentId)
    {
        $miniTournament = MiniTournament::withFullRelations()->findOrFail($tournamentId);
        $user = Auth::user();

        $validated = $request->validate([
            'scope' => 'required|in:club,friends,area,all',
            'club_id' => 'required_if:scope,club|exists:clubs,id',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:200',
            'lat' => 'required_if:scope,area|numeric',
            'lng' => 'required_if:scope,area|numeric',
            'radius' => 'required_if:scope,area|numeric|min:0.1|max:200',
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $scope = $validated['scope'];

        // 🧮 Tính mid level cho sorting (nếu mini tournament có min/max level)
        $midLevel = null;
        if (isset($miniTournament->min_level) && isset($miniTournament->max_level)
            && $miniTournament->min_level !== null && $miniTournament->max_level !== null) {
            $midLevel = (float)(($miniTournament->min_level + $miniTournament->max_level) / 2);
        }

        // 🎯 Tùy theo phạm vi (scope)
        switch ($scope) {
            case 'club':
                $query = User::withFullRelations()
                    ->whereHas('clubs', fn($q) => $q->where('clubs.id', $validated['club_id']));
                break;

            case 'friends':
                $query = User::withFullRelations()
                    ->whereExists(function ($q) use ($user) {
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

                $query = User::withFullRelations()
                    ->whereNotNull('users.latitude')
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
                $query = User::withFullRelations();
                break;
        }

        // 🔐 Visibility filter (trừ scope 'all')
        if ($scope !== 'all') {
            $query->whereIn('users.visibility', [
                User::VISIBILITY_PUBLIC,
                User::VISIBILITY_FRIEND_ONLY
            ]);
        } else {
            $query->whereIn('users.visibility', [User::VISIBILITY_PUBLIC]);
        }

        // ⚽ Filter theo setting của giải (chỉ áp dụng khi scope !== 'all')
        if ($scope !== 'all') {
            // 1. Có môn thể thao phù hợp (nếu mini tournament có sport_id)
            if (isset($miniTournament->sport_id)) {
                $query->whereHas('sports', function ($q) use ($miniTournament) {
                    $q->where('sport_id', $miniTournament->sport_id);
                });
            }

            // 2. Tuổi (nếu mini tournament có age_group)
            if (isset($miniTournament->age_group)) {
                $query->tap(fn ($q) => $this->filterByAge($q, $miniTournament->age_group));
            }

            // 3. Giới tính (nếu mini tournament có gender_policy)
            if (isset($miniTournament->gender_policy)) {
                $query->tap(fn ($q) => $this->filterByGender($q, $miniTournament->gender_policy));
            }
        }

        // 4. Loại trừ người có ĐỒNG THỜI trong cả participant VÀ staff (áp dụng cho tất cả scope)
        $participantUserIds = $miniTournament->participants->pluck('user_id')->toArray();
        $staffUserIds = $miniTournament->miniTournamentStaffs->pluck('user_id')->toArray();

        // Lấy những user có trong CẢ 2 mảng (giao của 2 tập hợp)
        $excludedUserIds = array_intersect($participantUserIds, $staffUserIds);

        // Loại trừ những user có trong cả 2 bảng
        if (!empty($excludedUserIds)) {
            $query->whereNotIn('users.id', $excludedUserIds);
        }

        // 5. Join để lấy level + filter level (chỉ khi scope !== 'all' và có sport_id)
        if ($scope !== 'all' && isset($miniTournament->sport_id)) {
            $query->leftJoin('user_sport', function ($join) use ($miniTournament) {
                $join->on('users.id', '=', 'user_sport.user_id')
                    ->where('user_sport.sport_id', $miniTournament->sport_id);
            })
            ->leftJoin('user_sport_scores', function ($join) {
                $join->on('user_sport.id', '=', 'user_sport_scores.user_sport_id')
                    ->where('user_sport_scores.score_type', 'vndupr_score');
            });

            // 6. Filter level
            if (isset($miniTournament->min_level)) {
                $query->where('user_sport_scores.score_value', '>=', $miniTournament->min_level);
            }
            if (isset($miniTournament->max_level)) {
                $query->where('user_sport_scores.score_value', '<=', $miniTournament->max_level);
            }
        }

        // 7. Select + Sort
        if ($scope !== 'all') {
            $query->select('users.*');

            if (isset($miniTournament->sport_id)) {
                $query->selectRaw('user_sport_scores.score_value as level');

                if ($midLevel !== null) {
                    $query->selectRaw(
                        'ABS(user_sport_scores.score_value - ?) as level_diff',
                        [$midLevel]
                    );
                }
            }

            if (isset($miniTournament->location_id)) {
                $query->selectRaw(
                    'CASE WHEN users.location_id = ? THEN 1 ELSE 0 END as same_location',
                    [$miniTournament->location_id]
                )
                ->orderByDesc('same_location');
            }

            if ($midLevel !== null) {
                $query->orderBy('level_diff');
            }
        } else {
            $query->select('users.*');
        }

        // 🔍 Tìm kiếm tên người dùng (áp dụng cho tất cả scope)
        if (!empty($validated['search'])) {
            $query->where('users.full_name', 'like', '%' . $validated['search'] . '%');
        }

        // 🧮 Phân trang
        $paginated = $query->paginate($perPage);
        $candidates = $paginated->getCollection()->map(function ($u) use ($user, $excludedUserIds) {
            return [
                'id' => $u->id,
                'name' => $u->full_name,
                'visibility' => $u->visibility,
                'age_group' => $u->age_group,
                'avatar_url' => $u->avatar_url,
                'thumbnail' => $u->thumbnail,
                'gender' => $u->gender,
                'gender_text' => $u->gender_text,
                'play_times' => [],

                'sports' => $u->sports->map(function ($userSport) {
                    $scores = $userSport->scores
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
                        'total_matches'     => $userSport->total_matches ?? 0,
                        'total_tournaments' => $userSport->total_tournaments ?? 0,
                        'total_prizes'      => $userSport->total_prizes ?? 0,
                    ];
                }),
                'is_friend' => $user && $u && $user->isFriendWith($u),
                'is_mini_participant' => in_array($u->id, $excludedUserIds),
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

    /**
     * Lọc theo độ tuổi
     */
    private function filterByAge($query, $ageGroup)
    {
        $today = Carbon::today();

        switch ($ageGroup) {
            case MiniTournament::YOUTH: // Dưới 18
                $minDate = $today->copy()->subYears(18);
                $query->where('date_of_birth', '>', $minDate);
                break;

            case MiniTournament::ADULT: // 18-55
                $minDate = $today->copy()->subYears(55);
                $maxDate = $today->copy()->subYears(18);
                $query->whereBetween('date_of_birth', [$minDate, $maxDate]);
                break;

            case MiniTournament::SENIOR: // Trên 55
                $maxDate = $today->copy()->subYears(55);
                $query->where('date_of_birth', '<', $maxDate);
                break;

            case MiniTournament::ALL_AGES:
            default:
                // Không lọc
                break;
        }

        return $query;
    }

    /**
     * Lọc theo giới tính
     */
    private function filterByGender($query, $genderPolicy)
    {
        if ($genderPolicy === MiniTournament::MALE) {
            $query->where('gender', MiniTournament::MALE);
        } elseif ($genderPolicy === MiniTournament::FEMALE) {
            $query->where('gender', MiniTournament::FEMALE);
        }
        // MIXED: không lọc

        return $query;
    }

    private function notifyOrganizersJoinRequest(MiniTournament $tournament, MiniParticipant $participant)
    {
        $organizers = $tournament->staff()
            ->wherePivot('role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->get();

        foreach ($organizers as $organizer) {
            if ($organizer->id !== Auth::id()) {
                $organizer->notify(
                    new MiniTournamentJoinRequestNotification($participant)
                );
            }
        }
    }

    private function pushToUsers(array $userIds, string $title, string $body, array $data = [])
    {
        foreach ($userIds as $userId) {
            SendPushJob::dispatch($userId, $title, $body, $data);
        }
    }
}
