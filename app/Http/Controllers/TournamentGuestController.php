<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Events\SuperAdmin\TournamentMemberAdded;
use App\Helpers\ResponseHelper;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\UserListResource;
use App\Models\Club;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Notifications\TournamentGuestAddedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TournamentGuestController extends Controller
{
    /**
     * Thêm guest vào tournament
     * API: POST /api/tournaments/{id}/guests
     *
     * Quyền: organizer (BTC/staff) HOẶC VĐV đã xác nhận tham gia (không phải guest).
     *
     * Xác nhận guest theo người gọi API:
     * - Admin/BTC/staff thêm → guest is_confirmed = true ngay
     * - VĐV đã confirm thêm → guest chờ BTC duyệt (is_pending_confirmation = true)
     */
    public function store(Request $request, $tournamentId)
    {
        $data = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'nullable|string|max:20',
            'guest_avatar' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'guarantor_user_id' => 'nullable|integer|exists:users,id',
            'estimated_level' => 'nullable|numeric|min:1|max:2.5',
        ]);

        $tournament = Tournament::with('staff')->findOrFail($tournamentId);

        $callerId = Auth::id();
        $callerIsOrganizer = $tournament->hasOrganizer($callerId);
        $callerIsConfirmedParticipant = Participant::where('tournament_id', $tournamentId)
            ->where('user_id', $callerId)
            ->where('is_confirmed', true)
            ->where('is_guest', false)
            ->exists();

        if (!$callerIsOrganizer && !$callerIsConfirmedParticipant) {
            return ResponseHelper::error('Bạn không có quyền thêm guest cho giải này', 403);
        }

        $guarantorUserId = $data['guarantor_user_id'] ?? null;

        if ($guarantorUserId) {
            $isOrganizer = $tournament->hasOrganizer($guarantorUserId);
            $isConfirmedParticipant = Participant::where('tournament_id', $tournamentId)
                ->where('user_id', $guarantorUserId)
                ->where('is_confirmed', true)
                ->exists();

            if (!$isOrganizer && !$isConfirmedParticipant) {
                return ResponseHelper::error('Người bảo lãnh phải là BTC hoặc VĐV đã xác nhận', 400);
            }
        }

        // Theo người thực hiện thêm guest (không theo guarantor)
        if ($callerIsOrganizer) {
            $isConfirmed = true;
            $isPendingConfirmation = false;
        } else {
            $isConfirmed = false;
            $isPendingConfirmation = true;
        }

        $guestAvatarUrl = null;
        $uploadedFile = $request->file('guest_avatar');
        if ($uploadedFile && $uploadedFile->isValid()) {
            $guestAvatarPath = $uploadedFile->store('guest-avatars', 'public');
            $guestAvatarUrl = asset('storage/' . $guestAvatarPath);
        } elseif (!empty($data['guest_avatar']) && is_string($data['guest_avatar'])) {
            $guestAvatarUrl = $data['guest_avatar'];
        }

        $guestUser = null;

        if (!empty($data['guest_phone'])) {
            $guestUser = User::where('phone', $data['guest_phone'])->first();

            if (!$guestUser) {
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
                if ($guestAvatarUrl) {
                    $guestUser->updateQuietly(['avatar_url' => $guestAvatarUrl]);
                }
                $guestUser->updateQuietly(['last_active_at' => now()]);
            } else {
                $guestUser->updateQuietly([
                    'full_name' => $data['guest_name'],
                    'avatar_url' => $guestAvatarUrl,
                    'last_active_at' => now(),
                ]);
            }
        } else {
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

        $participant = Participant::create([
            'tournament_id' => $tournamentId,
            'user_id' => $guestUser->id,
            'is_confirmed' => $isConfirmed,
            'is_guest' => true,
            'guest_name' => $guestUser->full_name,
            'guest_phone' => $guestUser->phone,
            'guest_avatar' => $guestAvatarUrl,
            'guarantor_user_id' => $guarantorUserId,
            'estimated_level' => $data['estimated_level'] ?? null,
            'is_pending_confirmation' => $isPendingConfirmation,
        ]);

        if ($guestAvatarUrl) {
            $guestUser->forceFill(['avatar_url' => $guestAvatarUrl])->saveQuietly();
        }

        if ($guarantorUserId && $guarantorUserId !== Auth::id()) {
            $guarantor = User::find($guarantorUserId);
            if ($guarantor) {
                $guarantor->notify(new TournamentGuestAddedNotification($tournament, $participant));
            }
        }

        if ($isPendingConfirmation) {
            $organizers = $tournament->staff()
                ->wherePivot('role', TournamentStaff::ROLE_ORGANIZER)
                ->where('users.id', '!=', Auth::id())
                ->get();

            foreach ($organizers as $organizer) {
                $organizer->notify(new TournamentGuestAddedNotification($tournament, $participant));
            }
        }

        $participant->load(['user', 'guarantor']);

        // Notify super admins via socket
        TournamentMemberAdded::dispatch(
            $tournament->id,
            $tournament->name,
            [
                'id' => $participant->id,
                'user' => [
                    'id' => $participant->user->id,
                    'full_name' => $participant->user->full_name,
                    'avatar_url' => $participant->user->avatar_url,
                ],
                'guest_name' => $participant->guest_name,
                'guest_phone' => $participant->guest_phone,
                'guest_avatar' => $participant->guest_avatar,
                'guarantor' => $participant->guarantor ? [
                    'id' => $participant->guarantor->id,
                    'full_name' => $participant->guarantor->full_name,
                    'avatar_url' => $participant->guarantor->avatar_url,
                ] : null,
                'is_pending_confirmation' => $participant->is_pending_confirmation,
            ],
            'guest'
        );

        return ResponseHelper::success(
            new ParticipantResource($participant),
            'Thêm guest thành công',
            201
        );
    }

    /**
     * Lấy danh sách guest của một tournament
     * API: GET /api/tournaments/{id}/guests
     */
    public function index(Request $request, $tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền xem danh sách guest', 403);
        }

        $guests = Participant::with(['user', 'guarantor'])
            ->where('tournament_id', $tournamentId)
            ->where('is_guest', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseHelper::success(
            ParticipantResource::collection($guests),
            'Lấy danh sách guest thành công'
        );
    }

    /**
     * Lấy danh sách guest mà user hiện tại bảo lãnh và đang chờ xác nhận
     * API: GET /api/tournaments/{id}/guaranteed-guests
     */
    public function guaranteedGuests(Request $request, $tournamentId)
    {
        $userId = Auth::id();

        $guests = Participant::with(['user', 'guarantor'])
            ->where('tournament_id', $tournamentId)
            ->where('is_guest', true)
            ->where('guarantor_user_id', $userId)
            ->where('is_pending_confirmation', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return ResponseHelper::success(
            ParticipantResource::collection($guests),
            'Lấy danh sách guest bảo lãnh thành công'
        );
    }

    /**
     * Lấy danh sách người có thể làm guarantor
     * API: GET /api/tournaments/{id}/guarantor-candidates
     *
     * Trả về: organizers + confirmed participants
     */
    public function guarantorCandidates(Request $request, $tournamentId)
    {
        $tournament = Tournament::with([
            'staff' => fn($q) => $q->wherePivot('role', TournamentStaff::ROLE_ORGANIZER),
            'participants' => fn($q) => $q->where('is_confirmed', true)->where('is_guest', false),
        ])->findOrFail($tournamentId);

        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền xem danh sách này', 403);
        }

        $organizers = collect($tournament->staff)->map(fn($user) => [
            'user_id' => $user->id,
            'full_name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'is_organizer' => true,
        ]);

        $participants = collect($tournament->participants)->map(fn($participant) => [
            'user_id' => $participant->user_id,
            'full_name' => $participant->user?->full_name,
            'avatar_url' => $participant->user?->avatar_url,
            'is_organizer' => false,
        ]);

        $all = $organizers->concat($participants)
            ->unique('user_id')
            ->values();

        return ResponseHelper::success($all, 'Lấy danh sách người bảo lãnh thành công');
    }

    /**
     * Lấy danh sách guest do một user bảo lãnh
     * API: GET /api/tournaments/{id}/guarantor-guests/{userId}
     */
    public function guarantorGuests(Request $request, $tournamentId, $userId)
    {
        $tournament = Tournament::findOrFail($tournamentId);

        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền xem danh sách này', 403);
        }

        $guests = Participant::with(['user', 'guarantor'])
            ->where('tournament_id', $tournamentId)
            ->where('is_guest', true)
            ->where('guarantor_user_id', $userId)
            ->get();

        return ResponseHelper::success(
            ParticipantResource::collection($guests),
            'Lấy danh sách guest thành công'
        );
    }

    /**
     * BTC duyệt guest do VĐV thêm (chờ xác nhận).
     * API: POST /api/tournaments/{id}/guests/confirm/{participantId}
     */
    public function confirmGuest($tournamentId, $participantId)
    {
        $tournament = Tournament::with('staff')->findOrFail($tournamentId);

        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền xác nhận guest này', 403);
        }

        $participant = Participant::where('tournament_id', $tournamentId)
            ->where('id', $participantId)
            ->where('is_guest', true)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Guest không tồn tại trong giải này', 404);
        }

        if (!$participant->is_pending_confirmation) {
            return ResponseHelper::error('Guest này không cần xác nhận', 400);
        }

        $participant->update([
            'is_confirmed' => true,
            'is_pending_confirmation' => false,
        ]);

        if ($participant->guarantor_user_id) {
            $guarantor = User::find($participant->guarantor_user_id);
            if ($guarantor) {
                $guarantor->notify(new TournamentGuestAddedNotification($tournament, $participant));
            }
        }

        $participant->load(['user', 'guarantor']);

        return ResponseHelper::success(
            new ParticipantResource($participant),
            'Xác nhận guest thành công'
        );
    }

    /**
     * Người bảo lãnh check-in cho guest.
     * API: POST /api/tournaments/{id}/guests/{participantId}/guarantor-check-in
     */
    public function guarantorCheckIn($tournamentId, $participantId)
    {
        $userId = Auth::id();

        $participant = Participant::where('tournament_id', $tournamentId)
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
            new ParticipantResource($participant),
            'Đã check-in guest thành công'
        );
    }

    /**
     * Organizer/admin đánh dấu check-in cho guest.
     * API: POST /api/tournaments/{id}/guests/{participantId}/mark-check-in
     */
    public function markGuestCheckIn(Request $request, $tournamentId, $participantId)
    {
        $userId = Auth::id();
        $tournament = Tournament::findOrFail($tournamentId);

        // === Giải đấu thuộc CLB: kiểm tra club_id và quyền staff ===
        if ($tournament->club_id) {
            $clubId = $request->input('club_id');

            if (!$clubId) {
                return ResponseHelper::error('Giải đấu thuộc CLB. Vui lòng truyền club_id trong body.', 422);
            }

            if ((int) $tournament->club_id !== (int) $clubId) {
                return ResponseHelper::error('Giải đấu không thuộc CLB này', 403);
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
            $isTournamentOrganizer = $tournament->hasOrganizer($userId);

            if (!$isClubStaff && !$isTournamentOrganizer) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho giải này', 403);
            }
        } else {
            if ($request->filled('club_id')) {
                return ResponseHelper::error('Giải đấu không thuộc CLB. Không cần truyền club_id.', 422);
            }

            if (!$tournament->hasOrganizer($userId)) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho guest này', 403);
            }
        }

        $participant = Participant::where('tournament_id', $tournamentId)
            ->where('id', $participantId)
            ->where('is_guest', true)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Guest không tồn tại trong giải này', 404);
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
            new ParticipantResource($participant),
            'Đã đánh dấu check-in guest thành công'
        );
    }

}
