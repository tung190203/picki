<?php

namespace Tests\Feature\User;

use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Club\ClubGuestProfile;
use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniTeamMember;
use App\Models\MiniTournament;
use App\Models\Sport;
use App\Models\User;
use App\Models\UserSport;
use App\Models\UserSportScore;
use App\Models\MatchHistory;
use App\Models\QuickMatch;
use App\Models\Match;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Models\TeamMember;
use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserSuggestTest extends TestCase
{
    use RefreshDatabase;

    private const GROUP_LIMIT = 15;

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'is_super_admin' => false,
            'is_banned' => false,
            'is_guest' => false,
            'is_merged' => false,
            'latitude' => 10.776,
            'longitude' => 106.701,
        ], $overrides));
    }

    private function makeClub(array $overrides = []): Club
    {
        return Club::factory()->create(array_merge([
            'status' => ClubStatus::Active->value,
            'is_public' => true,
        ], $overrides));
    }

    private function makeMember(Club $club, User $user, string $role = 'member'): ClubMember
    {
        return ClubMember::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => ClubMemberStatus::Active->value,
        ]);
    }

    private function setVndupr(User $user, float $score): void
    {
        $us = UserSport::create([
            'user_id' => $user->id,
            'sport_id' => Sport::PICKLEBALL_ID,
            'tier' => 'intermediate',
            'total_matches' => 0,
        ]);
        UserSportScore::create([
            'user_sport_id' => $us->id,
            'score_type' => 'vndupr_score',
            'score_value' => $score,
        ]);
    }

    private function addFriend(User $a, User $b): void
    {
        $now = now();
        DB::table('follows')->insert([
            ['user_id' => $a->id, 'followable_id' => $b->id, 'followable_type' => User::class, 'created_at' => $now, 'updated_at' => $now],
            ['user_id' => $b->id, 'followable_id' => $a->id, 'followable_type' => User::class, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private function createQuickMatchWithUsers(array $userIds): QuickMatch
    {
        $quickMatch = QuickMatch::create([
            'created_by' => $userIds[0],
            'sport_id' => Sport::PICKLEBALL_ID,
            'status' => 'completed',
            'confirmed_at' => now(),
            'winner' => 'team_a',
            'team_a' => json_encode(array_slice($userIds, 0, 2)),
            'team_b' => json_encode(array_slice($userIds, 2, 2)),
        ]);

        foreach ($userIds as $userId) {
            $teamSide = $userId == $userIds[0] || $userId == $userIds[1] ? 'team_a' : 'team_b';
            MatchHistory::create([
                'quick_match_id' => $quickMatch->id,
                'user_id' => $userId,
                'team_side' => $teamSide,
            ]);
        }

        return $quickMatch;
    }

    private function createMiniTournamentWithUsers(User $organizer, array $userIds): MiniTournament
    {
        $tournament = MiniTournament::create([
            'created_by' => $organizer->id,
            'sport_id' => Sport::PICKLEBALL_ID,
            'status' => MiniTournament::STATUS_COMPLETED,
            'start_time' => now()->subDays(1),
            'end_time' => now(),
        ]);

        // Create mini participants
        foreach ($userIds as $userId) {
            MiniParticipant::create([
                'mini_tournament_id' => $tournament->id,
                'user_id' => $userId,
                'is_confirmed' => 1,
            ]);
        }

        // Create team 1
        $team1Id = DB::table('mini_teams')->insertGetId(['mini_tournament_id' => $tournament->id]);
        foreach (array_slice($userIds, 0, 2) as $userId) {
            MiniTeamMember::create([
                'mini_team_id' => $team1Id,
                'user_id' => $userId,
            ]);
        }

        // Create team 2
        $team2Id = DB::table('mini_teams')->insertGetId(['mini_tournament_id' => $tournament->id]);
        foreach (array_slice($userIds, 2, 2) as $userId) {
            MiniTeamMember::create([
                'mini_team_id' => $team2Id,
                'user_id' => $userId,
            ]);
        }

        // Create a completed match
        MiniMatch::create([
            'mini_tournament_id' => $tournament->id,
            'status' => MiniMatch::STATUS_COMPLETED,
            'team1_id' => $team1Id,
            'team2_id' => $team2Id,
            'team_win_id' => $team1Id,
        ]);

        return $tournament;
    }

    public function test_anonymous_user_returns_empty_suggestions(): void
    {
        $resp = $this->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('played_tournament', $data);
        $this->assertArrayHasKey('same_club', $data);
        $this->assertArrayHasKey('nearby', $data);
        $this->assertEmpty($data['played_tournament']);
        $this->assertEmpty($data['same_club']);
        $this->assertEmpty($data['nearby']);
    }

    public function test_same_club_returns_users_in_same_club(): void
    {
        $viewer = $this->makeUser();
        $otherUser = $this->makeUser(['full_name' => 'Other User']);

        $club = $this->makeClub();
        $this->makeMember($club, $viewer);
        $this->makeMember($club, $otherUser);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('same_club', $data);
        $this->assertNotEmpty($data['same_club']);

        $foundUser = collect($data['same_club'])->firstWhere('id', $otherUser->id);
        $this->assertNotNull($foundUser);
        $this->assertSame('same_club', $foundUser['category']);
        $this->assertSame('Cùng CLB', $foundUser['category_text']);
    }

    public function test_same_club_returns_correct_clubs_together_count(): void
    {
        $viewer = $this->makeUser();
        $otherUser = $this->makeUser(['full_name' => 'Other User']);

        // Create 2 clubs and add both users to both
        $club1 = $this->makeClub(['name' => 'Club 1']);
        $club2 = $this->makeClub(['name' => 'Club 2']);

        $this->makeMember($club1, $viewer);
        $this->makeMember($club1, $otherUser);
        $this->makeMember($club2, $viewer);
        $this->makeMember($club2, $otherUser);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $foundUser = collect($data['same_club'])->firstWhere('id', $otherUser->id);
        $this->assertNotNull($foundUser);
        $this->assertArrayHasKey('relation', $foundUser);
        $this->assertSame(2, $foundUser['relation']['clubs_together']);
    }

    public function test_same_club_caps_at_fifteen(): void
    {
        $viewer = $this->makeUser();
        $club = $this->makeClub();

        $this->makeMember($club, $viewer);

        // Create 20 other users in the same club
        for ($i = 0; $i < 20; $i++) {
            $otherUser = $this->makeUser();
            $this->makeMember($club, $otherUser);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('same_club', $data);
        $this->assertCount(self::GROUP_LIMIT, $data['same_club']);
    }

    public function test_user_without_club_returns_empty_same_club(): void
    {
        $viewer = $this->makeUser();
        $otherUser = $this->makeUser();

        $club = $this->makeClub();
        $this->makeMember($club, $otherUser);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('same_club', $data);
        $this->assertEmpty($data['same_club']);
    }

    public function test_played_together_returns_users_played_quick_match_together(): void
    {
        $viewer = $this->makeUser();
        $otherUser = $this->makeUser(['full_name' => 'Played Together']);

        // Create a quick match with both users
        $this->createQuickMatchWithUsers([$viewer->id, $otherUser->id, $this->makeUser()->id, $this->makeUser()->id]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('played_tournament', $data);
        $this->assertNotEmpty($data['played_tournament']);

        $foundUser = collect($data['played_tournament'])->firstWhere('id', $otherUser->id);
        $this->assertNotNull($foundUser);
        $this->assertSame('played_tournament', $foundUser['category']);
        $this->assertSame('Hay chơi cùng kèo giải', $foundUser['category_text']);
    }

    public function test_played_together_returns_users_played_mini_tournament_together(): void
    {
        $viewer = $this->makeUser();
        $otherUser = $this->makeUser(['full_name' => 'Mini Tournament Friend']);

        // Create mini tournament with both users on the same team
        $this->createMiniTournamentWithUsers($viewer, [
            $viewer->id,
            $otherUser->id,
            $this->makeUser()->id,
            $this->makeUser()->id,
        ]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('played_tournament', $data);
        $this->assertNotEmpty($data['played_tournament']);

        $foundUser = collect($data['played_tournament'])->firstWhere('id', $otherUser->id);
        $this->assertNotNull($foundUser);
        $this->assertSame('played_tournament', $foundUser['category']);
    }

    public function test_played_together_caps_at_fifteen(): void
    {
        $viewer = $this->makeUser();

        // Create quick matches with 20 different users
        for ($i = 0; $i < 20; $i++) {
            $otherUser = $this->makeUser();
            $this->createQuickMatchWithUsers([
                $viewer->id,
                $otherUser->id,
                $this->makeUser()->id,
                $this->makeUser()->id,
            ]);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('played_tournament', $data);
        $this->assertCount(self::GROUP_LIMIT, $data['played_tournament']);
    }

    public function test_played_together_sorts_by_total_matches(): void
    {
        $viewer = $this->makeUser();
        $userWith1Match = $this->makeUser(['full_name' => '1 Match User']);
        $userWith3Matches = $this->makeUser(['full_name' => '3 Matches User']);

        // User with 1 quick match
        $this->createQuickMatchWithUsers([
            $viewer->id, $userWith1Match->id,
            $this->makeUser()->id, $this->makeUser()->id,
        ]);

        // User with 3 quick matches
        for ($i = 0; $i < 3; $i++) {
            $this->createQuickMatchWithUsers([
                $viewer->id, $userWith3Matches->id,
                $this->makeUser()->id, $this->makeUser()->id,
            ]);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('played_tournament', $data);
        $this->assertNotEmpty($data['played_tournament']);

        // User with 3 matches should be first
        $firstUser = $data['played_tournament'][0];
        $this->assertSame($userWith3Matches->id, $firstUser['id']);
    }

    public function test_nearby_returns_users_within_radius(): void
    {
        $viewer = $this->makeUser([
            'latitude' => 10.776,
            'longitude' => 106.701,
        ]);
        $nearbyUser = $this->makeUser([
            'full_name' => 'Nearby User',
            'latitude' => 10.780,
            'longitude' => 106.701,
        ]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest', [
            'X-User-Lat' => '10.776',
            'X-User-Lng' => '106.701',
        ]);
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('nearby', $data);
        $this->assertNotEmpty($data['nearby']);

        $foundUser = collect($data['nearby'])->firstWhere('id', $nearbyUser->id);
        $this->assertNotNull($foundUser);
        $this->assertSame('nearby', $foundUser['category']);
        $this->assertSame('Ở gần bạn', $foundUser['category_text']);
        $this->assertArrayHasKey('distance', $foundUser);
    }

    public function test_nearby_caps_at_fifteen(): void
    {
        $viewer = $this->makeUser([
            'latitude' => 10.776,
            'longitude' => 106.701,
        ]);

        // Create 20 nearby users
        for ($i = 0; $i < 20; $i++) {
            $this->makeUser([
                'latitude' => 10.780 + ($i * 0.001),
                'longitude' => 106.701,
            ]);
        }

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest', [
            'X-User-Lat' => '10.776',
            'X-User-Lng' => '106.701',
        ]);
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('nearby', $data);
        $this->assertCount(self::GROUP_LIMIT, $data['nearby']);
    }

    public function test_nearby_excludes_user_without_location(): void
    {
        $viewer = $this->makeUser([
            'latitude' => 10.776,
            'longitude' => 106.701,
        ]);

        // Create user without location
        $this->makeUser([
            'full_name' => 'No Location',
            'latitude' => null,
            'longitude' => null,
        ]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest', [
            'X-User-Lat' => '10.776',
            'X-User-Lng' => '106.701',
        ]);
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('nearby', $data);
        $this->assertEmpty($data['nearby']);
    }

    public function test_nearby_excludes_users_outside_radius(): void
    {
        $viewer = $this->makeUser([
            'latitude' => 10.776,
            'longitude' => 106.701,
        ]);

        // Create user far away (about 100km)
        $this->makeUser([
            'full_name' => 'Far Away',
            'latitude' => 11.776,
            'longitude' => 106.701,
        ]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest', [
            'X-User-Lat' => '10.776',
            'X-User-Lng' => '106.701',
        ]);
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('nearby', $data);
        $this->assertEmpty($data['nearby']);
    }

    public function test_users_in_multiple_groups_are_deduplicated(): void
    {
        $viewer = $this->makeUser([
            'latitude' => 10.776,
            'longitude' => 106.701,
        ]);
        $otherUser = $this->makeUser([
            'full_name' => 'Multi Group User',
            'latitude' => 10.780,
            'longitude' => 106.701,
        ]);

        // Put in same club
        $club = $this->makeClub();
        $this->makeMember($club, $viewer);
        $this->makeMember($club, $otherUser);

        // Create quick match together
        $this->createQuickMatchWithUsers([
            $viewer->id, $otherUser->id,
            $this->makeUser()->id, $this->makeUser()->id,
        ]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest', [
            'X-User-Lat' => '10.776',
            'X-User-Lng' => '106.701',
        ]);
        $resp->assertOk();
        $data = $resp->json('data');

        // User should only appear in one group (played_tournament takes priority)
        $totalOccurrences = 0;
        foreach (['played_tournament', 'same_club', 'nearby'] as $group) {
            if (isset($data[$group])) {
                foreach ($data[$group] as $item) {
                    if ($item['id'] === $otherUser->id) {
                        $totalOccurrences++;
                    }
                }
            }
        }
        $this->assertSame(1, $totalOccurrences);

        // Should be in played_tournament group (first priority)
        $foundInPlayed = collect($data['played_tournament'] ?? [])->firstWhere('id', $otherUser->id);
        $this->assertNotNull($foundInPlayed);
    }

    public function test_response_includes_relation_data(): void
    {
        $viewer = $this->makeUser();
        $otherUser = $this->makeUser(['full_name' => 'Relation Test']);

        $club = $this->makeClub();
        $this->makeMember($club, $viewer);
        $this->makeMember($club, $otherUser);

        $this->createQuickMatchWithUsers([
            $viewer->id, $otherUser->id,
            $this->makeUser()->id, $this->makeUser()->id,
        ]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $foundUser = collect($data['played_tournament'])->firstWhere('id', $otherUser->id);
        $this->assertNotNull($foundUser);
        $this->assertArrayHasKey('relation', $foundUser);
        $this->assertArrayHasKey('matches_together', $foundUser['relation']);
        $this->assertArrayHasKey('mini_tournaments_together', $foundUser['relation']);
        $this->assertArrayHasKey('tournaments_together', $foundUser['relation']);
        $this->assertArrayHasKey('clubs_together', $foundUser['relation']);
    }

    public function test_response_excludes_banned_users(): void
    {
        $viewer = $this->makeUser();
        $bannedUser = $this->makeUser(['full_name' => 'Banned User', 'is_banned' => true]);

        $club = $this->makeClub();
        $this->makeMember($club, $viewer);
        $this->makeMember($club, $bannedUser);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('same_club', $data);
        $foundUser = collect($data['same_club'])->firstWhere('id', $bannedUser->id);
        $this->assertNull($foundUser);
    }

    public function test_response_excludes_viewer_themselves(): void
    {
        $viewer = $this->makeUser(['full_name' => 'Viewer']);

        $club = $this->makeClub();
        $this->makeMember($club, $viewer);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest');
        $resp->assertOk();
        $data = $resp->json('data');

        foreach (['played_tournament', 'same_club', 'nearby'] as $group) {
            if (isset($data[$group])) {
                foreach ($data[$group] as $item) {
                    $this->assertNotSame($viewer->id, $item['id']);
                }
            }
        }
    }

    public function test_all_three_groups_present_when_data_available(): void
    {
        $viewer = $this->makeUser([
            'latitude' => 10.776,
            'longitude' => 106.701,
        ]);
        $playedUser = $this->makeUser(['full_name' => 'Played User']);
        $clubUser = $this->makeUser(['full_name' => 'Club User']);
        $nearbyUser = $this->makeUser([
            'full_name' => 'Nearby User',
            'latitude' => 10.780,
            'longitude' => 106.701,
        ]);

        // Add to club
        $club = $this->makeClub();
        $this->makeMember($club, $viewer);
        $this->makeMember($club, $clubUser);

        // Quick match with playedUser
        $this->createQuickMatchWithUsers([
            $viewer->id, $playedUser->id,
            $this->makeUser()->id, $this->makeUser()->id,
        ]);

        $resp = $this->actingAs($viewer)->getJson('/api/players/search?tab=user&sub_tab=suggest', [
            'X-User-Lat' => '10.776',
            'X-User-Lng' => '106.701',
        ]);
        $resp->assertOk();
        $data = $resp->json('data');

        $this->assertArrayHasKey('played_tournament', $data);
        $this->assertArrayHasKey('same_club', $data);
        $this->assertArrayHasKey('nearby', $data);

        // Verify correct users in each group
        $this->assertNotEmpty($data['played_tournament']);
        $this->assertNotEmpty($data['same_club']);
        $this->assertNotEmpty($data['nearby']);

        $this->assertNotNull(collect($data['played_tournament'])->firstWhere('id', $playedUser->id));
        $this->assertNotNull(collect($data['same_club'])->firstWhere('id', $clubUser->id));
        $this->assertNotNull(collect($data['nearby'])->firstWhere('id', $nearbyUser->id));
    }
}
