<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberRole;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\TournamentTypeController;
use App\Http\Resources\ParticipantResource;
use App\Http\Resources\TournamentResource;
use App\Models\Club\Club;
use App\Models\Matches;
use App\Models\Participant;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\TournamentType;
use App\Services\ImageOptimizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TournamentController extends Controller
{
    protected $imageService;
    protected $tournamentTypeController;

    public function __construct(
        ImageOptimizationService $imageService,
        TournamentTypeController $tournamentTypeController
    ) {
        $this->imageService = $imageService;
        $this->tournamentTypeController = $tournamentTypeController;
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'poster' => 'nullable|image|max:350',
            'sport_id' => 'required|exists:sports,id',
            'name' => 'required|string',
            'competition_location_id' => 'nullable|exists:competition_locations,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'registration_open_at' => 'nullable|date',
            'registration_closed_at' => 'nullable|date',
            'early_registration_deadline' => 'nullable|date',
            'duration' => 'nullable|integer',
            'enable_dupr' => 'nullable|boolean',
            'enable_vndupr' => 'nullable|boolean',
            'min_level' => 'nullable',
            'max_level' => 'nullable',
            'age_group' => 'nullable|in:' . implode(',', Tournament::AGES),
            'gender_policy' => 'nullable|in:' . implode(',', Tournament::GENDER),
            'participant' => 'nullable|in:team,user',
            'max_team' => 'nullable|integer|required_if:participant,team',
            'player_per_team' => 'nullable|integer|required_if:participant,team',
            'max_player' => 'nullable|integer|required_if:participant,user',
            'fee' => 'nullable|in:free,pair',
            'standard_fee_amount' => 'nullable|numeric|required_if:fee,pair',
            'is_private' => 'nullable|boolean',
            'auto_approve' => 'nullable|boolean',
            'description' => 'nullable|string',
            'club_id' => 'nullable|exists:clubs,id',
        ]);

        $tournament = null;

        DB::transaction(function () use ($validated, &$tournament, $request) {
            if ($request->hasFile('poster')) {
                $path = $request->file('poster')->store('tournaments/posters', 'public');
                $validated['poster'] = $path;
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
        });

        if ($tournament) {
            $tournament = Tournament::withBasicRelations()->find($tournament->id);
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

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'poster' => 'nullable|image|max:5120',
            'remove_poster' => 'nullable|boolean', // Thêm field này
            'sport_id' => 'nullable|exists:sports,id',
            'name' => 'nullable|string',
            'competition_location_id' => 'nullable|exists:competition_locations,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'registration_open_at' => 'nullable|date',
            'registration_closed_at' => 'nullable|date',
            'early_registration_deadline' => 'nullable|date',
            'duration' => 'nullable|integer',
            'enable_dupr' => 'nullable|boolean',
            'enable_vndupr' => 'nullable|boolean',
            'min_level' => 'nullable',
            'max_level' => 'nullable',
            'age_group' => 'nullable|in:' . implode(',', Tournament::AGES),
            'gender_policy' => 'nullable|in:' . implode(',', Tournament::GENDER),
            'participant' => 'nullable|in:team,user',
            'max_team' => 'nullable|integer|required_if:participant,team',
            'player_per_team' => 'nullable|integer|required_if:participant,team',
            'max_player' => 'nullable|integer|required_if:participant,user',
            'fee' => 'nullable|in:free,pair',
            'standard_fee_amount' => 'nullable|numeric|required_if:fee,pair',
            'is_private' => 'nullable|boolean',
            'auto_approve' => 'nullable|boolean',
            'description' => 'nullable|string',
            'club_id' => 'nullable|exists:clubs,id',
            'is_public_branch' => 'nullable|boolean',
            'is_own_score' => 'nullable|boolean',
            'status' => 'nullable|in:' . implode(',', Tournament::STATUS),
        ]);

        $tournament = Tournament::findOrFail($id);
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thay đổi giải đấu', 400);
        }

        DB::transaction(function () use ($validated, $tournament, $request) {
            if ($request->hasFile('poster')) {
                $this->imageService->deleteOldImage($tournament->poster);
                $path = $this->imageService->optimize(
                    $validated['poster'],
                    'tournaments/posters'
                );
                $validated['poster'] = $path;
            } elseif ($request->has('remove_poster') && $request->input('remove_poster')) {
                $this->imageService->deleteOldImage($tournament->poster);
                $validated['poster'] = null;
            } else {
                unset($validated['poster']);
            }
            unset($validated['remove_poster']);
            $tournament->fill($validated);
            $tournament->save();
        });

        $tournament = Tournament::withBasicRelations()->find($tournament->id);

        return ResponseHelper::success(new TournamentResource($tournament), 'Cập nhật giải đấu thành công');
    }

    public function destroy(Request $request)
    {
        $tournament = Tournament::find($request->id);

        if (!$tournament) {
            return ResponseHelper::error('Giải đấu không tồn tại', 404);
        }

        $hasCompletedMatch = Matches::whereHas('tournamentType', function ($q) use ($tournament) {
            $q->where('tournament_id', $tournament->id);
        })
        ->where('status', Matches::STATUS_COMPLETED)
        ->exists();

        if ($hasCompletedMatch) {
            return ResponseHelper::error(
                'Không thể huỷ bỏ giải. Đã có trận đấu hoàn thành thuộc giải này.',
                400
            );
        }

        DB::transaction(function () use ($tournament) {
            $tournament->delete();
        });

        return ResponseHelper::success(null, 'Xoá giải đấu thành công');
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
        } catch (\Throwable $e) {
            Log::error('Error in getBracket', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ResponseHelper::error('Lỗi khi lấy bracket: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Organizer / Club staff đánh dấu member check-in giải đấu.
     * - Kèo CLB: cần truyền club_id trong body, chỉ admin/manager/secretary hoặc organizer mới được phép.
     * - Kèo thường: không cần club_id, chỉ organizer mới được phép.
     * - Đã check-in: không thể check-in lại.
     * - Đã vắng: cho phép check-in lại (đổi is_absent = false).
     */
    public function markParticipantCheckIn(Request $request, int $tournamentId, int $participantId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

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
            $isTournamentOrganizer = $tournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === TournamentStaff::ROLE_ORGANIZER
            );

            if (!$isClubStaff && !$isTournamentOrganizer) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu check-in cho giải đấu này', 403);
            }
        } else {
            // === Giải đấu thường: chỉ organizer ===
            if ($request->filled('club_id')) {
                return ResponseHelper::error('Giải đấu không thuộc CLB. Không cần truyền club_id.', 422);
            }

            $isOrganizer = $tournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === TournamentStaff::ROLE_ORGANIZER
            );

            if (!$isOrganizer) {
                return ResponseHelper::error('Chỉ organizer giải đấu mới có quyền đánh dấu check-in', 403);
            }
        }

        $participant = $tournament->participants()->where('id', $participantId)->first();
        if (!$participant) {
            return ResponseHelper::error('Thành viên không tồn tại trong giải đấu này', 404);
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

        return ResponseHelper::success(
            new ParticipantResource($participant),
            'Đã đánh dấu check-in thành công'
        );
    }

    /**
     * Organizer / Club staff đánh dấu member vắng mặt giải đấu.
     * - Kèo CLB: cần truyền club_id trong body, chỉ admin/manager/secretary hoặc organizer mới được phép.
     * - Kèo thường: không cần club_id, chỉ organizer mới được phép.
     * - Đã check-in: không thể đánh dấu vắng mặt.
     * - Đã vắng: không thể đánh dấu vắng mặt lại.
     */
    public function markParticipantAbsent(Request $request, int $tournamentId, int $participantId)
    {
        $userId = Auth::id();

        if (!$userId) {
            return ResponseHelper::error('Bạn cần đăng nhập', 401);
        }

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
            $isTournamentOrganizer = $tournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === TournamentStaff::ROLE_ORGANIZER
            );

            if (!$isClubStaff && !$isTournamentOrganizer) {
                return ResponseHelper::error('Bạn không có quyền đánh dấu vắng mặt cho giải đấu này', 403);
            }
        } else {
            // === Giải đấu thường: chỉ organizer ===
            if ($request->filled('club_id')) {
                return ResponseHelper::error('Giải đấu không thuộc CLB. Không cần truyền club_id.', 422);
            }

            $isOrganizer = $tournament->staff->contains(
                fn ($staff) => (int) $staff->pivot->user_id === $userId
                && (int) $staff->pivot->role === TournamentStaff::ROLE_ORGANIZER
            );

            if (!$isOrganizer) {
                return ResponseHelper::error('Chỉ organizer giải đấu mới có quyền đánh dấu vắng mặt', 403);
            }
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

        $participant->update([
            'is_absent' => true,
        ]);

        $participant->load('user');

        return ResponseHelper::success(
            new ParticipantResource($participant),
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

        if (!$participant) {
            return ResponseHelper::error('Bạn chưa tham gia giải đấu này', 422);
        }

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

        return ResponseHelper::success(
            new ParticipantResource($participant),
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

        if (!$participant) {
            return ResponseHelper::error('Bạn chưa tham gia giải đấu này', 422);
        }

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

        return ResponseHelper::success(
            new ParticipantResource($participant),
            'Đã báo vắng thành công'
        );
    }
}
