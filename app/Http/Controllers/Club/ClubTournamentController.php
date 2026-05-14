<?php

namespace App\Http\Controllers\Club;

use App\Enums\ClubMemberRole;
use App\Enums\PaymentStatusEnum;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\TournamentResource;
use App\Models\Club\Club;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentParticipantPayment;
use App\Services\ImageOptimizationService;
use App\Services\TournamentFundService;
use App\Services\TournamentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ClubTournamentController extends Controller
{
    public function __construct(
        protected TournamentService $tournamentService,
        protected TournamentFundService $fundService,
    ) {
    }

    /**
     * POST /api/clubs/{clubId}/tournaments
     * Tạo giải đấu cho CLB
     */
    public function store(StoreTournamentRequest $request, int $clubId)
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
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền tạo giải cho CLB', 403);
        }

        $validated = $request->validated();
        $validated['club_id'] = $club->id;

        $tournament = null;

        DB::transaction(function () use ($validated, $request, $userId, &$tournament) {
            $imageService = app(ImageOptimizationService::class);

            // Poster: resize + convert WebP + lưu ngay
            if ($request->hasFile('poster')) {
                $savedPath = $imageService->processAndSaveImage(
                    $request->file('poster'),
                    'tournaments/posters',
                    'poster_',
                    720,
                    80
                );
                $validated['poster'] = $savedPath;
            }

            // QR code: resize + convert WebP + lưu ngay
            $qrUrl = null;
            if ($request->hasFile('qr_code_url')) {
                $qrFile = $request->file('qr_code_url');
                if ($qrFile && $qrFile->isValid()) {
                    $qrUrl = $imageService->processAndSaveImage($qrFile, 'tournaments/qr', 'qr_', 500, 75);
                }
            } elseif ($request->filled('qr_code_url') && is_string($request->input('qr_code_url'))) {
                $qrStr = trim((string) $request->input('qr_code_url'));
                if ($qrStr !== '' && filter_var($qrStr, FILTER_VALIDATE_URL)) {
                    $qrUrl = $qrStr;
                }
            }
            if ($qrUrl) {
                $validated['qr_code_url'] = $qrUrl;
            }

            $tournament = Tournament::create([
                ...$validated,
                'created_by' => $userId,
            ]);

            TournamentStaff::create([
                'tournament_id' => $tournament->id,
                'user_id' => $userId,
                'role' => TournamentStaff::ROLE_ORGANIZER,
            ]);

            $this->tournamentService->calculateEndDate($tournament);

            if (!empty($validated['creator_join'])) {
                $participantData = [
                    'tournament_id' => $tournament->id,
                    'user_id' => $userId,
                    'is_confirmed' => true,
                ];

                if (!empty($validated['has_fee'])) {
                    $participantData['payment_status'] = PaymentStatusEnum::CONFIRMED;
                }

                Participant::create($participantData);
            }

            if (!empty($validated['has_financial_management']) && !empty($validated['has_fee'])) {
                $this->fundService->createTournamentFundCollection($tournament, $validated);
            }
        });

        if (!$tournament) {
            return ResponseHelper::error('Tạo giải đấu thất bại', 500);
        }

        $tournament = Tournament::withFullRelations()->find($tournament->id);
        Cache::increment('club_content_version:' . $club->id);

        return ResponseHelper::success(new TournamentResource($tournament), 'Tạo giải đấu cho CLB thành công', 201);
    }

    /**
     * PUT/PATCH /api/clubs/{clubId}/tournaments/{tournamentId}
     * Cập nhật giải đấu của CLB
     */
    public function update(UpdateTournamentRequest $request, int $clubId, int $tournamentId)
    {
        $club = Club::findOrFail($clubId);
        $tournament = Tournament::findOrFail($tournamentId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ((int) $tournament->club_id !== $club->id) {
            return ResponseHelper::error('Giải đấu không thuộc CLB này', 404);
        }

        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền cập nhật giải của CLB', 403);
        }

        $validated = $request->validated();

        DB::transaction(function () use ($validated, $tournament, $request) {
            $imageService = app(ImageOptimizationService::class);

            // Poster: resize + convert WebP + lưu ngay
            $newPosterPath = null;
            if ($request->hasFile('poster')) {
                $newPosterPath = $imageService->processAndSaveImage(
                    $request->file('poster'),
                    'tournaments/posters',
                    'poster_',
                    720,
                    80
                );
                $imageService->deleteOldImage($tournament->poster);
                $validated['poster'] = $newPosterPath;
            } elseif ($request->has('remove_poster') && $request->input('remove_poster')) {
                $imageService->deleteOldImage($tournament->poster);
                $validated['poster'] = null;
            } else {
                unset($validated['poster']);
            }

            // QR code: resize + convert WebP + lưu ngay
            if ($request->hasFile('qr_code_url')) {
                $qrUrl = $imageService->processAndSaveImage(
                    $request->file('qr_code_url'),
                    'tournaments/qr',
                    'qr_',
                    800,
                    75
                );
                $imageService->deleteOldImage($tournament->qr_code_url);
                $validated['qr_code_url'] = $qrUrl;
            } elseif ($request->filled('qr_code_url') && is_string($request->input('qr_code_url'))) {
                $qrStr = trim((string) $request->input('qr_code_url'));
                if ($qrStr !== '' && filter_var($qrStr, FILTER_VALIDATE_URL)) {
                    $validated['qr_code_url'] = $qrStr;
                }
            } else {
                unset($validated['qr_code_url']);
            }

            unset($validated['remove_poster']);

            $oldStatus = $tournament->status;
            $tournament->fill($validated);
            $tournament->save();

            // Sync payment status khi has_fee thay đổi (free→paid hoặc paid→free)
            $wasPaid = (bool) ($tournament->getOriginal('has_fee') ?? false);
            $isNowPaid = isset($validated['has_fee']) ? (bool) $validated['has_fee'] : $wasPaid;
            if ($wasPaid !== $isNowPaid) {
                $this->syncParticipantsPaymentStatus($tournament, $isNowPaid);
            }

            $this->tournamentService->calculateEndDate($tournament);

            if ($tournament->status === Tournament::CLOSED && $oldStatus !== Tournament::CLOSED) {
                $this->tournamentService->updateParticipantsRatingStats($tournament);
            }
        });

        $tournament = Tournament::withFullRelations()->find($tournament->id);
        Cache::increment('club_content_version:' . $club->id);

        return ResponseHelper::success(new TournamentResource($tournament), 'Cập nhật giải đấu cho CLB thành công');
    }

    /**
     * GET /api/clubs/{clubId}/tournaments
     * Lấy danh sách giải đấu của CLB
     */
    public function index(Request $request, int $clubId)
    {
        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('CLB không tồn tại', 404);
        }

        $query = Tournament::withFullRelations()
            ->where('club_id', $club->id);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('keyword')) {
            $query->search($request->keyword);
        }

        $perPage = (int) $request->input('per_page', 15);
        $tournaments = $query->paginate($perPage);

        return ResponseHelper::success(
            [
                'tournaments' => TournamentResource::collection($tournaments),
            ],
            'Lấy danh sách giải đấu của CLB thành công',
            200,
            [
                'current_page' => $tournaments->currentPage(),
                'last_page' => $tournaments->lastPage(),
                'per_page' => $tournaments->perPage(),
                'total' => $tournaments->total(),
            ]
        );
    }

    /**
     * GET /api/clubs/{clubId}/tournaments/{tournamentId}
     * Lấy chi tiết giải đấu của CLB
     */
    public function show(int $clubId, int $tournamentId)
    {
        $club = Club::find($clubId);
        if (!$club) {
            return ResponseHelper::error('CLB không tồn tại', 404);
        }

        $tournament = Tournament::withFullRelations()->find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ((int) $tournament->club_id !== $club->id) {
            return ResponseHelper::error('Giải đấu không thuộc CLB này', 404);
        }

        return ResponseHelper::success(new TournamentResource($tournament), 'Lấy chi tiết giải đấu thành công');
    }

    /**
     * DELETE /api/clubs/{clubId}/tournaments/{tournamentId}
     * Hủy giải đấu của CLB (đổi status thành cancelled, không xóa bản ghi)
     */
    public function destroy(Request $request, int $clubId, int $tournamentId)
    {
        $club = Club::findOrFail($clubId);
        $tournament = Tournament::findOrFail($tournamentId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ((int) $tournament->club_id !== $club->id) {
            return ResponseHelper::error('Giải đấu không thuộc CLB này', 404);
        }

        $member = $club->activeMembers()->where('user_id', $userId)->first();
        if (!$member || !in_array($member->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true)) {
            return ResponseHelper::error('Chỉ admin/manager/secretary mới có quyền hủy giải của CLB', 403);
        }

        if ($tournament->status === Tournament::CANCELLED) {
            return ResponseHelper::error('Giải đấu đã bị hủy trước đó rồi', 422);
        }

        $hasCompletedMatch = \App\Models\Matches::whereHas('tournamentType', fn($q) => $q->where('tournament_id', $tournament->id))
            ->where('status', \App\Models\Matches::STATUS_COMPLETED)
            ->exists();

        if ($hasCompletedMatch) {
            return ResponseHelper::error('Không thể hủy giải. Đã có trận đấu hoàn thành thuộc giải này.', 400);
        }

        $tournament->update([
            'status' => Tournament::CANCELLED,
            'cancelled_reason' => $request->input('cancellation_reason', 'Hủy giải đấu'),
        ]);

        Cache::increment('club_content_version:' . $club->id);

        return ResponseHelper::success(null, 'Hủy giải đấu thành công');
    }

    /**
     * POST /api/clubs/{clubId}/tournaments/{tournamentId}/participants/{participantId}/check-in
     * Admin CLB đánh dấu participant đã check-in
     */
    public function markCheckIn(int $clubId, int $tournamentId, int $participantId)
    {
        $club = Club::findOrFail($clubId);
        $tournament = Tournament::with('staff')->findOrFail($tournamentId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ((int) $tournament->club_id !== $club->id) {
            return ResponseHelper::error('Giải đấu không thuộc CLB này', 404);
        }

        $clubMember = $club->activeMembers()->where('user_id', $userId)->first();
        $isClubStaff = $clubMember && in_array($clubMember->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true);
        $isTournamentOrganizer = $tournament->staff->contains(
            fn($staff) => (int) $staff->pivot->user_id === $userId && (int) $staff->pivot->role === TournamentStaff::ROLE_ORGANIZER
        );

        if (!$isClubStaff && !$isTournamentOrganizer) {
            return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho giải này', 403);
        }

        $participant = $tournament->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong giải đấu này', 404);
        }

        if ($participant->checked_in_at) {
            return ResponseHelper::error('Thành viên đã check-in rồi. Không thể check-in lại.', 422);
        }

        $participant->update([
            'is_confirmed' => true,
            'checked_in_at' => now(),
            'is_absent' => false,
        ]);

        $participant->load('user');

        return ResponseHelper::success(new ParticipantResource($participant), 'Đã đánh dấu check-in thành công');
    }

    /**
     * POST /api/clubs/{clubId}/tournaments/{tournamentId}/participants/{participantId}/absent
     * Admin CLB đánh dấu participant vắng mặt
     */
    public function markAbsent(int $clubId, int $tournamentId, int $participantId)
    {
        $club = Club::findOrFail($clubId);
        $tournament = Tournament::with('staff')->findOrFail($tournamentId);
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        if ((int) $tournament->club_id !== $club->id) {
            return ResponseHelper::error('Giải đấu không thuộc CLB này', 404);
        }

        $clubMember = $club->activeMembers()->where('user_id', $userId)->first();
        $isClubStaff = $clubMember && in_array($clubMember->role, [ClubMemberRole::Admin, ClubMemberRole::Manager, ClubMemberRole::Secretary], true);
        $isTournamentOrganizer = $tournament->staff->contains(
            fn($staff) => (int) $staff->pivot->user_id === $userId && (int) $staff->pivot->role === TournamentStaff::ROLE_ORGANIZER
        );

        if (!$isClubStaff && !$isTournamentOrganizer) {
            return ResponseHelper::error('Bạn không có quyền đánh dấu vắng mặt cho giải này', 403);
        }

        $participant = $tournament->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong giải đấu này', 404);
        }

        if ($participant->is_absent) {
            return ResponseHelper::error('Thành viên đã được đánh dấu vắng mặt rồi', 422);
        }

        if ($participant->checked_in_at) {
            return ResponseHelper::error('Thành viên đã check-in. Không thể đánh dấu vắng mặt.', 422);
        }

        $participant->update(['is_absent' => true]);
        $participant->load('user');

        return ResponseHelper::success(new ParticipantResource($participant), 'Đã đánh dấu vắng mặt thành công');
    }

    private function syncParticipantsPaymentStatus(Tournament $tournament, bool $isNowPaid): void
    {
        $organizerIds = $tournament->staff()
            ->where('role', TournamentStaff::ROLE_ORGANIZER)
            ->pluck('user_id')
            ->toArray();

        $sponsoredByOrganizerGuestIds = [];
        if (!empty($organizerIds)) {
            $sponsoredByOrganizerGuestIds = Participant::where('tournament_id', $tournament->id)
                ->where('is_guest', true)
                ->whereNotNull('guarantor_user_id')
                ->whereIn('guarantor_user_id', $organizerIds)
                ->pluck('user_id')
                ->toArray();
        }

        $confirmedParticipants = $tournament->participants()
            ->where('is_confirmed', true)
            ->get();

        if ($confirmedParticipants->isEmpty()) {
            return;
        }

        $feePerPerson = 0;
        if ($isNowPaid) {
            $feePerPerson = $tournament->fee_amount ?? 0;
        }

        foreach ($confirmedParticipants as $participant) {
            $isOrganizer = in_array($participant->user_id, $organizerIds);
            $isSponsoredByOrganizer = in_array($participant->user_id, $sponsoredByOrganizerGuestIds);

            if (!$isNowPaid) {
                if ($participant->payment_status !== PaymentStatusEnum::CANCELLED) {
                    $participant->update(['payment_status' => PaymentStatusEnum::CANCELLED]);
                }
                TournamentParticipantPayment::where('tournament_id', $tournament->id)
                    ->where('participant_id', $participant->id)
                    ->update(['status' => TournamentParticipantPayment::STATUS_REJECTED]);
            } elseif ($isOrganizer || $isSponsoredByOrganizer) {
                if ($participant->payment_status !== PaymentStatusEnum::CONFIRMED) {
                    $participant->update(['payment_status' => PaymentStatusEnum::CONFIRMED]);
                }
                $this->upsertTournamentPaymentRecord($tournament, $participant, 0, TournamentParticipantPayment::STATUS_CONFIRMED);
            } else {
                if ($participant->payment_status !== PaymentStatusEnum::PENDING) {
                    $participant->update(['payment_status' => PaymentStatusEnum::PENDING]);
                }
                $this->upsertTournamentPaymentRecord($tournament, $participant, $feePerPerson, TournamentParticipantPayment::STATUS_PENDING);
            }
        }
    }

    private function upsertTournamentPaymentRecord(Tournament $tournament, Participant $participant, float $amount, string $status): void
    {
        $existing = TournamentParticipantPayment::where('tournament_id', $tournament->id)
            ->where('participant_id', $participant->id)
            ->first();

        if ($existing) {
            $existing->update(['amount' => $amount, 'status' => $status]);
        } else {
            TournamentParticipantPayment::create([
                'tournament_id' => $tournament->id,
                'participant_id' => $participant->id,
                'user_id' => $participant->user_id,
                'amount' => $amount,
                'status' => $status,
            ]);
        }
    }
}
