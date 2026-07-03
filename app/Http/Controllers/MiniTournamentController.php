<?php

namespace App\Http\Controllers;

use App\Enums\ClubFundCollectionStatus;
use App\Enums\ClubFundContributionStatus;
use App\Enums\ClubMemberRole;
use App\Exceptions\BusinessException;
use App\Enums\ClubWalletTransactionDirection;
use App\Enums\ClubWalletTransactionSourceType;
use App\Enums\ClubWalletTransactionStatus;
use App\Enums\PaymentMethod;
use App\Events\SuperAdmin\DashboardStatUpdated;
use App\Events\SuperAdmin\MiniTournamentCreated;
use App\Events\SuperAdmin\MiniTournamentDeleted;
use App\Events\SuperAdmin\MiniTournamentUpdated;
use App\Jobs\SendPushJob;
use App\Helpers\ResponseHelper;
use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use App\Http\Resources\ListMiniTournamentResource;
use App\Http\Resources\MiniParticipantPaymentResource;
use App\Http\Resources\MiniParticipantResource;
use App\Http\Resources\MiniTournamentResource;
use App\Models\Club\Club;
use App\Models\Club\ClubExpense;
use App\Models\Club\ClubFundCollection;
use App\Models\Club\ClubFundContribution;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniParticipantPayment;
use App\Models\MiniTeam;
use App\Models\MiniTeamMember;
use App\Models\CompetitionLocation;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\User;
use App\Support\MiniTeamNameBuilder;
use App\Notifications\MiniTournamentInvitationNotification;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\PaymentReminderNotification;
use App\Services\Club\ClubFundContributionService;
use App\Services\ImageOptimizationService;
use App\Services\MiniTournamentService;
use App\Services\UserSportMatchCounter;
use App\Services\RoundRobinSchedulerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MiniTournamentController extends Controller
{
    public function __construct(
        protected MiniTournamentService $tournamentService,
        protected ClubFundContributionService $fundContributionService,
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

        if (!empty($data['competition_location_id'])) {
            $location = CompetitionLocation::find($data['competition_location_id']);
            if ($location && $location->is_banned) {
                return ResponseHelper::error('Địa điểm tạm thời bị cấm truy cập', 422);
            }
        }

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

        // === use_club_fund = true: trừ quỹ CLB ===
        if ($miniTournament->use_club_fund && $miniTournament->club_id) {
            $club = Club::with('mainWallet')->find($miniTournament->club_id);
            if ($club) {
                $feeAmount = (float) ($miniTournament->fee_amount ?? 0);
                if ($feeAmount > 0) {
                    $currentBalance = (float) ($club->mainWallet?->balance ?? 0);
                    if ($currentBalance < $feeAmount) {
                        $miniTournament->forceDelete();
                        return ResponseHelper::error('Số dư quỹ CLB hiện tại (' . number_format($currentBalance) . 'đ) không đủ để chi trả phí kèo (' . number_format($feeAmount) . 'đ). Vui lòng nạp thêm quỹ.', 422);
                    }

                    DB::transaction(function () use ($miniTournament, $club, $feeAmount) {
                        $clubExpense = ClubExpense::create([
                            'club_id' => $club->id,
                            'mini_tournament_id' => $miniTournament->id,
                            'title' => $miniTournament->name,
                            'amount' => $feeAmount,
                            'spent_by' => Auth::id(),
                            'spent_at' => now(),
                            'note' => "Quỹ chi kèo CLB. Kèo ID: {$miniTournament->id}.",
                        ]);

                        $mainWallet = $club->mainWallet;
                        if (!$mainWallet) {
                            $mainWallet = \App\Models\Club\ClubWallet::create([
                                'club_id' => $club->id,
                                'currency' => 'VND',
                            ]);
                        }

                        $transaction = $mainWallet->transactions()->create([
                            'direction' => ClubWalletTransactionDirection::Out,
                            'amount' => $feeAmount,
                            'source_type' => ClubWalletTransactionSourceType::TournamentFee,
                            'source_id' => $clubExpense->id,
                            'payment_method' => \App\Enums\PaymentMethod::Other,
                            'status' => ClubWalletTransactionStatus::Confirmed,
                            'description' => "Quỹ chi kèo: {$miniTournament->name}",
                            'created_by' => Auth::id(),
                            'confirmed_by' => Auth::id(),
                            'confirmed_at' => now(),
                            'included_in_club_fund' => true,
                        ]);

                        $clubExpense->updateQuietly(['wallet_transaction_id' => $transaction->id]);
                    });
                }
            }
        }

        $imageService = app(ImageOptimizationService::class);

        // Handle poster file: resize + convert WebP + lưu ngay
        $posterFile = $request->file('poster');
        if ($posterFile) {
            $savedPath = $imageService->processAndSaveImage($posterFile, 'posters', 'poster_', 720, 65);
            $miniTournament->update(['poster' => asset('storage/' . $savedPath)]);
        }

        // Handle qr_code_url file: resize + convert WebP + lưu ngay
        $qrUrl = null;
        if ($request->boolean('use_cached_qr') && Auth::user()->latest_used_qr) {
            $qrUrl = Auth::user()->latest_used_qr;
        } elseif ($qrFile = $request->file('qr_code_url')) {
            $savedPath = $imageService->processAndSaveImage($qrFile, 'qr_codes', 'qr_', 500, 60);
            $qrUrl = asset('storage/' . $savedPath);
        } elseif ($request->has('qr_code_url') && is_string($request->input('qr_code_url'))) {
            $qrUrl = $request->input('qr_code_url');
        }

        if ($qrUrl) {
            $miniTournament->update(['qr_code_url' => $qrUrl]);
            Auth::user()->update(['latest_used_qr' => $qrUrl]);
        }

        // === Tạo ClubFundCollection khi included_in_club_fund = true ===
        if ($miniTournament->included_in_club_fund && $miniTournament->club_id) {
            $club = Club::find($miniTournament->club_id);
            if ($club) {
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
                    'qr_code_url' => $qrUrl,
                    'created_by' => Auth::id(),
                    'included_in_club_fund' => true,
                ]);

                $miniTournament->update(['club_fund_collection_id' => $collection->id]);

                // Gán organizer (creator) vào assignedMembers → Confirmed (exempt)
                $collection->assignedMembers()->attach(Auth::id(), ['amount_due' => 0]);

                ClubFundContribution::create([
                    'club_fund_collection_id' => $collection->id,
                    'user_id' => Auth::id(),
                    'amount' => (float) $miniTournament->fee_amount,
                    'receipt_url' => null,
                    'note' => 'Chủ kèo - bao phí',
                    'status' => ClubFundContributionStatus::Confirmed,
                ]);

                // Tạo wallet tx IN cho organizer exempt
                if ($club->mainWallet) {
                    $club->mainWallet->transactions()->create([
                        'direction' => ClubWalletTransactionDirection::In,
                        'amount' => (float) $miniTournament->fee_amount,
                        'source_type' => ClubWalletTransactionSourceType::FundCollection,
                        'source_id' => $collection->id,
                        'payment_method' => PaymentMethod::Other,
                        'status' => ClubWalletTransactionStatus::Confirmed,
                        'description' => 'Thu quỹ kèo: ' . $miniTournament->name,
                        'created_by' => Auth::id(),
                        'confirmed_by' => Auth::id(),
                        'confirmed_at' => now(),
                        'included_in_club_fund' => true,
                    ]);
                    $collection->updateCollectedAmount();
                }

            }
        }

        $miniTournament->loadFullRelations();

        MiniTournamentCreated::dispatch($miniTournament);

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

        if (!empty($data['competition_location_id'])) {
            $location = CompetitionLocation::find($data['competition_location_id']);
            if ($location && $location->is_banned) {
                return ResponseHelper::error('Địa điểm tạm thời bị cấm truy cập', 422);
            }
        }

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
            } catch (BusinessException $e) {
                return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
            } catch (\Exception $e) {
                return ResponseHelper::error('Có lỗi xảy ra khi cập nhật kèo đấu', 400);
            }
        }

        $originalFormat = $miniTournament->match_format;
        $miniTournament->update($data);

        // Reset participant groups and session state when match_format changes
        if (isset($data['match_format']) && $data['match_format'] !== $originalFormat) {
            $miniTournament->participants()->update(['player_group' => null]);
        }

        // Sync session fields when match_format changes
        if (isset($data['match_format'])) {
            $newFormat = $data['match_format'];
            $wasRoundRobin = in_array($originalFormat, [
                MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
                MiniTournament::MATCH_FORMAT_MIXED_GENDER,
                MiniTournament::MATCH_FORMAT_RANK_PAIRING,
            ], true);
            $isNewRoundRobin = in_array($newFormat, [
                MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
                MiniTournament::MATCH_FORMAT_MIXED_GENDER,
                MiniTournament::MATCH_FORMAT_RANK_PAIRING,
            ], true);

            if ($newFormat === MiniTournament::MATCH_FORMAT_STANDARD || $newFormat === null) {
                $miniTournament->update([
                    'session_status' => MiniTournament::SESSION_STATUS_ONGOING,
                    'is_session_started' => true,
                ]);
                if ($wasRoundRobin) {
                    $this->clearRoundRobinMatches($miniTournament);
                }
            } elseif ($newFormat === MiniTournament::MATCH_FORMAT_PARTNER_ROTATION) {
                // Always clear matches when switching TO partner_rotation
                if ($wasRoundRobin) {
                    $this->clearRoundRobinMatches($miniTournament);
                }
                // generatePartnerRotationMatches handles session field update internally
                $this->generatePartnerRotationMatches($miniTournament);
            } elseif (in_array($newFormat, [
                MiniTournament::MATCH_FORMAT_MIXED_GENDER,
                MiniTournament::MATCH_FORMAT_RANK_PAIRING,
            ], true)) {
                // Always clear matches when switching TO mixed_gender or rank_pairing
                if ($wasRoundRobin) {
                    $this->clearRoundRobinMatches($miniTournament);
                }
                $miniTournament->update([
                    'session_status' => MiniTournament::SESSION_STATUS_PENDING_GROUP,
                    'is_session_started' => false,
                ]);
            }
        }

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

        $imageService = app(ImageOptimizationService::class);

        if ($request->hasFile('poster')) {
            $oldPoster = $miniTournament->poster;
            $savedPath = $imageService->processAndSaveImage($request->file('poster'), 'posters', 'poster_', 720, 65);
            $imageService->deleteOldImage($oldPoster);
            $miniTournament->update(['poster' => asset('storage/' . $savedPath)]);
        } elseif ($request->filled('poster') && is_string($request->input('poster'))) {
            $posterStr = trim((string) $request->input('poster'));
            if ($posterStr !== '' && filter_var($posterStr, FILTER_VALIDATE_URL)) {
                $miniTournament->update(['poster' => $posterStr]);
            }
        }

        if ($request->hasFile('qr_code_url')) {
            $oldQr = $miniTournament->qr_code_url;
            $savedPath = $imageService->processAndSaveImage($request->file('qr_code_url'), 'qr_codes', 'qr_', 500, 60);
            $imageService->deleteOldImage($oldQr);
            $miniTournament->update(['qr_code_url' => asset('storage/' . $savedPath)]);
        } elseif ($request->boolean('use_cached_qr') && Auth::user()->latest_used_qr) {
            $miniTournament->update(['qr_code_url' => Auth::user()->latest_used_qr]);
            Auth::user()->update(['latest_used_qr' => Auth::user()->latest_used_qr]);
        }

        $miniTournament->loadFullRelations();

        MiniTournamentUpdated::dispatch($miniTournament);

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

        // Admin/organizer được huỷ kèo bất cứ lúc nào (không bị giới hạn bởi allow_cancellation).
        // allow_cancellation chỉ áp dụng cho member tự huỷ tham gia.

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

        $miniTournamentName = $miniTournament->name;
        $miniTournamentId = $miniTournament->id;

        DB::transaction(function () use ($miniTournament) {
            $miniTournament->delete();
        });

        MiniTournamentDeleted::dispatch($miniTournamentId, $miniTournamentName);
        DashboardStatUpdated::dispatch('mini_tournament_growth', 1, 'decremented');

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

    /**
     * Clear all matches for a tournament when switching formats.
     */
    private function clearRoundRobinMatches(MiniTournament $miniTournament): void
    {
        DB::transaction(function () use ($miniTournament) {
            MiniMatch::where('mini_tournament_id', $miniTournament->id)->delete();

            $teamIds = MiniTeam::where('mini_tournament_id', $miniTournament->id)
                ->pluck('id')
                ->toArray();
            if (!empty($teamIds)) {
                MiniTeamMember::whereIn('mini_team_id', $teamIds)->delete();
                MiniTeam::where('mini_tournament_id', $miniTournament->id)->delete();
            }
        });
    }

    /**
     * Generate partner_rotation matches when organizer switches format to partner_rotation.
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
                'session_status' => MiniTournament::SESSION_STATUS_PENDING_GROUP,
                'is_session_started' => false,
            ]);
            return;
        }

        $participantIds = $confirmedParticipants->pluck('id')->toArray();
        $count = count($participantIds);
        if ($count < 3 || $count > 8) {
            $miniTournament->update([
                'session_status' => MiniTournament::SESSION_STATUS_PENDING_GROUP,
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
                'session_status' => MiniTournament::SESSION_STATUS_PENDING_GROUP,
                'is_session_started' => false,
            ]);
            return;
        }

        $participantUserMap = [];
        foreach ($confirmedParticipants as $p) {
            $participantUserMap[$p->id] = $p->user_id;
        }

        $matchesToInsert = [];
        foreach ($schedule['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                $isBye = !empty($match['is_bye']);
                $row = [
                    'mini_tournament_id' => $miniTournament->id,
                    'round_number' => $round['round_number'],
                    'is_bye' => $isBye,
                    'status' => ($round['round_number'] === 1 && !$isBye)
                        ? MiniMatch::STATUS_GOING_ON
                        : MiniMatch::STATUS_PENDING,
                    'team1_id' => null,
                    'team2_id' => null,
                    'participant1_id' => null,
                    'participant2_id' => null,
                    'participant_win_id' => null,
                    'team_win_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($isDouble && !empty($match['team1_players'])) {
                    $team1Key = implode('-', $match['team1_players']);
                    $team1Id = $this->getOrCreateMiniTeam($team1Key, $match['team1_players'], $miniTournament->id, $participantUserMap);
                    $row['team1_id'] = $team1Id;

                    if (!empty($match['is_bye'])) {
                        $row['is_bye'] = true;
                        $row['status'] = MiniMatch::STATUS_COMPLETED;
                    } elseif (!empty($match['team2_players'])) {
                        $team2Key = implode('-', $match['team2_players']);
                        $team2Id = $this->getOrCreateMiniTeam($team2Key, $match['team2_players'], $miniTournament->id, $participantUserMap);
                        $row['team2_id'] = $team2Id;
                    }
                }

                $matchesToInsert[] = $row;
            }
        }

        DB::transaction(function () use ($miniTournament, $matchesToInsert) {
            MiniMatch::insert($matchesToInsert);
            $miniTournament->update([
                'session_status' => MiniTournament::SESSION_STATUS_ONGOING,
                'session_started_at' => now(),
                'is_session_started' => true,
            ]);
        });

        // Increment total_matches for bye matches (insert bypasses observer).
        $byeMatches = collect($matchesToInsert)->where('status', MiniMatch::STATUS_COMPLETED);
        if ($byeMatches->isNotEmpty()) {
            $sportId = $miniTournament->sport_id;
            $matchCounter = app(UserSportMatchCounter::class);
            $insertedMatches = MiniMatch::where('mini_tournament_id', $miniTournament->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->whereIn('round_number', $byeMatches->pluck('round_number')->unique()->values())
                ->whereIn('team1_id', $byeMatches->pluck('team1_id')->filter()->unique()->values())
                ->get();
            foreach ($insertedMatches as $byeMatch) {
                if ($byeMatch->team1_id) {
                    $matchCounter->incrementForMiniTeam($byeMatch->team1_id, $sportId);
                }
                if ($byeMatch->team2_id) {
                    $matchCounter->incrementForMiniTeam($byeMatch->team2_id, $sportId);
                }
            }
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
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Exception $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi hủy chuỗi kèo đấu', 403);
        }
    }

    /**
     * Format schedule rounds với thông tin user cho partner_rotation matches.
     *
     * @param array $rounds
     * @param \Illuminate\Support\Collection $participants
     * @return array
     */
    /**
     * Create a MiniTeam and its members for a given list of player IDs.
     * Used for Mix/A-B double format where teams are temporary per-round constructs.
     */
    private function createMiniTeam(array $playerIds, int $miniTournamentId, array $participantUserMap): int
    {
        $userIds = array_map(fn($pid) => $participantUserMap[$pid] ?? $pid, $playerIds);
        $team = MiniTeam::create([
            'name' => MiniTeamNameBuilder::buildFromUserIds($userIds, $miniTournamentId),
            'mini_tournament_id' => $miniTournamentId,
        ]);
        foreach ($playerIds as $pid) {
            MiniTeamMember::create([
                'mini_team_id' => $team->id,
                'user_id' => $participantUserMap[$pid] ?? $pid,
                'is_guest' => false,
            ]);
        }
        return $team->id;
    }

    /** @var array<string, int> */
    private array $miniTeamCache = [];

    /**
     * Get or create a MiniTeam by its player-key, with in-request caching.
     */
    private function getOrCreateMiniTeam(string $key, array $playerIds, int $miniTournamentId, array $participantUserMap): int
    {
        if (!isset($this->miniTeamCache[$key])) {
            $this->miniTeamCache[$key] = $this->createMiniTeam($playerIds, $miniTournamentId, $participantUserMap);
        }
        return $this->miniTeamCache[$key];
    }

    private function formatMatchesWithUserInfo(array $rounds, $participants, bool $isDouble = false, array $teams = []): array
    {
        // Map participant_id => user info
        $participantMap = [];
        foreach ($participants as $p) {
            $participantMap[$p->id] = [
                'id' => $p->id,
                'full_name' => $p->user?->full_name ?? $p->guest_name ?? 'Unknown',
                'avatar_url' => $p->user?->avatar_url ?? ($p->guest_avatar ?? ''),
            ];
        }

        if ($isDouble) {
            // Load team details with members
            $teamIds = array_filter(array_column($teams, 'id'));
            $loadedTeams = $teamIds
                ? MiniTeam::with('members.user')->whereIn('id', $teamIds)->get()->keyBy('id')
                : collect();

            $teamMap = [];
            foreach ($loadedTeams as $team) {
                $members = $team->members->map(function ($member) {
                    $user = $member->relationLoaded('user') ? $member->user : null;
                    return [
                        'id' => $member->user_id,
                        'full_name' => $user?->full_name ?? ($member->is_guest ? 'Guest' : 'Unknown'),
                        'avatar_url' => $user?->avatar_url ?? '',
                    ];
                })->values()->all();

                $teamMap[$team->id] = [
                    'id' => $team->id,
                    'name' => $team->name,
                    'members' => $members,
                ];
            }

            foreach ($rounds as &$round) {
                if (!isset($round['matches'])) continue;
                foreach ($round['matches'] as &$match) {
                    $roundNumber = $round['round_number'];

                    if (isset($match['team1_id'], $match['team2_id'])) {
                        // Teams already created (mixed_gender / rank_pairing double)
                        $match['team_1'] = $teamMap[$match['team1_id']] ?? null;
                        $match['team_2'] = $teamMap[$match['team2_id']] ?? null;
                    } elseif (!empty($match['is_bye']) && isset($match['team1_players'])) {
                        // BYE match: mixed_gender double has a mixed partnership (2 players) with no opponent
                        $playerIds = array_filter($match['team1_players']);
                        $memberNames = [];
                        $members = [];
                        foreach ($playerIds as $pid) {
                            $memberNames[] = $participantMap[$pid]['full_name'] ?? 'BYE';
                            $members[] = $participantMap[$pid] ?? null;
                        }
                        $name = implode(' & ', array_filter($memberNames)) ?: 'BYE';
                        $match['team_1'] = [
                            'id' => null,
                            'name' => $name,
                            'members' => array_filter($members),
                        ];
                        $match['team_2'] = null;
                    } elseif (isset($match['team1_players'], $match['team2_players'])) {
                        // Dynamic teams from player pairs (partner_rotation double)
                        $p1 = $match['team1_players'][0] ?? null;
                        $p2 = $match['team1_players'][1] ?? null;
                        $p3 = $match['team2_players'][0] ?? null;
                        $p4 = $match['team2_players'][1] ?? null;

                        $match['team_1'] = [
                            'id' => null,
                            'name' => ($participantMap[$p1]['full_name'] ?? '') . ' & ' . ($participantMap[$p2]['full_name'] ?? ''),
                            'members' => array_filter([
                                $participantMap[$p1] ?? null,
                                $participantMap[$p2] ?? null,
                            ]),
                        ];
                        $match['team_2'] = [
                            'id' => null,
                            'name' => ($participantMap[$p3]['full_name'] ?? '') . ' & ' . ($participantMap[$p4]['full_name'] ?? ''),
                            'members' => array_filter([
                                $participantMap[$p3] ?? null,
                                $participantMap[$p4] ?? null,
                            ]),
                        ];
                    } elseif (isset($match['participant1_id'], $match['participant2_id'])) {
                        // Fallback: two individual participants as a team (shouldn't happen in double)
                        $p1 = $match['participant1_id'];
                        $p2 = $match['participant2_id'];
                        $match['team_1'] = $participantMap[$p1] ?? null;
                        $match['team_2'] = $participantMap[$p2] ?? null;
                    }

                    $match['round_number'] = $roundNumber;
                    unset($match['team1_id'], $match['team2_id'], $match['participant1_id'], $match['participant2_id'], $match['team1_players'], $match['team2_players']);
                }
            }
        } else {
            // Single format
            foreach ($rounds as &$round) {
                if (!isset($round['matches'])) continue;
                foreach ($round['matches'] as &$match) {
                    $p1Id = $match['participant1_id'] ?? null;
                    $p2Id = $match['participant2_id'] ?? null;
                    $match['team_1'] = $participantMap[$p1Id] ?? null;
                    $match['team_2'] = $participantMap[$p2Id] ?? null;
                    $match['round_number'] = $round['round_number'];
                    unset($match['participant1_id'], $match['participant2_id']);
                }
            }
        }

        return $rounds;
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

        // === Kèo thuộc CLB: lấy club_id từ kèo đấu ===
        if ($miniTournament->club_id) {
            $club = Club::find($miniTournament->club_id);
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

        if (!$participant->is_confirmed) {
            return ResponseHelper::error('Người này chưa được xác nhận tham gia kèo đấu. Không thể check-in.', 422);
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
     * Organizer / Club staff đánh dấu check-in nhiều participants cùng lúc.
     * Body: { participant_ids: int[] }
     */
    public function markCheckInAll(Request $request, int $miniTournamentId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $validated = $request->validate([
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'integer',
        ]);

        $participantIds = $validated['participant_ids'];

        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        // === Kèo thuộc CLB ===
        if ($miniTournament->club_id) {
            $club = Club::find($miniTournament->club_id);
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

        $participants = $miniTournament->participants()
            ->whereIn('id', $participantIds)
            ->get();

        if ($participants->isEmpty()) {
            return ResponseHelper::error('Không tìm thấy thành viên nào trong danh sách', 404);
        }

        $updatedCount = 0;
        $skippedIds = [];

        foreach ($participants as $participant) {
            if (!$participant->is_confirmed) {
                $skippedIds[] = $participant->id;
                continue;
            }
            if ($participant->checked_in_at) {
                $skippedIds[] = $participant->id;
                continue;
            }

            $participant->update([
                'is_confirmed' => true,
                'checked_in_at' => now(),
                'is_absent' => false,
            ]);

            $this->syncMiniStaffAttendanceFromParticipant($participant);
            $updatedCount++;
        }

        return ResponseHelper::success([
            'updated_count' => $updatedCount,
            'skipped_count' => count($skippedIds),
            'skipped_ids' => $skippedIds,
        ], "Đã đánh dấu check-in cho {$updatedCount} thành viên");
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

        // === Kèo thuộc CLB: lấy club_id từ kèo đấu ===
        if ($miniTournament->club_id) {
            $club = Club::find($miniTournament->club_id);
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

        if (!$participant->is_confirmed) {
            return ResponseHelper::error('Người này chưa được xác nhận tham gia kèo đấu. Không thể đánh dấu vắng mặt.', 422);
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

    /**
     * Organizer / Club staff đánh dấu vắng mặt nhiều participants cùng lúc.
     * Body: { participant_ids: int[] }
     */
    public function markAbsentAll(Request $request, int $miniTournamentId)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $validated = $request->validate([
            'participant_ids' => 'required|array|min:1',
            'participant_ids.*' => 'integer',
        ]);

        $miniTournament = MiniTournament::with('staff')->findOrFail($miniTournamentId);

        if ($miniTournament->club_id) {
            $club = Club::find($miniTournament->club_id);
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
            $isOrganizer = $miniTournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === MiniTournamentStaff::ROLE_ORGANIZER
            );
            if (!$isOrganizer) {
                return ResponseHelper::error('Chỉ organizer kèo đấu mới có quyền đánh dấu vắng mặt', 403);
            }
        }

        $participants = $miniTournament->participants()
            ->whereIn('id', $validated['participant_ids'])
            ->get();

        if ($participants->isEmpty()) {
            return ResponseHelper::error('Không tìm thấy thành viên nào trong danh sách', 404);
        }

        $marked = [];
        $skipped = [];

        foreach ($participants as $participant) {
            if (!$participant->is_confirmed) {
                $skipped[] = ['participant_id' => $participant->id, 'reason' => 'not_confirmed'];
                continue;
            }
            if ($participant->is_absent) {
                $skipped[] = ['participant_id' => $participant->id, 'reason' => 'already_absent'];
                continue;
            }
            if ($participant->checked_in_at) {
                $skipped[] = ['participant_id' => $participant->id, 'reason' => 'already_checked_in'];
                continue;
            }

            $participant->update(['is_absent' => true]);
            $participant->load('user');
            $this->syncMiniStaffAbsentFromParticipant($participant);
            $marked[] = $participant;
        }

        return ResponseHelper::success([
            'marked_count' => count($marked),
            'skipped_count' => count($skipped),
            'skipped' => $skipped,
        ], 'Đã đánh dấu vắng mặt cho ' . count($marked) . ' thành viên');
    }

    /**
     * Bắt đầu session Round Robin: lưu phân nhóm (nếu có) + sinh lịch đấu tự động (organizer only).
     * mixed_gender / rank_pairing: nhận participant_ids trong payload để lưu nhóm trước khi sinh lịch.
     * partner_rotation: không cần payload, đọc confirmed participants từ DB.
     */
    public function startSession(Request $request, int $id)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $miniTournament = MiniTournament::findOrFail($id);

        if (!$miniTournament->hasOrganizer($userId)) {
            return ResponseHelper::error('Chỉ organizer kèo đấu mới có quyền thực hiện', 403);
        }

        $format = $miniTournament->match_format;
        if ($format === MiniTournament::MATCH_FORMAT_STANDARD) {
            return ResponseHelper::error('Kèo standard không cần start session', 422);
        }

        if (in_array($format, [MiniTournament::MATCH_FORMAT_PARTNER_ROTATION, MiniTournament::MATCH_FORMAT_MIXED_GENDER, MiniTournament::MATCH_FORMAT_RANK_PAIRING]) && $miniTournament->format !== 'double') {
            return ResponseHelper::error('Round Robin chỉ hỗ trợ kèo đánh đôi.', 422);
        }

        if ($miniTournament->session_status === MiniTournament::SESSION_STATUS_ONGOING) {
            return ResponseHelper::error('Session đã được bắt đầu', 422);
        }

        if ($miniTournament->session_status === MiniTournament::SESSION_STATUS_FINISHED) {
            return ResponseHelper::error('Session đã kết thúc, không thể bắt đầu lại', 422);
        }

        $groupAssignments = $request->input('participant_ids', []);

        // Lưu phân nhóm từ payload (mixed_gender / rank_pairing)
        if (!empty($groupAssignments)) {
            $validGroups = ['male', 'female', 'a', 'b'];
            foreach ($groupAssignments as $participantId => $group) {
                if (!in_array($group, $validGroups, true)) {
                    return ResponseHelper::error("Giá trị player_group '{$group}' không hợp lệ. Chỉ chấp nhận: " . implode(', ', $validGroups), 422);
                }
            }

            DB::transaction(function () use ($miniTournament, $groupAssignments) {
                foreach ($groupAssignments as $participantId => $group) {
                    MiniParticipant::where('id', $participantId)
                        ->where('mini_tournament_id', $miniTournament->id)
                        ->update(['player_group' => $group]);
                }
            });
        }

        // Validate phân nhóm đầy đủ cho mixed_gender / rank_pairing
        if (in_array($format, [MiniTournament::MATCH_FORMAT_MIXED_GENDER, MiniTournament::MATCH_FORMAT_RANK_PAIRING])) {
            $confirmed = $miniTournament->participants()->where('is_confirmed', true)->count();
            $grouped = $miniTournament->participants()
                ->where('is_confirmed', true)->whereNotNull('player_group')->count();
            if ($confirmed > 0 && $confirmed !== $grouped) {
                return ResponseHelper::error('Cần phân nhóm đầy đủ tất cả người chơi đã xác nhận', 422);
            }
        }

        $confirmedParticipants = $miniTournament->participants()
            ->with('user:id,full_name,avatar_url')
            ->where('is_confirmed', true)
            ->where('is_absent', false)
            ->get();

        if ($confirmedParticipants->isEmpty()) {
            return ResponseHelper::error('Chưa có người chơi đã xác nhận tham gia kèo đấu', 422);
        }

        $participantIds = $confirmedParticipants->pluck('id')->toArray();
        // Map participant ID -> actual user ID (for MiniTeamMember creation)
        $participantUserMap = [];
        foreach ($confirmedParticipants as $p) {
            $participantUserMap[$p->id] = $p->user_id;
        }
        $scheduler = new RoundRobinSchedulerService();
        $schedule = null;

        $isDouble = $miniTournament->format === 'double';
        $matchType = $isDouble
            ? RoundRobinSchedulerService::MATCH_TYPE_DOUBLE
            : RoundRobinSchedulerService::MATCH_TYPE_SINGLE;

        try {
            if ($format === MiniTournament::MATCH_FORMAT_PARTNER_ROTATION) {
                $count = count($participantIds);
                if ($count < 3 || $count > 8) {
                    return ResponseHelper::error('partner_rotation cần 3 đến 8 người đã xác nhận, đang có ' . $count . ' người', 422);
                }
                $schedule = $scheduler->generatePartnerRotationSchedule($participantIds, $matchType);
            } elseif ($format === MiniTournament::MATCH_FORMAT_MIXED_GENDER) {
                $maleIds = $confirmedParticipants->where('player_group', 'male')->pluck('id')->toArray();
                $femaleIds = $confirmedParticipants->where('player_group', 'female')->pluck('id')->toArray();
                if (count($maleIds) < 1 || count($femaleIds) < 1) {
                    return ResponseHelper::error('mixed_gender cần ít nhất 1 nam và 1 nữ đã phân nhóm', 422);
                }
                if ($isDouble && (count($maleIds) < 2 || count($femaleIds) < 2)) {
                    return ResponseHelper::error('double mixed_gender cần ít nhất 2 nam và 2 nữ đã phân nhóm', 422);
                }
                $schedule = $scheduler->generateMixedGenderSchedule($maleIds, $femaleIds, $matchType);
            } elseif ($format === MiniTournament::MATCH_FORMAT_RANK_PAIRING) {
                $aIds = $confirmedParticipants->where('player_group', 'a')->pluck('id')->toArray();
                $bIds = $confirmedParticipants->where('player_group', 'b')->pluck('id')->toArray();
                if (count($aIds) < 1 || count($bIds) < 1) {
                    return ResponseHelper::error('rank_pairing cần ít nhất 1 người nhóm A và 1 người nhóm B đã phân nhóm', 422);
                }
                if ($isDouble && (count($aIds) < 2 || count($bIds) < 2)) {
                    return ResponseHelper::error('double rank_pairing cần ít nhất 2 người mỗi nhóm', 422);
                }
                $schedule = $scheduler->generateRankPairingSchedule($aIds, $bIds, $matchType);
            }
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        $unbalancedNotice = $schedule['summary']['unbalanced_notice'] ?? null;
        $matchesToInsert = [];

        foreach ($schedule['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                $isBye = !empty($match['is_bye']);
                $row = [
                    'mini_tournament_id' => $miniTournament->id,
                    'round_number' => $round['round_number'],
                    'is_bye' => $isBye,
                    'status' => ($round['round_number'] === 1 && !$isBye)
                        ? MiniMatch::STATUS_GOING_ON
                        : MiniMatch::STATUS_PENDING,
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

        $miniTournamentId = $miniTournament->id;
        DB::transaction(function () use ($miniTournamentId, $matchesToInsert, $schedule, $isDouble, $participantUserMap) {
            // partner_rotation double: create MiniTeam records per round's match
            if ($isDouble) {
                $miniTeamByKey = [];
                $matchOffset = 0;
                foreach ($schedule['rounds'] as $round) {
                    foreach ($round['matches'] as $match) {
                        $isBye = !empty($match['is_bye']);

                        if ($isBye) {
                            // BYE: record the match so round structure is complete,
                            // but do NOT mark as completed or assign a winner
                            $players1 = array_filter(
                                !empty($match['team1_players']) ? $match['team1_players']
                                    : (!empty($match['player_a']) && $match['player_a'] !== null ? [$match['player_a']] : [])
                            );
                            $players2 = array_filter(
                                !empty($match['team2_players']) ? $match['team2_players']
                                    : (!empty($match['player_b']) && $match['player_b'] !== null ? [$match['player_b']] : [])
                            );

                            if (!empty($players1)) {
                                $key1 = implode('-', $players1);
                                if (!isset($miniTeamByKey[$key1])) {
                                    $miniTeamByKey[$key1] = $this->createMiniTeam($players1, $miniTournamentId, $participantUserMap);
                                }
                                $matchesToInsert[$matchOffset]['team1_id'] = $miniTeamByKey[$key1];
                            } elseif (!empty($players2)) {
                                $key2 = implode('-', $players2);
                                if (!isset($miniTeamByKey[$key2])) {
                                    $miniTeamByKey[$key2] = $this->createMiniTeam($players2, $miniTournamentId, $participantUserMap);
                                }
                                $matchesToInsert[$matchOffset]['team2_id'] = $miniTeamByKey[$key2];
                            }
                            $matchesToInsert[$matchOffset]['is_bye'] = true;
                            $matchesToInsert[$matchOffset]['status'] = MiniMatch::STATUS_COMPLETED;
                            $matchOffset++;
                            continue;
                        }

                        if (empty($match['team1_players']) || empty($match['team2_players'])) {
                            // Fallback BYE: one side has players, the other is empty.
                            $players1 = array_filter(
                                !empty($match['team1_players']) ? $match['team1_players']
                                    : (!empty($match['player_a']) && $match['player_a'] !== null ? [$match['player_a']] : [])
                            );
                            $players2 = array_filter(
                                !empty($match['team2_players']) ? $match['team2_players']
                                    : (!empty($match['player_b']) && $match['player_b'] !== null ? [$match['player_b']] : [])
                            );
                            if (!empty($players1)) {
                                $key1 = implode('-', $players1);
                                if (!isset($miniTeamByKey[$key1])) {
                                    $miniTeamByKey[$key1] = $this->createMiniTeam($players1, $miniTournamentId, $participantUserMap);
                                }
                                $matchesToInsert[$matchOffset]['team1_id'] = $miniTeamByKey[$key1];
                            } elseif (!empty($players2)) {
                                $key2 = implode('-', $players2);
                                if (!isset($miniTeamByKey[$key2])) {
                                    $miniTeamByKey[$key2] = $this->createMiniTeam($players2, $miniTournamentId, $participantUserMap);
                                }
                                $matchesToInsert[$matchOffset]['team2_id'] = $miniTeamByKey[$key2];
                            }
                            $matchesToInsert[$matchOffset]['is_bye'] = true;
                            $matchesToInsert[$matchOffset]['status'] = MiniMatch::STATUS_COMPLETED;
                            $matchOffset++;
                            continue;
                        }

                        // Build team name from user names
                        $team1UserIds = array_map(fn($pid) => $participantUserMap[$pid] ?? $pid, $match['team1_players']);
                        $team2UserIds = array_map(fn($pid) => $participantUserMap[$pid] ?? $pid, $match['team2_players']);

                        // Team 1: members in team1_players
                        $key1 = implode('-', $match['team1_players']);
                        if (!isset($miniTeamByKey[$key1])) {
                            $team1 = MiniTeam::create([
                                'name' => MiniTeamNameBuilder::buildFromUserIds($team1UserIds, $miniTournamentId),
                                'mini_tournament_id' => $miniTournamentId,
                            ]);
                            foreach ($match['team1_players'] as $pid) {
                                MiniTeamMember::create([
                                    'mini_team_id' => $team1->id,
                                    'user_id' => $participantUserMap[$pid] ?? $pid,
                                    'is_guest' => false,
                                ]);
                            }
                            $miniTeamByKey[$key1] = $team1->id;
                        }

                        // Team 2: members in team2_players
                        $key2 = implode('-', $match['team2_players']);
                        if (!isset($miniTeamByKey[$key2])) {
                            $team2 = MiniTeam::create([
                                'name' => MiniTeamNameBuilder::buildFromUserIds($team2UserIds, $miniTournamentId),
                                'mini_tournament_id' => $miniTournamentId,
                            ]);
                            foreach ($match['team2_players'] as $pid) {
                                MiniTeamMember::create([
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

            // Round 1 is going_on; all other rounds start as pending.
            // When round N completes, checkSessionCompletion auto-activates round N+1.
            $firstRound = (int) ($schedule['first_round_number'] ?? 1);
            foreach ($matchesToInsert as $idx => &$m) {
                $m['round_number'] = (int) $m['round_number'];
                if ($m['is_bye'] ?? false) {
                    $m['status'] = MiniMatch::STATUS_COMPLETED;
                } else {
                    $m['status'] = $m['round_number'] === $firstRound
                        ? MiniMatch::STATUS_GOING_ON
                        : MiniMatch::STATUS_PENDING;
                }
            }
            unset($m);

            MiniMatch::insert($matchesToInsert);
            MiniTournament::where('id', $miniTournamentId)->update([
                'session_status' => MiniTournament::SESSION_STATUS_ONGOING,
                'session_started_at' => now(),
                'is_session_started' => true,
            ]);
        });

        // Increment total_matches for bye matches (insert bypasses observer).
        $byeMatches = collect($matchesToInsert)->where('status', MiniMatch::STATUS_COMPLETED)->where('is_bye', true);
        if ($byeMatches->isNotEmpty()) {
            $sportId = $miniTournament->sport_id;
            $matchCounter = app(UserSportMatchCounter::class);
            $insertedByeMatches = MiniMatch::with('team1.members')
                ->where('mini_tournament_id', $miniTournament->id)
                ->where('status', MiniMatch::STATUS_COMPLETED)
                ->where('is_bye', true)
                ->whereIn('team1_id', $byeMatches->pluck('team1_id')->filter()->unique()->values())
                ->get();
            foreach ($insertedByeMatches as $byeMatch) {
                if ($byeMatch->team1_id) {
                    $matchCounter->incrementForMiniTeam($byeMatch->team1_id, $sportId);
                }
            }
        }

        $responseRounds = $this->formatMatchesWithUserInfo($schedule['rounds'], $confirmedParticipants, $isDouble, $schedule['teams'] ?? []);

        return ResponseHelper::success([
            'session_status' => MiniTournament::SESSION_STATUS_ONGOING,
            'is_session_started' => true,
            'summary' => $schedule['summary'],
            'unbalanced_notice' => $unbalancedNotice,
            'rounds' => $responseRounds,
        ], 'Đã bắt đầu session và sinh lịch đấu thành công', 200);
    }

    /**
     * Lấy lịch đấu đã sinh, nhóm theo vòng.
     */
    public function getSchedule(int $id)
    {
        $miniTournament = MiniTournament::with([
            'matches' => function ($q) {
                $q->orderByRaw('COALESCE(round_number, 0)')
                    ->orderBy('id');
            },
            'matches.participant1.user.sports.scores',
            'matches.participant2.user.sports.scores',
            'matches.participant2.user.sports.scores',
            'matches.results.team.members',
            'matches.team1.members.user.sports.scores',
            'matches.team2.members.user.sports.scores',
        ])->findOrFail($id);

        $matches = $miniTournament->matches;

        if ($matches->isEmpty()) {
            return ResponseHelper::success([
                'rounds' => [],
                'total_matches' => 0,
                'confirmed_matches' => 0,
                'current_round' => null,
            ], 'Chưa có lịch đấu');
        }

        $isDouble = $miniTournament->format === 'double';
        $firstMatch = $matches->first();
        $hasTeamMatches = $firstMatch && $firstMatch->team1_id !== null;

        $confirmedMatches = $matches->where('status', '!=', MiniMatch::STATUS_PENDING)->count();
        $totalMatches = $matches->count();
        $currentRound = $matches->where('status', MiniMatch::STATUS_GOING_ON)->max('round_number')
            ?? $matches->whereIn('status', [MiniMatch::STATUS_WAITING_CONFIRM])->min('round_number')
            ?? null;

        // Build team lookup map with full member info (nested eager load via string path doesn't work reliably)
        $teamMap = [];
        if ($hasTeamMatches) {
            $teamIds = $matches->pluck('team1_id')->merge($matches->pluck('team2_id'))->filter()->unique()->values();
            if ($teamIds->isNotEmpty()) {
                $loadedTeams = MiniTeam::with('members.user.sports.scores')
                    ->whereIn('id', $teamIds)
                    ->get()
                    ->keyBy('id');
                foreach ($loadedTeams as $team) {
                    $members = [];
                    foreach ($team->members as $m) {
                        $user = $m->relationLoaded('user') ? $m->user : null;
                        $sportsArray = [];
                        if ($user && $user->relationLoaded('sports')) {
                            foreach ($user->sports as $sport) {
                                $scores = $sport->relationLoaded('scores') ? $sport->scores : collect();
                                $formattedScores = [];
                                foreach (['personal_score', 'dupr_score', 'vndupr_score'] as $type) {
                                    $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
                                    $formattedScores[$type] = number_format((float) ($latestScore?->score_value ?? 0), 3);
                                }
                                $sportsArray[] = [
                                    'sport_id' => $sport->sport_id,
                                    'scores' => $formattedScores,
                                ];
                            }
                        }
                        $members[] = [
                            'id' => $m->user_id,
                            'full_name' => $user?->full_name ?? ($m->is_guest ? 'Guest' : ''),
                            'avatar_url' => $user?->avatar_url ?? '',
                            'is_guest' => (bool) $m->is_guest,
                            'visibility' => $user?->visibility,
                            'user' => $user ? [
                                'id' => $user->id,
                                'full_name' => $user->full_name,
                                'avatar_url' => $user->avatar_url,
                                'visibility' => $user->visibility,
                                'sports' => $sportsArray,
                            ] : null,
                        ];
                    }
                    $teamMap[$team->id] = [
                        'id' => $team->id,
                        'name' => $team->name,
                        'members' => $members,
                    ];
                }
            }
        }

        $hasExtraMatch = false;
        if (!$isDouble) {
            $matchCounts = $matches->pluck('participant1_id')
                ->merge($matches->pluck('participant2_id'))
                ->filter()
                ->countBy();
            $maxCount = $matchCounts->max();
            $hasExtraMatch = $maxCount > 0 && $matchCounts->filter(fn($count) => $count === $maxCount)->count() < $matchCounts->count();
        }

        $grouped = $matches->groupBy('round_number')->map(function ($roundMatches, $roundNumber) use ($isDouble, $hasTeamMatches, $teamMap, $miniTournament, $hasExtraMatch) {
            $completedCount = $roundMatches->where('status', MiniMatch::STATUS_COMPLETED)->count();
            $totalCount = $roundMatches->count();

            $status = 'upcoming';
            $isRR = in_array($miniTournament->match_format, [
                MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
                MiniTournament::MATCH_FORMAT_MIXED_GENDER,
                MiniTournament::MATCH_FORMAT_RANK_PAIRING,
            ]);

            if ($isRR) {
                $activeCount = $roundMatches->whereIn('status', [
                    MiniMatch::STATUS_GOING_ON,
                    MiniMatch::STATUS_WAITING_CONFIRM,
                ])->count();
                $doneCount = $roundMatches->where('status', MiniMatch::STATUS_COMPLETED)->count();

                if ($doneCount === $totalCount) {
                    $status = 'done';
                } elseif ($activeCount > 0 || $doneCount > 0) {
                    $status = 'active';
                }
            } else {
                $standardActiveCount = $roundMatches->whereIn('status', [
                    MiniMatch::STATUS_WAITING_CONFIRM,
                    MiniMatch::STATUS_COMPLETED,
                ])->count();

                if ($standardActiveCount === $totalCount) {
                    $status = 'done';
                } elseif ($standardActiveCount > 0) {
                    $status = 'active';
                }
            }

            return [
                'round_number' => (int) $roundNumber,
                'status' => $status,
                'matches' => $roundMatches->map(function ($match) use ($isDouble, $hasTeamMatches, $teamMap, $hasExtraMatch) {
                    // Format member từ participant
                    $formatMember = function ($participant) use ($hasExtraMatch) {
                        if (!$participant) return null;
                        $user = $participant->relationLoaded('user') ? $participant->user : null;
                        $isGuest = (bool) ($participant->is_guest);

                        $sportsArray = [];
                        if ($user && $user->relationLoaded('sports')) {
                            foreach ($user->sports as $sport) {
                                $scores = $sport->relationLoaded('scores') ? $sport->scores : collect();
                                $types = ['personal_score', 'dupr_score', 'vndupr_score'];
                                $formattedScores = [];
                                foreach ($types as $type) {
                                    $latestScore = $scores->where('score_type', $type)->sortByDesc('created_at')->first();
                                    $scoreValue = $latestScore ? $latestScore->score_value : 0;
                                    $formattedScores[$type] = number_format((float) $scoreValue, 3);
                                }
                                if ($isGuest) {
                                    $formattedScores['vndupr_score'] = number_format((float) ($participant->estimated_level ?? 0), 3);
                                }
                                $sportsArray[] = [
                                    'sport_id' => $sport->sport_id,
                                    'scores' => $formattedScores,
                                ];
                            }
                        }

                        $userData = $user !== null ? [
                            'id' => $user->id,
                            'full_name' => $user->full_name,
                            'avatar_url' => $user->avatar_url,
                            'visibility' => $user->visibility,
                            'sports' => $sportsArray,
                        ] : null;

                        return [
                            'id' => $participant->id,
                            'full_name' => $isGuest
                                ? ($participant->guest_name ?? $user?->full_name ?? '')
                                : ($user?->full_name ?? ''),
                            'avatar_url' => $isGuest
                                ? ($participant->guest_avatar ?? $user?->avatar_url ?? '')
                                : ($user?->avatar_url ?? ''),
                            'is_guest' => $isGuest,
                            'visibility' => $user?->visibility,
                            'user' => $userData,
                            'has_extra_match' => $hasExtraMatch,
                        ];
                    };

                    // Format results_by_sets
                    $groupedResults = [];
                    if ($match->relationLoaded('results') && $match->results->isNotEmpty()) {
                        $groupedResults = $match->results
                            ->groupBy('set_number')
                            ->mapWithKeys(fn ($set, $setNumber) => [
                                "set{$setNumber}" => $set->map(fn ($r) => [
                                    'id' => $r->id,
                                    'mini_match_id' => $r->mini_match_id,
                                    'team' => [
                                        'id' => $r->team?->id,
                                        'name' => $r->team?->name,
                                        'members' => $r->team?->relationLoaded('members')
                                            ? $r->team->members->map(fn ($m) => [
                                                'id' => $m->user_id,
                                                'team_id' => $r->team->id,
                                                'full_name' => $m->user?->full_name ?? '',
                                                'avatar_url' => $m->user?->avatar_url ?? '',
                                                'is_guest' => false,
                                                'visibility' => $m->user?->visibility,
                                            ])->values() : [],
                                    ],
                                    'score' => $r->score,
                                    'won_set' => $r->won_set,
                                ])->values(),
                            ])->toArray();
                    }

                    $team1Data = null;
                    $team2Data = null;

                    if ($isDouble || $hasTeamMatches) {
                        $team1Data = $teamMap[$match->team1_id] ?? null;
                        $team2Data = $teamMap[$match->team2_id] ?? null;
                    } else {
                        // Single format: format from participant1/participant2
                        $team1Data = $formatMember($match->participant1);
                        $team2Data = $formatMember($match->participant2);
                    }

                    return [
                        'id' => $match->id,
                        'mini_tournament_id' => $match->mini_tournament_id,
                        'name' => $match->name,
                        'round_number' => $match->round_number,
                        'team1' => $team1Data,
                        'team2' => $team2Data,
                        'score_1' => $match->team_1_score,
                        'score_2' => $match->team_2_score,
                        'status' => $match->status,
                        'team_win_id' => $match->team_win_id,
                        'participant_win_id' => $match->participant_win_id,
                        'results_by_sets' => $groupedResults,
                        'scheduled_at' => $match->scheduled_at,
                        'yard_number' => $match->yard_number,
                        'is_bye' => $match->is_bye,
                    ];
                })->values(),
                'completed_count' => $completedCount,
                'total_count' => $totalCount,
            ];
        })->sortBy('round_number')->values();

        return ResponseHelper::success([
            'rounds' => $grouped,
            'total_matches' => $totalMatches,
            'confirmed_matches' => $confirmedMatches,
            'current_round' => $currentRound,
        ], 'Lấy lịch đấu thành công');
    }

    /**
     * Lấy bảng xếp hạng realtime.
     */
    public function getLeaderboard(int $id)
    {
        $miniTournament = MiniTournament::findOrFail($id);
        $scheduler = new RoundRobinSchedulerService();
        $result = $scheduler->calculateLeaderboard($id);

        return ResponseHelper::success($result, 'Lấy bảng xếp hạng thành công');
    }

    /**
     * Đánh dấu người chơi vắng mặt trong trận đấu cụ thể (forfeit).
     */
    public function markAbsentPlayer(Request $request, int $id)
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $miniTournament = MiniTournament::findOrFail($id);

        if (!$miniTournament->hasOrganizer($userId)) {
            return ResponseHelper::error('Chỉ organizer kèo đấu mới có quyền thực hiện', 403);
        }

        $participantId = $request->input('participant_id');
        $matchId = $request->input('match_id');

        if (!$participantId || !$matchId) {
            return ResponseHelper::error('Thiếu participant_id hoặc match_id', 422);
        }

        $match = MiniMatch::where('id', $matchId)
            ->where('mini_tournament_id', $id)
            ->first();

        if (!$match) {
            return ResponseHelper::error('Trận đấu không tồn tại', 404);
        }

        $participant = MiniParticipant::where('id', $participantId)
            ->where('mini_tournament_id', $id)
            ->first();

        if (!$participant) {
            return ResponseHelper::error('Người chơi không tồn tại trong kèo đấu này', 404);
        }

        // Determine which team score to set to forfeit (0)
        $sportId = $miniTournament->sport_id;
        if ((int) $match->participant1_id === (int) $participantId) {
            $match->update(['team_1_score' => 0, 'status' => MiniMatch::STATUS_COMPLETED]);
            $matchCounter = app(UserSportMatchCounter::class);
            if ($match->team1_id) {
                $matchCounter->incrementForMiniTeam($match->team1_id, $sportId);
            }
            if ($match->team2_id) {
                $matchCounter->incrementForMiniTeam($match->team2_id, $sportId);
            }
        } elseif ((int) $match->participant2_id === (int) $participantId) {
            $match->update(['team_2_score' => 0, 'status' => MiniMatch::STATUS_COMPLETED]);
            $matchCounter = app(UserSportMatchCounter::class);
            if ($match->team1_id) {
                $matchCounter->incrementForMiniTeam($match->team1_id, $sportId);
            }
            if ($match->team2_id) {
                $matchCounter->incrementForMiniTeam($match->team2_id, $sportId);
            }
        } else {
            return ResponseHelper::error('Người chơi không tham gia trận đấu này', 422);
        }

        $match->load(['participant1.user', 'participant2.user']);

        return ResponseHelper::success([
            'match_id' => $match->id,
            'participant_id' => $participantId,
        ], 'Đã đánh dấu vắng mặt trong trận đấu');
    }
}
