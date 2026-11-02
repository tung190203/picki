<?php

namespace Tests\Feature;

use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MatchesController;
use App\Http\Resources\UserTournamentResource;
use App\Models\MatchResult;
use App\Models\Matches;
use App\Models\Participant;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\TournamentType\TournamentRankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression test cho bug "tournament_rank sai so với bảng xếp hạng".
 *
 * Trước đây UserTournamentResource đọc rank từ bảng persist `team_rankings.rank`
 * (sort theo ranking rules của bảng đấu, và chỉ lấy tournament_type đầu tiên),
 * còn API leaderboard tính rank bằng TournamentRankService (Vô địch/Á quân
 * /Hạng 3 từ bracket). Hai nguồn lệch nhau nên user thấy hạng sai.
 */
class TournamentRankConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function makeTournament(): Tournament
    {
        // LeaderboardController fallback về sport slug 'pickleball' khi không truyền sport_id.
        if (!\App\Models\Sport::where('slug', 'pickleball')->exists()) {
            \App\Models\Sport::create(['name' => 'Pickleball', 'slug' => 'pickleball']);
        }

        return Tournament::create([
            'name' => 'Giải Loại Trực Tiếp',
            'sport_id' => \App\Models\Sport::where('slug', 'pickleball')->value('id'),
            'start_date' => now()->subDays(3),
            'duration' => 1440,
            'participant' => 'team',
            'status' => Tournament::CLOSED,
        ]);
    }

    /**
     * Tạo user thật + gắn vào team và participants để resource nhận diện
     * được là người tham gia (nếu không stats sẽ null hết).
     */
    private function registerMember(Tournament $tournament, TournamentType $type, Team $team): int
    {
        $user = \App\Models\User::factory()->create();

        DB::table('team_members')->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Participant::create([
            'tournament_id' => $tournament->id,
            'tournament_type_id' => $type->id,
            'user_id' => $user->id,
            'type' => 'user',
            'team_id' => $team->id,
        ]);

        return $user->id;
    }

    /**
     * Tạo trận đã có kết quả + 1 set thắng cho đội thắng (BracketService cần
     * match_results để resolve winner_team_id).
     */
    private function playMatch(
        TournamentType $type,
        Team $winner,
        Team $loser,
        int $round,
        bool $isThirdPlace = false
    ): Matches {
        $match = Matches::create([
            'tournament_type_id' => $type->id,
            'round' => $round,
            'home_team_id' => $winner->id,
            'away_team_id' => $loser->id,
            'status' => Matches::STATUS_COMPLETED,
            'winner_id' => $winner->id,
            'is_third_place' => $isThirdPlace,
        ]);

        MatchResult::create([
            'match_id' => $match->id,
            'team_id' => $winner->id,
            'score' => 21,
            'set_number' => 1,
        ]);

        MatchResult::create([
            'match_id' => $match->id,
            'team_id' => $loser->id,
            'score' => 15,
            'set_number' => 1,
        ]);

        return $match;
    }

    /**
     * Giải 8 đội loại trực tiếp, đã kết thúc đầy đủ từ tứ kết đến chung kết.
     * Trả về [tournament, type, teams theo hạng].
     */
    private function buildFinishedKnockout(): array
    {
        $tournament = $this->makeTournament();

        $type = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_ELIMINATION,
            'match_rules' => [],
        ]);

        // champion, runner-up, third, fourth, và 4 đội bị loại ở tứ kết
        $teams = collect(range(1, 8))->map(fn($i) => Team::create([
            'name' => "Team $i",
            'tournament_id' => $tournament->id,
        ]));

        // Tứ kết: 8 -> 4 (T0,T1 | T2,T3 vào bán kết)
        $this->playMatch($type, $teams[0], $teams[4], 2);
        $this->playMatch($type, $teams[1], $teams[5], 2);
        $this->playMatch($type, $teams[2], $teams[6], 2);
        $this->playMatch($type, $teams[3], $teams[7], 2);

        // Bán kết: T0 vs T1, T2 vs T3. Loser T1 + T3 vào tranh hạng 3.
        $this->playMatch($type, $teams[0], $teams[1], 3);
        $this->playMatch($type, $teams[2], $teams[3], 3);

        // Tranh hạng 3: T1 thắng T3 -> T1 hạng 3, T3 hạng 4.
        $this->playMatch($type, $teams[1], $teams[3], 4, isThirdPlace: true);

        // Chung kết: T0 thắng T2 -> T0 vô địch, T2 á quân.
        $this->playMatch($type, $teams[0], $teams[2], 4);

        // Mỗi team một user thật để resource xác định được người tham gia.
        $userIds = [];
        foreach ($teams as $team) {
            $userIds[$team->id] = $this->registerMember($tournament, $type, $team);
        }

        // Rank lưu xuống team_rankings giống flow thật (MatchesController).
        (new MatchesController())->recalculateRankings($type->id);

        return [$tournament, $type, $teams, $userIds];
    }

    /**
     * tournament_rank trong user/tournaments/list phải khớp đúng hạng mà
     * API leaderboard trả về cho cùng team.
     */
    public function test_tournament_rank_matches_leaderboard_for_champion(): void
    {
        [$tournament, $type, $teams, $userIds] = $this->buildFinishedKnockout();

        $rank = app(TournamentRankService::class)->bestRanksForTournament($tournament->id);
        $this->assertSame(1, $rank[$teams[0]->id]['overall_rank'] ?? null);
        $this->assertSame('Vô địch', $rank[$teams[0]->id]['rank_label'] ?? null);

        $stats = $this->statsForTeam($tournament, $teams[0]->id, $userIds[$teams[0]->id]);
        $this->assertSame(
            1,
            $stats['tournament_rank'],
            'Đội vô địch phải có tournament_rank = 1.'
        );

        $this->assertSame(
            $this->leaderboardRank($tournament->id, $teams[0]->id),
            $stats['tournament_rank']
        );
    }

    /**
     * Á quân và hạng 3 phải khớp leaderboard — trước đây đọc team_rankings
     * nên các hạng này bị lệch.
     */
    public function test_tournament_rank_matches_leaderboard_for_other_places(): void
    {
        [$tournament, , $teams, $userIds] = $this->buildFinishedKnockout();

        $expected = [
            $teams[2]->id => 2, // á quân (thua chung kết)
            $teams[1]->id => 3, // hạng 3 (thắng trận tranh hạng 3)
            $teams[3]->id => 4, // hạng 4 (thua trận tranh hạng 3)
        ];

        foreach ($expected as $teamId => $rank) {
            $stats = $this->statsForTeam($tournament, $teamId, $userIds[$teamId]);

            $this->assertSame(
                $rank,
                $stats['tournament_rank'],
                "Team $teamId phải có tournament_rank = $rank."
            );
            $this->assertSame(
                $this->leaderboardRank($tournament->id, $teamId),
                $stats['tournament_rank'],
                'tournament_rank phải khớp leaderboard.'
            );
        }
    }

    /**
     * final_round phải là tên vòng thật, không phải giá trị suy đoán từ rank.
     */
    public function test_final_round_uses_bracket_round_name(): void
    {
        [$tournament, , $teams, $userIds] = $this->buildFinishedKnockout();

        $stats = $this->statsForTeam($tournament, $teams[0]->id, $userIds[$teams[0]->id]);
        $this->assertSame('Vô địch', $stats['final_round']);

        $statsLoser = $this->statsForTeam($tournament, $teams[4]->id, $userIds[$teams[4]->id]);
        $this->assertNotNull($statsLoser['final_round']);
    }

    /**
     * Giải có nhiều tournament_type: team chỉ đứng ở type thứ hai vẫn phải
     * có rank (trước đây code chỉ đọc type đầu tiên).
     */
    public function test_rank_is_found_across_multiple_tournament_types(): void
    {
        $tournament = $this->makeTournament();

        $type1 = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_ELIMINATION,
            'match_rules' => [],
        ]);

        $type2 = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_ELIMINATION,
            'match_rules' => [],
        ]);

        $teamA = Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        $teamB = Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);
        $teamC = Team::create(['name' => 'C', 'tournament_id' => $tournament->id]);

        // Type 1: A thắng B.
        $this->playMatch($type1, $teamA, $teamB, 2);

        // Type 2: C thắng A ở chung kết -> C vô địch type 2.
        $this->playMatch($type2, $teamC, $teamA, 2);

        $ranks = app(TournamentRankService::class)->bestRanksForTournament($tournament->id);

        $this->assertSame(1, $ranks[$teamC->id]['overall_rank'] ?? null, 'C phải là vô địch type 2.');
        $this->assertArrayHasKey($teamA->id, $ranks, 'A phải có rank từ type nào đó.');
    }

    /**
     * Trận tranh hạng 3 không được tính vào win/lose (khớp LeaderboardController).
     */
    public function test_win_lose_excludes_third_place_match(): void
    {
        [$tournament, , $teams, $userIds] = $this->buildFinishedKnockout();

        // Team 4 (index 3): thắng tứ kết (vs team8), thua bán kết, thua tranh hạng 3.
        // Trận hạng 3 phải bị loại khỏi thống kê => 1 thắng, 1 thua
        // (trước đây: 2 thắng, 2 thua).
        $stats = $this->statsForTeam($tournament, $teams[3]->id, $userIds[$teams[3]->id]);

        $this->assertSame(1, $stats['total_win'], 'Không được tính trận tranh hạng 3.');
        $this->assertSame(1, $stats['total_lose']);
    }

    /**
     * Cache phải bị xoá sau khi có kết quả trận mới, nếu không list API
     * trả rank cũ.
     */
    public function test_rank_cache_is_cleared_on_new_result(): void
    {
        $tournament = $this->makeTournament();
        $type = TournamentType::create([
            'tournament_id' => $tournament->id,
            'format' => TournamentType::FORMAT_ELIMINATION,
            'match_rules' => [],
        ]);

        $teamA = Team::create(['name' => 'A', 'tournament_id' => $tournament->id]);
        $teamB = Team::create(['name' => 'B', 'tournament_id' => $tournament->id]);

        $this->playMatch($type, $teamA, $teamB, 2);

        $service = app(TournamentRankService::class);
        $before = $service->bestRanksForTournament($tournament->id);
        $this->assertSame(1, $before[$teamA->id]['overall_rank'] ?? null);

        // Đảo kết quả (A thua) => sau khi recalculate, cache phải cập nhật.
        $match = Matches::where('tournament_type_id', $type->id)->first();
        $match->update([
            'winner_id' => $teamB->id,
            'home_team_id' => $teamB->id,
            'away_team_id' => $teamA->id,
        ]);
        MatchResult::where('match_id', $match->id)->delete();
        MatchResult::create(['match_id' => $match->id, 'team_id' => $teamB->id, 'score' => 21, 'set_number' => 1]);
        MatchResult::create(['match_id' => $match->id, 'team_id' => $teamA->id, 'score' => 15, 'set_number' => 1]);

        // Gọi lại - phải trả rank mới (B = 1).
        (new MatchesController())->recalculateRankings($type->id);

        $after = $service->bestRanksForTournament($tournament->id);
        $this->assertSame(
            1,
            $after[$teamB->id]['overall_rank'] ?? null,
            'Cache phải được xoá khi có kết quả mới.'
        );
    }

    // =========== Helpers ===========

    /**
     * Chạy UserTournamentResource cho 1 team và trả về mảng stats.
     */
    private function statsForTeam(Tournament $tournament, int $teamId, int $userId): array
    {
        $resource = new UserTournamentResource($this->loadTournament($tournament->id));

        $request = Request::create('/api/user/tournaments/list', 'GET', ['user_id' => $userId]);
        $request->setUserResolver(fn() => null);

        return $resource->toArray($request)['stats'];
    }

    private function loadTournament(int $id): Tournament
    {
        return Tournament::with([
            'teams.members',
            'participants',
            'tournamentTypes.matches',
            'tournamentTypes.groups.matches',
        ])->find($id);
    }

    /**
     * Gọi API leaderboard để lấy rank chuẩn của team.
     */
    private function leaderboardRank(int $tournamentId, int $teamId): ?int
    {
        $controller = new LeaderboardController(app(TournamentRankService::class));

        $request = Request::create("/api/tournaments/$tournamentId/leaderboard", 'GET');
        $request->setUserResolver(fn() => null);

        $response = $controller->index($request, $tournamentId);
        $payload = json_decode($response->getContent(), true);
        $data = $payload['data'] ?? [];

        foreach ($data['leaderboard'] ?? [] as $row) {
            if (($row['id'] ?? null) == $teamId) {
                return $row['rank'] ?? null;
            }
        }

        return null;
    }
}