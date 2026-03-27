<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ListParticipantResource;
use App\Http\Resources\ParticipantResource;
use App\Jobs\SendPushJob;
use App\Models\Participant;
use App\Models\SuperAdminDraft;
use App\Models\Tournament;
use App\Models\TournamentStaff;
use App\Models\User;
use App\Notifications\TournamentInvitationNotification;
use App\Notifications\TournamentJoinConfirmedNotification;
use App\Notifications\TournamentJoinRequestNotification;
use App\Notifications\TournamentRemovedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ParticipantController extends Controller
{
    public function index(Request $request, $tournamentId)
    {
        $validated = $request->validate([
            'is_confirmed' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $query = Participant::with(['user'])
            ->where('tournament_id', $tournamentId);

        if (isset($validated['is_confirmed'])) {
            $query->where('is_confirmed', $validated['is_confirmed']);
        }

        $participants = $query->paginate($validated['per_page'] ?? Participant::PER_PAGE);

        $data = [
            'participants' => ParticipantResource::collection($participants),
        ];

        $meta = [
            'current_page'   => $participants->currentPage(),
            'last_page'      => $participants->lastPage(),
            'per_page'       => $participants->perPage(),
            'total'          => $participants->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách người tham gia thành công', 200, $meta);
    }

    /* Lấy danh sách người được mời tham gia giải đấu */
    public function listInvite(Request $request, $tournamentId){
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        $tournament = Tournament::findOrFail($tournamentId);

        $participantsIds = Participant::where('tournament_id', $tournamentId)->where('is_invite_by_organizer', true)->pluck('user_id');
        $tournamentStaffIds = $tournament->staff()->where('is_invite_by_organizer', true)->pluck('user_id');
        $createId = $tournament->created_by;

        $listIds = array_values(
            array_diff(
                $participantsIds->merge($tournamentStaffIds)->unique()->toArray(),
                [$createId]
            )
        );

        $participants = Participant::where('tournament_id', $tournamentId)
            ->whereIn('user_id', $listIds)
            ->get(['id', 'user_id', 'is_confirmed']);

        $participantMap = $participants->keyBy('user_id');
        $staffIdMap = array_flip($tournamentStaffIds->toArray());

        $listInviteQuery = User::whereIn('id', $listIds)
            ->select('users.*');

        $listInvite = $listInviteQuery->paginate($validated['per_page'] ?? Participant::PER_PAGE);

        $inviteList = $listInvite->getCollection()->map(function ($user) use ($participantMap, $staffIdMap) {
            $isConfirmed = 0;
            $participantId = null;
            if (isset($staffIdMap[$user->id])) {
                $isConfirmed = 1;
            } elseif ($participantMap->has($user->id)) {
                $isConfirmed = (int) $participantMap[$user->id]->is_confirmed;
                $participantId = $participantMap[$user->id]->id;
            }
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'avatar' => $user->avatar_url,
                'gender' => $user->gender,
                'gender_text' => $user->gender_text,
                'is_confirmed' => $isConfirmed,
                'visibility' => $user->visibility,
                'participant_id' => $participantId,
            ];
        });

        $data = [
            'invitations' => $inviteList,
        ];
        $meta = [
            'current_page' => $listInvite->currentPage(),
            'last_page' => $listInvite->lastPage(),
            'per_page' => $listInvite->perPage(),
            'total' => $listInvite->total(),
        ];
        return ResponseHelper::success($data, 'Lấy danh sách lời mời người tham gia thành công', 200, $meta);
    }

    public function join(Request $request, $tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);
        $user = Auth::user();
        if ($tournament->start_date < now()) {
            return ResponseHelper::error('Thời gian đăng ký đã kết thúc', 400);
        }
        $userSport = $user->sports()
            ->where('sport_id', $tournament->sport_id)
            ->first();
        if (!$userSport) {
            return ResponseHelper::error('Bạn không đạt yêu cầu tham gia giải đấu này', 400);
        }
        $score = $userSport->scores()
            ->where('score_type', 'vndupr_score')
            ->value('score_value');
        if (is_null($score)) {
            return ResponseHelper::error('Bạn chưa có điểm số cho môn thể thao này.', 422);
        }

        $min = $tournament->min_level;
        $max = $tournament->max_level;
        if ($min == 0 && $max == 0) {
        } else {
            if ($min > 0 && $score < $min) {
                return ResponseHelper::error('Điểm của bạn thấp hơn mức yêu cầu', 422);
            }
            if ($max > 0 && $score > $max) {
                return ResponseHelper::error('Điểm của bạn vượt quá mức cho phép', 422);
            }
        }
        $age = Carbon::parse($user->date_of_birth)->age;
        switch ($tournament->age_group) {
            case Tournament::ALL_AGES:
                break;
            case Tournament::YOUTH:
                if ($age >= 18) {
                    return ResponseHelper::error('Giải đấu chỉ dành cho người dưới 18 tuổi.', 422);
                }
                break;
            case Tournament::ADULT:
                if ($age < 18 || $age > 55) {
                    return ResponseHelper::error('Giải đấu chỉ dành cho người từ 18 đến 55 tuổi.', 422);
                }
                break;
            case Tournament::SENIOR:
                if ($age <= 55) {
                    return ResponseHelper::error('Giải đấu chỉ dành cho người trên 55 tuổi.', 422);
                }
                break;
        }
        if ($user->gender != null) {
            switch ($tournament->gender_policy) {
                case Tournament::MALE:
                    if ($user->gender != Tournament::MALE) {
                        return ResponseHelper::error('Giải này chỉ dành cho Nam.', 422);
                    }
                    break;

                case Tournament::FEMALE:
                    if ($user->gender != Tournament::FEMALE) {
                        return ResponseHelper::error('Giải này chỉ dành cho Nữ.', 422);
                    }
                    break;

                case Tournament::MIXED:
                    break;
            }
        }
        if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->player_per_team * $tournament->max_team)) {
            return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
        }

        $exists = Participant::where('tournament_id', $tournament->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Bạn đã tham gia giải này.'], 422);
        }

        $participant = Participant::create([
            'tournament_id' => $tournamentId,
            'user_id' => $user->id,
            'is_confirmed' => $tournament->auto_approve && !$tournament->is_private,
        ]);

        $organizers = $tournament->staff()->wherePivot('role', TournamentStaff::ROLE_ORGANIZER)->get();
        if(!$participant->is_confirmed){
            foreach ($organizers as $organizer) {
                if ($organizer->id === Auth::id()) {
                    continue;
                }

                // 📩 Notification DB
                $organizer->notify(
                    new TournamentJoinRequestNotification($participant)
                );

                // 🔔 PUSH
                $this->pushToUsers(
                    [$organizer->id],
                    'Yêu cầu tham gia giải đấu',
                    $user->full_name . ' yêu cầu tham gia giải "' . $tournament->name . '"',
                    [
                        'type' => 'TOURNAMENT_JOIN_REQUEST',
                        'tournament_id' => $tournament->id,
                        'participant_id' => $participant->id,
                    ]
                );
            }
        }else {
            foreach ($organizers as $organizer) {
                if ($organizer->id === Auth::id()) {
                    continue;
                }

                $this->pushToUsers(
                    [$organizer->id],
                    'Người tham gia mới',
                    $user->full_name . ' đã tham gia giải "' . $tournament->name . '"',
                    [
                        'type' => 'TOURNAMENT_JOINED',
                        'tournament_id' => $tournament->id,
                        'participant_id' => $participant->id,
                    ]
                );
            }
        }

        return ResponseHelper::success(new ParticipantResource($participant),'Tham gia giải đấu thành công',201);
    }

    public function confirm($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        $tournamentWithStaff = $participant->tournament->load('staff');
        $isOrganizer = $tournamentWithStaff->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xác nhận người tham gia này', 403);
        }
        if($participant->is_invite_by_organizer == 1) {
            return ResponseHelper::error('Chỉ người được mời mới có thể xác nhận yêu cầu này', 403);
        }
        if ($participant->is_confirmed) {
            return ResponseHelper::error('Người tham gia đã được xác nhận', 400);
        }
        if($participant->tournament->participants()->where('is_confirmed', true)->count() >= ($participant->tournament->max_team * $participant->tournament->player_per_team)){
            return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
        }
        $participant->is_confirmed = true;
        $participant->save();

        $participant->user->notify(new TournamentJoinConfirmedNotification($participant));

        return ResponseHelper::success(new ParticipantResource($participant), 'Xác nhận người tham gia thành công');
    }

    public function acceptInvite($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        if ($participant->user_id !== Auth::id()) {
            return ResponseHelper::error('Bạn không có quyền xác nhận lời mời tham gia này', 403);
        }
        if ($participant && $participant->is_confirmed) {
            return ResponseHelper::error('Lời mời tham gia đã được chấp nhận', 400);
        }
        $tournament = Tournament::findOrFail($participant->tournament_id);
        if ($tournament->start_date < now()) {
            return ResponseHelper::error('Thời gian tham gia đã kết thúc', 400);
        }
        $participantType = $tournament->participant;
        if ($participantType === 'user') {
            if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->player_per_team * $tournament->max_team)) {
                return ResponseHelper::error('Số lượng người chơi đã đạt giới hạn.', 422);
            }
        } else {
            if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->max_team * $tournament->player_per_team)) {
                return ResponseHelper::error('Số lượng người chơi đã đạt giới hạn.', 422);
            }
        }
        $participant->is_confirmed = true;
        $participant->save();

        return ResponseHelper::success(new ParticipantResource($participant), 'Chấp nhận lời mời tham gia thành công');
    }

    public function declineInvite($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        if ($participant->user_id !== Auth::id()) {
            return ResponseHelper::error('Bạn không có quyền từ chối lời mời tham gia này', 403);
        }
        if ($participant->is_confirmed) {
            return ResponseHelper::error('Người tham gia đã được xác nhận, không thể từ chối', 400);
        }
        $participant->delete();

        return ResponseHelper::success(null, 'Từ chối lời mời tham gia thành công', 200);
    }

    public function inviteUsers(Request $request, $tournamentId)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $tournament = Tournament::findOrFail($tournamentId);
        $organizer = Auth::user();
        $isSuperAdminDraft = SuperAdminDraft::where('user_id', $organizer->id)->exists();

        if (!$tournament->hasOrganizer($organizer->id)) {
            return ResponseHelper::error('Bạn không có quyền mời người chơi.', 403);
        }

        $participantType = $tournament->participant;
        if ($participantType === 'user') {
            if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->player_per_team * $tournament->max_team)) {
                return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
            }
        } else {
            if ($tournament->participants()->where('is_confirmed', true)->count() >= ($tournament->max_team * $tournament->player_per_team)) {
                return ResponseHelper::error('Số lượng người tham gia đã đạt giới hạn.', 422);
            }
        }

        $existingUserIds = Participant::where('tournament_id', $tournament->id)
            ->whereIn('user_id', $validated['user_ids'])
            ->pluck('user_id')
            ->toArray();

        $newUserIds = array_diff($validated['user_ids'], $existingUserIds);

        if (empty($newUserIds)) {
            return ResponseHelper::error('Tất cả người chơi đã được mời hoặc đã tham gia.', 422);
        }

        $organizerId = $organizer->id;

        $insertData = array_map(function ($invitedUserId) use ($tournament, $organizerId, $isSuperAdminDraft) {
            return [
                'tournament_id' => $tournament->id,
                'user_id' => $invitedUserId,
                'is_confirmed' => ($invitedUserId === $organizerId) || $isSuperAdminDraft,
                'created_at' => now(),
                'updated_at' => now(),
                'is_invite_by_organizer' => true,
            ];
        }, $newUserIds);

        $tournament->participants()->insert($insertData);
        $invitedUsers = User::whereIn('id', $newUserIds)->get();

        foreach ($invitedUsers as $user) {
            $user->notify(new TournamentInvitationNotification($tournament));
        }

        $participants = Participant::where('tournament_id', $tournament->id)
            ->whereIn('user_id', $newUserIds)
            ->get();

        return ResponseHelper::success(
            ParticipantResource::collection($participants),
            'Đã gửi lời mời thành công cho ' . count($newUserIds) . ' người chơi.'
        );
    }

    public function delete($participantId)
    {
        $participant = Participant::with('tournament')->findOrFail($participantId);
        $tournamentId = $participant->tournament_id;
        $tournamentWithStaff = $participant->tournament->load('staff');
        $isOrganizer = $tournamentWithStaff->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xoá người tham gia này', 403);
        }
        $userNeedRemove = $participant->user_id;
        $teamIdsInTournament = DB::table('teams')
        ->where('tournament_id', $tournamentId)
        ->pluck('id');
        DB::table('team_members')
        ->where('user_id', $userNeedRemove)
        ->whereIn('team_id', $teamIdsInTournament)
        ->delete();
        $participant->delete();

        $participant->user?->notify(new TournamentRemovedNotification($participant));

        return ResponseHelper::success(null, 'Xoá người tham gia thành công', 200);
    }

    public function deleteStaff($staffId)
    {
        $tournamentStaff = DB::table('tournament_staff')->where('id', $staffId)->first();
        if (!$tournamentStaff) {
            return ResponseHelper::error('Nhân viên không tồn tại', 404);
        }
        $tournament = Tournament::with('staff')->findOrFail($tournamentStaff->tournament_id);
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xoá nhân viên này', 403);
        }
        if( $tournamentStaff->role === 'organizer') {
            return ResponseHelper::error('Không thể xoá nhân viên với vai trò tổ chức', 400);
        }
        if ($tournamentStaff->user_id === Auth::id()) {
            return ResponseHelper::error('Bạn không thể tự xoá chính mình', 400);
        }
        DB::table('tournament_staff')->where('id', $staffId)->delete();

        return ResponseHelper::success(null, 'Xoá nhân viên thành công', 200);
    }

    public function getParticipantsNonTeam(Request $request, $tournamentId)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);
        $user_ids_in_teams = DB::table('team_members')
            ->join('teams', 'team_members.team_id', '=', 'teams.id')
            ->where('teams.tournament_id', $tournamentId)
            ->pluck('team_members.user_id')
            ->unique();

        $nonTeamParticipants = Participant::withFullRelations()
            ->where('tournament_id', $tournamentId)
            ->where('is_confirmed', 1)
            ->whereNotIn('user_id', $user_ids_in_teams)
            ->paginate($validated['per_page'] ?? Participant::PER_PAGE);

        $data = [
            'participants' => ListParticipantResource::collection($nonTeamParticipants->items()),
        ];

        $meta = [
            'current_page' => $nonTeamParticipants->currentPage(),
            'last_page' => $nonTeamParticipants->lastPage(),
            'total' => $nonTeamParticipants->total(),
            'per_page' => $nonTeamParticipants->perPage(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách người chơi thành công', 200, $meta);
    }
    /**
     * Lọc theo độ tuổi
     */
    private function filterByAge($query, $ageGroup)
    {
        $today = Carbon::today();

        switch ($ageGroup) {
            case Tournament::YOUTH: // Dưới 18
                $minDate = $today->copy()->subYears(18);
                $query->where('date_of_birth', '>', $minDate);
                break;

            case Tournament::ADULT: // 18-55
                $minDate = $today->copy()->subYears(55);
                $maxDate = $today->copy()->subYears(18);
                $query->whereBetween('date_of_birth', [$minDate, $maxDate]);
                break;

            case Tournament::SENIOR: // Trên 55
                $maxDate = $today->copy()->subYears(55);
                $query->where('date_of_birth', '<', $maxDate);
                break;

            case Tournament::ALL_AGES:
            default:
                // Không lọc
                break;
        }

        return $query;
    }

    /**
     * Lọc theo giới tính
     */
    private function filterByGender($query, $genderPolicy)
    {
        if ($genderPolicy === Tournament::MALE) {
            $query->where('gender', Tournament::MALE);
        } elseif ($genderPolicy === Tournament::FEMALE) {
            $query->where('gender', Tournament::FEMALE);
        }
        // MIXED: không lọc

        return $query;
    }


    public function getCandidates(Request $request, $tournamentId)
    {
        $tournament = Tournament::withFullRelations()->findOrFail($tournamentId);
        $user = Auth::user();

        $validated = $request->validate([
            'scope' => 'required|in:club,friends,area,all',
            'club_id' => 'required_if:scope,club|exists:clubs,id',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:200',
            'lat' => 'required_if:scope,area|numeric',
            'lng' => 'required_if:scope,area|numeric',
            'radius' => 'required_if:scope,area|numeric|min:0.1|max:200',
        ]);

        $perPage = $validated['per_page'] ?? 20;
        $scope = $validated['scope'];

        // 🧮 Tính mid level cho sorting
        $midLevel = null;
        if ($tournament->min_level !== null && $tournament->max_level !== null) {
            $midLevel = (float)(($tournament->min_level + $tournament->max_level) / 2);
        }

        // 🎯 Tùy theo phạm vi (scope)
        switch ($scope) {
            case 'club':
                $query = User::withFullRelations()
                    ->whereHas('clubs', fn($q) => $q->where('clubs.id', $validated['club_id']));
                break;

            case 'friends':
                $query = User::withFullRelations()
                    ->whereExists(function ($q) use ($user) {
                        $q->select(DB::raw(1))
                            ->from('follows as f1')
                            ->whereColumn('f1.followable_id', 'users.id')
                            ->where('f1.user_id', $user->id)
                            ->where('f1.followable_type', User::class);
                    })
                    ->whereExists(function ($q) use ($user) {
                        $q->select(DB::raw(1))
                            ->from('follows as f2')
                            ->whereColumn('f2.user_id', 'users.id')
                            ->where('f2.followable_id', $user->id)
                            ->where('f2.followable_type', User::class);
                    });
                break;

            case 'area':
                $lat = $validated['lat'];
                $lng = $validated['lng'];
                $radius = $validated['radius'];

                $haversine = "(6371 * acos(
                        cos(radians(?))
                        * cos(radians(users.latitude))
                        * cos(radians(users.longitude) - radians(?))
                        + sin(radians(?))
                        * sin(radians(users.latitude))
                    ))";

                $query = User::withFullRelations()
                    ->whereNotNull('users.latitude')
                    ->whereNotNull('users.longitude')
                    ->whereRaw("$haversine <= ?", [
                        $lat,
                        $lng,
                        $lat,
                        $radius
                    ])
                    ->orderByRaw("$haversine asc", [
                        $lat,
                        $lng,
                        $lat
                    ]);
                break;
            case 'all':
                $query = User::withFullRelations();
                break;
        }

        // 🔐 Visibility filter (trừ scope 'all')
        if ($scope !== 'all') {
            $query->whereIn('users.visibility', [
                User::VISIBILITY_PUBLIC,
                User::VISIBILITY_FRIEND_ONLY
            ]);
        } else {
            $query->whereIn('users.visibility', [User::VISIBILITY_PUBLIC]);
        }

        // ⚽ Filter theo setting của giải (chỉ áp dụng khi scope !== 'all')
        if ($scope !== 'all') {
            // 1. Có môn thể thao phù hợp
            $query->whereHas('sports', function ($q) use ($tournament) {
                $q->where('sport_id', $tournament->sport_id);
            });

            // 2. Tuổi
            $query->tap(fn ($q) => $this->filterByAge($q, $tournament->age_group));

            // 3. Giới tính
            $query->tap(fn ($q) => $this->filterByGender($q, $tournament->gender_policy));
        }

        // 4. Loại trừ người có ĐỒNG THỜI trong cả participant VÀ staff (áp dụng cho tất cả scope)
        $participantUserIds = $tournament->participants->pluck('user_id')->toArray();
        $staffUserIds = $tournament->tournamentStaffs->pluck('user_id')->toArray();

        // Lấy những user có trong CẢ 2 mảng (giao của 2 tập hợp)
        $excludedUserIds = array_intersect($participantUserIds, $staffUserIds);

        // Loại trừ những user có trong cả 2 bảng
        $query->whereNotIn('users.id', $excludedUserIds);

        // 5. Join để lấy level + filter level (chỉ khi scope !== 'all')
        if ($scope !== 'all') {
            $query->leftJoin('user_sport', function ($join) use ($tournament) {
                $join->on('users.id', '=', 'user_sport.user_id')
                    ->where('user_sport.sport_id', $tournament->sport_id);
            })
            ->leftJoin('user_sport_scores', function ($join) {
                $join->on('user_sport.id', '=', 'user_sport_scores.user_sport_id')
                    ->where('user_sport_scores.score_type', 'vndupr_score');
            });

            // 6. Filter level
            $query->when(
                $tournament->min_level !== null,
                fn ($q) => $q->where('user_sport_scores.score_value', '>=', $tournament->min_level)
            )
            ->when(
                $tournament->max_level !== null,
                fn ($q) => $q->where('user_sport_scores.score_value', '<=', $tournament->max_level)
            );
        }

        // 7. Select + Sort (chỉ khi scope !== 'all')
        if ($scope !== 'all') {
            $query->select('users.*')
                ->selectRaw('user_sport_scores.score_value as level')
                ->when(
                    $midLevel !== null,
                    fn ($q) => $q->selectRaw(
                        'ABS(user_sport_scores.score_value - ?) as level_diff',
                        [$midLevel]
                    )
                )
                ->selectRaw(
                    'CASE WHEN users.location_id = ? THEN 1 ELSE 0 END as same_location',
                    [$tournament->location_id]
                )
                ->orderByDesc('same_location')
                ->when($midLevel !== null, fn ($q) => $q->orderBy('level_diff'));
        } else {
            $query->select('users.*');
        }

        // 🔍 Tìm kiếm tên người dùng (áp dụng cho tất cả scope)
        if (!empty($validated['search'])) {
            $query->where('users.full_name', 'like', '%' . $validated['search'] . '%');
        }

        // 🧮 Phân trang
        $paginated = $query->paginate($perPage);
        $candidates = $paginated->getCollection()->map(function ($u) use ($user, $excludedUserIds) {

            return [
                'id' => $u->id,
                'name' => $u->full_name,
                'visibility' => $u->visibility,
                'age_group' => $u->age_group,
                'avatar_url' => $u->avatar_url,
                'thumbnail' => $u->thumbnail,
                'gender' => $u->gender,
                'gender_text' => $u->gender_text,
                'play_times' => [],

                'sports' => $u->sports->map(function ($userSport) {
                    $scores = $userSport->scores
                        ->pluck('score_value', 'score_type')
                        ->toArray();

                    return [
                        'sport_id' => $userSport->sport_id,
                        'sport_icon' => $userSport->sport?->icon,
                        'sport_name' => $userSport->sport?->name,
                        'scores' => [
                            'personal_score' => $scores['personal_score'] ?? '0.000',
                            'dupr_score'     => $scores['dupr_score'] ?? '0.000',
                            'vndupr_score'   => $scores['vndupr_score'] ?? '0.000',
                        ],
                        'total_matches'     => $userSport->total_matches ?? 0,
                        'total_tournaments' => $userSport->total_tournaments ?? 0,
                        'total_prizes'      => $userSport->total_prizes ?? 0,
                    ];
                }),
                'is_friend' => $user->isFriendWith($u),
                'is_participant' => in_array($u->id, $excludedUserIds),
            ];
        });

        return ResponseHelper::success([
            'result' => $candidates,
        ], 'Danh sách ứng viên', 200, [
            'current_page' => $paginated->currentPage(),
            'last_page'    => $paginated->lastPage(),
            'per_page'     => $paginated->perPage(),
            'total'        => $paginated->total(),
        ]);
    }

    private function pushToUsers(array $userIds, string $title, string $body, array $data = [])
    {
        foreach ($userIds as $userId) {
            SendPushJob::dispatch($userId, $title, $body, $data);
        }
    }
}
