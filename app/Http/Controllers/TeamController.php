<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Resources\ListTeamResource;
use App\Http\Resources\TeamResource;
use App\Models\Matches;
use App\Models\MatchResult;
use App\Models\Participant;
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
        return ['members.sports.scores', 'members.sports.sport'];
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
            ],
            [
                'name.required' => 'Vui lòng nhập tên đội',
                'name.string' => 'Tên đội phải là chuỗi ký tự',
                'name.max' => 'Tên đội không được vượt quá 255 ký tự',
                'avatar.image' => 'Ảnh đại diện phải là một tệp hình ảnh',
                'avatar.max' => 'Ảnh đại diện không được vượt quá 2MB',
            ]
        );

        $tournament = Tournament::findOrFail($tournamentId);
        if ($tournament->max_team && $tournament->teams()->count() >= $tournament->max_team) {
            return ResponseHelper::error('Đã đạt số lượng đội tối đa cho giải đấu', 400);
        }
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền tạo đội', 400);
        }
        // if ($tournament->tournamentTypes()->exists()) {
        //     return ResponseHelper::error('Không thể tạo đội khi giải đấu đã có loại hình thi đấu', 400);
        // }
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
            ],
            [
                'name.required' => 'Vui lòng nhập tên đội',
                'name.string' => 'Tên đội phải là chuỗi ký tự',
                'name.max' => 'Tên đội không được vượt quá 255 ký tự',
            ]
        );

        $team = Team::findOrFail($request->route('teamId'));
        $tournament = $team->tournament;
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
                'user_id' => 'required|exists:participants,user_id',
            ],
            [
                'user_id.exists' => 'Người dùng chưa tham gia giải đấu',
                'user_id.required' => 'Vui lòng chọn người dùng',
            ]
        );

        $team = Team::findOrFail($teamId);
        $tournament = $team->tournament;
        $isOrganizer = $tournament->hasOrganizer(Auth::id());
        if (!$isOrganizer) {
            return ResponseHelper::error('Bạn không có quyền thêm người vào đội', 400);
        }
        $participant = Participant::where('user_id', $request->user_id)
            ->where('tournament_id', $tournament->id)
            ->where('is_confirmed', true)
            ->first();

        if (!$participant) {
            return ResponseHelper::error("Người dùng chưa được xác nhận tham gia giải đấu", 422);
        }
        $currentCount = $team->members()->count();
        $maxPlayers = $tournament->player_per_team;

        if ($maxPlayers && $currentCount >= $maxPlayers) {
            return ResponseHelper::error("Đội đã đủ số lượng tối đa {$maxPlayers} thành viên", 422);
        }

        // tránh thêm trùng
        if ($team->members()->where('user_id', $request->user_id)->exists()) {
            return ResponseHelper::error("Người dùng đã nằm trong đội", 422);
        }

        $team->members()->attach($request->user_id);

        $team->load($this->withMembersRelations());
        TournamentTeamMemberHydrator::hydrateTeam($team, $tournament->id);

        return ResponseHelper::success(new TeamResource($team), 'Thêm thành viên vào đội thành công');
    }

    public function autoAssignTeams($tournamentId)
    {
        $tournament = Tournament::findOrFail($tournamentId);
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
            $teams[$teamIndex]->members()->attach($participant->user_id);
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
                'user_id' => 'required|exists:participants,user_id',
            ],
            [
                'user_id.exists' => 'Người dùng chưa tham gia giải đấu',
                'user_id.required' => 'Vui lòng chọn người dùng',
            ]
        );
        $tournament = Team::findOrFail($teamId)->tournament;
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
        $team->members()->detach($request->user_id);

        $team->load($this->withMembersRelations());
        TournamentTeamMemberHydrator::hydrateTeam($team, $team->tournament_id);

        return ResponseHelper::success(new TeamResource($team), 'Xóa thành viên khỏi đội thành công');
    }

    public function deleteTeam($teamId)
    {
        $team = Team::findOrFail($teamId);
        $tournament = $team->tournament;
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
        $team->members()->detach();
        $team->delete();

        return ResponseHelper::success(null, 'Xoá đội thành công');
    }
}
