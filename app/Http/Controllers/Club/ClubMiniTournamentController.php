<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubFundContributionStatus;
use App\Enums\ClubMemberRole;
use App\Exceptions\BusinessException;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatusEnum;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MiniTournamentController;
use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use App\Http\Resources\MiniParticipantResource;
use App\Http\Resources\MiniTournamentResource;
use App\Models\Club\Club;
use App\Models\Club\ClubExpense;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Support\MiniTeamNameBuilder;
use App\Notifications\MiniTournamentInvitationNotification;
use App\Services\Club\ClubExpenseService;
use App\Services\Club\ClubFundContributionService;
use App\Services\ImageOptimizationService;
use App\Services\MiniTournamentService;
use App\Services\RoundRobinSchedulerService;
use App\Services\UserSportMatchCounter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClubMiniTournamentController extends Controller
{
    public function __construct(
        protected MiniTournamentService $tournamentService,
        protected ClubFundContributionService $fundContributionService,
        protected ClubExpenseService $expenseService,
    ) {
    }

    public function store(StoreMiniTournamentRequest $request, int $clubId)
    {
        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('CLB không tồn tại', 404);
        }

        if ($club->is_banned) {
            return ResponseHelper::error('CLB tạm thời bị cấm truy cập', 422);
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

        // === Xử lý QR code: ưu tiên use_cached_qr, nếu không thì dùng club wallet (khi use_club_fund=true) ===
        $qrFile = $request->file('qr_code_url');
        if (!$qrFile) {
            if ($request->boolean('use_cached_qr') && Auth::user()->latest_used_qr) {
                $data['qr_code_url'] = Auth::user()->latest_used_qr;
            } elseif ($request->boolean('use_club_fund')) {
                $clubQrWallet = $club->activeQrWallet();
                if ($clubQrWallet && $clubQrWallet->qr_code_url) {
                    $data['qr_code_url'] = str_starts_with($clubQrWallet->qr_code_url, 'http')
                        ? $clubQrWallet->qr_code_url
                        : asset('storage/' . $clubQrWallet->qr_code_url);
                }
            }
        }

        $miniTournament = $this->tournamentService->createTournament($data, $userId);
        $miniTournament->staff()->attach($userId, ['role' => MiniTournamentStaff::ROLE_ORGANIZER]);

        // === use_club_fund = true: tạo khoản chi từ quỹ CLB ===
        // CLB chi tiền cho kèo đấu → trừ quỹ chung + hiển thị trong lịch sử thu chi
        if ($miniTournament->use_club_fund) {
            $feeAmount = (float) ($miniTournament->fee_amount ?? 0);
            if ($feeAmount > 0) {
                $currentBalance = (float) ($club->mainWallet?->balance ?? 0);
                if ($currentBalance < $feeAmount) {
                    $miniTournament->delete();
                    return ResponseHelper::error(
                        "Số dư quỹ CLB hiện tại (" . number_format($currentBalance) . "đ) không đủ để chi trả phí kèo (" . number_format($feeAmount) . "đ). Vui lòng nạp thêm quỹ.",
                        422
                    );
                }
            }
            $this->createClubExpenseForTournament($miniTournament, $club, $userId);
        }

        // === Xử lý included_in_club_fund: tạo ClubFundCollection + ClubFundContribution ===
        // Chỉ tạo collection khi included_in_club_fund = true (đợt thu quỹ chung CLB)
        // use_club_fund = true: tạo ClubExpense trong createClubExpenseForTournament()
        if ($miniTournament->included_in_club_fund) {
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

            // Bước 1: Tạo assignedMembers trước (để markOrganizerExempt có thể check)
            // Organizer/guest exempt → amount_due = 0, Member thường → amount_due = fee_amount
            $allContributorUserIds = [];
            $exemptUserIds = [];
            foreach ($miniTournament->participants as $participant) {
                if (!in_array($participant->user_id, $commonUserIds) && !$participant->is_guest) {
                    continue;
                }

                $isOrganizer = in_array($participant->user_id, $organizerIds);
                $isGuaranteedGuest = in_array($participant->user_id, $guaranteedGuestIds);
                $allContributorUserIds[] = $participant->user_id;

                if ($isOrganizer || $isGuaranteedGuest) {
                    $exemptUserIds[] = $participant->user_id;
                }
            }

            if (!empty($allContributorUserIds)) {
                $pivotData = [];
                foreach ($allContributorUserIds as $uid) {
                    $pivotData[$uid] = [
                        'amount_due' => in_array($uid, $exemptUserIds) ? 0 : $miniTournament->fee_amount,
                    ];
                }
                $collection->assignedMembers()->attach($pivotData);
            }

            // Bước 2: Tạo ClubFundContribution
            // - Organizer/guest exempt → dùng markOrganizerExempt() → Confirmed + wallet tx
            // - Member thường → tạo PENDING (chờ nộp biên lai)
            foreach ($miniTournament->participants as $participant) {
                if (!in_array($participant->user_id, $commonUserIds) && !$participant->is_guest) {
                    continue;
                }

                $isOrganizer = in_array($participant->user_id, $organizerIds);
                $isGuaranteedGuest = in_array($participant->user_id, $guaranteedGuestIds);

                if ($isOrganizer || $isGuaranteedGuest) {
                    // Organizer / guest được organizer bảo lãnh → Confirmed + wallet tx
                    try {
                        $this->fundContributionService->markOrganizerExempt(
                            $collection,
                            $participant->user_id,
                            $userId,
                            (float) $miniTournament->fee_amount
                        );
                    } catch (\Exception $e) {
                        Log::warning('ClubMiniTournamentController: Failed to create organizer exempt contribution', [
                            'tournament_id' => $miniTournament->id,
                            'user_id' => $participant->user_id,
                        ]);
                    }
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

                // Gắn invited user vào ClubFundCollection nếu kèo tính vào quỹ chung CLB
                $this->tournamentService->attachUserToMiniTournamentClubFund($miniTournament, $invitedUserId);

                $user = User::find($invitedUserId);
                if ($user) {
                    $user->notify(new MiniTournamentInvitationNotification($miniTournament, $userId));
                }
            }

            // Nếu use_club_fund = true, cập nhật lại payment_status của invited users
            if ($miniTournament->use_club_fund) {
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

        $imageService = app(ImageOptimizationService::class);

        // Poster: resize + convert WebP + lưu ngay
        $posterFile = $request->file('poster');
        if ($posterFile) {
            $savedPath = $imageService->processAndSaveImage($posterFile, 'posters', 'poster_', 720, 65);
            $miniTournament->update(['poster' => asset('storage/' . $savedPath)]);
        }

        // QR code: resize + convert WebP + lưu ngay
        $qrUrl = null;
        $qrFile = $request->file('qr_code_url');
        if ($qrFile) {
            $savedPath = $imageService->processAndSaveImage($qrFile, 'qr_codes', 'qr_', 500, 60);
            $qrUrl = asset('storage/' . $savedPath);
            $miniTournament->update(['qr_code_url' => $qrUrl]);

            if ($miniTournament->club_fund_collection_id) {
                $miniTournament->fundCollection->update(['qr_code_url' => $qrUrl]);
            }
        }

        $miniTournament->loadFullRelations();
        Cache::increment('club_content_version:' . $club->id);

        return ResponseHelper::success(new MiniTournamentResource($miniTournament), 'Tạo kèo cho CLB thành công', 201);
    }

    public function update(UpdateMiniTournamentRequest $request, int $clubId, int $miniTournamentId)
    {
        $club = Club::findOrFail($clubId);

        if ($club->is_banned) {
            return ResponseHelper::error('CLB tạm thời bị cấm truy cập', 422);
        }

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
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi cập nhật chuỗi kèo đấu', 400);
        }
        }

        unset($data['edit_scope']);

        $wasPartnerRotation = $miniTournament->match_format === \App\Models\MiniTournament::MATCH_FORMAT_PARTNER_ROTATION;
        $miniTournament->update($data);

        // Sync session fields when match_format changes (same logic as createTournament)
        if (isset($data['match_format'])) {
            $newFormat = $data['match_format'];
            if ($newFormat === \App\Models\MiniTournament::MATCH_FORMAT_STANDARD || $newFormat === null) {
                $miniTournament->update([
                    'session_status' => \App\Models\MiniTournament::SESSION_STATUS_ONGOING,
                    'is_session_started' => true,
                ]);
                // Switching away from partner_rotation: clear old matches
                if ($wasPartnerRotation) {
                    $this->clearRoundRobinMatches($miniTournament);
                }
            } elseif ($newFormat === \App\Models\MiniTournament::MATCH_FORMAT_PARTNER_ROTATION) {
                // Switching to partner_rotation: clear old matches first, then gen new ones
                if ($wasPartnerRotation) {
                    $this->clearRoundRobinMatches($miniTournament);
                }
                $this->generatePartnerRotationMatches($miniTournament);
            } elseif (in_array($newFormat, [
                \App\Models\MiniTournament::MATCH_FORMAT_MIXED_GENDER,
                \App\Models\MiniTournament::MATCH_FORMAT_RANK_PAIRING,
            ], true)) {
                $miniTournament->update([
                    'session_status' => \App\Models\MiniTournament::SESSION_STATUS_PENDING_GROUP,
                    'is_session_started' => false,
                ]);
                if ($wasPartnerRotation) {
                    $this->clearRoundRobinMatches($miniTournament);
                }
            }
        }

        $imageService = app(ImageOptimizationService::class);

        $posterFile = $request->file('poster');
        if ($posterFile) {
            $oldPoster = $miniTournament->poster;
            $savedPath = $imageService->processAndSaveImage($posterFile, 'posters', 'poster_', 720, 65);
            $imageService->deleteOldImage($oldPoster);
            $miniTournament->update(['poster' => asset('storage/' . $savedPath)]);
        } elseif ($request->filled('poster') && is_string($request->input('poster'))) {
            $posterStr = trim((string) $request->input('poster'));
            if ($posterStr !== '' && filter_var($posterStr, FILTER_VALIDATE_URL)) {
                $miniTournament->update(['poster' => $posterStr]);
            }
        }

        $qrFile = $request->file('qr_code_url');
        if ($qrFile) {
            $oldQr = $miniTournament->qr_code_url;
            $savedPath = $imageService->processAndSaveImage($qrFile, 'qr_codes', 'qr_', 500, 60);
            $imageService->deleteOldImage($oldQr);
            $miniTournament->update(['qr_code_url' => asset('storage/' . $savedPath)]);
        } elseif ($request->boolean('use_cached_qr') && Auth::user()->latest_used_qr) {
            $miniTournament->update(['qr_code_url' => Auth::user()->latest_used_qr]);
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

    /**
     * Tạo khoản chi ClubExpense + ClubWalletTransaction OUT khi use_club_fund = true.
     * Số tiền chi = fee_amount của kèo.
     * Tạo trong transaction để đảm bảo atomicity.
     */
    protected function createClubExpenseForTournament(MiniTournament $miniTournament, Club $club, int $userId): void
    {
        $totalExpense = (float) ($miniTournament->fee_amount ?? 0);
        if ($totalExpense <= 0) {
            return;
        }

        if (ClubExpense::where('mini_tournament_id', $miniTournament->id)->exists()) {
            return;
        }

        // Bỏ qua check quyền vì user đã được verify ở createClubTournament
        $this->expenseService->createExpense($club, [
            'title' => $miniTournament->name,
            'amount' => $totalExpense,
            'payment_method' => \App\Enums\PaymentMethod::Other,
            'spent_at' => now(),
            'note' => "Quỹ chi kèo CLB. Kèo ID: {$miniTournament->id}.",
            'mini_tournament_id' => $miniTournament->id,
        ], $userId, skipPermissionCheck: true);
    }

    /**
     * Hủy toàn bộ chuỗi lặp lại của kèo thuộc CLB.
     */
    public function cancelRecurrenceSeries(int $clubId, int $miniTournamentId)
    {
        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('CLB không tồn tại', 404);
        }

        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $miniTournament = MiniTournament::find($miniTournamentId);
        if (!$miniTournament || (int) $miniTournament->club_id !== $club->id) {
            return ResponseHelper::error('Kèo đấu không tồn tại hoặc không thuộc CLB này', 404);
        }

        try {
            $count = $this->tournamentService->cancelRecurrenceSeriesForClub($club, (string) $miniTournamentId, $userId);
            \App\Http\Controllers\Club\ClubActivityController::forgetClubContentCache($club->id);
            return ResponseHelper::success(
                ['deleted_count' => $count],
                'Đã xóa các kèo hợp lệ trong chuỗi lặp lại',
                200
            );
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi hủy chuỗi kèo đấu', 403);
        }
    }

    /**
     * Clear Round Robin matches (partner_rotation / mixed_gender / rank_pairing).
     */
    private function clearRoundRobinMatches(MiniTournament $miniTournament): void
    {
        DB::transaction(function () use ($miniTournament) {
            // Delete MiniMatch records with round_number (RR matches)
            \App\Models\MiniMatch::where('mini_tournament_id', $miniTournament->id)
                ->whereNotNull('round_number')
                ->delete();

            // Delete MiniTeam records (created by RR scheduler)
            $teamIds = \App\Models\MiniTeam::where('mini_tournament_id', $miniTournament->id)
                ->pluck('id')
                ->toArray();
            if (!empty($teamIds)) {
                \App\Models\MiniTeamMember::whereIn('mini_team_id', $teamIds)->delete();
                \App\Models\MiniTeam::where('mini_tournament_id', $miniTournament->id)->delete();
            }
        });
    }

    /**
     * Generate partner_rotation matches when organizer switches format to partner_rotation.
     * Validates participants count, generates schedule via RoundRobinSchedulerService,
     * inserts MiniMatch records, and sets session fields to ONGOING.
     */
    private function generatePartnerRotationMatches(MiniTournament $miniTournament): void
    {
        $confirmedParticipants = $miniTournament->participants()
            ->with('user:id,full_name,avatar_url')
            ->where('is_confirmed', true)
            ->where('is_absent', false)
            ->get();

        if ($confirmedParticipants->isEmpty()) {
            $miniTournament->update([
                'session_status' => \App\Models\MiniTournament::SESSION_STATUS_PENDING_GROUP,
                'is_session_started' => false,
            ]);
            return;
        }

        $participantIds = $confirmedParticipants->pluck('id')->toArray();
        $count = count($participantIds);
        if ($count < 3 || $count > 8) {
            $miniTournament->update([
                'session_status' => \App\Models\MiniTournament::SESSION_STATUS_PENDING_GROUP,
                'is_session_started' => false,
            ]);
            return;
        }

        $isDouble = $miniTournament->format === 'double';
        $matchType = $isDouble
            ? RoundRobinSchedulerService::MATCH_TYPE_DOUBLE
            : RoundRobinSchedulerService::MATCH_TYPE_SINGLE;

        $scheduler = new RoundRobinSchedulerService();
        try {
            $schedule = $scheduler->generatePartnerRotationSchedule($participantIds, $matchType);
        } catch (\InvalidArgumentException $e) {
            $miniTournament->update([
                'session_status' => \App\Models\MiniTournament::SESSION_STATUS_PENDING_GROUP,
                'is_session_started' => false,
            ]);
            return;
        }

        // Map participant ID -> user ID
        $participantUserMap = [];
        foreach ($confirmedParticipants as $p) {
            $participantUserMap[$p->id] = $p->user_id;
        }

        $matchesToInsert = [];
        foreach ($schedule['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                $row = [
                    'mini_tournament_id' => $miniTournament->id,
                    'round_number' => $round['round_number'],
                    'is_bye' => $match['is_bye'] ?? false,
                    'status' => \App\Models\MiniMatch::STATUS_PENDING,
                    'team1_id' => null,
                    'team2_id' => null,
                    'participant1_id' => null,
                    'participant2_id' => null,
                    'participant_win_id' => null,
                    'team_win_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($isDouble) {
                    if (isset($match['team1_id']) && isset($match['team2_id'])) {
                        $row['team1_id'] = $match['team1_id'];
                        $row['team2_id'] = $match['team2_id'];
                    }
                } else {
                    if (isset($match['participant1_id'])) {
                        $row['participant1_id'] = $match['participant1_id'];
                    }
                    if (isset($match['participant2_id'])) {
                        $row['participant2_id'] = $match['participant2_id'];
                    }
                }
                $matchesToInsert[] = $row;
            }
        }

        DB::transaction(function () use ($miniTournament, $matchesToInsert, $schedule, $isDouble, $participantUserMap) {
            if ($isDouble) {
                $miniTeamByKey = [];
                $matchOffset = 0;
                foreach ($schedule['rounds'] as $round) {
                    foreach ($round['matches'] as $match) {
                        $isBye = !empty($match['is_bye']);
                        if ($isBye) {
                            $players1 = array_filter($match['team1_players'] ?? []);
                            $players2 = array_filter($match['team2_players'] ?? []);
                            if (!empty($players1)) {
                                $key1 = implode('-', $players1);
                                if (!isset($miniTeamByKey[$key1])) {
                                    $team1UserIds = array_map(fn($pid) => $participantUserMap[$pid] ?? $pid, $players1);
                                    $team1 = \App\Models\MiniTeam::create([
                                        'name' => MiniTeamNameBuilder::buildFromUserIds($team1UserIds, $miniTournament->id),
                                        'mini_tournament_id' => $miniTournament->id,
                                    ]);
                                    foreach ($players1 as $pid) {
                                        \App\Models\MiniTeamMember::create([
                                            'mini_team_id' => $team1->id,
                                            'user_id' => $participantUserMap[$pid] ?? $pid,
                                            'is_guest' => false,
                                        ]);
                                    }
                                    $miniTeamByKey[$key1] = $team1->id;
                                }
                                $matchesToInsert[$matchOffset]['team1_id'] = $miniTeamByKey[$key1];
                            } elseif (!empty($players2)) {
                                $key2 = implode('-', $players2);
                                if (!isset($miniTeamByKey[$key2])) {
                                    $team2UserIds = array_map(fn($pid) => $participantUserMap[$pid] ?? $pid, $players2);
                                    $team2 = \App\Models\MiniTeam::create([
                                        'name' => MiniTeamNameBuilder::buildFromUserIds($team2UserIds, $miniTournament->id),
                                        'mini_tournament_id' => $miniTournament->id,
                                    ]);
                                    foreach ($players2 as $pid) {
                                        \App\Models\MiniTeamMember::create([
                                            'mini_team_id' => $team2->id,
                                            'user_id' => $participantUserMap[$pid] ?? $pid,
                                            'is_guest' => false,
                                        ]);
                                    }
                                    $miniTeamByKey[$key2] = $team2->id;
                                }
                                $matchesToInsert[$matchOffset]['team2_id'] = $miniTeamByKey[$key2];
                            }
                            $matchesToInsert[$matchOffset]['is_bye'] = true;
                            $matchesToInsert[$matchOffset]['status'] = \App\Models\MiniMatch::STATUS_COMPLETED;
                            $matchOffset++;
                            continue;
                        }

                        if (empty($match['team1_players']) || empty($match['team2_players'])) {
                            $matchOffset++;
                            continue;
                        }

                        $key1 = implode('-', $match['team1_players']);
                        if (!isset($miniTeamByKey[$key1])) {
                            $team1UserIds = array_map(fn($pid) => $participantUserMap[$pid] ?? $pid, $match['team1_players']);
                            $team1 = \App\Models\MiniTeam::create([
                                'name' => MiniTeamNameBuilder::buildFromUserIds($team1UserIds, $miniTournament->id),
                                'mini_tournament_id' => $miniTournament->id,
                            ]);
                            foreach ($match['team1_players'] as $pid) {
                                \App\Models\MiniTeamMember::create([
                                    'mini_team_id' => $team1->id,
                                    'user_id' => $participantUserMap[$pid] ?? $pid,
                                    'is_guest' => false,
                                ]);
                            }
                            $miniTeamByKey[$key1] = $team1->id;
                        }

                        $key2 = implode('-', $match['team2_players']);
                        if (!isset($miniTeamByKey[$key2])) {
                            $team2UserIds = array_map(fn($pid) => $participantUserMap[$pid] ?? $pid, $match['team2_players']);
                            $team2 = \App\Models\MiniTeam::create([
                                'name' => MiniTeamNameBuilder::buildFromUserIds($team2UserIds, $miniTournament->id),
                                'mini_tournament_id' => $miniTournament->id,
                            ]);
                            foreach ($match['team2_players'] as $pid) {
                                \App\Models\MiniTeamMember::create([
                                    'mini_team_id' => $team2->id,
                                    'user_id' => $participantUserMap[$pid] ?? $pid,
                                    'is_guest' => false,
                                ]);
                            }
                            $miniTeamByKey[$key2] = $team2->id;
                        }

                        $matchesToInsert[$matchOffset]['team1_id'] = $miniTeamByKey[$key1];
                        $matchesToInsert[$matchOffset]['team2_id'] = $miniTeamByKey[$key2];
                        $matchesToInsert[$matchOffset]['is_bye'] = false;
                        $matchOffset++;
                    }
                }
            }

            \App\Models\MiniMatch::insert($matchesToInsert);
            $miniTournament->update([
                'session_status' => \App\Models\MiniTournament::SESSION_STATUS_ONGOING,
                'session_started_at' => now(),
                'is_session_started' => true,
            ]);
        });

        // Increment total_matches for bye matches (insert bypasses observer).
        $byeMatches = collect($matchesToInsert)->where('status', \App\Models\MiniMatch::STATUS_COMPLETED)->where('is_bye', true);
        if ($byeMatches->isNotEmpty()) {
            $sportId = $miniTournament->sport_id;
            $matchCounter = app(UserSportMatchCounter::class);
            $insertedByeMatches = \App\Models\MiniMatch::with('team1.members')
                ->where('mini_tournament_id', $miniTournament->id)
                ->where('status', \App\Models\MiniMatch::STATUS_COMPLETED)
                ->where('is_bye', true)
                ->whereIn('team1_id', $byeMatches->pluck('team1_id')->filter()->unique()->values())
                ->get();
            foreach ($insertedByeMatches as $byeMatch) {
                if ($byeMatch->team1_id) {
                    $matchCounter->incrementForMiniTeam($byeMatch->team1_id, $sportId);
                }
            }
        }
    }
}
