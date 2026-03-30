<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubFundContributionStatus;
use App\Enums\ClubMemberRole;
use App\Enums\PaymentStatusEnum;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use App\Http\Resources\MiniParticipantResource;
use App\Http\Resources\MiniTournamentResource;
use App\Models\Club\Club;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Notifications\MiniTournamentInvitationNotification;
use App\Services\MiniTournamentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ClubMiniTournamentController extends Controller
{
    public function __construct(
        protected MiniTournamentService $tournamentService,
    ) {
    }

    public function store(StoreMiniTournamentRequest $request, int $clubId)
    {
        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('CLB không tồn tại', 404);
        }

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

        // === Xử lý QR code: nếu CLB có mã QR chung và use_club_fund = true thì gán luôn ===
        $qrFile = $request->file('qr_code_url');
        if (!$qrFile && $request->boolean('use_club_fund')) {
            $clubQrWallet = $club->activeQrWallet();
            if ($clubQrWallet && $clubQrWallet->qr_code_url) {
                $qrUrl = str_starts_with($clubQrWallet->qr_code_url, 'http')
                    ? $clubQrWallet->qr_code_url
                    : asset('storage/' . $clubQrWallet->qr_code_url);
                $data['qr_code_url'] = $qrUrl;
            }
        }

        $miniTournament = $this->tournamentService->createTournament($data, $userId);
        $miniTournament->staff()->attach($userId, ['role' => MiniTournamentStaff::ROLE_ORGANIZER]);

        // === Xử lý use_club_fund: tạo ClubFundCollection + ClubFundContribution ===
        if ($miniTournament->use_club_fund && $miniTournament->has_fee) {
            // Tạo ClubFundCollection
            $collection = ClubFundCollection::create([
                'club_id' => $club->id,
                'title' => $miniTournament->name,
                'description' => $miniTournament->fee_description,
                'target_amount' => $miniTournament->fee_amount,
                'amount_per_member' => $miniTournament->fee_amount,
                'currency' => 'VND',
                'start_date' => $miniTournament->start_time,
                'end_date' => $miniTournament->end_time ?? $miniTournament->start_time,
                'status' => ClubFundCollectionStatus::Active,
                'qr_code_url' => $miniTournament->qr_code_url,
                'created_by' => $userId,
                'included_in_club_fund' => true,
            ]);

            $miniTournament->update(['club_fund_collection_id' => $collection->id]);

            // Gán member CLB (intersect participant.user_id với club.member.user_id)
            $clubMemberUserIds = $club->activeMembers()->pluck('user_id')->toArray();
            $participantUserIds = $miniTournament->participants()->pluck('user_id')->toArray();
            $commonUserIds = array_intersect($clubMemberUserIds, $participantUserIds);

            // Lấy organizer IDs
            $organizerIds = $miniTournament->staff()->pluck('user_id')->toArray();

            // Lấy guest được organizer bảo lãnh
            $guaranteedGuestIds = $miniTournament->participants()
                ->where('is_guest', true)
                ->whereIn('guarantor_user_id', $organizerIds)
                ->pluck('user_id')
                ->toArray();

            // === Tạo ClubFundContribution cho member CLB ===
            foreach ($miniTournament->participants as $participant) {
                if (!in_array($participant->user_id, $commonUserIds)) {
                    continue;
                }

                $isOrganizer = in_array($participant->user_id, $organizerIds);
                $isGuaranteedGuest = in_array($participant->user_id, $guaranteedGuestIds);

                if ($isOrganizer || $isGuaranteedGuest) {
                    // Organizer / guest được organizer bảo lãnh → CONFIRMED
                    // (wallet IN được tạo khi kèo bắt đầu hoặc khi admin xác nhận thanh toán)
                    ClubFundContribution::create([
                        'club_fund_collection_id' => $collection->id,
                        'user_id' => $participant->user_id,
                        'amount' => $miniTournament->fee_amount,
                        'receipt_url' => null,
                        'note' => 'Admin tạo kèo CLB - bao phí',
                        'status' => ClubFundContributionStatus::Confirmed,
                    ]);
                } else {
                    // Member thường / guest thường → PENDING (chờ nộp biên lai)
                    ClubFundContribution::create([
                        'club_fund_collection_id' => $collection->id,
                        'user_id' => $participant->user_id,
                        'amount' => $miniTournament->fee_amount,
                        'receipt_url' => null,
                        'note' => 'Khoản thu cố định - vui lòng nộp biên lai',
                        'status' => ClubFundContributionStatus::Pending,
                    ]);
                }
            }

            // Gán assignedMembers với amount_due
            if (!empty($commonUserIds)) {
                if ($miniTournament->auto_split_fee) {
                    // auto_split_fee=true: chưa biết số tiền, đợi FinalizeCommand tính
                    $collection->assignedMembers()->attach($commonUserIds, [
                        'amount_due' => 0,
                    ]);
                } else {
                    $collection->assignedMembers()->attach($commonUserIds, [
                        'amount_due' => $miniTournament->fee_amount,
                    ]);
                }
            }
        }

        if ($request->has('invite_user')) {
            $inviteUsers = $request->input('invite_user', []);

            $paymentStatus = \App\Enums\PaymentStatusEnum::CONFIRMED;
            if ($miniTournament->has_fee && !$miniTournament->auto_split_fee && !$miniTournament->use_club_fund) {
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

            // Nếu use_club_fund = true, cập nhật lại payment_status của invited users
            if ($miniTournament->use_club_fund && $miniTournament->has_fee) {
                $invitedParticipantIds = $miniTournament->participants()
                    ->whereIn('user_id', $inviteUsers)
                    ->pluck('id')
                    ->toArray();
                if (!empty($invitedParticipantIds)) {
                    $miniTournament->participants()
                        ->whereIn('id', $invitedParticipantIds)
                        ->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
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
        Cache::increment('club_content_version:' . $club->id);

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
        } elseif ($request->filled('poster') && is_string($request->input('poster'))) {
            $posterStr = trim((string) $request->input('poster'));
            if ($posterStr !== '' && filter_var($posterStr, FILTER_VALIDATE_URL)) {
                $miniTournament->update(['poster' => $posterStr]);
            }
        }

        $qrFile = $request->file('qr_code_url');
        if ($qrFile) {
            $qrPath = $qrFile->store('qr_codes', 'public');
            $miniTournament->update(['qr_code_url' => asset('storage/' . $qrPath)]);
        }

        $miniTournament->loadFullRelations();
        Cache::increment('club_content_version:' . $club->id);

        return ResponseHelper::success(new MiniTournamentResource($miniTournament), 'Cập nhật kèo cho CLB thành công');
    }

    /**
     * Admin đánh dấu member đã check-in kèo đấu
     */
    public function markCheckIn(int $clubId, int $miniTournamentId, int $participantId)
    {
        $club = Club::findOrFail($clubId);
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);
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
        if ($participant->checked_in_at) {
            return ResponseHelper::error('Thành viên đã được đánh dấu check-in rồi', 422);
        }

        // Admin/organizer được quyền check-in hộ thành viên mà không cần thanh toán đã confirmed
        $participant->update([
            'is_confirmed' => true,
            'checked_in_at' => now(),
        ]);

        $participant->load('user');

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Đã đánh dấu check-in thành công'
        );
    }

    /**
     * Admin đánh dấu member vắng mặt kèo đấu
     */
    public function markAbsent(int $clubId, int $miniTournamentId, int $participantId)
    {
        $club = Club::findOrFail($clubId);
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ((int) $miniTournament->club_id !== $club->id) {
            return ResponseHelper::error('Kèo đấu không thuộc CLB này', 404);
        }

        $clubMember = $club->activeMembers()->where('user_id', $userId)->first();
        $isClubStaff = $clubMember && in_array($clubMember->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true);
        $isTournamentOrganizer = $miniTournament->staff->contains(fn($staff) => (int) $staff->pivot->user_id === $userId && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER);

        if (!$isClubStaff && !$isTournamentOrganizer) {
            return ResponseHelper::error('Bạn không có quyền đánh dấu vắng mặt cho kèo này', 403);
        }

        $participant = $miniTournament->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong kèo đấu này', 404);
        }

        if ($participant->is_absent) {
            return ResponseHelper::error('Thành viên đã được đánh dấu vắng mặt rồi', 422);
        }

        $participant->update([
            'is_absent' => true,
        ]);

        $participant->load('user');

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Đã đánh dấu vắng mặt thành công'
        );
    }
}
