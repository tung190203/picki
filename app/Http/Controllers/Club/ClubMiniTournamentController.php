<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubMemberRole;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use App\Http\Resources\MiniTournamentResource;
use App\Models\Club\Club;
use App\Models\MiniParticipant;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Notifications\MiniTournamentInvitationNotification;
use App\Services\MiniTournamentService;
use Illuminate\Support\Facades\Auth;

class ClubMiniTournamentController extends Controller
{
    public function __construct(
        protected MiniTournamentService $tournamentService
    ) {
    }

    public function store(StoreMiniTournamentRequest $request, int $clubId)
    {
        $club = Club::findOrFail($clubId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền tạo kèo cho CLB', 403);
        }

        $data = $request->safe()->except(['invite_user', 'poster', 'qr_code_url']);
        $data['club_id'] = $club->id;

        $miniTournament = $this->tournamentService->createTournament($data, $userId);
        $miniTournament->staff()->attach($userId, ['role' => MiniTournamentStaff::ROLE_ORGANIZER]);

        if ($request->has('invite_user')) {
            $inviteUsers = $request->input('invite_user', []);

            $paymentStatus = \App\Enums\PaymentStatusEnum::CONFIRMED;
            if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
                $paymentStatus = \App\Enums\PaymentStatusEnum::PENDING;
            }

            foreach ($inviteUsers as $invitedUserId) {
                $miniTournament->participants()->create([
                    'user_id' => $invitedUserId,
                    'is_confirmed' => true,
                    'is_invited' => true,
                    'payment_status' => $paymentStatus,
                ]);
                $user = User::find($invitedUserId);
                if ($user) {
                    $user->notify(new MiniTournamentInvitationNotification($miniTournament, $userId));
                }
            }
        }

        $posterFile = $request->file('poster');
        if ($posterFile) {
            $posterPath = $posterFile->store('posters', 'public');
            $posterUrl = asset('storage/' . $posterPath);
            $miniTournament->update(['poster' => $posterUrl]);
        }

        $qrFile = $request->file('qr_code_url');
        if ($qrFile) {
            $qrPath = $qrFile->store('qr_codes', 'public');
            $qrUrl = asset('storage/' . $qrPath);
            $miniTournament->update(['qr_code_url' => $qrUrl]);
        }

        $miniTournament->loadFullRelations();

        return ResponseHelper::success(new MiniTournamentResource($miniTournament), 'Tạo kèo cho CLB thành công', 201);
    }

    public function update(UpdateMiniTournamentRequest $request, int $clubId, int $miniTournamentId)
    {
        $club = Club::findOrFail($clubId);
        $miniTournament = \App\Models\MiniTournament::findOrFail($miniTournamentId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ((int) $miniTournament->club_id !== $club->id) {
            return ResponseHelper::error('Kèo đấu không thuộc CLB này', 404);
        }

        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền cập nhật kèo của CLB', 403);
        }

        $editScope = $request->input('edit_scope', 'this_occurrence');
        $data = $request->safe()->except(['invite_user', 'poster', 'qr_code_url']);

        if ($editScope === 'entire_series' && !empty($miniTournament->recurrence_series_id)) {
            try {
                $updated = $this->tournamentService->updateTournamentAsNewSeries($miniTournament, $data, $userId);
                return ResponseHelper::success(
                    new MiniTournamentResource($updated->loadFullRelations()),
                    'Cập nhật chuỗi kèo đấu thành công'
                );
            } catch (\Exception $e) {
                return ResponseHelper::error($e->getMessage(), 400);
            }
        }

        unset($data['edit_scope']);

        $miniTournament->update($data);

        $posterFile = $request->file('poster');
        if ($posterFile) {
            $posterPath = $posterFile->store('posters', 'public');
            $miniTournament->update(['poster' => asset('storage/' . $posterPath)]);
        }

        $qrFile = $request->file('qr_code_url');
        if ($qrFile) {
            $qrPath = $qrFile->store('qr_codes', 'public');
            $miniTournament->update(['qr_code_url' => asset('storage/' . $qrPath)]);
        }

        $miniTournament->loadFullRelations();

        return ResponseHelper::success(new MiniTournamentResource($miniTournament), 'Cập nhật kèo cho CLB thành công');
    }

    /**
     * Admin đánh dấu member đã check-in kèo đấu
     */
    public function markCheckIn(int $clubId, int $miniTournamentId, int $participantId)
    {
        $club = Club::findOrFail($clubId);
        $miniTournament = \App\Models\MiniTournament::findOrFail($miniTournamentId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ((int) $miniTournament->club_id !== $club->id) {
            return ResponseHelper::error('Kèo đấu không thuộc CLB này', 404);
        }

        // Check permission: chỉ admin, manager, secretary của CLB HOẶC organizer của kèo mới được check-in hộ
        $clubMember = $club->activeMembers()->where('user_id', $userId)->first();
        $isClubStaff = $clubMember && in_array($clubMember->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true);
        $isTournamentOrganizer = $miniTournament->staff->contains(fn($staff) => (int) $staff->pivot->user_id === $userId && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER);

        if (!$isClubStaff && !$isTournamentOrganizer) {
            return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho kèo này', 403);
        }

        $participant = $miniTournament->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong kèo đấu này', 404);
        }

        // Kiểm tra đã check-in chưa
        if ($participant->is_confirmed) {
            return ResponseHelper::error('Thành viên đã được đánh dấu check-in rồi', 422);
        }

        // Kiểm tra thanh toán: kèo có phí thì phải CONFIRMED mới check-in được
        if ($miniTournament->has_fee && $participant->payment_status !== \App\Enums\PaymentStatusEnum::CONFIRMED) {
            return ResponseHelper::error('Thành viên chưa thanh toán hoặc chưa được xác nhận thanh toán', 422);
        }

        $participant->update([
            'is_confirmed' => true,
        ]);

        $participant->load('user');

        return ResponseHelper::success(
            new \App\Http\Resources\MiniParticipantResource($participant),
            'Đã đánh dấu check-in thành công'
        );
    }
}
