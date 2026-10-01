<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ListTeamResource;
use App\Http\Resources\TeamResource;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\Participant;
use App\Models\TeamMember;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\ImageOptimizationService;
use App\Support\TournamentTeamMemberHydrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    protected $imageService;

    public function __construct(ImageOptimizationService $imageService)
    {
        $this->imageService = $imageService;
    }

    /**
     * Eager-load relations cần thiết cho team members trước hydrate.
     */
    private function withMembersRelations(): array
    {
        return ['members.sports.scores', 'members.sports.sport', 'guestMembers.participant.user', 'guestMembers.participant.guarantor'];
    }

    /**
     * Thêm nhiều thành viên vào đội cùng lúc.
     */
    private function addMembersToTeam(Team $team, Tournament $tournament, array $participantIds): array
    {
        $maxPlayers = $tournament->player_per_team;
        $currentCount = $team->members()->count();
        $existingParticipantIds = $team->members()->pluck('participant_id')->filter()->toArray();

        $validParticipantIds = [];
        $errors = [];

        // Validate và lọc participants hợp lệ
        $participants = Participant::whereIn('id', $participantIds)
            ->where('tournament_id', $tournament->id)
            ->where('is_confirmed', true)
            ->whereNotIn('id', $existingParticipantIds)
            ->get()
            ->keyBy('id');

        foreach ($participantIds as $pid) {
            if (!isset($participants[$pid])) {
                if (!in_array($pid, $validParticipantIds)) {
                    $errors[] = "Thành viên ID {$pid} không hợp lệ hoặc đã nằm trong đội khác";
                }
                continue;
            }

            // Kiểm tra số lượng tối đa
            if ($maxPlayers && ($currentCount + count($validParticipantIds)) >= $maxPlayers) {
                $errors[] = "Đội đã đạt số lượng tối đa {$maxPlayers} thành viên";
                break;
            }

            $validParticipantIds[] = $pid;
        }

        if (!empty($validParticipantIds)) {
            // Insert qua TeamMember thay vì attach() của belongsToMany:
            // - user thật  → ghi user_id
            // - guest       → user_id = null, ghi participant_id
            // Tránh key '' (user_id NULL của guest) bị Eloquent ép thành 0 / đè row.
            foreach ($validParticipantIds as $pid) {
                TeamMember::firstOrCreate(
                    [
                        'team_id'       => $team->id,
                        'participant_id' => $pid,
                    ],
                    [
                        'user_id' => $participants[$pid]->user_id,
                    ]
                );
            }
        }

        return $errors;
    }

    /**
     * Thay đổi toàn bộ thành viên của đội (dùng cho update).
     */
    private function syncMembersToTeam(Team $team, Tournament $tournament, array $participantIds): array
    {
        $maxPlayers = $tournament->player_per_team;
        $errors = [];

        // Kiểm tra nếu giải đấu đã có trận đấu với kết quả
        if ($tournament->hasMatchesWithResults()) {
            $errors[] = 'Không thể thay đổi thành viên đội. Giải đấu đã có trận đấu đang diễn ra/hoàn thành';
            return $errors;
        }

        // Kiểm tra nếu team đã có trận đấu với kết quả
        $matches = Matches::where('home_team_id', $team->id)
            ->orWhere('away_team_id', $team->id)
            ->get();
        if ($matches->isNotEmpty() && MatchResult::whereIn('match_id', $matches->pluck('id'))->exists()) {
            $errors[] = 'Không thể thay đổi thành viên đội. Đội đã có trận đấu đang diễn ra/hoàn thành';
            return $errors;
        }

        // Validate participants
        $participants = Participant::whereIn('id', $participantIds)
            ->where('tournament_id', $tournament->id)
            ->where('is_confirmed', true)
            ->get()
            ->keyBy('id');

        // Kiểm tra số lượng
        if ($maxPlayers && count($participantIds) > $maxPlayers) {
            $errors[] = "Số lượng thành viên vượt quá tối đa {$maxPlayers}";
            return $errors;
        }

        foreach ($participantIds as $pid) {
            if (!isset($participants[$pid])) {
                $errors[] = "Thành viên ID {$pid} không hợp lệ hoặc chưa được xác nhận tham gia giải đấu";
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        // Xoá toàn bộ bản ghi trong pivot table team_members (cả user thật và guest)
        TeamMember::where('team_id', $team->id)->delete();

        // Thêm members mới: phân biệt user thật vs guest qua TeamMember trực tiếp
        foreach ($participantIds as $pid) {
            if (!isset($participants[$pid])) {
                continue;
            }
            TeamMember::create([
                'team_id'        => $team->id,
                'user_id'        => $participants[$pid]->user_id,
                'participant_id' => $pid,
            ]);
        }

        return $errors;
    }

    public function listTeams(Request $request, $tournamentId)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        $perPage = $validated['per_page'] ?? Team::PER_PAGE;

        $teams = Team::where('tournament_id', $tournamentId)
            ->with($this->withMembersRelations())
            ->paginate($perPage);

        TournamentTeamMemberHydrator::hydrateCollection($teams->getCollection(), (int) $tournamentId);

        $data = [
            'teams' => ListTeamResource::collection($teams),
        ];

        $meta = [
            'current_page' => $teams->currentPage(),
            'last_page'    => $teams->lastPage(),
            'per_page'     => $teams->perPage(),
            'total'        => $teams->total(),
        ];

        return ResponseHelper::success($data, 'Lấy danh sách đội thành công', 200, $meta);
    }

    public function createTeam(Request $request, $tournamentId)
    {
        $validated = $request->validate(
            [
                'name' => 'required|string|max:255',
                'avatar' => 'nullable|image|max:2048',
                'participant_ids' => 'nullable|array',
                'participant_ids.*' => 'integer|exists:participants,id',
            ],
            [
                'name.required' => 'Vui lòng nhập tên đội',
                'name.string' => 'Tên đội phải là chuỗi ký tự',
                'name.max' => 'Tên đội không được vượt quá 255 ký tự',
                'avatar.image' => 'Ảnh đại diện phải là một tệp hình ảnh',
                'avatar.max' => 'Ảnh đại diện không được vượt quá 2MB',
                'participant_ids.array' => 'Danh sách thành viên phải là một mảng',
                'participant_ids.*.integer' => 'ID thành viên phải là số nguyên',
                'participant_ids.*.exists' => 'Thành viên không tồn tại trong giải đấu',
            ]
        );

        $tournament = Tournament::with('staff')->findOrFail($tournamentId);
        if ($tournament->max_team && $tournament->teams()->count() >= $tournament->max_team) {
            return ResponseHelper::error('Đã đạt số lượng đội tối đa cho giải đấu', 400);
        }
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền tạo đội', 400);
        }

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $this->imageService->optimize(
                $validated['avatar'],
                'team_avatar'
            );
        }

        $team = Team::create([
            'name' => $validated['name'],
            'tournament_id' => $tournament->id,
            'avatar' => $avatarPath,
        ]);

        // Thêm thành viên vào đội nếu có participant_ids
        $participantIds = $validated['participant_ids'] ?? [];
        if (!empty($participantIds)) {
            $this->addMembersToTeam($team, $tournament, $participantIds);
        }

        $team->load($this->withMembersRelations());
        TournamentTeamMemberHydrator::hydrateTeam($team, $tournament->id);

        return ResponseHelper::success(new TeamResource($team), 'Tạo đội thành công');
    }

    public function updateTeam(Request $request)
    {
        $validated = $request->validate(
            [
                'name' => 'sometimes|required|string|max:255',
                'avatar' => 'nullable',
                'participant_ids' => 'nullable|array',
                'participant_ids.*' => 'integer|exists:participants,id',
            ],
            [
                'name.required' => 'Vui lòng nhập tên đội',
                'name.string' => 'Tên đội phải là chuỗi ký tự',
                'name.max' => 'Tên đội không được vượt quá 255 ký tự',
                'participant_ids.array' => 'Danh sách thành viên phải là một mảng',
                'participant_ids.*.integer' => 'ID thành viên phải là số nguyên',
                'participant_ids.*.exists' => 'Thành viên không tồn tại trong giải đấu',
            ]
        );

        $team = Team::findOrFail($request->route('teamId'));
        $tournament = $team->tournament;
        if (!$tournament->relationLoaded('staff')) {
            $tournament->load('staff');
        }
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thay đổi vào đội', 400);
        }
        if (isset($validated['name'])) {
            $team->name = $validated['name'];
        }

        if ($request->hasFile('avatar')) {
            $this->imageService->deleteOldImage($team->avatar);

            $path = $this->imageService->optimize(
                $request->file('avatar'),
                'team_avatar'
            );

            $team->avatar = $path;

        } elseif ($request->has('avatar')) {
        } else {
            $this->imageService->deleteOldImage($team->avatar);
            $team->avatar = null;
        }

        $team->save();

        // Cập nhật thành viên nếu có participant_ids (thay thế toàn bộ)
        if (isset($validated['participant_ids'])) {
            $errors = $this->syncMembersToTeam($team, $tournament, $validated['participant_ids']);
            if (!empty($errors)) {
                // Load lại team sau khi sync members
                $team->load($this->withMembersRelations());
                TournamentTeamMemberHydrator::hydrateTeam($team, $team->tournament_id);

                return ResponseHelper::success([
                    'team' => new TeamResource($team),
                    'warnings' => $errors,
                ], 'Cập nhật đội thành công với một số cảnh báo');
            }
        }

        $team->load($this->withMembersRelations());
        TournamentTeamMemberHydrator::hydrateTeam($team, $team->tournament_id);

        return ResponseHelper::success(
            new TeamResource($team),
            'Cập nhật đội thành công'
        );
    }

    public function addMember(Request $request, $teamId)
    {
        $request->validate(
            [
                'user_id'           => 'sometimes|nullable|integer',
                'participant_id'    => 'sometimes|nullable|integer',
                'virtual_member_id' => 'sometimes|nullable|integer',
            ],
            [
                'user_id.integer'           => 'user_id phải là số nguyên',
                'participant_id.integer'    => 'participant_id phải là số nguyên',
                'virtual_member_id.integer' => 'virtual_member_id phải là số nguyên',
            ]
        );

        $team = Team::findOrFail($teamId);
        $tournament = $team->tournament;
        if (!$tournament->relationLoaded('staff')) {
            $tournament->load('staff');
        }
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thêm người vào đội', 400);
        }

        // Resolve participant: real user / guest đã có / VM (tạo guest mới giống flow add guest)
        $participant = $this->resolveParticipant($request, $tournament);
        if (!$participant) {
            return ResponseHelper::error('Không tìm thấy người chơi hợp lệ trong giải đấu', 422);
        }

        // Reject nếu participant đã ở team khác cùng tournament
        $existingElsewhere = \App\Models\TeamMember::where('participant_id', $participant->id)
            ->whereHas('team', fn ($q) => $q->where('tournament_id', $tournament->id)->where('id', '!=', $team->id))
            ->exists();
        if ($existingElsewhere) {
            return ResponseHelper::error('Người chơi đã nằm trong đội khác của giải đấu', 422);
        }

        $currentCount = $team->members()->count();
        $maxPlayers = $tournament->player_per_team;
        if ($maxPlayers && $currentCount >= $maxPlayers) {
            return ResponseHelper::error("Đội đã đủ số lượng tối đa {$maxPlayers} thành viên", 422);
        }

        $alreadyInTeam = \App\Models\TeamMember::where('team_id', $team->id)
            ->where(function ($q) use ($participant) {
                if ($participant->user_id) {
                    $q->where('user_id', $participant->user_id);
                }
                $q->orWhere('participant_id', $participant->id);
            })
            ->exists();
        if ($alreadyInTeam) {
            return ResponseHelper::error('Người chơi đã nằm trong đội', 422);
        }

        \App\Models\TeamMember::create([
            'team_id'        => $team->id,
            'user_id'        => $participant->user_id,
            'participant_id' => $participant->id,
        ]);

        $team->load($this->withMembersRelations());
        TournamentTeamMemberHydrator::hydrateTeam($team, $tournament->id);

        return ResponseHelper::success(new TeamResource($team), 'Thêm thành viên vào đội thành công');
    }

    /**
     * Resolve Participant từ 1 trong 3 input.
     * - user_id: real user đã confirmed trong tournament
     * - participant_id: guest đã tồn tại (confirmed)
     * - virtual_member_id: ClubVirtualMember → tạo Participant guest mới (giống flow guest)
     *
     * Trả về Participant hoặc null nếu không resolve được.
     */
    private function resolveParticipant(Request $request, Tournament $tournament): ?Participant
    {
        if ($request->filled('user_id')) {
            return Participant::where('user_id', $request->user_id)
                ->where('tournament_id', $tournament->id)
                ->where('is_confirmed', true)
                ->first();
        }

        if ($request->filled('participant_id')) {
            return Participant::where('id', $request->participant_id)
                ->where('tournament_id', $tournament->id)
                ->where('is_guest', true)
                ->where('is_confirmed', true)
                ->first();
        }

        if ($request->filled('virtual_member_id')) {
            if (!$tournament->club_id) return null;
            $vm = \App\Models\Club\ClubVirtualMember::where('id', $request->virtual_member_id)
                ->where('club_id', $tournament->club_id)
                ->first();
            if (!$vm) return null;

            // Tái sử dụng guest participant đã tồn tại trùng tên (VM ↔ guest 1-1)
            $existing = Participant::where('tournament_id', $tournament->id)
                ->where('is_guest', true)
                ->where('guest_name', $vm->name)
                ->first();
            if ($existing) {
                if (!$existing->is_confirmed) {
                    $existing->is_confirmed = true;
                    $existing->save();
                }
                return $existing;
            }

            // Tạo mới — giống flow add guest participant
            return Participant::create([
                'tournament_id'            => $tournament->id,
                'user_id'                  => null,
                'is_guest'                 => true,
                'is_confirmed'             => true,
                'guest_name'               => $vm->name,
                'guest_avatar'             => $vm->avatar_url,
                'guarantor_user_id'        => Auth::id(),
                'is_pending_confirmation'  => false,
            ]);
        }

        return null;
    }

    public function autoAssignTeams($tournamentId)
    {
        $tournament = Tournament::with('staff')->findOrFail($tournamentId);
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền tự động chia đội', 400);
        }

        if ($tournament->hasMatchesWithResults()) {
            return ResponseHelper::error(
                'Không thể chia lại đội. Đội đã có trận đấu đang diễn ra/hoàn thành',
                400
            );
        }

        $participants = Participant::where('tournament_id', $tournamentId)
            ->where('is_confirmed', true)
            ->get()
            ->shuffle();

        if ($participants->isEmpty()) {
            return ResponseHelper::error('Cần ít nhất 1 người chơi để tiến hành phân chia đội', 400);
        }

        // Kiểm tra đủ người để tạo ít nhất 1 team hoàn chỉnh
        $maxPlayers = $tournament->player_per_team ?? 1;
        if ($maxPlayers > 0 && $participants->count() < $maxPlayers) {
            return ResponseHelper::error("Cần ít nhất {$maxPlayers} người chơi để tạo 1 đội.", 400);
        }
        // if ($tournament->tournamentTypes()->exists()) {
        //     return ResponseHelper::error('Không thể tự động chia lại đội khi giải đấu đã có loại hình thi đấu', 400);
        // }

        $maxPlayers = $tournament->player_per_team ?? 1;
        $maxTeams = $tournament->max_team ?? 1;

        // Xóa các team cũ trước khi phân lại
        Team::where('tournament_id', $tournamentId)->delete();

        // Tạo đủ số team theo max_team
        $teams = [];
        for ($i = 0; $i < $maxTeams; $i++) {
            $teams[] = Team::create([
                'name' => 'Đội số ' . ($i + 1),
                'tournament_id' => $tournamentId,
            ]);
        }

        // Gán người lần lượt từ Đội 1 → Đội 2 → ...
        $teamIndex = 0;
        $teamMemberCount = array_fill(0, $maxTeams, 0);

        foreach ($participants as $participant) {
            if ($participant->is_guest) {
                // Guest/user ảo: không có user_id, gắn qua participant_id.
                TeamMember::create([
                    'team_id' => $teams[$teamIndex]->id,
                    'user_id' => null,
                    'participant_id' => $participant->id,
                ]);
            } else {
                // User thật: gắn cả user_id và participant_id để load đúng.
                TeamMember::create([
                    'team_id' => $teams[$teamIndex]->id,
                    'user_id' => $participant->user_id,
                    'participant_id' => $participant->id,
                ]);
            }
            $teamMemberCount[$teamIndex]++;

            // nếu đội hiện tại đã full thì chuyển sang đội tiếp theo
            if ($teamMemberCount[$teamIndex] >= $maxPlayers) {
                $teamIndex++;
                if ($teamIndex >= $maxTeams) {
                    break;
                }
            }
        }

        // load lại danh sách teams + members
        $teamsWithMembers = Team::where('tournament_id', $tournamentId)
            ->with($this->withMembersRelations())
            ->get();

        TournamentTeamMemberHydrator::hydrateCollection($teamsWithMembers, (int) $tournamentId);

        return ResponseHelper::success(
            ListTeamResource::collection($teamsWithMembers),
            'Phân đội tự động thành công'
        );
    }

    public function removeMember(Request $request, $teamId)
    {
        $request->validate(
            [
                // Frontend có thể gửi user_id (User.id) cho user thật,
                // hoặc participant.id cho guest (vì response không có User).
                // Chấp nhận cả 2 — chỉ cần khớp 1 participant thuộc giải.
                'user_id' => 'required|integer',
            ],
            [
                'user_id.required' => 'Vui lòng chọn người dùng',
            ]
        );
        $team = Team::findOrFail($teamId);
        $tournament = $team->tournament;

        // Tìm participant: ưu tiên match theo participants.user_id (user thật),
        // fallback theo participants.id (guest) nếu không thấy.
        $sentId = (int) $request->user_id;
        $participant = Participant::where('tournament_id', $tournament->id)
            ->where(function ($q) use ($sentId) {
                $q->where('user_id', $sentId)->orWhere('id', $sentId);
            })
            ->first();

        if (!$participant) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'user_id' => 'Người dùng chưa tham gia giải đấu',
            ]);
        }

        if (!$tournament->relationLoaded('staff')) {
            $tournament->load('staff');
        }
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xoá thành viên khỏi đội', 400);
        }

        if ($tournament->hasMatchesWithResults()) {
            return ResponseHelper::error(
                'Không thể xoá thành viên khỏi đội. Đội đã có trận đấu đang diễn ra/hoàn thành',
                400
            );
        }

        $team = Team::findOrFail($teamId);
        $matches = Matches::where('home_team_id', $teamId)
            ->orWhere('away_team_id', $teamId)
            ->get();
        if ($matches->isNotEmpty() && MatchResult::whereIn('match_id', $matches->pluck('id'))->exists()) {
            return ResponseHelper::error(
                'Không thể xoá thành viên khỏi đội. Đội đã có trận đấu đang diễn ra/hoàn thành',
                400
            );
        }
        // Xoá qua TeamMember theo participant_id để đảm bảo xoá đúng row
        // dù participant là guest (user_id NULL) hay user thật.
        TeamMember::where('team_id', $team->id)
            ->where('participant_id', $participant->id)
            ->delete();

        $team->load($this->withMembersRelations());
        TournamentTeamMemberHydrator::hydrateTeam($team, $team->tournament_id);

        return ResponseHelper::success(new TeamResource($team), 'Xóa thành viên khỏi đội thành công');
    }

    public function deleteTeam($teamId)
    {
        $team = Team::findOrFail($teamId);
        $tournament = $team->tournament;
        if (!$tournament->relationLoaded('staff')) {
            $tournament->load('staff');
        }
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền xoá đội', 400);
        }

        if ($tournament->hasMatchesWithResults()) {
            return ResponseHelper::error(
                'Không thể xoá đội. Đội đã có trận đấu đang diễn ra/hoàn thành',
                400
            );
        }

        $tournamentTypeIds = $tournament->tournamentTypes()->pluck('id');
        // if ($tournament->tournamentTypes()->exists()) {
        //     return ResponseHelper::error('Không thể xoá đội khi giải đấu đã có loại hình thi đấu', 400);
        // }
        if ($tournamentTypeIds->isEmpty()) {
            return $this->forceDeleteTeam($team);
        }
        $matches = Matches::where('home_team_id', $teamId)
            ->orWhere('away_team_id', $teamId)
            ->get();
        if ($matches->isEmpty()) {
            return $this->forceDeleteTeam($team);
        }
        $hasResult = MatchResult::whereIn('match_id', $matches->pluck('id'))->exists();
        if ($hasResult) {
            return ResponseHelper::error(
                'Đội đã tham gia trận đấu và có kết quả, không thể xoá.',
                400
            );
        }
        return $this->forceDeleteTeam($team);
    }

    private function forceDeleteTeam(Team $team)
    {
        TeamMember::where('team_id', $team->id)->delete();
        $team->delete();

        return ResponseHelper::success(null, 'Xoá đội thành công');
    }
}
