<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatusEnum;
use App\Helpers\ResponseHelper;
use App\Http\Resources\MiniParticipantResource;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Notifications\GuestAddedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GuestController extends Controller
{
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
            'guest_avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
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

        // Xác định payment_status
        // auto_split_fee = true: luôn CONFIRMED (kèo kết thúc sẽ chia lại tiền)
        $paymentStatus = PaymentStatusEnum::CONFIRMED;
        if ($miniTournament->has_fee && !$miniTournament->auto_split_fee && $guarantorUserId) {
            // Chỉ set PENDING khi KHÔNG phải auto_split_fee và guarantor không phải organizer
            $isGuarantorOrganizer = $miniTournament->hasOrganizer($guarantorUserId);
            if (!$isGuarantorOrganizer) {
                $paymentStatus = PaymentStatusEnum::PENDING;
            }
        }

        // Xử lý guest_avatar: có thể là file (mobile) hoặc URL string (web)
        $guestAvatarUrl = null;
        if ($request->hasFile('guest_avatar')) {
            $guestAvatarPath = $request->file('guest_avatar')->store('guest-avatars', 'public');
            $guestAvatarUrl = asset('storage/' . $guestAvatarPath);
        } elseif (!empty($data['guest_avatar']) && is_string($data['guest_avatar'])) {
            $guestAvatarUrl = $data['guest_avatar'];
        }

        // Tạo participant cho guest
        $participantData = [
            'mini_tournament_id' => $miniTournamentId,
            'is_confirmed' => true,
            'is_guest' => true,
            'guest_name' => $data['guest_name'],
            'guest_phone' => $data['guest_phone'] ?? null,
            'guest_avatar' => $guestAvatarUrl,
            'guarantor_user_id' => $guarantorUserId,
            'payment_status' => $paymentStatus,
            'estimated_level_min' => $data['estimated_level_min'] ?? null,
            'estimated_level_max' => $data['estimated_level_max'] ?? null,
        ];

        // Chỉ gán user_id khi có guest_phone (tìm hoặc tạo user theo phone)
        if (!empty($data['guest_phone'])) {
            $guestUser = User::where('phone', $data['guest_phone'])->first();
            if (!$guestUser) {
                $randomPassword = Str::random(12);
                $guestUser = User::create([
                    'full_name' => $data['guest_name'],
                    'phone' => $data['guest_phone'],
                    'password' => $randomPassword,
                    'visibility' => User::VISIBILITY_PRIVATE,
                ]);
            }
            $participantData['user_id'] = $guestUser->id;
        }

        $participant = MiniParticipant::create($participantData);

        // Luôn tạo payment record cho guest khi kèo có thu phí VÀ KHÔNG phải auto_split_fee
        // auto_split_fee = true: KHÔNG tạo payment ở đây, sẽ tạo khi kèo kết thúc
        // Chỉ tạo payment khi có user_id (tức có guest_phone)
        if ($miniTournament->has_fee && !$miniTournament->auto_split_fee && !empty($data['guest_phone'])) {
            // Tiền cố định mỗi người
            $feeAmount = $miniTournament->fee_amount;

            MiniParticipantPayment::create([
                'mini_tournament_id' => $miniTournamentId,
                'participant_id' => $participant->id,
                'user_id' => $guestUser->id,
                'amount' => $feeAmount,
                'status' => $paymentStatus,
                'note' => "Guest {$data['guest_name']}" . ($data['guest_phone'] ? " - {$data['guest_phone']}" : ''),
                'confirmed_at' => $paymentStatus === PaymentStatusEnum::CONFIRMED ? now() : null,
                'confirmed_by' => $paymentStatus === PaymentStatusEnum::CONFIRMED ? $guarantorUserId : null,
                'paid_at' => $paymentStatus === PaymentStatusEnum::CONFIRMED ? now() : null,
            ]);
        }

        // Load relations for response
        $participant->load(['user', 'guarantor']);

        // Gửi thông báo cho người bảo lãnh (nếu có)
        if ($guarantorUserId && $guarantorUserId !== auth()->id()) {
            $guarantor = User::find($guarantorUserId);
            if ($guarantor) {
                $guarantor->notify(new GuestAddedNotification($miniTournament, $participant));
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
}
