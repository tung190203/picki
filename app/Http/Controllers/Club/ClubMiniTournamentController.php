<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubMemberRole;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use App\Http\Resources\MiniTournamentResource;
use App\Models\Club\Club;
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
}
