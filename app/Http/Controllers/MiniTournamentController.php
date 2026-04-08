<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Jobs\SendPushJob;
use App\Helpers\ResponseHelper;
use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use App\Http\Resources\ListMiniTournamentResource;
use App\Http\Resources\MiniParticipantResource;
use App\Http\Resources\MiniTournamentResource;
use App\Models\Club\Club;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Notifications\MiniTournamentInvitationNotification;
use App\Services\MiniTournamentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MiniTournamentController extends Controller
{
    public function __construct(
        protected MiniTournamentService $tournamentService
    ) {
    }

    /**
     * Sau khi cập nhật check-in ở mini_participants, đồng thời cập nhật
     * bản ghi mini_tournament_staff cùng user_id — nếu chưa có trạng thái.
     */
    private function syncMiniStaffAttendanceFromParticipant(MiniParticipant $participant): void
    {
        $staff = MiniTournamentStaff::where('mini_tournament_id', $participant->mini_tournament_id)
            ->where('user_id', $participant->user_id)
            ->first();

        if (!$staff || $staff->checked_in_at || $staff->is_absent) {
            return;
        }

        $staff->update([
            'checked_in_at' => $participant->checked_in_at,
            'is_absent' => false,
        ]);
    }

    /**
     * Sau khi báo vắng ở mini_participants, đồng thời báo vắng ở
     * mini_tournament_staff cùng user_id — nếu chưa có trạng thái.
     */
    private function syncMiniStaffAbsentFromParticipant(MiniParticipant $participant): void
    {
        $staff = MiniTournamentStaff::where('mini_tournament_id', $participant->mini_tournament_id)
            ->where('user_id', $participant->user_id)
            ->first();

        if (!$staff || $staff->checked_in_at || $staff->is_absent) {
            return;
        }

        $staff->update(['is_absent' => true]);
    }

    /**
     * tạo mini tournament
     */
    public function store(StoreMiniTournamentRequest $request)
    {
        $data = $request->safe()->except(['invite_user', 'poster', 'qr_code_url']);

        $miniTournament = $this->tournamentService->createTournament($data, Auth::id());
        $miniTournament->staff()->attach(Auth::id(), ['role' => MiniTournamentStaff::ROLE_ORGANIZER]);

        if ($request->has('invite_user')) {
            $inviteUsers = $request->input('invite_user', []);

            // Calculate payment_status for invited users
            // - use_club_fund = true: CLB chi → invited users = CONFIRMED
            // - auto_split_fee = true: chia đều → CONFIRMED (chờ command tính)
            // - has_fee + auto_split_fee off: phí cố định → PENDING
            $paymentStatus = \App\Enums\PaymentStatusEnum::CONFIRMED;
            if ($miniTournament->has_fee && !$miniTournament->auto_split_fee && !$miniTournament->use_club_fund) {
                $paymentStatus = \App\Enums\PaymentStatusEnum::PENDING;
            }

            foreach ($inviteUsers as $userId) {
                MiniParticipant::create([
                    'mini_tournament_id' => $miniTournament->id,
                    'user_id' => $userId,
                    'is_confirmed' => true,
                    'is_invited' => true,
                    'payment_status' => $paymentStatus,
                ]);
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new MiniTournamentInvitationNotification($miniTournament, Auth::id()));
                }
            }
        }

        // Handle poster file
        $posterFile = $request->file('poster');
        if ($posterFile) {
            $posterPath = $posterFile->store('posters', 'public');
            $posterUrl = asset('storage/' . $posterPath);
            $miniTournament->update(['poster' => $posterUrl]);
        }

        // Handle qr_code_url file
        $qrFile = $request->file('qr_code_url');
        if ($qrFile) {
            $qrPath = $qrFile->store('qr_codes', 'public');
            $qrUrl = asset('storage/' . $qrPath);
            $miniTournament->update(['qr_code_url' => $qrUrl]);
        }

        $miniTournament->loadFullRelations();

        return ResponseHelper::success(new MiniTournamentResource($miniTournament), 'Tạo kèo đấu thành công', 201);
    }
    /**
     * danh sách mini tournament
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sport_id' => 'sometimes|integer|exists:sports,id',
            'status' => 'sometimes|in:upcoming,ongoing,completed,cancelled',
            'per_page' => 'sometimes|integer|min:1|max:200',
            'keyword'  => 'nullable|string'
        ]);
        $nowVN = Carbon::now('Asia/Ho_Chi_Minh')->toDateString();
        $query = MiniTournament::withFullRelations();

        if ($request->has('sport_id')) {
            $query->where('sport_id', $validated['sport_id']);
        }

        if ($request->has('status')) {
            $query->where('status', $validated['status']);
        }

        // 🔥 keyword search (tên kèo + tên sân + địa chỉ sân)
        if (!empty($validated['keyword'])) {
            $kw = trim($validated['keyword']);

            $query->where(function ($q) use ($kw) {
                $q->where('mini_tournaments.name', 'LIKE', "%{$kw}%")
                  ->orWhereHas('competitionLocation', function ($loc) use ($kw) {
                      $loc->where('competition_locations.name', 'LIKE', "%{$kw}%")
                          ->orWhere('competition_locations.address', 'LIKE', "%{$kw}%");
                  });
            });
        }

        $query->whereDate('start_time', '>=', $nowVN);
        $userId = auth()->id();
        $query->where(function ($q) use ($userId) {
            // Kèo công khai, không private, không ở trạng thái draft/cancelled/closed
            $q->where(function ($publicSub) {
                $publicSub->where('is_private', '!=', 1)
                    ->whereNotIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_CLOSED, MiniTournament::STATUS_CANCELLED]);
            });

            if ($userId) {
                // Organizer: thấy tất cả kèo mình tổ chức (kể cả draft)
                $q->orWhere(function ($staffSub) use ($userId) {
                    $staffSub->whereHas('miniTournamentStaffs', function ($staffQuery) use ($userId) {
                        $staffQuery->where('user_id', $userId)
                            ->where('role', MiniTournamentStaff::ROLE_ORGANIZER);
                    });
                });

                // Participant: thấy tất cả kèo mình tham gia (kể cả draft)
                $q->orWhere(function ($partSub) use ($userId) {
                    $partSub->whereHas('participants', function ($partQuery) use ($userId) {
                        $partQuery->where('user_id', $userId);
                    });
                });
            }
        });

        $miniTournaments = $query->paginate($validated['per_page'] ?? MiniTournament::PER_PAGE);

        $data = [
            'mini_tournaments' => ListMiniTournamentResource::collection($miniTournaments),
        ];

        $meta = [
            'current_page' => $miniTournaments->currentPage(),
            'last_page' => $miniTournaments->lastPage(),
            'per_page' => $miniTournaments->perPage(),
            'total' => $miniTournaments->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách kèo đấu thành công', 200, $meta);
    }
    /**
     * chi tiết mini tournament
     */
    public function show($id)
    {
        $miniTournament = MiniTournament::withFullRelations()
            ->with('participants.invitedBy')
            ->findOrFail($id);

        $userId = Auth::id();
        $isOrganizer = $miniTournament->hasOrganizer($userId);

        // Kèo chưa công bố (status = 1 = STATUS_DRAFT):
        // - Organizer: thấy tất cả (bao gồm matches)
        // - Người được mời (is_invited=true, is_confirmed=false): thấy kèo nhưng ẩn matches
        // - Người khác: không thấy matches
        if ($miniTournament->status === MiniTournament::STATUS_DRAFT) {
            $isInvited = $miniTournament->participants->contains(fn($p) =>
                (int) $p->user_id === (int) $userId
                && (bool) $p->is_invited === true
                && (bool) $p->is_confirmed === false
            );

            if (!$isOrganizer && !$isInvited) {
                $miniTournament->setRelation('matches', collect());
            }
        }

        return ResponseHelper::success(new MiniTournamentResource($miniTournament), 'Lấy thông tin chi tiết kèo đấu thành công');
    }
    /**
     * cập nhật mini tournament
     */
    public function update(UpdateMiniTournamentRequest $request, $id)
    {
        $miniTournament = MiniTournament::withFullRelations()->findOrFail($id);
        $data = $request->validated();

        $editScope = $data['edit_scope'] ?? 'this_occurrence';
        unset($data['edit_scope']);

        $data = collect($data)->except(['poster', 'qr_code_url'])->toArray();

        if (array_key_exists('has_fee', $data) && !$data['has_fee']) {
            $data['fee_amount'] = 0;
            $data['auto_split_fee'] = false;
            $data['fee_description'] = null;
            $data['payment_account_id'] = null;
        }

        $isOrganizer = $miniTournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền cập nhật kèo đấu', 403);
        }

        if ($editScope === 'entire_series' && !empty($miniTournament->recurrence_series_id)) {
            try {
                $updatedTournament = $this->tournamentService->updateTournamentAsNewSeries($miniTournament, $data, Auth::id());
                return ResponseHelper::success(
                    new MiniTournamentResource($updatedTournament->loadFullRelations()),
                    'Cập nhật chuỗi kèo đấu thành công'
                );
            } catch (\Exception $e) {
                return ResponseHelper::error($e->getMessage(), 400);
            }
        }

        $miniTournament->update($data);

        // Sync payment status khi has_fee thay đổi (free→paid hoặc paid→free)
        $wasPaid = (bool) $miniTournament->has_fee;
        $isNowPaid = isset($data['has_fee']) ? (bool) $data['has_fee'] : $wasPaid;
        if ($wasPaid !== $isNowPaid) {
            $this->syncParticipantsPaymentStatus($miniTournament, $isNowPaid);
        }

        // Sync payment status khi auto_split_fee hoặc fee_amount thay đổi (giữ nguyên has_fee)
        if ($miniTournament->has_fee) {
            $autoSplitChanged = isset($data['auto_split_fee']) && (bool) $data['auto_split_fee'] !== (bool) $miniTournament->auto_split_fee;
            $feeAmountChanged = isset($data['fee_amount']) && (float) $data['fee_amount'] !== (float) $miniTournament->fee_amount;
            if ($autoSplitChanged || $feeAmountChanged) {
                $this->syncParticipantsPaymentStatus($miniTournament, true);
            }
        }

        if ($request->hasFile('poster')) {
            $posterPath = $request->file('poster')->store('posters', 'public');
            $posterUrl = asset('storage/' . $posterPath);
            $miniTournament->update(['poster' => $posterUrl]);
        } elseif ($request->filled('poster') && is_string($request->input('poster'))) {
            $posterStr = trim((string) $request->input('poster'));
            if ($posterStr !== '' && filter_var($posterStr, FILTER_VALIDATE_URL)) {
                $miniTournament->update(['poster' => $posterStr]);
            }
        }

        if ($request->hasFile('qr_code_url')) {
            $qrPath = $request->file('qr_code_url')->store('qr_codes', 'public');
            $qrUrl = asset('storage/' . $qrPath);
            $miniTournament->update(['qr_code_url' => $qrUrl]);
        }

        $miniTournament->loadFullRelations();

        return ResponseHelper::success(new MiniTournamentResource($miniTournament), 'Cập nhật thông tin kèo đấu thành công');
    }

    public function destroy(Request $request, $id)
    {
        $miniTournament = MiniTournament::with(['participants', 'miniTournamentStaffs'])->find($id);

        if(!$miniTournament) {
            return ResponseHelper::error('Kèo đấu không tồn tại', 404);
        }

        $isOrganizer = $miniTournament->hasOrganizer(Auth::id());

        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền huỷ kèo đấu', 403);
        }

        $hasCompletedMatch = MiniMatch::where('mini_tournament_id', $miniTournament->id)->where('status', MiniMatch::STATUS_COMPLETED)->exists();

        if($hasCompletedMatch) {
            return ResponseHelper::error('Không thể huỷ bỏ kèo đã có trận đấu được xác nhận', 404);
        }

        // Check allow_cancellation setting + thời điểm hết hạn hủy kèo
        if (!$miniTournament->allow_cancellation) {
            return ResponseHelper::error('Kèo đấu này không cho phép hủy', 403);
        }

        if ($miniTournament->isCancellationClosed(Carbon::now())) {
            $minutesRemaining = null;

            if ($miniTournament->start_time && $miniTournament->cancellation_duration !== null) {
                $now = Carbon::now();
                $minutesUntilStart = $now->diffInMinutes($miniTournament->start_time, false);
                $minutesRemaining = $miniTournament->cancellation_duration - $minutesUntilStart;
            }

            $message = "Không thể hủy kèo lúc này. Phải hủy ít nhất {$miniTournament->cancellation_duration} phút trước khi kèo bắt đầu.";

            if ($minutesRemaining !== null) {
                $message .= " Còn {$minutesRemaining} phút nữa mới hết hạn.";
            }

            return ResponseHelper::error($message, 403);
        }

        $organizerIds = $miniTournament->miniTournamentStaffs
            ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->toArray();

        $memberIds = $miniTournament->participants
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->reject(fn($userId) => in_array((int)$userId, $organizerIds, true))
            ->values()
            ->toArray();

        DB::transaction(function () use ($miniTournament) {
            $miniTournament->delete();
        });

        if ($miniTournament->club_id) {
            Cache::increment('club_content_version:' . $miniTournament->club_id);
        }

        if (!empty($memberIds)) {
            $this->pushToUsers(
                $memberIds,
                'Kèo đấu đã bị hủy',
                'Kèo đấu "' . $miniTournament->name . '" đã bị chủ kèo hủy.',
                [
                    'type' => 'MINI_TOURNAMENT_CANCELLED',
                    'mini_tournament_id' => $miniTournament->id,
                ]
            );
        }

        return ResponseHelper::success(null, 'Xoá kèo đấu thành công');
    }

    private function pushToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        foreach ($userIds as $userId) {
            SendPushJob::dispatch($userId, $title, $body, $data);
        }
    }

    /**
     * Sync trạng thái thanh toán của participants khi thay đổi phí
     *
     * Cac truong hop xu ly:
     * 1. has_fee: true → false (co phi → mien phi) → CANCELLED
     * 2. has_fee: false → true (mien phi → co phi)
     * 3. auto_split_fee thay doi (gia co dinh / chia tu dong)
     * 4. fee_amount thay doi
     *
     * Confirmed payments:
     *   - Organizer (chu keo)
     *   - Guest duoc chinh organizer bao lan
     * Pending payments:
     *   - Member thuong
     *   - Guest duoc member khac (khong phai organizer) bao lan
     */
    private function syncParticipantsPaymentStatus(MiniTournament $miniTournament, bool $isNowPaid): void
    {
        $organizerIds = $miniTournament->staff()->pluck('user_id')->toArray();

        $sponsoredByOrganizerGuestIds = [];
        if (!empty($organizerIds)) {
            $sponsoredByOrganizerGuestIds = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                ->where('is_guest', true)
                ->whereIn('guarantor_user_id', $organizerIds)
                ->pluck('user_id')
                ->toArray();
        }

        $confirmedParticipants = $miniTournament->participants()
            ->where('is_confirmed', true)
            ->get();

        if ($confirmedParticipants->isEmpty()) {
            return;
        }

        $feePerPerson = 0;
        if ($isNowPaid) {
            $participantCount = $confirmedParticipants->count();
            if ($miniTournament->auto_split_fee) {
                $feePerPerson = $miniTournament->final_fee_per_person !== null
                    ? $miniTournament->final_fee_per_person
                    : round($miniTournament->fee_amount / $participantCount);
            } else {
                $feePerPerson = $miniTournament->fee_amount;
            }
        }

        foreach ($confirmedParticipants as $participant) {
            $isOrganizer = in_array($participant->user_id, $organizerIds);
            $isSponsoredByOrganizer = in_array($participant->user_id, $sponsoredByOrganizerGuestIds);

            if (!$isNowPaid) {
                if ($participant->payment_status !== \App\Enums\PaymentStatusEnum::CANCELLED) {
                    $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::CANCELLED]);
                }
                MiniParticipantPayment::where('mini_tournament_id', $miniTournament->id)
                    ->where('participant_id', $participant->id)
                    ->update(['status' => MiniParticipantPayment::STATUS_REJECTED]);
            } elseif ($isOrganizer || $isSponsoredByOrganizer) {
                if ($participant->payment_status !== \App\Enums\PaymentStatusEnum::CONFIRMED) {
                    $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::CONFIRMED]);
                }
                $this->upsertPaymentRecord($miniTournament, $participant, 0, MiniParticipantPayment::STATUS_CONFIRMED);
            } else {
                if ($participant->payment_status !== \App\Enums\PaymentStatusEnum::PENDING) {
                    $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::PENDING]);
                }
                $this->upsertPaymentRecord($miniTournament, $participant, $feePerPerson, MiniParticipantPayment::STATUS_PENDING);
            }
        }
    }

    /**
     * Tao hoac cap nhat payment record cho participant
     */
    private function upsertPaymentRecord(
        MiniTournament $tournament,
        MiniParticipant $participant,
        float $amount,
        string $status
    ): void {
        $existing = MiniParticipantPayment::where('mini_tournament_id', $tournament->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($existing) {
            $existing->update(['amount' => $amount, 'status' => $status]);
        } else {
            MiniParticipantPayment::create([
                'mini_tournament_id' => $tournament->id,
                'participant_id' => $participant->id,
                'user_id' => $participant->user_id,
                'amount' => $amount,
                'status' => $status,
            ]);
        }
    }

    public function cancelRecurrenceSeries(Request $request, $tournamentId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        try {
            $count = $this->tournamentService->cancelRecurrenceSeries((string) $tournamentId, $userId);
            return ResponseHelper::success(
                ['deleted_count' => $count],
                'Đã xóa các kèo hợp lệ trong chuỗi lặp lại',
                200
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    /**
     * Organizer / Club staff đánh dấu member đã check-in kèo đấu.
     * - Kèo CLB: cần truyền club_id trong body, chỉ admin/manager/secretary hoặc organizer mới được phép.
     * - Kèo thường: không cần club_id, chỉ organizer mới được phép.
     */
    public function markParticipantCheckIn(Request $request, int $miniTournamentId, int $participantId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

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
            $isTournamentOrganizer = $miniTournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER
            );

            if (!$isClubStaff && !$isTournamentOrganizer) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho kèo này', 403);
            }
        } else {
            // === Kèo thường: chỉ organizer ===
            if ($request->filled('club_id')) {
                return ResponseHelper::error('Kèo không thuộc CLB. Không cần truyền club_id.', 422);
            }

            $isOrganizer = $miniTournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER
            );

            if (!$isOrganizer) {
                return ResponseHelper::error('Chỉ organizer kèo đấu mới có quyền đánh dấu check-in', 403);
            }
        }

        $participant = $miniTournament->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong kèo đấu này', 404);
        }

        if ($participant->checked_in_at) {
            return ResponseHelper::error('Thành viên đã check-in rồi. Không thể check-in lại.', 422);
        }

        if ($participant->is_absent) {
            $participant->update([
                'is_confirmed' => true,
                'checked_in_at' => now(),
                'is_absent' => false,
            ]);
        } else {
            $participant->update([
                'is_confirmed' => true,
                'checked_in_at' => now(),
            ]);
        }

        $participant->load('user');

        $this->syncMiniStaffAttendanceFromParticipant($participant);

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Đã đánh dấu check-in thành công'
        );
    }

    /**
     * Organizer / Club staff đánh dấu member vắng mặt kèo đấu.
     * - Kèo CLB: cần truyền club_id trong body, chỉ admin/manager/secretary hoặc organizer mới được phép.
     * - Kèo thường: không cần club_id, chỉ organizer mới được phép.
     */
    public function markParticipantAbsent(Request $request, int $miniTournamentId, int $participantId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

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
            $isTournamentOrganizer = $miniTournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER
            );

            if (!$isClubStaff && !$isTournamentOrganizer) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu vắng mặt cho kèo này', 403);
            }
        } else {
            // === Kèo thường: chỉ organizer ===
            if ($request->filled('club_id')) {
                return ResponseHelper::error('Kèo không thuộc CLB. Không cần truyền club_id.', 422);
            }

            $isOrganizer = $miniTournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER
            );

            if (!$isOrganizer) {
                return ResponseHelper::error('Chỉ organizer kèo đấu mới có quyền đánh dấu vắng mặt', 403);
            }
        }

        $participant = $miniTournament->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong kèo đấu này', 404);
        }

        if ($participant->is_absent) {
            return ResponseHelper::error('Thành viên đã được đánh dấu vắng mặt rồi', 422);
        }

        if ($participant->checked_in_at) {
            return ResponseHelper::error('Thành viên đã check-in. Không thể đánh dấu vắng mặt.', 422);
        }

        $participant->update([
            'is_absent' => true,
        ]);

        $participant->load('user');

        $this->syncMiniStaffAbsentFromParticipant($participant);

        return ResponseHelper::success(
            new MiniParticipantResource($participant),
            'Đã đánh dấu vắng mặt thành công'
        );
    }
}
