<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushJob;
use App\Helpers\ResponseHelper;
use App\Http\Requests\StoreMiniTournamentRequest;
use App\Http\Requests\UpdateMiniTournamentRequest;
use App\Http\Resources\ListMiniTournamentResource;
use App\Http\Resources\MiniTournamentResource;
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
            $paymentStatus = \App\Enums\PaymentStatusEnum::CONFIRMED;
            if ($miniTournament->has_fee && !$miniTournament->auto_split_fee) {
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
            $q->where('is_private', 0)
                ->orWhereHas('participants', fn($sub) => $sub->where('user_id', $userId));
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
        $miniTournament = MiniTournament::withFullRelations()->findOrFail($id);

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

        // Kiểm tra nếu chuyển từ miễn phí sang có phí
        $wasFree = !$miniTournament->has_fee;
        $isNowPaid = isset($data['has_fee']) && $data['has_fee'];

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

        // Nếu chuyển từ miễn phí sang có phí, tự động xử lý payment cho participants
        if ($wasFree && $isNowPaid) {
            $this->syncFreeParticipantsToPaid($miniTournament);
        }

        if ($request->hasFile('poster')) {
            $posterPath = $request->file('poster')->store('posters', 'public');
            $posterUrl = asset('storage/' . $posterPath);
            $miniTournament->update(['poster' => $posterUrl]);
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
     * Khi kèo chuyển từ miễn phí sang có phí:
     * - Organizers và guests được chủ kèo bảo lãnh → confirmed_payments (miễn phí)
     * - Member thường và guest bảo lãnh bởi người khác → pending_payments (phải đóng tiền)
     */
    private function syncFreeParticipantsToPaid(MiniTournament $miniTournament): void
    {
        // Lấy organizer IDs (chủ kèo)
        $organizerIds = $miniTournament->staff()->pluck('user_id')->toArray();

        // Lấy guests được bảo lãnh bởi chủ kèo
        $sponsoredGuestIds = [];
        if (!empty($organizerIds)) {
            $sponsoredGuestIds = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                ->where('is_guest', true)
                ->whereIn('guarantor_user_id', $organizerIds)
                ->pluck('user_id')
                ->toArray();
        }

        // Lấy tất cả participants đã confirmed
        $confirmedParticipants = $miniTournament->participants()
            ->where('is_confirmed', true)
            ->get();

        if ($confirmedParticipants->isEmpty()) {
            return;
        }

        // Tính fee theo logic auto_split
        $totalConfirmed = $confirmedParticipants->count();
        if ($miniTournament->auto_split_fee) {
            $feePerPerson = $miniTournament->final_fee_per_person !== null
                ? $miniTournament->final_fee_per_person
                : round($miniTournament->fee_amount / $totalConfirmed);
        } else {
            $feePerPerson = $miniTournament->fee_amount;
        }

        foreach ($confirmedParticipants as $participant) {
            $isOrganizer = in_array($participant->user_id, $organizerIds);
            $isSponsoredByOrganizer = in_array($participant->user_id, $sponsoredGuestIds);

            // Organizers và guests được chủ kèo bảo lãnh → CONFIRMED (miễn phí)
            if ($isOrganizer || $isSponsoredByOrganizer) {
                if ($participant->payment_status !== \App\Enums\PaymentStatusEnum::CONFIRMED) {
                    $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::CONFIRMED]);
                }

                $existingPayment = MiniParticipantPayment::where('mini_tournament_id', $miniTournament->id)
                    ->where('user_id', $participant->user_id)
                    ->first();

                if (!$existingPayment) {
                    MiniParticipantPayment::create([
                        'mini_tournament_id' => $miniTournament->id,
                        'participant_id' => $participant->id,
                        'user_id' => $participant->user_id,
                        'amount' => 0,
                        'status' => MiniParticipantPayment::STATUS_CONFIRMED,
                        'confirmed_at' => now(),
                        'confirmed_by' => $participant->guarantor_user_id ?? $participant->user_id,
                        'paid_at' => now(),
                        'note' => 'Miễn phí tham gia (chuyển từ free sang paid)',
                    ]);
                }
            }
            // Member thường và guest bảo lãnh bởi người khác → PENDING (phải đóng tiền)
            else {
                if ($participant->payment_status !== \App\Enums\PaymentStatusEnum::PENDING) {
                    $participant->update(['payment_status' => \App\Enums\PaymentStatusEnum::PENDING]);
                }

                $existingPayment = MiniParticipantPayment::where('mini_tournament_id', $miniTournament->id)
                    ->where('user_id', $participant->user_id)
                    ->first();

                if (!$existingPayment) {
                    MiniParticipantPayment::create([
                        'mini_tournament_id' => $miniTournament->id,
                        'participant_id' => $participant->id,
                        'user_id' => $participant->user_id,
                        'amount' => $feePerPerson,
                        'status' => MiniParticipantPayment::STATUS_PENDING,
                        'note' => 'Chờ thanh toán (kèo chuyển từ free sang paid)',
                    ]);
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
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }
}
