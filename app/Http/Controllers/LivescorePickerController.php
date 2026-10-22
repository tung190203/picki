<?php

namespace App\Http\Controllers;

use App\Events\TournamentScoreUpdated;
use App\Helpers\ResponseHelper;
use App\Models\Matches;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivescorePickerController extends Controller
{
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
            ->where('is_bye', 0)
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            // Phải chọn cả khoá chính 'id' — thiếu nó thì Eloquent trả null
            // cho toàn bộ quan hệ (đã dính bug này một lần).
            ->with(['homeTeam:id,name', 'awayTeam:id,name', 'tournamentType:id,tournament_id'])
            ->orderBy('scheduled_at')
            ->orderBy('id')
            ->get();

        if ($matches->isEmpty()) {
            return [];
        }

        $currentScores = $this->currentScores($matches->pluck('id')->all());

        return $matches->map(fn ($m) => $this->formatPickerMatch(
            $m,
            $currentScores[$m->id] ?? ['team1_score' => 0, 'team2_score' => 0]
        ))->values()->all();
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

    private function formatPickerMatch(Matches $m, array $score): array
    {
        return [
            'match_id' => (int) $m->id,
            'round' => $m->round,
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
            'team1' => [
                'id' => $m->home_team_id,
                'name' => $m->homeTeam?->name ?? 'Đội A',
            ],
            'team2' => [
                'id' => $m->away_team_id,
                'name' => $m->awayTeam?->name ?? 'Đội B',
            ],
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
