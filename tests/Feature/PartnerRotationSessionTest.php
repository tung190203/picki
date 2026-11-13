<?php

namespace Tests\Feature;

use App\Models\MiniMatch;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\MiniTournamentStaff;
use App\Models\Sport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PartnerRotationSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Sport::find(1)) {
            DB::table('sports')->insert([
                'id' => 1,
                'name' => 'Pickleball',
                'slug' => 'pickleball',
            ]);
        }

        $this->organizer = User::factory()->create();
    }

    private function createDoubleTournament(?string $matchFormat = MiniTournament::MATCH_FORMAT_STANDARD, int $confirmedCount = 1): MiniTournament
    {
        $isSessionStarted = in_array($matchFormat, [
            MiniTournament::MATCH_FORMAT_STANDARD,
            MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
            null,
        ], true);

        $tournament = MiniTournament::create([
            'name' => 'Kèo Partner Rotation Test',
            'sport_id' => 1,
            'format' => MiniTournament::FORMAT_DOUBLE,
            'match_format' => $matchFormat,
            'session_status' => $isSessionStarted
                ? MiniTournament::SESSION_STATUS_ONGOING
                : MiniTournament::SESSION_STATUS_PENDING_GROUP,
            'is_session_started' => $isSessionStarted,
            'status' => MiniTournament::STATUS_OPEN,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'max_players' => 8,
            'has_fee' => false,
        ]);

        MiniTournamentStaff::create([
            'mini_tournament_id' => $tournament->id,
            'user_id' => $this->organizer->id,
            'role' => MiniTournamentStaff::ROLE_ORGANIZER,
        ]);

        MiniParticipant::create([
            'mini_tournament_id' => $tournament->id,
            'user_id' => $this->organizer->id,
            'is_confirmed' => true,
            'is_absent' => false,
        ]);

        for ($i = 1; $i < $confirmedCount; $i++) {
            $player = User::factory()->create();
            MiniParticipant::create([
                'mini_tournament_id' => $tournament->id,
                'user_id' => $player->id,
                'is_confirmed' => true,
                'is_absent' => false,
            ]);
        }

        return $tournament;
    }

    public function test_switching_to_partner_rotation_with_fewer_than_4_players_keeps_is_session_started_true(): void
    {
        $tournament = $this->createDoubleTournament(MiniTournament::MATCH_FORMAT_STANDARD, 2);

        $response = $this->actingAs($this->organizer, 'api')
            ->postJson("/api/mini-tournaments/update/{$tournament->id}", [
                'match_format' => MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
            ]);

        $response->assertStatus(200);
        $tournament->refresh();

        $this->assertTrue((bool) $tournament->is_session_started);
        $this->assertSame(MiniTournament::SESSION_STATUS_ONGOING, $tournament->session_status);
        $this->assertSame(0, MiniMatch::where('mini_tournament_id', $tournament->id)->count());
    }

    public function test_switching_to_mixed_gender_or_rank_pairing_keeps_is_session_started_false(): void
    {
        $tournament = $this->createDoubleTournament(MiniTournament::MATCH_FORMAT_STANDARD, 4);

        foreach ([MiniTournament::MATCH_FORMAT_MIXED_GENDER, MiniTournament::MATCH_FORMAT_RANK_PAIRING] as $format) {
            $response = $this->actingAs($this->organizer, 'api')
                ->postJson("/api/mini-tournaments/update/{$tournament->id}", [
                    'match_format' => $format,
                ]);

            $response->assertStatus(200);
            $tournament->refresh();

            $this->assertFalse((bool) $tournament->is_session_started);
            $this->assertSame(MiniTournament::SESSION_STATUS_PENDING_GROUP, $tournament->session_status);
        }
    }

    public function test_switching_to_partner_rotation_with_4_players_generates_matches_and_rounds(): void
    {
        $tournament = $this->createDoubleTournament(MiniTournament::MATCH_FORMAT_STANDARD, 4);

        $response = $this->actingAs($this->organizer, 'api')
            ->postJson("/api/mini-tournaments/update/{$tournament->id}", [
                'match_format' => MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
            ]);

        $response->assertStatus(200);
        $tournament->refresh();

        $this->assertTrue((bool) $tournament->is_session_started);
        $this->assertSame(MiniTournament::SESSION_STATUS_ONGOING, $tournament->session_status);
        $this->assertGreaterThan(0, MiniMatch::where('mini_tournament_id', $tournament->id)->count());

        $matchesResponse = $this->actingAs($this->organizer, 'api')
            ->getJson("/api/mini-matches/index/{$tournament->id}");

        $matchesResponse->assertStatus(200);
        $this->assertTrue((bool) $matchesResponse->json('data.is_session_started'));
        $this->assertNotEmpty($matchesResponse->json('data.rounds'));
    }

    public function test_opening_matches_auto_generates_rounds_for_partner_rotation_when_players_join_later(): void
    {
        // Created as partner_rotation with only 1 player (so 0 matches initially)
        $tournament = $this->createDoubleTournament(MiniTournament::MATCH_FORMAT_PARTNER_ROTATION, 1);
        $this->assertTrue((bool) $tournament->is_session_started);
        $this->assertSame(0, MiniMatch::where('mini_tournament_id', $tournament->id)->count());

        // 3 more confirmed players join later
        for ($i = 0; $i < 3; $i++) {
            $player = User::factory()->create();
            MiniParticipant::create([
                'mini_tournament_id' => $tournament->id,
                'user_id' => $player->id,
                'is_confirmed' => true,
                'is_absent' => false,
            ]);
        }

        $matchesResponse = $this->actingAs($this->organizer, 'api')
            ->getJson("/api/mini-matches/index/{$tournament->id}");

        $matchesResponse->assertStatus(200);
        $this->assertTrue((bool) $matchesResponse->json('data.is_session_started'));
        $this->assertNotEmpty($matchesResponse->json('data.rounds'));
        $this->assertGreaterThan(0, MiniMatch::where('mini_tournament_id', $tournament->id)->count());
    }

    public function test_start_session_succeeds_for_partner_rotation_when_no_matches_exist_yet(): void
    {
        $tournament = $this->createDoubleTournament(MiniTournament::MATCH_FORMAT_PARTNER_ROTATION, 4);

        $response = $this->actingAs($this->organizer, 'api')
            ->putJson("/api/mini-tournaments/{$tournament->id}/start-session");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.rounds'));
        $this->assertGreaterThan(0, MiniMatch::where('mini_tournament_id', $tournament->id)->count());
    }

    public function test_partner_rotation_rejects_more_than_8_players_and_keeps_null_format_and_false_session(): void
    {
        $tournament = $this->createDoubleTournament(null, 10);
        $tournament->update([
            'match_format' => null,
            'is_session_started' => false,
            'session_status' => MiniTournament::SESSION_STATUS_PENDING_GROUP,
        ]);

        $response = $this->actingAs($this->organizer, 'api')
            ->postJson("/api/mini-tournaments/update/{$tournament->id}", [
                'match_format' => MiniTournament::MATCH_FORMAT_PARTNER_ROTATION,
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('tối đa 8 người', $response->json('message'));

        $tournament->refresh();
        $this->assertNull($tournament->match_format);
        $this->assertFalse((bool) $tournament->is_session_started);
    }

    public function test_partner_rotation_works_up_to_8_players(): void
    {
        $tournament = $this->createDoubleTournament(MiniTournament::MATCH_FORMAT_PARTNER_ROTATION, 6);

        $matchesResponse = $this->actingAs($this->organizer, 'api')
            ->getJson("/api/mini-matches/index/{$tournament->id}");

        $matchesResponse->assertStatus(200);
        $this->assertTrue((bool) $matchesResponse->json('data.is_session_started'));
        $this->assertNotEmpty($matchesResponse->json('data.rounds'));
        $this->assertGreaterThan(0, MiniMatch::where('mini_tournament_id', $tournament->id)->count());
    }
}

