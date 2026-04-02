<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Enums\PaymentStatusEnum;
use App\Helpers\ResponseHelper;
use App\Http\Resources\MiniParticipantResource;
use App\Models\Club;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Notifications\GuestAddedNotification;
use App\Services\MiniTournamentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestController extends Controller
{
    public function __construct(
        protected MiniTournamentService $tournamentService,
    ) {
    }
    /**
     * Thêm guest vào mini tournament
     * API: POST /api/mini-tournaments/{id}/guests
     *
     * Chỉ chủ kèo hoặc staff (organizer) mới có quyền thêm guest.
     *
     * Logic thanh toán:
     * - Kèo miễn phí (has_fee=false) → payment_status = confirmed
     * - Kèo có phí (has_fee=true):
     *   - Guarantor là host/staff → payment_status = confirmed
     *   - Guarantor là user khác (đã tham gia & đã đóng tiền trước đó) → payment_status = pending
     */
    public function store(Request $request, $miniTournamentId)
    {
        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'guest_avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'guarantor_user_id' => 'nullable|integer|exists:users,id',
            'estimated_level_min' => 'nullable|numeric|min:1|max:8',
            'estimated_level_max' => 'nullable|numeric|min:1|max:8',
        ]);

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // Chỉ chủ kèo hoặc staff (organizer) mới được thêm guest
        if (!$miniTournament->hasOrganizer(auth()->id())) {
            return ResponseHelper::error('Bạn không có quyền thêm guest cho kèo này', 403);
        }

        // Nếu tournament miễn phí → auto gán host làm guarantor, payment_status = confirmed
        if (!$miniTournament->has_fee) {
            $data['guarantor_user_id'] = $miniTournament->staff()
                ->where('mini_tournament_staff.role', MiniTournamentStaff::ROLE_ORGANIZER)
                ->first()?->user_id ?? auth()->id();
        }

        // Nếu có fee và có guarantor_user_id, kiểm tra guarantor hợp lệ
        $guarantorUserId = $data['guarantor_user_id'] ?? null;
        if ($miniTournament->has_fee && $guarantorUserId) {
            // Guarantor phải là host/staff HOẶC là thành viên đã tham gia kèo
            $isOrganizer = $miniTournament->hasOrganizer($guarantorUserId);
            $isParticipant = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
                ->where('user_id', $guarantorUserId)
                ->where('is_confirmed', true)
                ->exists();

            if (!$isOrganizer && !$isParticipant) {
                return ResponseHelper::error('Người bảo lãnh phải là chủ kèo hoặc thành viên đã tham gia kèo', 400);
            }

            // Kiểm tra nếu guarantor là thành viên thường (không phải organizer)
            // thì phải đã đóng tiền rồi mới được bảo lãnh
            if (!$isOrganizer) {
                $guarantorParticipant = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
                    ->where('user_id', $guarantorUserId)
                    ->first();

                if ($guarantorParticipant && !$guarantorParticipant->isConfirmedPayment()) {
                    return ResponseHelper::error('Người bảo lãnh phải là chủ kèo hoặc đã đóng tiền trước đó', 400);
                }
            }
        }

        // ─────────────────────────────────────────────────────────────────────────
        // Xác định is_confirmed & is_pending_confirmation
        //
        // Ưu tiên role BAN TỔ CHỨC (ORGANIZER) cao nhất:
        //   - User vừa là BTC vừa là VĐV (đã tham gia kèo) → hasOrganizer = true → guest được xác nhận ngay
        //   - Chỉ là VĐV (participant đã xác nhận & đã đóng tiền) → chờ BTC duyệt
        //   - Không có guarantor → guest được xác nhận ngay
        // ─────────────────────────────────────────────────────────────────────────
        $isGuarantorOrganizer = $guarantorUserId
            ? $miniTournament->hasOrganizer($guarantorUserId)
            : false;

        $isConfirmed = $isGuarantorOrganizer || !$guarantorUserId;
        $isPendingConfirmation = $guarantorUserId && !$isGuarantorOrganizer;

        // ─────────────────────────────────────────────────────────────────────────
        // Xác định payment_status
        // use_club_fund = true: CLB chi tiền → CONFIRMED, không cần nộp
        // auto_split_fee = true: luôn CONFIRMED (kèo kết thúc sẽ chia lại tiền)
        // ─────────────────────────────────────────────────────────────────────────
        $paymentStatus = PaymentStatusEnum::CONFIRMED;
        if (
            !$miniTournament->use_club_fund
            && $miniTournament->has_fee
            && !$miniTournament->auto_split_fee
            && $guarantorUserId
            && !$isGuarantorOrganizer
        ) {
            $paymentStatus = PaymentStatusEnum::PENDING;
        }

        // Xử lý guest_avatar: file upload hoặc URL string (web)
        $guestAvatarUrl = null;
        $uploadedFile = $request->file('guest_avatar');
        if ($uploadedFile && $uploadedFile->isValid()) {
            $guestAvatarPath = $uploadedFile->store('guest-avatars', 'public');
            $guestAvatarUrl = asset('storage/' . $guestAvatarPath);
        } elseif (!empty($data['guest_avatar']) && is_string($data['guest_avatar'])) {
            $guestAvatarUrl = $data['guest_avatar'];
        }

        // Tạo hoặc tìm user cho guest - LUÔN LUÔN tạo user (có hoặc không có phone)
        // Nếu có phone: tìm user theo phone hoặc tạo mới is_guest
        // Nếu không phone: tạo user is_guest không có phone
        $guestUser = null;

        if (!empty($data['guest_phone'])) {
            // Tìm user theo phone (kể cả user thường hay guest)
            $guestUser = User::where('phone', $data['guest_phone'])->first();

            if (!$guestUser) {
                // Tạo mới user guest với phone
                $guestUser = User::create([
                    'full_name' => $data['guest_name'],
                    'phone' => $data['guest_phone'],
                    'avatar_url' => $guestAvatarUrl,
                    'password' => Str::random(12),
                    'visibility' => User::VISIBILITY_PRIVATE,
                    'is_guest' => true,
                    'last_active_at' => now(),
                ]);
            } elseif (!$guestUser->is_guest) {
                // Phone đã tồn tại trong hệ thống (user thật) → không gán is_guest
                // Cập nhật avatar nếu có
                if ($guestAvatarUrl) {
                    $guestUser->updateQuietly(['avatar_url' => $guestAvatarUrl]);
                }
                $guestUser->updateQuietly(['last_active_at' => now()]);
            } else {
                // Tìm thấy user guest cũ → cập nhật avatar + last_active_at
                $guestUser->updateQuietly([
                    'full_name' => $data['guest_name'],
                    'avatar_url' => $guestAvatarUrl,
                    'last_active_at' => now(),
                ]);
            }
        } else {
            // Không có phone → tạo user guest mới không có phone
            $guestUser = User::create([
                'full_name' => $data['guest_name'],
                'phone' => null,
                    'avatar_url' => $guestAvatarUrl,
                'password' => Str::random(12),
                'visibility' => User::VISIBILITY_PRIVATE,
                'is_guest' => true,
                'last_active_at' => now(),
            ]);
        }

        // Tạo participant cho guest
        $participantData = [
            'mini_tournament_id' => $miniTournamentId,
            'user_id' => $guestUser->id,
            'is_confirmed' => $isConfirmed,
            'is_guest' => true,
            'guest_name' => $guestUser->full_name,
            'guest_phone' => $guestUser->phone,
            'guest_avatar' => $guestAvatarUrl,
            'guarantor_user_id' => $guarantorUserId,
            'payment_status' => $paymentStatus,
            'estimated_level_min' => $data['estimated_level_min'] ?? null,
            'estimated_level_max' => $data['estimated_level_max'] ?? null,
            'is_pending_confirmation' => $isPendingConfirmation,
        ];

        $participant = MiniParticipant::create($participantData);

        // Đồng bộ avatar lên bảng users (nested user.avatar_url trong API)
        if ($guestAvatarUrl) {
            $guestUser->forceFill(['avatar_url' => $guestAvatarUrl])->saveQuietly();
        }

        // Luôn tạo payment record cho guest khi kèo thu phí VÀ KHÔNG phải use_club_fund VÀ KHÔNG phải auto_split_fee
        // use_club_fund = true: CLB chi tiền → KHÔNG tạo payment
        // auto_split_fee = true: chỉ tạo payment khi kèo kết thúc → KHÔNG tạo payment ở đây
        if ($miniTournament->has_fee && !$miniTournament->auto_split_fee && !$miniTournament->use_club_fund) {
            $feeAmount = $miniTournament->fee_amount;

            MiniParticipantPayment::create([
                'mini_tournament_id' => $miniTournamentId,
                'participant_id' => $participant->id,
                'user_id' => $participant->user_id,
                'amount' => $feeAmount,
                'status' => $paymentStatus,
                'note' => "Guest {$data['guest_name']}" . ($data['guest_phone'] ?? null ? " - {$data['guest_phone']}" : ''),
                'confirmed_at' => $paymentStatus === PaymentStatusEnum::CONFIRMED ? now() : null,
                'confirmed_by' => $paymentStatus === PaymentStatusEnum::CONFIRMED ? $guarantorUserId : null,
                'paid_at' => $paymentStatus === PaymentStatusEnum::CONFIRMED ? now() : null,
            ]);
        }

        // Sync guest vào ClubFundContribution cho kèo CLB
        $this->tournamentService->syncGuestToClubFund($participant, auth()->id());

        // Load relations for response
        $participant->load(['user', 'guarantor']);

        // Gửi thông báo cho người bảo lãnh (nếu có)
        if ($guarantorUserId && $guarantorUserId !== auth()->id()) {
            $guarantor = User::find($guarantorUserId);
            if ($guarantor) {
                $guarantor->notify(new GuestAddedNotification($miniTournament, $participant));
            }
        }

        // Nếu là VĐV bảo lãnh (chờ BTC duyệt), thông báo cho tất cả organizers
        if ($isPendingConfirmation) {
            $organizers = $miniTournament->staff()
                ->where('mini_tournament_staff.role', MiniTournamentStaff::ROLE_ORGANIZER)
                ->where('users.id', '!=', auth()->id())
                ->get();

            foreach ($organizers as $organizer) {
                $organizer->notify(new GuestAddedNotification($miniTournament, $participant));
            }
        }

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Thêm guest thành công',
            201
        );
    }

    /**
     * Lấy danh sách guest của một tournament
     * API: GET /api/mini-tournaments/{id}/guests
     */
    public function index(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // Chỉ chủ kèo hoặc staff mới xem được danh sách guest
        if (!$miniTournament->hasOrganizer(auth()->id())) {
            return ResponseHelper::error('Bạn không có quyền xem danh sách guest', 403);
        }

        $guests = MiniParticipant::with(['user', 'guarantor'])
            ->where('mini_tournament_id', $miniTournamentId)
            ->where('is_guest', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseHelper::success(
            MiniParticipantResource::collection($guests),
            'Lấy danh sách guest thành công'
        );
    }

    /**
     * Lấy danh sách guest mà user hiện tại bảo lãnh và CHƯA đóng tiền
     * API: GET /api/mini-tournaments/{id}/guaranteed-guests
     *
     * Dùng cho app: khi user mở modal đóng tiền, show danh sách guest
     * mà họ đã bảo lãnh để họ đóng tiền giúp.
     */
    public function guaranteedGuests(Request $request, $miniTournamentId)
    {
        $userId = auth()->id();

        $guests = MiniParticipant::with(['user', 'guarantor', 'payments'])
            ->where('mini_tournament_id', $miniTournamentId)
            ->where('is_guest', true)
            ->where('guarantor_user_id', $userId)
            ->whereHas('payments', function ($query) {
                $query->whereIn('status', [
                    MiniParticipantPayment::STATUS_PENDING,
                    MiniParticipantPayment::STATUS_REJECTED,
                ]);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseHelper::success(
            MiniParticipantResource::collection($guests),
            'Lấy danh sách guest bảo lãnh thành công'
        );
    }

    /**
     * Lấy danh sách người có thể làm guarantor
     * API: GET /api/mini-tournaments/{id}/guarantor-candidates
     *
     * Trả về: organizers + confirmed participants đã đóng tiền
     */
    public function guarantorCandidates(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::with([
            'staff' => fn($q) => $q->where('mini_tournament_staff.role', MiniTournamentStaff::ROLE_ORGANIZER),
            'participants' => fn($q) => $q->where('is_confirmed', true)
                ->where('is_guest', false)
                ->where('payment_status', PaymentStatusEnum::CONFIRMED),
        ])->findOrFail($miniTournamentId);

        // Chỉ chủ kèo hoặc staff mới xem được
        if (!$miniTournament->hasOrganizer(auth()->id())) {
            return ResponseHelper::error('Bạn không có quyền xem danh sách này', 403);
        }

        // Organizers (đã load relation staff)
        $organizers = collect($miniTournament->staff)->map(fn($user) => [
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'is_organizer' => true,
        ]);

        // Participants đã xác nhận và đã đóng tiền
        $paidParticipants = collect($miniTournament->participants)->map(fn($participant) => [
            'user_id' => $participant->user_id,
            'full_name' => $participant->user?->full_name,
            'avatar_url' => $participant->user?->avatar_url,
            'is_organizer' => false,
        ]);

        // Merge và loại bỏ trùng lặp theo user_id
        $all = $organizers->concat($paidParticipants)
            ->unique('user_id')
            ->values();

        return ResponseHelper::success($all, 'Lấy danh sách người bảo lãnh thành công');
    }

    /**
     * Lấy danh sách guest do một user bảo lãnh
     * API: GET /api/mini-tournaments/{id}/guarantor-guests/{userId}
     */
    public function guarantorGuests(Request $request, $miniTournamentId, $userId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->hasOrganizer(auth()->id())) {
            return ResponseHelper::error('Bạn không có quyền xem danh sách này', 403);
        }

        $guests = MiniParticipant::with(['user', 'guarantor'])
            ->where('mini_tournament_id', $miniTournamentId)
            ->where('is_guest', true)
            ->where('guarantor_user_id', $userId)
            ->get();

        return ResponseHelper::success(
            MiniParticipantResource::collection($guests),
            'Lấy danh sách guest thành công'
        );
    }

    /**
     * BTC duyệt guest khi VĐV bảo lãnh
     * API: POST /api/mini-tournaments/{id}/guests/confirm/{participantId}
     */
    public function confirmGuest($miniTournamentId, $participantId)
    {
        $miniTournament = MiniTournament::with('staff')->findOrFail($miniTournamentId);

        if (!$miniTournament->hasOrganizer(auth()->id())) {
            return ResponseHelper::error('Bạn không có quyền xác nhận guest này', 403);
        }

        $participant = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->where('id', $participantId)
            ->where('is_guest', true)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Guest không tồn tại trong kèo này', 404);
        }

        if (!$participant->is_pending_confirmation) {
            return ResponseHelper::error('Guest này không cần xác nhận', 400);
        }

        DB::beginTransaction();
        try {
            $participant->update([
                'is_confirmed' => true,
                'is_pending_confirmation' => false,
            ]);

            // Guest được organizer bảo lãnh → chuyển PENDING → CONFIRMED (exempt)
            // Guest được member bảo lãnh → giữ PENDING (member sẽ đóng tiền giúp)
            if ($miniTournament->hasOrganizer($participant->guarantor_user_id)) {
                try {
                    $this->tournamentService->syncGuestToClubFund($participant, auth()->id());
                } catch (\Exception $e) {
                    // Log nhưng không break transaction
                }
            }

            if ($participant->guarantor_user_id) {
                $guarantor = User::find($participant->guarantor_user_id);
                if ($guarantor) {
                    $guarantor->notify(new GuestAddedNotification($miniTournament, $participant));
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage());
        }

        $participant->load(['user', 'guarantor']);

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Xác nhận guest thành công'
        );
    }

    /**
     * Người bảo lãnh check-in cho guest.
     * API: POST /api/mini-tournaments/{id}/guests/{participantId}/guarantor-check-in
     */
    public function guarantorCheckIn($miniTournamentId, $participantId)
    {
        $userId = auth()->id();

        $participant = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->where('id', $participantId)
            ->where('is_guest', true)
            ->where('guarantor_user_id', $userId)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Guest không tồn tại hoặc bạn không phải người bảo lãnh', 404);
        }

        if ($participant->checked_in_at) {
            return ResponseHelper::error('Guest đã check-in rồi', 422);
        }

        if ($participant->is_absent) {
            $participant->update([
                'checked_in_at' => now(),
                'is_absent' => false,
            ]);
        } else {
            $participant->update([
                'checked_in_at' => now(),
            ]);
        }

        $participant->load(['user', 'guarantor']);

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Đã check-in guest thành công'
        );
    }

    /**
     * Organizer/admin đánh dấu check-in cho guest.
     * API: POST /api/mini-tournaments/{id}/guests/{participantId}/mark-check-in
     */
    public function markGuestCheckIn(Request $request, $miniTournamentId, $participantId)
    {
        $userId = auth()->id();
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // === Kèo thuộc CLB: kiểm tra club_id và quyền staff ===
        if ($miniTournament->club_id) {
            $clubId = $request->input('club_id');

            if (!$clubId) {
                return ResponseHelper::error('Kèo thuộc CLB. Vui lòng truyền club_id trong body.', 422);
            }

            if ((int) $miniTournament->club_id !== (int) $clubId) {
                return ResponseHelper::error('Kèo không thuộc CLB này', 403);
            }

            $club = Club::find($clubId);
            if (!$club) {
                return ResponseHelper::error('CLB không tồn tại', 404);
            }

            $clubMember = $club->activeMembers()->where('user_id', $userId)->first();
            $isClubStaff = $clubMember && in_array(
                $clubMember->role,
                [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary],
                true
            );
            $isTournamentOrganizer = $miniTournament->hasOrganizer($userId);

            if (!$isClubStaff && !$isTournamentOrganizer) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho kèo này', 403);
            }
        } else {
            // === Kèo thường: chỉ organizer ===
            if ($request->filled('club_id')) {
                return ResponseHelper::error('Kèo không thuộc CLB. Không cần truyền club_id.', 422);
            }

            if (!$miniTournament->hasOrganizer($userId)) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho guest này', 403);
            }
        }

        $participant = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->where('id', $participantId)
            ->where('is_guest', true)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Guest không tồn tại trong kèo này', 404);
        }

        if ($participant->checked_in_at) {
            return ResponseHelper::error('Guest đã check-in rồi. Không thể check-in lại.', 422);
        }

        if ($participant->is_absent) {
            $participant->update([
                'checked_in_at' => now(),
                'is_absent' => false,
            ]);
        } else {
            $participant->update([
                'checked_in_at' => now(),
            ]);
        }

        $participant->load(['user', 'guarantor']);

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Đã đánh dấu check-in guest thành công'
        );
    }

}
