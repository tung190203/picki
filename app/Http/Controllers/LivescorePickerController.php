<?php

namespace App\Http\Controllers;

use App\Events\TournamentScoreUpdated;
use App\Helpers\ResponseHelper;
use App\Models\Matches;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LivescorePickerController extends Controller
{
    /**
     * Danh sách giải đang diễn ra cho màn chọn trận Livescore.
     *
     * Scope: các giải user THAM GIA (đăng ký làm VĐV) hoặc thuộc BTC / trọng tài
     * (`tournament_staff`). Giống logic `upcoming_tournaments` ở API `/api/home`.
     *
     * `data` trả thẳng list giải, mỗi giải KHÔNG kèm danh sách trận.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return ResponseHelper::error('Cần đăng nhập để dùng Livescore', 401);
        }

        $tournamentIds = $this->accessibleTournamentIds($user->id);
        if (empty($tournamentIds)) {
            return ResponseHelper::success([]);
        }

        $today = now()->toDateString();

        $tournaments = DB::table('tournaments')
            ->whereIn('id', $tournamentIds)
            ->whereNull('deleted_at')
            // Giải đang diễn ra: đã bắt đầu và chưa kết thúc.
            ->whereNotNull('start_date')
            ->whereDate('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today);
            })
            ->orderByDesc('start_date')
            ->get([
                'id', 'name', 'poster', 'description', 'start_date', 'end_date',
                'competition_location_id',
            ]);

        $locationNames = $this->locationMap(array_filter($tournaments->pluck('competition_location_id')->all()));

        $payload = $tournaments->map(function ($t) use ($locationNames) {
            return [
                'id' => (int) $t->id,
                'name' => $t->name,
                'poster_url' => $t->poster ? \Illuminate\Support\Str::startsWith($t->poster, 'http')
                    ? $t->poster
                    : url('/storage/' . ltrim($t->poster, '/')) : null,
                'description' => $t->description,
                'location_name' => $locationNames[$t->competition_location_id] ?? null,
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

        $exists = DB::table('tournaments')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->exists();

        if (!$exists) {
            return ResponseHelper::error('Không tìm thấy giải đấu', 404);
        }

        if (!in_array($id, $this->accessibleTournamentIds($user->id), true)) {
            return ResponseHelper::error('Bạn không có quyền xem trận của giải này', 403);
        }

        return ResponseHelper::success($this->tournamentMatches($id));
    }

    /**
     * Giải user được phép xem: user THAM GIA giải (VĐV trong `participants`)
     * hoặc thuộc BTC / trọng tài (`tournament_staff`).
     *
     * Giống logic `upcoming_tournaments` ở API `/api/home`.
     */
    private function accessibleTournamentIds(int $userId): array
    {
        $participantIds = DB::table('participants')
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('tournament_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $staffIds = DB::table('tournament_staff')
            ->where('user_id', $userId)
            ->distinct()
            ->pluck('tournament_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($participantIds, $staffIds)));
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

    private function locationMap(array $locationIds): array
    {
        if (empty($locationIds)) {
            return [];
        }

        return DB::table('competition_locations')
            ->whereIn('id', $locationIds)
            ->pluck('name', 'id')
            ->all();
    }
}
