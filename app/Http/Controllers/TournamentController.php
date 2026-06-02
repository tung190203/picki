<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Enums\PaymentStatusEnum;
use App\Events\SuperAdmin\DashboardStatUpdated;
use App\Events\SuperAdmin\TournamentCreated;
use App\Events\SuperAdmin\TournamentDeleted;
use App\Events\SuperAdmin\TournamentUpdated;
use App\Helpers\ResponseHelper;
use App\Jobs\OptimizeTournamentImageJob;
use App\Http\Controllers\TournamentTypeController;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateTournamentRequest;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\TournamentResource;
use App\Http\Resources\TournamentStaffResource;
use App\Models\Club\Club;
use App\Models\Matches;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentParticipantPayment;
use App\Models\TournamentType;
use App\Services\ImageOptimizationService;
use App\Services\TournamentFundService;
use App\Services\TournamentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentController extends Controller
{
    protected $imageService;
    protected $tournamentTypeController;
    protected $tournamentService;

    /**
     * Quyền đánh dấu check-in / vắng: host và staff BTC
     */
    protected function authorizeMarkParticipantAttendance(Request $request, Tournament $tournament, int $userId): ?\Illuminate\Http\JsonResponse
    {
        if ($tournament->club_id) {
            $clubId = $tournament->club_id;

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

            if (!$isClubStaff && !$tournament->hasAttendancePermission($userId)) {
                return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này với giải đấu', 403);
            }
        } else {
            if ($request->filled('club_id')) {
                return ResponseHelper::error('Giải đấu không thuộc CLB. Không cần truyền club_id.', 422);
            }

            if (!$tournament->hasAttendancePermission($userId)) {
                return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này với giải đấu', 403);
            }
        }

        return null;
    }

    /**
     * Khi người dùng đồng thời là VĐV (participants) và BTC (tournament_staff),
     * sau khi cập nhật bản ghi participants thì gọi method này để đồng thời
     * cập nhật bản ghi tournament_staff cùng user_id — nếu chưa có trạng thái.
     */
    protected function syncStaffAttendanceFromParticipant(Participant $participant): void
    {
        $staff = TournamentStaff::where('tournament_id', $participant->tournament_id)
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
     * Khi người dùng đồng thời là VĐV (participants) và BTC (tournament_staff),
     * sau khi cập nhật bản ghi tournament_staff thì gọi method này để đồng thời
     * cập nhật bản ghi participants cùng user_id — nếu chưa có trạng thái.
     */
    protected function syncParticipantAttendanceFromStaff(TournamentStaff $staff): void
    {
        $participant = Participant::where('tournament_id', $staff->tournament_id)
            ->where('user_id', $staff->user_id)
            ->first();

        if (!$participant || $participant->checked_in_at || $participant->is_absent) {
            return;
        }

        $participant->update([
            'is_confirmed' => true,
            'checked_in_at' => $staff->checked_in_at,
            'is_absent' => false,
        ]);
    }

    /**
     * Khi người dùng đồng thời là VĐV và BTC, sau khi báo vắng ở bảng participants
     * thì đồng thời báo vắng ở bảng tournament_staff cùng user_id — nếu chưa có trạng thái.
     */
    protected function syncStaffAbsentFromParticipant(Participant $participant): void
    {
        $staff = TournamentStaff::where('tournament_id', $participant->tournament_id)
            ->where('user_id', $participant->user_id)
            ->first();

        if (!$staff || $staff->checked_in_at || $staff->is_absent) {
            return;
        }

        $staff->update(['is_absent' => true]);
    }

    /**
     * Khi người dùng đồng thời là VĐV và BTC, sau khi báo vắng ở bảng tournament_staff
     * thì đồng thời báo vắng ở bảng participants cùng user_id — nếu chưa có trạng thái.
     */
    protected function syncParticipantAbsentFromStaff(TournamentStaff $staff): void
    {
        $participant = Participant::where('tournament_id', $staff->tournament_id)
            ->where('user_id', $staff->user_id)
            ->first();

        if (!$participant || $participant->checked_in_at || $participant->is_absent) {
            return;
        }

        $participant->update(['is_absent' => true]);
    }

    public function __construct(
        ImageOptimizationService $imageService,
        TournamentTypeController $tournamentTypeController,
        TournamentService $tournamentService,
        TournamentFundService $fundService
    ) {
        $this->imageService = $imageService;
        $this->tournamentTypeController = $tournamentTypeController;
        $this->tournamentService = $tournamentService;
        $this->fundService = $fundService;
    }

    protected function authorizeAdmin(Tournament $tournament): ?\Illuminate\Http\JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }
        if (!$tournament->hasOrganizerOrStaff($userId)) {
            return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này', 403);
        }
        return null;
    }

    public function store(StoreTournamentRequest $request)
    {
        $validated = $request->validated();
        $tournament = null;

        DB::transaction(function () use ($validated, &$tournament, $request) {
            // Poster: resize + convert WebP + lưu ngay
            if ($request->hasFile('poster')) {
                $savedPath = $this->imageService->processAndSaveImage(
                    $request->file('poster'),
                    'tournaments/posters',
                    'poster_',
                    720,
                    65
                );
                $validated['poster'] = $savedPath;
            }

            $tournament = Tournament::create([
                ...$validated,
                'created_by' => auth()->id(),
            ]);

            TournamentStaff::create([
                'tournament_id' => $tournament->id,
                'user_id' => auth()->id(),
                'role' => TournamentStaff::ROLE_ORGANIZER,
            ]);

            $this->tournamentService->calculateEndDate($tournament);

            if (!empty($validated['creator_join'])) {
                $participantData = [
                    'tournament_id' => $tournament->id,
                    'user_id' => auth()->id(),
                    'is_confirmed' => true,
                ];

                // Nếu giải có thu phí, chủ giải tự động tham gia thì xác nhận thanh toán luôn
                if (!empty($validated['has_fee'])) {
                    $participantData['payment_status'] = \App\Enums\PaymentStatusEnum::CONFIRMED;
                }

                Participant::create($participantData);
            }

            if (!empty($validated['has_financial_management']) && !empty($validated['has_fee'])) {
                $this->fundService->createTournamentFundCollection($tournament, $validated);
            }
        });

        $qrUrl = null;
        if ($request->boolean('use_cached_qr') && auth()->user()->latest_used_qr) {
            $qrUrl = auth()->user()->latest_used_qr;
        } elseif ($request->hasFile('qr_code_url')) {
            $qrUrl = $this->imageService->processAndSaveImage(
                $request->file('qr_code_url'),
                'tournaments/qr',
                'qr_',
                500,
                60
            );
        }

        if ($qrUrl) {
            $tournament->update(['qr_code_url' => $qrUrl]);
            auth()->user()->update(['latest_used_qr' => $qrUrl]);
        }

        if ($tournament) {
            $tournament = Tournament::with([
                'createdBy',
                'sport',
                'competitionLocation',
                'club' => fn($q) => $q->withCount('members'),
            ])->find($tournament->id);
            TournamentCreated::dispatch($tournament);
            DashboardStatUpdated::dispatch('tournaments_this_month', 1, 'incremented');
        } else {
            return ResponseHelper::error('Tạo giải đấu thất bại', 500);
        }

        return ResponseHelper::success(new TournamentResource($tournament), 'Tạo giải đấu thành công');
    }

    public function index(Request $request)
    {
        $query = Tournament::withFullRelations();

        if ($request->has('keyword')) {
            $query->search($request->keyword);
        }

        if ($request->has('start_date') || $request->has('end_date')) {
            $query->filterByDate($request->start_date, $request->end_date);
        }

        $tournaments = $query->paginate(Tournament::PER_PAGE);

        $data = [
            'tournaments' => TournamentResource::collection($tournaments),
        ];

        $meta = [
            'current_page' => $tournaments->currentPage(),
            'last_page' => $tournaments->lastPage(),
            'per_page' => $tournaments->perPage(),
            'total' => $tournaments->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách giải đấu thành công', 200, $meta);
    }

    public function show($id)
    {
        $tournament = Tournament::withFullRelations()->find($id);

        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        return ResponseHelper::success(new TournamentResource($tournament), 'Lấy chi tiết giải đấu thành công');
    }

    public function update(UpdateTournamentRequest $request, $id)
    {
        $validated = $request->validated();
        $tournament = Tournament::findOrFail($id);
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thay đổi giải đấu', 400);
        }

        $oldCreatorJoin = $tournament->creator_join;
        $oldStatus = $tournament->status;

        DB::transaction(function () use ($validated, $tournament, $request) {
            // Poster: resize + convert WebP + lưu ngay
            $newPosterPath = null;
            if ($request->hasFile('poster')) {
                $newPosterPath = $this->imageService->processAndSaveImage(
                    $request->file('poster'),
                    'tournaments/posters',
                    'poster_',
                    720,
                    65
                );
                $this->imageService->deleteOldImage($tournament->poster);
                $validated['poster'] = $newPosterPath;
            } elseif ($request->has('remove_poster') && $request->input('remove_poster')) {
                $this->imageService->deleteOldImage($tournament->poster);
                $validated['poster'] = null;
            } else {
                unset($validated['poster']);
            }

            // QR code: resize + convert WebP + lưu ngay
            if ($request->hasFile('qr_code_url')) {
                $qrUrl = $this->imageService->processAndSaveImage(
                    $request->file('qr_code_url'),
                    'tournaments/qr',
                    'qr_',
                    500,
                    60
                );
                $validated['qr_code_url'] = $qrUrl;
                $this->imageService->deleteOldImage($tournament->qr_code_url);
            } elseif ($request->boolean('use_cached_qr') && auth()->user()->latest_used_qr) {
                $validated['qr_code_url'] = auth()->user()->latest_used_qr;
            } elseif ($request->filled('qr_code_url')) {
                // Keep existing or string value
            } else {
                unset($validated['qr_code_url']);
            }

            unset($validated['remove_poster']);
            $newStatus = $validated['status'] ?? $tournament->status;
            $tournament->fill($validated);
            $tournament->save();

            // Sync payment status khi has_fee thay đổi (free→paid hoặc paid→free)
            $wasPaid = (bool) ($tournament->getOriginal('has_fee') ?? false);
            $isNowPaid = isset($validated['has_fee']) ? (bool) $validated['has_fee'] : $wasPaid;
            if ($wasPaid !== $isNowPaid) {
                $this->syncParticipantsPaymentStatus($tournament, $isNowPaid);
            }

            $this->tournamentService->calculateEndDate($tournament);

            if ($newStatus == Tournament::CLOSED && $tournament->getOriginal('status') != Tournament::CLOSED) {
                $this->updateParticipantsRatingStats($tournament);
            }

            if (array_key_exists('creator_join', $validated)) {
                $newCreatorJoin = !empty($validated['creator_join']);

                if ($newCreatorJoin && !$tournament->getOriginal('creator_join')) {
                    Participant::firstOrCreate([
                        'tournament_id' => $tournament->id,
                        'user_id' => Auth::id(),
                    ], [
                        'is_confirmed' => true,
                    ]);
                }

                if (!$newCreatorJoin && $tournament->getOriginal('creator_join')) {
                    Participant::where('tournament_id', $tournament->id)
                        ->where('user_id', Auth::id())
                        ->whereNull('checked_in_at')
                        ->delete();
                }
            }

        });

        $tournament = Tournament::withBasicRelations()->find($tournament->id);
        $tournament->load(['sport', 'club', 'createdBy', 'participants']);
        TournamentUpdated::dispatch($tournament, $oldStatus !== $tournament->status ? ['status' => $oldStatus] : []);

        return ResponseHelper::success(new TournamentResource($tournament), 'Cập nhật giải đấu thành công');
    }

    public function destroy(Request $request)
    {
        $tournament = Tournament::find($request->id);

        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ($err = $this->authorizeAdmin($tournament)) {
            return $err;
        }

        if ($tournament->status === Tournament::CANCELLED) {
            return ResponseHelper::error('Giải đấu đã bị hủy trước đó rồi', 422);
        }

        $hasCompletedMatch = Matches::whereHas('tournamentType', function ($q) use ($tournament) {
            $q->where('tournament_id', $tournament->id);
        })
        ->where('status', Matches::STATUS_COMPLETED)
        ->exists();

        if ($hasCompletedMatch) {
            return ResponseHelper::error(
                'Không thể hủy giải. Đã có trận đấu hoàn thành thuộc giải này.',
                400
            );
        }

        $tournamentName = $tournament->name;
        $tournamentId = $tournament->id;

        DB::transaction(function () use ($tournament) {
            // tournament_types cascade xóa participants, groups → matches
            // tournament_fund_collections cascade xóa contributions, collection_members
            // tournament_participant_payments đã có cascade qua participants
            $tournament->delete();
        });

        TournamentDeleted::dispatch($tournamentId, $tournamentName);
        DashboardStatUpdated::dispatch('active_tournaments', 1, 'decremented');

        return ResponseHelper::success(null, 'Xóa giải đấu thành công');
    }

    /**
     * POST /api/tournaments/{id}/lock-fee
     * Lock phí mỗi người sau khi giải đấu bắt đầu
     *
     * Chỉ áp dụng khi auto_split_fee = true.
     * Tính feePerPerson dựa trên số participant đã confirmed và cập nhật final_fee_per_person.
     */
    public function lockFee(int $tournamentId)
    {
        $tournament = Tournament::find($tournamentId);
        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        if ($err = $this->authorizeAdmin($tournament)) {
            return $err;
        }

        if (!$tournament->auto_split_fee) {
            return ResponseHelper::error('Chỉ áp dụng cho giải có chia tiền tự động', 422);
        }

        if ($tournament->auto_payment_created) {
            return ResponseHelper::error('Phí đã được lock trước đó, không thể cập nhật lại', 422);
        }

        $participantCount = $tournament->participants()->where('is_confirmed', true)->count();
        $feePerPerson = $participantCount > 0
            ? (int) round($tournament->fee_amount / $participantCount)
            : 0;

        $tournament->update([
            'final_fee_per_person' => $feePerPerson,
            'auto_payment_created' => true,
        ]);

        return ResponseHelper::success([
            'final_fee_per_person' => $feePerPerson,
            'auto_payment_created' => true,
            'participant_count' => $participantCount,
        ], 'Đã lock phí mỗi người');
    }

    /**
     * Lấy bracket cho tournament với cấu trúc mới
     * Trả về: poolStage, leftSide, rightSide, finalMatch, thirdPlaceMatch
     *
     * Route: GET /api/tournaments/{id}/bracket
     * Alias: GET /api/tournament-detail/{id}/bracket (backward compatible)
     *
     * @param int $id Tournament ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBracket($id)
    {
        try {
            $tournament = Tournament::with('tournamentTypes')->find($id);

            if (!$tournament) {
                return ResponseHelper::error('Giải đấu không tồn tại', 404);
            }

            $tournamentType = $tournament->tournamentTypes->first();

            if (!$tournamentType) {
                return ResponseHelper::error('Giải đấu chưa có tournament type', 404);
            }

            // Sử dụng getBracketNew cho format Mixed, getBracket cho các format khác
            if ($tournamentType->format === TournamentType::FORMAT_MIXED) {
                return $this->tournamentTypeController->getBracketNew($tournamentType);
            } else {
                return $this->tournamentTypeController->getBracket($tournamentType);
            }
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Throwable $e) {
            return ResponseHelper::error('Có lỗi xảy ra khi lấy bracket giải đấu', 500);
        }
    }

    /**
     * BTC / staff / trọng tài / staff CLB đánh dấu check-in.
     * - `participantId` trong URL: ưu tiên là participants.id (VĐV); nếu không có thì coi là tournament_staff.id (BTC).
     * - Kèo CLB: body cần club_id khi giải thuộc CLB.
     */
    public function markParticipantCheckIn(Request $request, int $tournamentId, int $participantId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $tournament = Tournament::with('staff')->findOrFail($tournamentId);

        if ($err = $this->authorizeMarkParticipantAttendance($request, $tournament, $userId)) {
            return $err;
        }

        $participant = $tournament->participants()->where('id', $participantId)->first();
        if ($participant) {
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

            $this->syncStaffAttendanceFromParticipant($participant);

            return ResponseHelper::success(
                new ParticipantResource($participant),
                'Đã đánh dấu check-in thành công'
            );
        }

        $tournamentStaff = TournamentStaff::where('tournament_id', $tournamentId)
            ->where('id', $participantId)
            ->first();

        if (!$tournamentStaff) {
            return ResponseHelper::error('Thành viên hoặc thành viên ban tổ chức không tồn tại trong giải đấu này', 404);
        }

        if ($tournamentStaff->checked_in_at) {
            return ResponseHelper::error('Thành viên ban tổ chức đã check-in rồi. Không thể check-in lại.', 422);
        }

        if ($tournamentStaff->is_absent) {
            $tournamentStaff->update([
                'checked_in_at' => now(),
                'is_absent' => false,
            ]);
        } else {
            $tournamentStaff->update([
                'checked_in_at' => now(),
            ]);
        }

        $tournamentStaff->load('user');

        $this->syncParticipantAttendanceFromStaff($tournamentStaff);

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Đã đánh dấu check-in thành công'
        );
    }

    /**
     * BTC / staff / trọng tài / staff CLB đánh dấu vắng.
     * - `participantId`: participants.id trước, không có thì tournament_staff.id.
     */
    public function markParticipantAbsent(Request $request, int $tournamentId, int $participantId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $tournament = Tournament::with('staff')->findOrFail($tournamentId);

        if ($err = $this->authorizeMarkParticipantAttendance($request, $tournament, $userId)) {
            return $err;
        }

        $participant = $tournament->participants()->where('id', $participantId)->first();
        if ($participant) {
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

            $this->syncStaffAttendanceFromParticipant($participant);

            return ResponseHelper::success(
                new ParticipantResource($participant),
                'Đã đánh dấu vắng mặt thành công'
            );
        }

        $tournamentStaff = TournamentStaff::where('tournament_id', $tournamentId)
            ->where('id', $participantId)
            ->first();

        if (!$tournamentStaff) {
            return ResponseHelper::error('Thành viên hoặc thành viên ban tổ chức không tồn tại trong giải đấu này', 404);
        }

        if ($tournamentStaff->is_absent) {
            return ResponseHelper::error('Thành viên ban tổ chức đã được đánh dấu vắng mặt rồi', 422);
        }

        if ($tournamentStaff->checked_in_at) {
            return ResponseHelper::error('Thành viên ban tổ chức đã check-in. Không thể đánh dấu vắng mặt.', 422);
        }

        $tournamentStaff->update([
            'is_absent' => true,
        ]);

        $tournamentStaff->load('user');

        $this->syncParticipantAttendanceFromStaff($tournamentStaff);

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Đã đánh dấu vắng mặt thành công'
        );
    }

    /**
     * Thành viên tự check-in vào giải đấu (self-service).
     * - Đã check-in: không thể check-in lại.
     * - Đã vắng: cho phép check-in lại (đổi is_absent = false).
     */
    public function selfCheckIn(int $tournamentId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $tournament = Tournament::withFullRelations()->findOrFail($tournamentId);

        // Kiểm tra giải đấu có đang mở không
        if ($tournament->status === 'completed' || $tournament->status === 'cancelled') {
            return ResponseHelper::error('Giải đấu đã kết thúc hoặc bị hủy. Không thể check-in.', 400);
        }

        $participant = $tournament->participants()
            ->where('user_id', $userId)
            ->first();

        if ($participant) {
            if ($participant->checked_in_at) {
                return ResponseHelper::error('Bạn đã check-in rồi. Không thể check-in lại.', 422);
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

            $this->syncStaffAttendanceFromParticipant($participant);

            return ResponseHelper::success(
                new ParticipantResource($participant),
                'Check-in thành công'
            );
        }

        $tournamentStaff = TournamentStaff::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        if (!$tournamentStaff) {
            return ResponseHelper::error('Bạn chưa tham gia giải đấu này', 422);
        }

        if ($tournamentStaff->checked_in_at) {
            return ResponseHelper::error('Bạn đã check-in rồi. Không thể check-in lại.', 422);
        }

        if ($tournamentStaff->is_absent) {
            $tournamentStaff->update([
                'checked_in_at' => now(),
                'is_absent' => false,
            ]);
        } else {
            $tournamentStaff->update([
                'checked_in_at' => now(),
            ]);
        }

        $tournamentStaff->load('user');

        $this->syncParticipantAttendanceFromStaff($tournamentStaff);

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Check-in thành công'
        );
    }

    /**
     * Thành viên tự báo vắng khỏi giải đấu (self-service).
     * - Đã check-in: không thể báo vắng.
     * - Đã vắng: không thể báo vắng lại.
     */
    public function selfMarkAbsent(int $tournamentId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

        $tournament = Tournament::withFullRelations()->findOrFail($tournamentId);

        // Kiểm tra giải đấu có đang mở không
        if ($tournament->status === 'completed' || $tournament->status === 'cancelled') {
            return ResponseHelper::error('Giải đấu đã kết thúc hoặc bị hủy. Không thể báo vắng.', 400);
        }

        $participant = $tournament->participants()
            ->where('user_id', $userId)
            ->first();

        if ($participant) {
            if ($participant->is_absent) {
                return ResponseHelper::error('Bạn đã báo vắng rồi. Không thể báo vắng lại.', 422);
            }

            if ($participant->checked_in_at) {
                return ResponseHelper::error('Bạn đã check-in rồi. Không thể báo vắng.', 422);
            }

            $participant->update([
                'is_absent' => true,
            ]);

            $participant->load('user');

            $this->syncStaffAbsentFromParticipant($participant);

            return ResponseHelper::success(
                new ParticipantResource($participant),
                'Đã báo vắng thành công'
            );
        }

        $tournamentStaff = TournamentStaff::where('tournament_id', $tournamentId)
            ->where('user_id', $userId)
            ->first();

        if (!$tournamentStaff) {
            return ResponseHelper::error('Bạn chưa tham gia giải đấu này', 422);
        }

        if ($tournamentStaff->is_absent) {
            return ResponseHelper::error('Bạn đã báo vắng rồi. Không thể báo vắng lại.', 422);
        }

        if ($tournamentStaff->checked_in_at) {
            return ResponseHelper::error('Bạn đã check-in rồi. Không thể báo vắng.', 422);
        }

        $tournamentStaff->update([
            'is_absent' => true,
        ]);

        $tournamentStaff->load('user');

        $this->syncParticipantAbsentFromStaff($tournamentStaff);

        return ResponseHelper::success(
            new TournamentStaffResource($tournamentStaff),
            'Đã báo vắng thành công'
        );
    }

    /**
     * Cập nhật rating và rank cho tất cả participants khi giải đấu kết thúc
     */
    private function updateParticipantsRatingStats(Tournament $tournament): void
    {
        $this->tournamentService->updateParticipantsRatingStats($tournament);
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
