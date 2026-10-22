<?php

namespace App\Http\Controllers;

use App\Events\TournamentScoreUpdated;
use App\Helpers\ResponseHelper;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\TournamentType\RoundNameResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivescorePickerController extends Controller
{
    public function __construct(private RoundNameResolver $roundNameResolver) {}

    /**
     * Danh sách giải cho màn chọn trận Livescore.
     *
     * Dùng CHUNG đúng bộ lọc với `api/home` (`upcoming_tournaments`):
     *   - quyền: user là VĐV (`participants`) HOẶC BTC / trọng tài (`tournament_staff`)
     *   - thời gian: `start_date >= hôm nay` (giải sắp tới / hôm nay)
     *
     * `data` trả thẳng list giải, mỗi giải KHÔNG kèm danh sách trận.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ResponseHelper::error('Cần đăng nhập để dùng Livescore', 401);
        }

        $tournaments = Tournament::query()
            ->accessibleByUser($user->id)
            ->startingFromToday()
            ->with('competitionLocation:id,name')
            ->orderBy('start_date', 'asc')
            ->get([
                'id', 'name', 'poster', 'description', 'start_date', 'end_date',
                'competition_location_id',
            ]);

        $payload = $tournaments->map(function ($t) {
            return [
                'id' => (int) $t->id,
                'name' => $t->name,
                'poster_url' => $this->posterUrl($t->poster),
                'description' => $t->description,
                'location_name' => $t->competitionLocation?->name ?? null,
                'start_date' => $t->start_date ? \Carbon\Carbon::parse($t->start_date)->toIso8601String() : null,
                'end_date' => $t->end_date ? \Carbon\Carbon::parse($t->end_date)->toIso8601String() : null,
            ];
        })->values()->all();

        return ResponseHelper::success($payload);
    }

    /**
     * Toàn bộ trận của 1 giải — dùng cho màn chiếu tỉ số.
     *
     * `data` trả thẳng list trận.
     */
    public function matches(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ResponseHelper::error('Cần đăng nhập để dùng Livescore', 401);
        }

        $tournament = Tournament::query()->find($id);

        if (!$tournament) {
            return ResponseHelper::error('Không tìm thấy giải đấu', 404);
        }

        // Cùng bộ lọc quyền với `api/home`: phải là VĐV hoặc BTC / trọng tài.
        $allowed = Tournament::query()
            ->accessibleByUser($user->id)
            ->whereKey($id)
            ->exists();

        if (!$allowed) {
            return ResponseHelper::error('Bạn không có quyền xem trận của giải này', 403);
        }

        return ResponseHelper::success($this->tournamentMatches($id));
    }

    /**
     * Toàn bộ trận của 1 giải, đã bốc sẵn field cho màn chiếu.
     */
    private function tournamentMatches(int $tournamentId): array
    {
        $matches = Matches::query()
            ->whereHas('tournamentType', fn ($q) => $q->where('tournament_id', $tournamentId))
            // KHÔNG lọc home_team_id / away_team_id: trận vòng trong của thể thức
            // hỗn hợp được tạo sẵn với đội = null chờ kết quả vòng trước, và có
            // những flow để is_bye = 0 cho các trận dạng đó (xem MatchesController).
            // Phải chọn cả khoá chính 'id' — thiếu nó thì Eloquent trả null
            // cho toàn bộ quan hệ (đã dính bug này một lần).
            ->with([
                'homeTeam:id,name',
                'awayTeam:id,name',
                // Thành viên đội (user thật + guest) để FE hiển thị tên người chơi.
                'homeTeam.members',
                'homeTeam.guestMembers.participant',
                'awayTeam.members',
                'awayTeam.guestMembers.participant',
                'tournamentType:id,tournament_id,format,num_legs',
            ])
            // Round trước để trận vòng trong nằm đúng chỗ; trong cùng vòng thì
            // theo giờ đã xếp, cuối cùng theo id cho ổn định.
            ->orderBy('round')
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return [];
        }

        // Tên vòng phải tính trên TOÀN BỘ trận (kể cả trận bye) để khớp với
        // màn bracket: một trận bye vẫn chiếm 1 chỗ trong vòng, nên loại nó ra
        // khỏi số cặp đấu sẽ làm sai tên vòng (ví dụ 2 vòng khác nhau cùng
        // bị gọi là "Chung kết"). Vòng tranh hạng Ba cũng được xác định theo
        // cờ is_third_place nên việc này không ảnh hưởng tới nó.
        $roundNames = $this->roundNames($matches);

        // Trận bye không hiển thị cho BTC.
        $visibleMatches = $matches->where('is_bye', 0)->values();

        if ($visibleMatches->isEmpty()) {
            return [];
        }

        $currentScores = $this->currentScores($visibleMatches->pluck('id')->all());

        return $visibleMatches->map(fn ($m) => $this->formatPickerMatch(
            $m,
            $currentScores[$m->id] ?? ['team1_score' => 0, 'team2_score' => 0],
            $roundNames
        ))->values()->all();
    }

    /**
     * Tên vòng của mọi trận, khoá theo `tournament_type_id:round`.
     *
     * Một giải có thể có nhiều thể thức (`Tournament::tournamentTypes()`) và mỗi
     * thể thức đánh số `round` lại từ 1, nên bắt buộc phải khoá theo cả hai.
     *
     * @param  \Illuminate\Support\Collection<int, Matches>  $matches
     * @return array<string, string>
     */
    private function roundNames($matches): array
    {
        $roundNames = [];

        foreach ($matches->groupBy('tournament_type_id') as $typeId => $typeMatches) {
            $type = $typeMatches->first()->tournamentType;

            $names = $this->roundNameResolver->resolve(
                $typeMatches,
                (int) ($type?->format ?? TournamentType::FORMAT_ELIMINATION),
                (int) ($type?->num_legs ?? 1)
            );

            foreach ($names as $round => $name) {
                $roundNames["{$typeId}:{$round}"] = $name;
            }
        }

        return $roundNames;
    }

    /**
     * Điểm set hiện tại của từng trận — 1 query cho tất cả match id.
     */
    private function currentScores(array $matchIds): array
    {
        if (empty($matchIds)) {
            return [];
        }

        $rows = DB::table('match_results')
            ->whereIn('match_id', $matchIds)
            ->join('matches', 'matches.id', '=', 'match_results.match_id')
            ->whereColumn('match_results.set_number', 'matches.current_set')
            ->whereColumn('match_results.team_id', 'matches.home_team_id')
            ->get(['match_results.match_id', 'match_results.team_score', 'match_results.opponent_score']);

        return $rows->mapWithKeys(fn ($r) => [
            $r->match_id => [
                'team1_score' => (int) $r->team_score,
                'team2_score' => (int) $r->opponent_score,
            ],
        ])->all();
    }

    /**
     * @param  array<string, string>  $roundNames  khoá `tournament_type_id:round`
     */
    private function formatPickerMatch(Matches $m, array $score, array $roundNames = []): array
    {
        return [
            'match_id' => (int) $m->id,
            // Tên vòng ("Vòng bảng" / "Tứ kết" / ...) chứ không phải số thứ tự.
            'round' => $roundNames["{$m->tournament_type_id}:{$m->round}"] ?? null,
            'scheduled_at' => $m->scheduled_at?->toIso8601String(),
            'live_status' => $m->live_status,
            'status' => $m->status,
            // Phiên bản trận — FE dùng để bỏ qua event realtime đến lệch thứ tự.
            'version' => (int) $m->match_version,
            // is_going_on = đã bắt đầu VÀ chưa confirm kết quả (spec).
            'is_going_on' => TournamentScoreUpdated::isGoingOn($m),
            'is_finished' => $m->status === 'completed' || $m->live_status === 'finish',
            'team1_score' => $score['team1_score'],
            'team2_score' => $score['team2_score'],
            'team1' => $this->formatPickerTeam($m->home_team_id, $m->homeTeam),
            'team2' => $this->formatPickerTeam($m->away_team_id, $m->awayTeam),
        ];
    }

    /**
     * Format 1 đội cho màn livescore: id, tên đội và danh sách thành viên.
     *
     * Trận vòng trong chưa biết đội: `id` = null, tên hiển thị là `TBD`,
     * `members` = mảng rỗng.
     *
     * Thành viên gồm cả user thật (`team.members`) và guest (`team.guestMembers`
     * — user thật không có bản ghi User riêng).
     *
     * @param  \App\Models\Team|null  $team
     * @return array{id: int|null, name: string, members: array<int, array>}
     */
    private function formatPickerTeam(?int $teamId, $team): array
    {
        if (!$team) {
            return [
                'id' => $teamId,
                'name' => 'TBD',
                'members' => [],
            ];
        }

        $realMembers = $team->members->map(fn ($user) => [
            'id' => (int) $user->id,
            'full_name' => $user->full_name,
            'name' => $user->full_name,
            'avatar_url' => $user->avatar_url,
            'avatar' => $user->avatar_url,
            'is_guest' => false,
        ])->values();

        $guestMembers = collect($team->guestMembers ?? [])
            ->map(function ($teamMember) {
                $participant = $teamMember->participant;
                if (!$participant) {
                    return null;
                }

                $fullName = $participant->guest_name ?? 'Thành viên ảo';

                return [
                    'id' => (int) $participant->id,
                    'full_name' => $fullName,
                    'name' => $fullName,
                    'avatar_url' => $participant->guest_avatar,
                    'avatar' => $participant->guest_avatar,
                    'is_guest' => true,
                ];
            })
            ->filter()
            ->values();

        return [
            'id' => $teamId ?? (int) $team->id,
            'name' => $team->name,
            'members' => $realMembers->concat($guestMembers)->values()->all(),
        ];
    }

    /**
     * Poster: DB lưu đường dẫn tương đối (`posters/xxx.jpg`) hoặc URL tuyệt đối.
     */
    private function posterUrl(?string $poster): ?string
    {
        if (!$poster) {
            return null;
        }

        return \Illuminate\Support\Str::startsWith($poster, 'http')
            ? $poster
            : url('/storage/' . ltrim($poster, '/'));
    }
}
