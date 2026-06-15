<?php

namespace App\Http\Controllers;

use App\Events\SuperAdmin\MiniMatchUpdated;
use App\Exceptions\BusinessException;
use App\Helpers\ResponseHelper;
use App\Http\Resources\MiniMatchResource;
use App\Jobs\SendPushJob;
use App\Models\MiniMatch;
use App\Models\MiniMatchResult;
use App\Models\MiniParticipant;
use App\Models\MiniTeam;
use App\Models\MiniTeamMember;
use App\Models\MiniTournament;
use App\Models\User;
use App\Models\VnduprHistory;
use App\Notifications\MiniMatchCreatedNotification;
use App\Notifications\MiniMatchResultConfirmedNotification;
use App\Notifications\MiniMatchUpdatedNotification;
use App\Services\RoundRobinSchedulerService;
use App\Services\Tournament\ByeResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MiniMatchController extends Controller
{
    private const VALIDATION_RULE = 'sometimes';
    /**
     * Lấy danh sách trận đấu trong mini tournament (theo vòng, thời gian, lọc theo người chơi)
     */
    public function index(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);
        $userId = Auth::id();
        $isOrganizer = $miniTournament->hasOrganizer($userId);

        // Kèo chưa công bố (draft): chỉ organizer xem được nội dung
        // Với standard format, participant đã confirmed cũng được thấy matches
        $isConfirmedParticipant = false;
        if ($miniTournament->status === MiniTournament::STATUS_DRAFT && !$isOrganizer) {
            $isConfirmedParticipant = $miniTournament->participants()
                ->where('user_id', $userId)
                ->where('is_confirmed', true)
                ->exists();
        }

        if ($miniTournament->status === MiniTournament::STATUS_DRAFT
            && !$isOrganizer
            && !$isConfirmedParticipant
        ) {
            return ResponseHelper::success([
                'matches' => [],
                'rounds' => [],
                'match_format' => $miniTournament->match_format,
                'is_session_started' => $miniTournament->is_session_started,
                'session_status' => $miniTournament->session_status,
                'total_matches' => 0,
                'confirmed_matches' => 0,
                'current_round' => null,
                'is_organizer' => false,
                'tournament' => [
                    'id' => $miniTournament->id,
                    'name' => $miniTournament->name,
                    'status' => $miniTournament->status,
                    'status_text' => $miniTournament->status_text,
                    'format' => $miniTournament->format,
                    'format_text' => $miniTournament->format_text,
                    'play_mode' => $miniTournament->play_mode,
                    'play_mode_text' => $miniTournament->play_mode_text,
                    'sport' => $miniTournament->sport ? [
                        'id' => $miniTournament->sport->id,
                        'name' => $miniTournament->sport->name,
                    ] : null,
                    'competition_location' => $miniTournament->competitionLocation ? [
                        'id' => $miniTournament->competitionLocation->id,
                        'name' => $miniTournament->competitionLocation->name,
                        'address' => $miniTournament->competitionLocation->address,
                    ] : null,
                    'start_time' => $miniTournament->start_time?->toIsoString(),
                    'end_time' => $miniTournament->end_time?->toIsoString(),
                ],
                'participants' => [],
                'statistics' => [
                    'total_participants' => 0,
                    'confirmed_participants' => 0,
                    'male_participants' => 0,
                    'female_participants' => 0,
                ],
            ], 'Lấy danh sách trận đấu thành công');
        }

        // Load matches with only the relations actually needed by MiniMatchResource.
        // Avoid withFullRelations() which loads heavy relations (sports/scores, clubs,
        // competitionLocation) that are not rendered in round-based formats.
        $baseQuery = MiniMatch::with([
            'team1.members.user.sports.scores',
            'team2.members.user.sports.scores',
            'results.team.members.user',
        ])
            ->where('mini_tournament_id', $miniTournament->id);

        // Load participants
        $participants = $miniTournament->participants()
            ->with('user:id,full_name,avatar_url,visibility')
            ->where('is_confirmed', true)
            ->get();

        // Pre-compute statistics from the already-loaded $participants collection
        // instead of issuing additional DB queries per statistic.
        $groupedStats = $participants->reduce(function ($carry, $p) {
            $carry['confirmed']++;
            if ($p->player_group === 'male') {
                $carry['male']++;
            } elseif ($p->player_group === 'female') {
                $carry['female']++;
            } elseif ($p->player_group === 'a') {
                $carry['group_a']++;
            } elseif ($p->player_group === 'b') {
                $carry['group_b']++;
            }
            return $carry;
        }, ['confirmed' => 0, 'male' => 0, 'female' => 0, 'group_a' => 0, 'group_b' => 0]);

        $totalMatches = (clone $baseQuery)->count();
        $confirmedMatches = (clone $baseQuery)
            ->where('status', MiniMatch::STATUS_COMPLETED)
            ->count();

        $isRoundBased = in_array($miniTournament->match_format, [
            MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
            MiniTournament::MATCH_FORMAT_MIXED_GENDER,
            MiniTournament::MATCH_FORMAT_RANK_PAIRING,
        ], true);

        $currentRound = null;
        if ($isRoundBased) {
            $activeRound = (clone $baseQuery)
                ->whereNotNull('round_number')
                ->where('status', MiniMatch::STATUS_GOING_ON)
                ->orderBy('round_number')
                ->value('round_number');
            if (!$activeRound) {
                $activeRound = (clone $baseQuery)
                    ->whereNotNull('round_number')
                    ->where('status', MiniMatch::STATUS_PENDING)
                    ->orderBy('round_number')
                    ->value('round_number');
            }
            $currentRound = $activeRound;
        }

        // Compute is_extra player flags (only meaningful for round-based formats)
        $isExtraPlayerFlags = [];

        if ($isRoundBased) {
            $allMatches = (clone $baseQuery)
                ->orderBy('round_number')
                ->orderBy('id')
                ->get();

            $isExtraPlayerFlags = $this->computeExtraPlayerFlags($allMatches, $participants, $miniTournament->match_format);

            $grouped = $allMatches->groupBy('round_number')->map(function ($roundMatches, $roundNumber) use ($isExtraPlayerFlags) {
                $completedCount = $roundMatches->where('status', MiniMatch::STATUS_COMPLETED)->count();
                $totalCount = $roundMatches->count();
                $pendingCount = $roundMatches->where('status', MiniMatch::STATUS_PENDING)->count();
                $goingOnCount = $roundMatches->where('status', MiniMatch::STATUS_GOING_ON)->count();
                $waitingConfirmCount = $roundMatches->where('status', MiniMatch::STATUS_WAITING_CONFIRM)->count();

                $roundStatus = $completedCount === $totalCount
                    ? 'done'
                    : ($goingOnCount > 0 || $waitingConfirmCount > 0 ? 'active' : 'upcoming');

                return [
                    'round_number' => (int) $roundNumber,
                    'status' => $roundStatus,
                    'completed_count' => $completedCount,
                    'pending_count' => $pendingCount,
                    'going_on_count' => $goingOnCount,
                    'waiting_confirm_count' => $waitingConfirmCount,
                    'total_count' => $totalCount,
                    'matches' => (function ($matches) use ($isExtraPlayerFlags) {
                        MiniMatchResource::setExtraPlayerFlags($isExtraPlayerFlags);
                        return MiniMatchResource::collection($matches);
                    })($roundMatches),
                ];
            })->sortBy('round_number')->values();

            return ResponseHelper::success([
                'matches' => [],
                'rounds' => $grouped,
                'match_format' => $miniTournament->match_format,
                'is_session_started' => $miniTournament->is_session_started,
                'session_status' => $miniTournament->session_status,
                'total_matches' => $totalMatches,
                'confirmed_matches' => $confirmedMatches,
                'current_round' => $currentRound,
                'is_organizer' => $isOrganizer,
                'tournament' => [
                    'id' => $miniTournament->id,
                    'name' => $miniTournament->name,
                    'status' => $miniTournament->status,
                    'status_text' => $miniTournament->status_text,
                    'format' => $miniTournament->format,
                    'format_text' => $miniTournament->format_text,
                    'play_mode' => $miniTournament->play_mode,
                    'play_mode_text' => $miniTournament->play_mode_text,
                    'sport' => $miniTournament->sport ? [
                        'id' => $miniTournament->sport->id,
                        'name' => $miniTournament->sport->name,
                    ] : null,
                    'competition_location' => $miniTournament->competitionLocation ? [
                        'id' => $miniTournament->competitionLocation->id,
                        'name' => $miniTournament->competitionLocation->name,
                        'address' => $miniTournament->competitionLocation->address,
                    ] : null,
                    'start_time' => $miniTournament->start_time?->toIsoString(),
                    'end_time' => $miniTournament->end_time?->toIsoString(),
                ],
                'participants' => $participants->map(fn($p) => [
                    'id' => $p->id,
                    'user_id' => $p->user_id,
                    'name' => $p->user?->name ?? $p->guest_name ?? 'Khách',
                    'avatar' => $p->user?->avatar ?? $p->guest_avatar,
                    'player_group' => $p->player_group,
                    'is_guest' => $p->is_guest,
                    'is_absent' => $p->is_absent,
                    'checked_in_at' => $p->checked_in_at?->toIsoString(),
                ]),
                'statistics' => [
                    'total_participants' => $miniTournament->participants()->count(),
                    'confirmed_participants' => $groupedStats['confirmed'],
                    'male_participants' => $groupedStats['male'],
                    'female_participants' => $groupedStats['female'],
                    'group_a_participants' => $groupedStats['group_a'],
                    'group_b_participants' => $groupedStats['group_b'],
                ],
            ], 'Lấy danh sách trận đấu thành công');
        }

        // Standard format: flat list
        $matches = (clone $baseQuery)->orderBy('created_at', 'desc')->get();

        return ResponseHelper::success([
            'matches' => (function ($matches) use ($isExtraPlayerFlags) {
                MiniMatchResource::setExtraPlayerFlags($isExtraPlayerFlags);
                return MiniMatchResource::collection($matches);
            })($matches),
            'rounds' => [],
            'match_format' => $miniTournament->match_format,
            'is_session_started' => $miniTournament->is_session_started,
            'session_status' => $miniTournament->session_status,
            'total_matches' => $totalMatches,
            'confirmed_matches' => $confirmedMatches,
            'current_round' => null,
            'is_organizer' => $isOrganizer,
            'tournament' => [
                'id' => $miniTournament->id,
                'name' => $miniTournament->name,
                'status' => $miniTournament->status,
                'status_text' => $miniTournament->status_text,
                'format' => $miniTournament->format,
                'format_text' => $miniTournament->format_text,
                'play_mode' => $miniTournament->play_mode,
                'play_mode_text' => $miniTournament->play_mode_text,
                'sport' => $miniTournament->sport ? [
                    'id' => $miniTournament->sport->id,
                    'name' => $miniTournament->sport->name,
                ] : null,
                'competition_location' => $miniTournament->competitionLocation ? [
                    'id' => $miniTournament->competitionLocation->id,
                    'name' => $miniTournament->competitionLocation->name,
                    'address' => $miniTournament->competitionLocation->address,
                ] : null,
                'start_time' => $miniTournament->start_time?->toIsoString(),
                'end_time' => $miniTournament->end_time?->toIsoString(),
            ],
            'participants' => $participants->map(fn($p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user?->name ?? $p->guest_name ?? 'Khách',
                'avatar' => $p->user?->avatar ?? $p->guest_avatar,
                'player_group' => $p->player_group,
                'is_guest' => $p->is_guest,
                'is_absent' => $p->is_absent,
                'checked_in_at' => $p->checked_in_at?->toIsoString(),
            ]),
            'statistics' => [
                'total_participants' => $miniTournament->participants()->count(),
                'confirmed_participants' => $groupedStats['confirmed'],
                'male_participants' => $groupedStats['male'],
                'female_participants' => $groupedStats['female'],
                'group_a_participants' => $groupedStats['group_a'],
                'group_b_participants' => $groupedStats['group_b'],
            ],
        ], 'Lấy danh sách trận đấu thành công');
    }
    /**
     * Lấy thông tin chi tiết trận đấu
     */
    public function show($matchId)
    {
        $match = MiniMatch::withFullRelations()->findOrFail($matchId);
        $miniTournament = $match->miniTournament;
        $userId = Auth::id();
        $isOrganizer = $miniTournament->hasOrganizer($userId);

        // Kèo chưa công bố (status = 1 = STATUS_DRAFT): chỉ organizer mới xem được
        if ($miniTournament->status === MiniTournament::STATUS_DRAFT && !$isOrganizer) {
            return ResponseHelper::error('Kèo đấu chưa được công bố', 403);
        }

        return ResponseHelper::success(new MiniMatchResource($match), 'Lấy thông tin trận đấu thành công');
    }
    public function store(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền tạo trận đấu', 403);
        }

        $data = $request->validate([
            'team1' => 'required|array|min:1',
            'team2' => 'required|array|min:1',
            'team1.*' => 'exists:users,id',
            'team2.*' => 'exists:users,id',
            'team1_name' => 'nullable|string|max:255',
            'team2_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $team1Count = count($data['team1']);
        $team2Count = count($data['team2']);

        if ($team1Count !== $team2Count) {
            return ResponseHelper::error('Số lượng người chơi của 2 đội phải bằng nhau', 422);
        }

        switch ($miniTournament->format) {
            case MiniTournament::FORMAT_SINGLE:
                if ($team1Count !== 1) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 1v1', 422);
                }
                break;
            case MiniTournament::FORMAT_DOUBLE:
            case MiniTournament::FORMAT_MENS_DOUBLES:
            case MiniTournament::FORMAT_WOMENS_DOUBLES:
            case MiniTournament::FORMAT_MIXED:
                if ($team1Count !== 2) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 2v2', 422);
                }
                break;
            default:
                if (!in_array($team1Count, [1, 2])) {
                    return ResponseHelper::error('Chỉ cho phép tạo trận 1v1 hoặc 2v2', 422);
                }
                break;
        }

        $allUserIds = array_unique(array_merge($data['team1'], $data['team2']));

        $validParticipants = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
            ->where('is_confirmed', true)
            ->whereIn('user_id', $allUserIds)
            ->pluck('user_id')
            ->toArray();

        if (count($validParticipants) !== count($allUserIds)) {
            return ResponseHelper::error(
                'Có người chơi chưa tham gia hoặc chưa được duyệt trong kèo',
                422
            );
        }

        DB::beginTransaction();

        try {
            $team1 = MiniTeam::create([
                'mini_tournament_id' => $miniTournament->id,
                'name' => $data['team1_name'] ?? $this->buildTeamName($data['team1'], $miniTournament->id),
            ]);

            foreach ($data['team1'] as $userId) {
                $isGuest = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                    ->where('user_id', $userId)
                    ->value('is_guest') ?? false;
                $team1->members()->create(['user_id' => $userId, 'is_guest' => $isGuest]);
            }

            $team2 = MiniTeam::create([
                'mini_tournament_id' => $miniTournament->id,
                'name' => $data['team2_name'] ?? $this->buildTeamName($data['team2'], $miniTournament->id),
            ]);

            foreach ($data['team2'] as $userId) {
                $isGuest = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                    ->where('user_id', $userId)
                    ->value('is_guest') ?? false;
                $team2->members()->create(['user_id' => $userId, 'is_guest' => $isGuest]);
            }
            $defaultMatchName = $this->generateMatchName($miniTournament);

            $match = MiniMatch::create([
                'mini_tournament_id' => $miniTournament->id,
                'team1_id' => $team1->id,
                'team2_id' => $team2->id,
                'status' => MiniMatch::STATUS_PENDING,
                'name' => $data['name'] ?? $defaultMatchName,
            ]);

            DB::commit();
            $allParticipantIds = array_unique(array_merge($data['team1'], $data['team2']));
            $users = User::whereIn('id', $allParticipantIds)->get();
            $users->each(function ($user) use ($match) {
                $user->notify(new MiniMatchCreatedNotification($match));
            });

            return ResponseHelper::success(new MiniMatchResource($match->loadFullRelations()), 'Tạo trận đấu thành công', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Có lỗi xảy ra khi tạo trận đấu', 500);
        }
    }
    /**
     * Cập nhật thông tin trận đấu trong kèo đấu
     */

    public function update(Request $request, $matchId)
    {
        $match = MiniMatch::withFullRelations()->findOrFail($matchId);

        $miniTournament = $match->miniTournament;

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền sửa trận đấu', 403);
        }

        $data = $request->validate([
            'team1' => 'sometimes|array|min:1',
            'team2' => 'sometimes|array|min:1',
            'team1.*' => 'exists:users,id',
            'team2.*' => 'exists:users,id',
            'team1_name' => 'nullable|string|max:255',
            'team2_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        // ---- CHECK MATCH TYPE ----
        $team1Count = isset($data['team1']) ? count($data['team1']) : $match->team1->members->count();

        $team2Count = isset($data['team2']) ? count($data['team2']) : $match->team2->members->count();

        if ($team1Count !== $team2Count) {
            return ResponseHelper::error('Số lượng người chơi của 2 đội phải bằng nhau', 422);
        }

        switch ($miniTournament->format) {
            case MiniTournament::FORMAT_SINGLE:
                if ($team1Count !== 1) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 1v1', 422);
                }
                break;

            case MiniTournament::FORMAT_DOUBLE:
            case MiniTournament::FORMAT_MENS_DOUBLES:
            case MiniTournament::FORMAT_WOMENS_DOUBLES:
            case MiniTournament::FORMAT_MIXED:
                if ($team1Count !== 2) {
                    return ResponseHelper::error('Kèo này chỉ cho phép tạo trận 2v2', 422);
                }
                break;

            default:
                if (!in_array($team1Count, [1, 2])) {
                    return ResponseHelper::error('Chỉ cho phép tạo trận 1v1 hoặc 2v2', 422);
                }
        }

        DB::beginTransaction();

        try {
            // ---- UPDATE TEAM 1 ----
            if (isset($data['team1'])) {
                $this->syncTeamMembers($match->team1, $data['team1']);
            }

            if (!empty($data['team1_name'])) {
                $match->team1->update(['name' => $data['team1_name']]);
            }

            // ---- UPDATE TEAM 2 ----
            if (isset($data['team2'])) {
                $this->syncTeamMembers($match->team2, $data['team2']);
            }

            if (!empty($data['team2_name'])) {
                $match->team2->update(['name' => $data['team2_name']]);
            }

            // ---- UPDATE MATCH INFO ----
            $match->update([
                'name' => $data['name'] ?? $match->name,
            ]);

            DB::commit();
            $team1UserIds = $match->team1->members()->pluck('user_id')->toArray();
            $team2UserIds = $match->team2->members()->pluck('user_id')->toArray();
            $allParticipantIds = array_unique(array_merge($team1UserIds, $team2UserIds));
            $users = User::whereIn('id', $allParticipantIds)->get();

            $users->each(function ($user) use ($match) {
                $user->notify(new MiniMatchUpdatedNotification($match));
            });

            MiniMatchUpdated::dispatch($match->loadFullRelations());

            return ResponseHelper::success(
                new MiniMatchResource($match->loadFullRelations()),
                'Cập nhật trận đấu thành công'
            );
        } catch (BusinessException $e) {
            return ResponseHelper::error($e->getMessage(), $e->getHttpCode());
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Có lỗi xảy ra khi cập nhật trận đấu', 500);
        }
    }

    protected function syncTeamMembers(MiniTeam $team, array $userIds)
    {
        $team->members()->delete();

        if (empty($userIds)) {
            return;
        }

        // Batch load is_guest flags for all users in one query
        $guestMap = DB::table('mini_participants')
            ->where('mini_tournament_id', $team->mini_tournament_id)
            ->whereIn('user_id', $userIds)
            ->pluck('is_guest', 'user_id');

        $records = [];
        $now = now();
        foreach ($userIds as $userId) {
            $records[] = [
                'mini_team_id' => $team->id,
                'user_id' => $userId,
                'is_guest' => $guestMap->get($userId, false),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        MiniTeamMember::insert($records);
    }

    /**
     * Thêm hoặc cập nhật kết quả 1 hiệp (set)
     */
    public function addSetResult(Request $request, $matchId)
    {
        $validated = $request->validate([
            'sets' => 'required|array|min:1',
            'sets.*.set_number' => 'required|integer|min:1',
            'sets.*.results' => 'required|array|size:2',
            'sets.*.results.*.team_id' => 'required|exists:mini_teams,id',
            'sets.*.results.*.score' => 'required|integer|min:0',
        ]);

        $match = MiniMatch::withFullRelations()->findOrFail($matchId);
        $tournament = $match->miniTournament->load('staff');

        // Kiểm tra quyền scoring
        if (!$tournament->hasScoringPermission(Auth::id())) {
            return ResponseHelper::error(
                'Người dùng không có quyền thêm kết quả trận đấu trong kèo đấu này',
                403
            );
        }

        // Kiểm tra trận đấu còn editable không
        if (!$match->isEditable()) {
            return ResponseHelper::error(
                'Trận đấu này đã được xác nhận kết quả',
                400
            );
        }

        // Validate team thuộc trận đấu
        $teamIds = [$match->team1_id, $match->team2_id];

        DB::transaction(function () use ($validated, $match, $teamIds) {

            foreach ($validated['sets'] as $set) {
                $inputResults = collect($set['results']);

                // Validate đủ 2 team
                if ($inputResults->count() !== 2) {
                    throw new \Exception('Cần cung cấp điểm số cho cả hai đội');
                }

                $teamA = $inputResults->firstWhere('team_id', $teamIds[0]);
                $teamB = $inputResults->firstWhere('team_id', $teamIds[1]);

                if (!$teamA || !$teamB) {
                    throw new \Exception(
                        'Team không hợp lệ hoặc không thuộc trận đấu này'
                    );
                }

                // Xóa set cũ (nếu update lại set)
                MiniMatchResult::where('mini_match_id', $match->id)
                    ->where('set_number', $set['set_number'])
                    ->delete();

                // Lưu kết quả set
                foreach ($set['results'] as $res) {
                    MiniMatchResult::create([
                        'mini_match_id' => $match->id,
                        'team_id' => $res['team_id'],
                        'score' => $res['score'],
                        'set_number' => $set['set_number'],
                        'won_set' => false, // sẽ tính khi confirm
                    ]);
                }
            }

            // Reset confirm and move to waiting_confirm, then activate next round if done
            $match->update([
                'team1_confirm' => false,
                'team2_confirm' => false,
                'status' => MiniMatch::STATUS_WAITING_CONFIRM,
            ]);

            $this->checkRoundActivation($match);
        });

        $match = MiniMatch::withFullRelations()->findOrFail($matchId);

        return ResponseHelper::success(
            new MiniMatchResource($match),
            'Lưu kết quả set thành công'
        );
    }

    /**
     * Xóa kết quả 1 hiệp
     */
    public function deleteSetResult($matchId, $setNumber)
    {
        $match = MiniMatch::with('miniTournament')->findOrFail($matchId);
        $tournament = $match->miniTournament->load('staff');
        // Kiểm tra quyền xoá kết quả (chỉ organizer được xoá)
        if (!$tournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Người dùng không có quyền xoá kết quả trận đấu trong kèo đấu này', 403);
        }

        if (!$match->isEditable()) {
            return ResponseHelper::error('Trận đấu đã được xác nhận không thể xoá kết quả', 400);
        }

        MiniMatchResult::where('mini_match_id', $match->id)
            ->where('set_number', $setNumber)
            ->delete();

        return ResponseHelper::success(null, 'Kết quả hiệp đã được xóa');
    }

    /**
     * Xóa trận đấu
     */
    public function destroy(Request $request)
    {
        $ids = $request->input('ids', []);

        if (!is_array($ids) || empty($ids)) {
            return ResponseHelper::error('Danh sách trận đấu không hợp lệ', 400);
        }

        $matches = MiniMatch::with('miniTournament')
            ->whereIn('id', $ids)
            ->get();

        if ($matches->isEmpty()) {
            return ResponseHelper::error('Không tìm thấy trận đấu nào', 404);
        }

        foreach ($matches as $match) {
            $tournament = $match->miniTournament->load('staff');
            if (!$tournament->hasOrganizer(Auth::id())) {
                return ResponseHelper::error('Người dùng không có quyền xoá trận đấu này', 403);
            }
            if (!$match->isEditable()) {
                return ResponseHelper::error("Không thể xóa trận đấu đã xác nhận kết quả", 400);
            }
        }

        DB::transaction(function () use ($ids) {
            MiniMatchResult::whereIn('mini_match_id', $ids)->delete();
            MiniMatch::whereIn('id', $ids)->delete();
        });

        return ResponseHelper::success(null, 'Xoá thành công');
    }

    /**
     * Tạo QR code để xác nhận kết quả trận đấu
     */

    public function generateQr($matchId)
    {
        $match = MiniMatch::with('miniTournament')->findOrFail($matchId);
        $miniTournament = $match->miniTournament;
        $userId = Auth::id();
        $isOrganizer = $miniTournament->hasOrganizer($userId);

        // Kèo chưa công bố: chỉ organizer mới tạo được QR
        if ($miniTournament->status === MiniTournament::STATUS_DRAFT && !$isOrganizer) {
            return ResponseHelper::error('Kèo đấu chưa được công bố', 403);
        }

        $url = url("/api/mini-matches/confirm-result/{$match->id}");

        return ResponseHelper::success(['qr_url' => $url], 'Thành công');
    }
    /**
     * Xác nhận kết quả trận đấu (thông qua QR code)
     */

    public function confirmResult($matchId)
    {
        $match = MiniMatch::withFullRelations()->findOrFail($matchId);
        $miniTournament = $match->miniTournament;

        // Kèo chưa công bố (status = 1 = STATUS_DRAFT): chỉ organizer mới thao tác được
        if ($miniTournament->status === MiniTournament::STATUS_DRAFT && !$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Kèo đấu chưa được công bố', 403);
        }

        if (!$match->isEditable()) {
            return ResponseHelper::error('Kết quả trận đấu đã được xác nhận trước đó', 400);
        }

        $tournament = $match->miniTournament;
        $sportId = $tournament->sport_id;
        $currentUserId = Auth::id();
        $isOrganizer = $tournament->hasOrganizer($currentUserId);

        // Kiểm tra quyền xác nhận
        $userTeam = null;
        if (!$isOrganizer) {
            if ($match->team1->members->contains('user_id', $currentUserId)) {
                $userTeam = $match->team1;
            } elseif ($match->team2->members->contains('user_id', $currentUserId)) {
                $userTeam = $match->team2;
            }

            if (!$userTeam) {
                return ResponseHelper::error('Bạn không có quyền xác nhận kết quả trận đấu này', 403);
            }
        }

        // ===================================
        // VALIDATE TOÀN BỘ KẾT QUẢ CÁC SET
        // ===================================
        $validationError = $this->validateAllSets($match, $tournament);
        if ($validationError) {
            return ResponseHelper::error($validationError, 400);
        }

        // Thực hiện confirm
        $result = DB::transaction(function () use ($match, $isOrganizer, $userTeam, $sportId) {
            if ($isOrganizer) {
                $match->team1_confirm = true;
                $match->team2_confirm = true;
            } else {
                if ($userTeam->id === $match->team1_id) $match->team1_confirm = true;
                if ($userTeam->id === $match->team2_id) $match->team2_confirm = true;
            }

            if ($match->team1_confirm && $match->team2_confirm) {
                $this->processMatchCompletion($match, $sportId);
                $this->checkRoundActivation($match);
            }

            $match->save();
            return $match;
        });

        $team1UserIds = $match->team1->members()->pluck('user_id')->toArray();
        $team2UserIds = $match->team2->members()->pluck('user_id')->toArray();
        $allParticipantIds = array_unique(array_merge($team1UserIds, $team2UserIds));
        $recipientIds = array_diff($allParticipantIds, [$currentUserId]);

        if (!empty($recipientIds)) {
            $users = User::whereIn('id', $recipientIds)->get();
            foreach ($users as $user) {
                $user->notify(new MiniMatchResultConfirmedNotification($match));
            }
        }

        $confirmedByOrganizer = $isOrganizer;
        $confirmedByPlayer    = !$isOrganizer && $userTeam;
        $actorName = auth()->user()->full_name;

        if ($confirmedByPlayer) {

            $opponentTeam = $userTeam->id === $match->team1_id
                ? $match->team2
                : $match->team1;

            $recipientIds = $opponentTeam->members()
                ->where('user_id', '!=', $currentUserId)
                ->pluck('user_id')
                ->toArray();

            $this->pushToUsers(
                $recipientIds,
                'Xác nhận kết quả kèo đấu',
                "{$actorName} đã xác nhận kết quả kèo đấu",
                [
                    'type' => 'MINI_MATCH_CONFIRM',
                    'match_id' => $match->id,
                    'by' => 'player',
                ]
            );
        }

        if ($confirmedByOrganizer) {

            $team1UserIds = $match->team1->members()
                ->where('user_id', '!=', $currentUserId)
                ->pluck('user_id')
                ->toArray();

            $team2UserIds = $match->team2->members()
                ->where('user_id', '!=', $currentUserId)
                ->pluck('user_id')
                ->toArray();

            $recipientIds = array_unique(array_merge($team1UserIds, $team2UserIds));

            $this->pushToUsers(
                $recipientIds,
                'Kết quả kèo đấu đã được xác nhận',
                'Ban tổ chức đã xác nhận kết quả kèo đấu',
                [
                    'type' => 'MINI_MATCH_CONFIRM',
                    'match_id' => $match->id,
                    'by' => 'organizer',
                ]
            );
        }

        return ResponseHelper::success(
            new MiniMatchResource($result->refresh()),
            'Xác nhận kết quả thành công'
        );
    }

    /**
     * Kiểm tra và tự động kích hoạt round tiếp theo khi round hiện tại đã hoàn tất.
     * Được gọi khi lưu kết quả (addSetResult) và khi xác nhận kết quả (confirmResult).
     */
    private function checkRoundActivation(MiniMatch $match): void
    {
        if ($match->round_number === null) {
            return;
        }

        $miniTournament = $match->miniTournament;
        if ($miniTournament->session_status !== MiniTournament::SESSION_STATUS_ONGOING) {
            return;
        }

        $format = $miniTournament->match_format;
        if ($format === MiniTournament::MATCH_FORMAT_STANDARD) {
            return;
        }

        $tournamentId = $miniTournament->id;
        $currentRound = $match->round_number;

        // Count non-bye matches per round
        $nonByeQuery = fn($status) => MiniMatch::where('mini_tournament_id', $tournamentId)
            ->whereNotNull('round_number')
            ->where('is_bye', false)
            ->where('status', $status);

        $totalNonBye = MiniMatch::where('mini_tournament_id', $tournamentId)
            ->whereNotNull('round_number')
            ->where('is_bye', false)
            ->count();

        // Check if ALL matches in the current round have results saved (waiting for confirm)
        $currentRoundTotal = MiniMatch::where('mini_tournament_id', $tournamentId)
            ->whereNotNull('round_number')
            ->where('is_bye', false)
            ->where('round_number', $currentRound)
            ->count();

        $currentRoundDoneOrWaiting = MiniMatch::where('mini_tournament_id', $tournamentId)
            ->whereNotNull('round_number')
            ->where('is_bye', false)
            ->where('round_number', $currentRound)
            ->whereIn('status', [MiniMatch::STATUS_WAITING_CONFIRM, MiniMatch::STATUS_COMPLETED])
            ->count();

        // Auto-activate next round when ALL non-bye matches are either waiting_confirm or completed
        if ($currentRoundTotal > 0 && $currentRoundTotal === $currentRoundDoneOrWaiting) {
            $nextRound = $currentRound + 1;
            $nextRoundExists = MiniMatch::where('mini_tournament_id', $tournamentId)
                ->whereNotNull('round_number')
                ->where('round_number', $nextRound)
                ->exists();

            if ($nextRoundExists) {
                MiniMatch::where('mini_tournament_id', $tournamentId)
                    ->whereNotNull('round_number')
                    ->where('round_number', $nextRound)
                    ->where('is_bye', false)
                    ->whereIn('status', [MiniMatch::STATUS_PENDING])
                    ->update(['status' => MiniMatch::STATUS_GOING_ON]);

                // Recursively trigger activation check for the newly activated round
                // (in case all its matches were already waiting_confirm and the
                // next-next round can be activated too)
                $nextMatch = MiniMatch::where('mini_tournament_id', $tournamentId)
                    ->where('round_number', $nextRound)
                    ->where('is_bye', false)
                    ->whereIn('status', [MiniMatch::STATUS_WAITING_CONFIRM])
                    ->first();

                if ($nextMatch) {
                    $this->checkRoundActivation($nextMatch);
                }
            }
        }

        // End session only when ALL non-bye matches are completed
        $totalCompleted = $nonByeQuery(MiniMatch::STATUS_COMPLETED)->count();
        if ($totalNonBye > 0 && $totalNonBye === $totalCompleted) {
            $miniTournament->update(['session_status' => MiniTournament::SESSION_STATUS_FINISHED]);
        }
    }

    private function validateAllSets($match, $tournament)
    {
        // Kiểm tra có kết quả không
        if ($match->results->isEmpty()) {
            return 'Trận đấu chưa có kết quả nào';
        }

        $team1Id = $match->team1_id;
        $team2Id = $match->team2_id;
        $allResults = $match->results->groupBy('set_number');

        $team1WonSets = 0;
        $team2WonSets = 0;
        $maxPoints = $tournament->max_points ?? 999;
        $configSetNumber = $tournament->set_number ?? 99;
        $pointsDiff = $tournament->points_difference ?? 2;
        $basePoints = $tournament->base_points ?? 11;
        $applyRule = $tournament->apply_rule ?? false;

        foreach ($allResults as $sNum => $setResults) {
            if ($setResults->count() !== 2) {
                return "Set {$sNum}: Thiếu điểm số của một trong hai đội";
            }

            $teamA = $setResults->firstWhere('team_id', $team1Id);
            $teamB = $setResults->firstWhere('team_id', $team2Id);

            if (!$teamA || !$teamB) {
                return "Set {$sNum}: Dữ liệu không hợp lệ";
            }

            $scoreA = (int) $teamA->score;
            $scoreB = (int) $teamB->score;

            if ($scoreA < 0 || $scoreB < 0) {
                return "Set {$sNum}: Điểm số không được âm";
            }

            if ($scoreA == $scoreB) {
                return "Set {$sNum}: Tỉ số hòa ({$scoreA}-{$scoreB}) không hợp lệ";
            }

            if ($scoreA > $maxPoints || $scoreB > $maxPoints) {
                return "Set {$sNum}: Điểm số vượt quá giới hạn ({$maxPoints})";
            }

            if ($scoreA > $scoreB) {
                $teamA->update(['won_set' => true]);
                $teamB->update(['won_set' => false]);
                $team1WonSets++;
            } else {
                $teamA->update(['won_set' => false]);
                $teamB->update(['won_set' => true]);
                $team2WonSets++;
            }
        }

        // Kiểm tra cách điểm sau khi xác định winner
        foreach ($allResults as $sNum => $setResults) {
            $teamA = $setResults->firstWhere('team_id', $team1Id);
            $teamB = $setResults->firstWhere('team_id', $team2Id);
            $scoreA = (int) $teamA->score;
            $scoreB = (int) $teamB->score;
            $winningScore = max($scoreA, $scoreB);
            $losingScore = min($scoreA, $scoreB);
            $actualDiff = $winningScore - $losingScore;

            if ($actualDiff < 1) {
                return "Set {$sNum}: Tỉ số hòa ({$scoreA}-{$scoreB}) không hợp lệ";
            }

            if ($applyRule) {
                if ($winningScore < $basePoints) {
                    return "Set {$sNum}: Điểm thắng phải đạt tối thiểu {$basePoints} điểm (hiện tại: {$winningScore})";
                }
                // Khi đạt max_points: được phép thắng cách 1 điểm (luật deuce)
                if ($maxPoints > 0 && $winningScore == $maxPoints) {
                    if ($actualDiff < 1) {
                        return "Set {$sNum}: Tỉ số hòa ({$scoreA}-{$scoreB}) không hợp lệ";
                    }
                    continue;
                }
                if ($actualDiff < $pointsDiff) {
                    return "Set {$sNum}: Thắng cách {$pointsDiff} điểm mới hợp lệ (hiện tại: {$actualDiff} điểm - {$scoreA}-{$scoreB})";
                }
            } else {
                // Không áp dụng luật: chỉ kiểm tra tỉ số bất thường
                if ($actualDiff < $pointsDiff) {
                    return "Set {$sNum}: Cách biệt {$actualDiff} điểm ({$scoreA}-{$scoreB}) nhỏ hơn quy định ({$pointsDiff} điểm)";
                }
            }
        }

        if ($team1WonSets === $team2WonSets) {
            return "Hòa số set ({$team1WonSets}-{$team2WonSets}), không xác định được đội thắng";
        }

        $totalSets = $allResults->count();
        if ($configSetNumber > 0 && $totalSets > $configSetNumber) {
            return "Số set vượt quá giới hạn ({$totalSets}/{$configSetNumber})";
        }

        return null;
    }


    /**
     * Logic xử lý khi trận đấu hoàn tất (Tính winner, Elo/VNDUPR)
     */
    private function processMatchCompletion($match, $sportId)
    {
        // A. Xác định đội thắng
        $wins = $match->results->where('won_set', true)->groupBy('team_id')->map->count();
        $maxWins = $wins->max();
        $winnerTeams = $wins->filter(fn($c) => $c === $maxWins)->keys();

        $team1WonSets = $wins->get($match->team1_id, 0);
        $team2WonSets = $wins->get($match->team2_id, 0);

        $match->team_win_id = $winnerTeams->count() === 1 ? $winnerTeams->first() : null;
        $match->team_1_score = $team1WonSets;
        $match->team_2_score = $team2WonSets;

        if ($match->team_win_id && $match->team_win_id === $match->team1_id) {
            $match->participant_win_id = $match->participant1_id;
        } elseif ($match->team_win_id && $match->team_win_id === $match->team2_id) {
            $match->participant_win_id = $match->participant2_id;
        } else {
            $match->participant_win_id = null;
        }

        $match->status = MiniMatch::STATUS_COMPLETED;
        $match->save();

        // Batch update result statuses
        $resultIds = $match->results->pluck('id');
        MiniMatchResult::whereIn('id', $resultIds)
            ->update(['status' => MiniMatchResult::STATUS_APPROVED]);

        // ===== ANCHOR MATCH LOGIC =====
        // FIX: dùng user_id thay vì member id (mini_team_member.id)
        $allMemberUserIds = $match->team1->members->pluck('user_id')
            ->merge($match->team2->members->pluck('user_id'))
            ->unique()
            ->values();

        $hasAnchorInMatch = User::whereIn('id', $allMemberUserIds)
            ->where(function ($q) {
                $q->where('is_anchor', true)
                    ->orWhere('total_matches_has_anchor', '>=', 10);
            })
            ->exists();

        if ($hasAnchorInMatch) {
            $nonAnchorIds = User::whereIn('id', $allMemberUserIds)
                ->where('is_anchor', false)
                ->where(function ($q) {
                    $q->whereNull('total_matches_has_anchor')
                        ->orWhere('total_matches_has_anchor', '<', 10);
                })
                ->pluck('id');

            if ($nonAnchorIds->isNotEmpty()) {
                DB::table('users')->whereIn('id', $nonAnchorIds)
                    ->increment('total_matches_has_anchor');
            }
        }

        // B. Tính toán S (Actual Score)
        $scores = $match->results->groupBy('team_id')->map->sum('score');
        $t1Score = $scores->get($match->team1_id, 0);
        $t2Score = $scores->get($match->team2_id, 0);
        $totalScore = $t1Score + $t2Score;

        $winnerTeamId = $match->team_win_id;
        $S_match_t1 = $winnerTeamId === $match->team1_id ? 1.0 : 0.0;
        $S_match_t2 = $winnerTeamId === $match->team2_id ? 1.0 : 0.0;
        $S_points_t1 = $totalScore > 0 ? $t1Score / $totalScore : 0;
        $S_points_t2 = $totalScore > 0 ? $t2Score / $totalScore : 0;
        $S_t1 = (0.5 * $S_match_t1) + (0.5 * $S_points_t1);
        $S_t2 = (0.5 * $S_match_t2) + (0.5 * $S_points_t2);

        // =====================================================
        // C. Batch load all data for rating calculation
        // =====================================================
        $userSportRecords = DB::table('user_sport')
            ->whereIn('user_id', $allMemberUserIds)
            ->where('sport_id', $sportId)
            ->get()
            ->keyBy('user_id');

        $userSportIds = $userSportRecords->pluck('id')->values();

        $scoreMap = DB::table('user_sport_scores')
            ->whereIn('user_sport_id', $userSportIds)
            ->where('score_type', 'vndupr_score')
            ->get()
            ->keyBy('user_sport_id');

        $historyMap = VnduprHistory::whereIn('user_id', $allMemberUserIds)
            ->orderByDesc('id')
            ->take(15 * $allMemberUserIds->count())
            ->get()
            ->groupBy('user_id')
            ->map(fn($col) => $col->sortBy('id')->values());

        // =====================================================
        // D. TÍNH RATING TRUNG BÌNH (E) — no N+1
        // =====================================================
        $calcAvgRating = function ($team) use ($userSportRecords, $scoreMap) {
            $total = 0;
            $count = 0;
            foreach ($team->members as $member) {
                // FIX: dùng user_id để lookup, không phải member->id (mini_team_member.id)
                $userSport = $userSportRecords->get($member->user_id);
                if ($userSport) {
                    $score = $scoreMap->get($userSport->id);
                    if ($score) {
                        $total += (float) $score->score_value;
                        $count++;
                    }
                }
            }
            return $count > 0 ? $total / $count : 0;
        };

        $R_t1 = $calcAvgRating($match->team1);
        $R_t2 = $calcAvgRating($match->team2);

        $E_t1 = 1 / (1 + pow(10, ($R_t2 - $R_t1)));
        $E_t2 = 1 / (1 + pow(10, ($R_t1 - $R_t2)));

        $teamData = [
            ['team' => $match->team1, 'S' => $S_t1, 'E' => $E_t1],
            ['team' => $match->team2, 'S' => $S_t2, 'E' => $E_t2],
        ];

        // =====================================================
        // E. Batch operations — no individual queries per user
        // =====================================================
        DB::table('users')->whereIn('id', $allMemberUserIds)->increment('total_matches');

        $vnduprHistoryRecords = [];
        $scoreUpserts = [];

        foreach ($teamData as $data) {
            foreach ($data['team']->members as $member) {
                $user = $member->user;
                $userSport = $userSportRecords->get($user->id);
                $scoreRecord = $userSport ? $scoreMap->get($userSport->id) : null;

                // Lấy rating cũ: ưu tiên vndupr_score, fallback sang history gần nhất
                $R_old = null;
                if ($scoreRecord) {
                    $R_old = (float) $scoreRecord->score_value;
                }
                // FIX: user chưa có vndupr_score → fallback từ history
                if ($R_old === null) {
                    $history = $historyMap->get($user->id, collect());
                    if ($history->isNotEmpty()) {
                        $R_old = (float) $history->last()->score_after;
                    }
                }
                $R_old = $R_old ?? 0;

                $history = $historyMap->get($user->id, collect());

                $K = 0.3;
                if ($user->is_anchor) {
                    $K = 0.1;
                } else {
                    if ($user->total_matches <= 10) {
                        $K = 1;
                    } elseif ($user->total_matches <= 50) {
                        $K = 0.6;
                    }
                }

                if ($history->count() >= 2) {
                    if (($history->first()->score_before - $history->last()->score_after) > 0.5) {
                        $K = 1;
                    }
                }

                $R_new = $R_old + (0.2 * $K * ($data['S'] - $data['E']));

                $vnduprHistoryRecords[] = [
                    'user_id' => $user->id,
                    'mini_match_id' => $match->id,
                    'score_before' => $R_old,
                    'score_after' => $R_new,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if ($userSport) {
                    $scoreUpserts[] = [
                        'user_sport_id' => $userSport->id,
                        'score_type' => 'vndupr_score',
                        'score_value' => $R_new,
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if (!empty($vnduprHistoryRecords)) {
            VnduprHistory::insert($vnduprHistoryRecords);
        }

        foreach ($scoreUpserts as $upsert) {
            DB::table('user_sport_scores')->updateOrInsert(
                ['user_sport_id' => $upsert['user_sport_id'], 'score_type' => $upsert['score_type']],
                ['score_value' => $upsert['score_value'], 'updated_at' => $upsert['updated_at']]
            );
        }
    }

    /**
     * Trình lọc trận đấu (theo địa điểm, môn thể thao, từ khóa, thời gian, vị trí)
     */
    public function listMiniMatch(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'sometimes',
            'lng' => 'sometimes',
            'radius' => 'sometimes|numeric|min:1',
            'minLat' => self::VALIDATION_RULE,
            'maxLat' => self::VALIDATION_RULE,
            'minLng' => self::VALIDATION_RULE,
            'maxLng' => self::VALIDATION_RULE,
            'per_page' => 'sometimes|integer|min:1|max:200',
            'is_map' => 'sometimes|boolean',
            'location_id' => 'sometimes|integer|exists:locations,id',
            'sport_id' => 'sometimes|integer|exists:sports,id',
            'keyword' => 'sometimes|string|max:255',
            'rating' => 'sometimes',
            'rating.*' => 'integer',
            'slot_status' => 'sometimes|array',
            'slot_status.*' => 'in:con_trong,da_day',
            'type' => 'sometimes|array',
            'type.*' => 'in:single,double',
            'fee' => 'sometimes|array',
            'fee.*' => 'in:free,paid',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
        ]);

        $query = MiniMatch::withFullRelations()->filter($validated);

        $hasFilter = collect([
            'sport_id',
            'location_id',
            'keyword',
            'lat',
            'lng',
            'radius',
            'type',
            'rating',
            'fee',
            'min_price',
            'max_price',
            'slot_status'
        ])->some(fn($key) => $request->filled($key));

        if (!$hasFilter && (!empty($validated['minLat']) || !empty($validated['maxLat']) || !empty($validated['minLng']) || !empty($validated['maxLng']))) {
            $query->inBounds(
                $validated['minLat'],
                $validated['maxLat'],
                $validated['minLng'],
                $validated['maxLng']
            );
        }

        if (!empty($validated['lat']) && !empty($validated['lng']) && !empty($validated['radius'])) {
            $query->nearBy($validated['lat'], $validated['lng'], $validated['radius']);
        }

        $isMap = filter_var($validated['is_map'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isMap) {
            $matches = $query->get();
            $paginationMeta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $matches->count(),
                'total' => $matches->count(),
            ];
        } else {
            $matches = $query->paginate($validated['per_page'] ?? MiniMatch::PER_PAGE);
            $paginationMeta = [
                'current_page' => $matches->currentPage(),
                'last_page' => $matches->lastPage(),
                'per_page' => $matches->perPage(),
                'total' => $matches->total(),
            ];
        }

        return ResponseHelper::success(
            ['matches' => (function ($matches) {
                MiniMatchResource::setExtraPlayerFlags([]);
                return MiniMatchResource::collection($matches);
            })($matches)],
            'Lấy danh sách Mini Match thành công',
            200,
            $paginationMeta
        );
    }

    private function generateMatchName(MiniTournament $miniTournament, ?int $roundNumber = null): string
    {
        $matchCount = MiniMatch::where('mini_tournament_id', $miniTournament->id)->count();
        $matchNumber = $matchCount + 1;

        if ($miniTournament->match_format !== MiniTournament::MATCH_FORMAT_STANDARD) {
            return 'Trận ' . $matchNumber . ' vòng ' . ($roundNumber ?? 1);
        }

        return 'Trận ' . $matchNumber . ' kèo ' . $miniTournament->name;
    }

    private function processSets(MiniMatch $match, array $setsData): void
    {
        // Lấy danh sách set_number được gửi lên
        $sentSetNumbers = collect($setsData)->pluck('set_number')->toArray();

        // Xóa các set không có trong request (tự động xóa set không được gửi lên)
        if (!empty($sentSetNumbers)) {
            MiniMatchResult::where('mini_match_id', $match->id)
                ->whereNotIn('set_number', $sentSetNumbers)
                ->delete();
        }

        // Lưu/cập nhật các set được gửi lên
        foreach ($setsData as $set) {
            // Xóa set cũ trước khi lưu mới
            MiniMatchResult::where('mini_match_id', $match->id)
                ->where('set_number', $set['set_number'])
                ->delete();

            // Lưu kết quả set mới
            foreach ($set['results'] as $res) {
                $teamId = $res['team'] === 'team1' ? $match->team1_id : $match->team2_id;
                MiniMatchResult::create([
                    'mini_match_id' => $match->id,
                    'team_id' => $teamId,
                    'score' => $res['score'],
                    'set_number' => $set['set_number'],
                    'won_set' => false,
                ]);
            }
        }

        $match->update([
            'team1_confirm' => false,
            'team2_confirm' => false,
        ]);
    }

    private function validateTeamFormat(MiniTournament $miniTournament, int $team1Count, int $team2Count)
    {
        if ($team1Count !== $team2Count) {
            return 'Số lượng người chơi của 2 đội phải bằng nhau';
        }

        switch ($miniTournament->format) {
            case MiniTournament::FORMAT_SINGLE:
                if ($team1Count !== 1) {
                    return 'Kèo này chỉ cho phép tạo trận 1v1';
                }
                break;
            case MiniTournament::FORMAT_DOUBLE:
            case MiniTournament::FORMAT_MENS_DOUBLES:
            case MiniTournament::FORMAT_WOMENS_DOUBLES:
            case MiniTournament::FORMAT_MIXED:
                if ($team1Count !== 2) {
                    return 'Kèo này chỉ cho phép tạo trận 2v2';
                }
                break;
            default:
                if (!in_array($team1Count, [1, 2])) {
                    return 'Chỉ cho phép tạo trận 1v1 hoặc 2v2';
                }
        }

        return null;
    }

    public function save(Request $request, $miniTournamentId)
    {
        $miniTournament = MiniTournament::findOrFail($miniTournamentId);

        if (!$miniTournament->hasOrganizer(Auth::id())) {
            return ResponseHelper::error('Bạn không có quyền thực hiện thao tác này', 403);
        }

        $data = $request->validate([
            'match_id' => 'nullable|exists:mini_matches,id',
            'team1' => 'required|array|min:1',
            'team2' => 'required|array|min:1',
            'team1.*' => 'exists:users,id',
            'team2.*' => 'exists:users,id',
            'team1_name' => 'nullable|string|max:255',
            'team2_name' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
            'round_number' => 'nullable|integer|min:1',
            'sets' => 'nullable|array',
            'sets.*.set_number' => 'required_with:sets|integer|min:1',
            'sets.*.results' => 'required_with:sets|array|size:2',
            'sets.*.results.*.team' => 'required_with:sets|in:team1,team2',
            'sets.*.results.*.score' => 'required_with:sets|integer|min:0',
        ]);

        $team1Count = count($data['team1']);
        $team2Count = count($data['team2']);

        $formatError = $this->validateTeamFormat($miniTournament, $team1Count, $team2Count);
        if ($formatError) {
            return ResponseHelper::error($formatError, 422);
        }

        $isUpdate = !empty($data['match_id']);

        DB::beginTransaction();

        try {
            if ($isUpdate) {
                $match = MiniMatch::withFullRelations()->findOrFail($data['match_id']);

                if ($match->mini_tournament_id !== $miniTournament->id) {
                    return ResponseHelper::error('Trận đấu không thuộc kèo đấu này', 422);
                }

                if (!$match->isEditable()) {
                    return ResponseHelper::error('Trận đấu đã được xác nhận, không thể sửa', 400);
                }

                if (isset($data['team1'])) {
                    $this->syncTeamMembers($match->team1, $data['team1']);
                }
                if (!empty($data['team1_name'])) {
                    $match->team1->update(['name' => $data['team1_name']]);
                }

                if (isset($data['team2'])) {
                    $this->syncTeamMembers($match->team2, $data['team2']);
                }
                if (!empty($data['team2_name'])) {
                    $match->team2->update(['name' => $data['team2_name']]);
                }

                $match->update([
                    'name' => $data['name'] ?? $match->name,
                ]);
            } else {
                $allUserIds = array_unique(array_merge($data['team1'], $data['team2']));

                $validParticipants = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                    ->where('is_confirmed', true)
                    ->whereIn('user_id', $allUserIds)
                    ->pluck('user_id')
                    ->toArray();

                if (count($validParticipants) !== count($allUserIds)) {
                    DB::rollBack();
                    return ResponseHelper::error('Có người chơi chưa tham gia hoặc chưa được duyệt trong kèo', 422);
                }

                $team1 = MiniTeam::create([
                    'mini_tournament_id' => $miniTournament->id,
                    'name' => $data['team1_name'] ?? $this->buildTeamName($data['team1'], $miniTournament->id),
                ]);
                foreach ($data['team1'] as $userId) {
                    $isGuest = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                        ->where('user_id', $userId)
                        ->value('is_guest') ?? false;
                    $team1->members()->create(['user_id' => $userId, 'is_guest' => $isGuest]);
                }

                $team2 = MiniTeam::create([
                    'mini_tournament_id' => $miniTournament->id,
                    'name' => $data['team2_name'] ?? $this->buildTeamName($data['team2'], $miniTournament->id),
                ]);
                foreach ($data['team2'] as $userId) {
                    $isGuest = MiniParticipant::where('mini_tournament_id', $miniTournament->id)
                        ->where('user_id', $userId)
                        ->value('is_guest') ?? false;
                    $team2->members()->create(['user_id' => $userId, 'is_guest' => $isGuest]);
                }

                $match = MiniMatch::create([
                    'mini_tournament_id' => $miniTournament->id,
                    'team1_id' => $team1->id,
                    'team2_id' => $team2->id,
                    'status' => MiniMatch::STATUS_PENDING,
                    'round_number' => $data['round_number'] ?? null,
                    'name' => $data['name'] ?? $this->generateMatchName($miniTournament, $data['round_number'] ?? null),
                ]);
            }

            // Lưu kết quả các set (nếu có)
            if (!empty($data['sets'])) {
                $this->processSets($match, $data['sets']);
                // Scores entered → move to waiting_confirm and require both teams to re-confirm
                $match->update([
                    'status' => MiniMatch::STATUS_WAITING_CONFIRM,
                    'team1_confirm' => false,
                    'team2_confirm' => false,
                ]);

                $this->checkRoundActivation($match);
            }

            DB::commit();

            $match = MiniMatch::withFullRelations()->findOrFail($match->id);

            if (!$isUpdate) {
                $allParticipantIds = array_unique(array_merge($data['team1'], $data['team2']));
                $users = User::whereIn('id', $allParticipantIds)->get();
                $users->each(function ($user) use ($match) {
                    $user->notify(new MiniMatchCreatedNotification($match));
                });
            }

            return ResponseHelper::success(
                new MiniMatchResource($match),
                $isUpdate ? 'Cập nhật trận đấu thành công' : 'Tạo trận đấu thành công',
                $isUpdate ? 200 : 201
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            return ResponseHelper::error('Có lỗi xảy ra khi tạo trận đấu', 500);
        }
    }

    private function pushToUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        foreach ($userIds as $userId) {
            SendPushJob::dispatch($userId, $title, $body, $data);
        }
    }

    private function buildTeamName(array $userIds, int $miniTournamentId): string
    {
        $participants = MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $names = [];
        foreach ($userIds as $userId) {
            $p = $participants->get($userId);
            if ($p && $p->is_guest) {
                $names[] = $p->guest_name ?? 'Khách';
            } else {
                $user = User::find($userId);
                $names[] = $user?->full_name ?? 'Người chơi';
            }
        }

        return implode(' - ', $names);
    }

    /**
     * Compute which participants play more matches than the minimum (extra matches).
     * Returns array: matchId => [participantUserId, ...]
     */
    private function computeExtraPlayerFlags(
        \Illuminate\Support\Collection $allMatches,
        \Illuminate\Support\Collection $participants,
        string $matchFormat
    ): array {
        // Map participant.id => user_id
        $participantToUser = [];
        foreach ($participants as $p) {
            if ($p->user_id) {
                $participantToUser[$p->id] = $p->user_id;
            }
        }

        // Count total matches per participant
        $matchCount = []; // participantId => count
        foreach ($allMatches as $match) {
            $playerIds = $this->getMatchParticipantIds($match);
            foreach ($playerIds as $pid) {
                if ($pid) {
                    $matchCount[$pid] = ($matchCount[$pid] ?? 0) + 1;
                }
            }
        }

        if (empty($matchCount)) {
            return [];
        }

        // Minimum match count = baseline (everyone should play at least this many)
        $minCount = min($matchCount);

        // For formats with imbalanced groups, use the minimum per group instead
        if (in_array($matchFormat, [
            \App\Models\MiniTournament::MATCH_FORMAT_MIXED_GENDER,
            \App\Models\MiniTournament::MATCH_FORMAT_RANK_PAIRING,
        ])) {
            $groupCounts = [];
            foreach ($participants as $p) {
                $group = $p->player_group;
                if (!isset($groupCounts[$group])) {
                    $groupCounts[$group] = [];
                }
                $groupCounts[$group][] = $matchCount[$p->id] ?? 0;
            }
            $minPerGroup = [];
            foreach ($groupCounts as $group => $counts) {
                $nonZero = array_filter($counts);
                if (!empty($nonZero)) {
                    $minPerGroup[$group] = min($nonZero);
                }
            }
        }

        // Flag matches where participants with > minCount are playing
        $result = []; // matchId => [userId, ...]
        foreach ($allMatches as $match) {
            if (!ByeResolver::isMatchRelevant($match)) {
                continue;
            }
            $extraUserIds = [];
            $playerIds = $this->getMatchParticipantIds($match);
            foreach ($playerIds as $pid) {
                if (!$pid) {
                    continue;
                }
                $threshold = isset($minPerGroup) ? ($minPerGroup[$participants->firstWhere('id', $pid)?->player_group] ?? $minCount) : $minCount;
                if (($matchCount[$pid] ?? 0) > $threshold) {
                    $uid = $participantToUser[$pid] ?? null;
                    if ($uid) {
                        $extraUserIds[$uid] = true;
                    }
                }
            }
            if (!empty($extraUserIds)) {
                $result[$match->id] = array_keys($extraUserIds);
            }
        }

        return $result;
    }

    private function getMatchParticipantIds(\App\Models\MiniMatch $match): array
    {
        $ids = [];

        // Single format: participant1_id / participant2_id
        if ($match->participant1_id) {
            $ids[] = $match->participant1_id;
        }
        if ($match->participant2_id) {
            $ids[] = $match->participant2_id;
        }

        // Double format: members of team1 / team2
        if ($match->relationLoaded('team1') && $match->team1) {
            foreach ($match->team1->members as $member) {
                $ids[] = $member->user_id;
            }
        }
        if ($match->relationLoaded('team2') && $match->team2) {
            foreach ($match->team2->members as $member) {
                $ids[] = $member->user_id;
            }
        }

        return $ids;
    }
}
