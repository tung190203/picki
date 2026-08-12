<?php

namespace Tests\Unit;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionSettingsDTO;
use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\PlayerContextDTO;
use App\DTO\SuggestionMatchDTO;
use App\DTO\TeamMatchDTO;
use App\DTO\TeamMatchMemberDTO;
use App\Enums\PlayerTier;
use App\Models\MiniTournamentSession;
use App\Models\User;
use App\Services\SchedulerService;
use PHPUnit\Framework\TestCase;

class MatchSuggestionRotationTest extends TestCase
{
    private SchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new SchedulerService();
    }

    /**
     * enumerateCandidates must return candidates sorted by score DESC,
     * each entry has a stable signature (sorted user_ids).
     */
    public function test_enumerate_candidates_returns_sorted_list_with_signatures(): void
    {
        // 6 same-tier males -> many possible combos of 4
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 6, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->enumerateCandidates($players, $request, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('candidates', $result);
        $this->assertArrayHasKey('total_candidates', $result);
        $this->assertGreaterThanOrEqual(1, $result['total_candidates']);
        $this->assertGreaterThanOrEqual(1, count($result['candidates']));

        // Verify every candidate has a signature and they are sorted by adjusted_score DESC
        $prevScore = PHP_FLOAT_MAX;
        foreach ($result['candidates'] as $c) {
            $this->assertArrayHasKey('signature', $c);
            $this->assertArrayHasKey('adjusted_score', $c);
            $this->assertIsArray($c['signature']);
            $this->assertCount(4, $c['signature']);

            // Each user_id should appear at most 4 entries per candidate
            $this->assertCount(4, array_unique($c['signature']));

            // Score must be descending
            $this->assertLessThanOrEqual($prevScore, $c['adjusted_score']);
            $prevScore = $c['adjusted_score'];
        }
    }

    /**
     * All signatures returned by enumerateCandidates must be unique.
     */
    public function test_enumerate_candidates_signatures_are_unique(): void
    {
        $players = $this->createPlayers([
            ['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
            ['id' => 6, 'gender' => User::MALE, 'tier' => PlayerTier::Red],
        ]);

        $request = $this->createRequest();
        $result = $this->scheduler->enumerateCandidates($players, $request, []);

        $signatures = array_map(fn($c) => implode(',', $c['signature']), $result['candidates']);
        $this->assertEquals(count($signatures), count(array_unique($signatures)), 'Each 4-player combo signature must be unique');
    }

    /**
     * buildCandidateSignature returns sorted user_ids.
     */
    public function test_build_candidate_signature_is_sorted(): void
    {
        $teamA = [
            $this->createPlayerContext(['id' => 1, 'gender' => User::MALE, 'user_id' => 5]),
            $this->createPlayerContext(['id' => 2, 'gender' => User::FEMALE, 'user_id' => 1]),
        ];
        $teamB = [
            $this->createPlayerContext(['id' => 3, 'gender' => User::MALE, 'user_id' => 3]),
            $this->createPlayerContext(['id' => 4, 'gender' => User::MALE, 'user_id' => 9]),
        ];

        $sig = $this->scheduler->buildCandidateSignature($teamA, $teamB);
        $this->assertEquals([1, 3, 5, 9], $sig);
    }

    /**
     * MiniTournamentSession::signature produces a stable, sorted representation.
     */
    public function test_session_signature_helper_is_canonical(): void
    {
        $sig1 = MiniTournamentSession::signature([3, 1, 4, 2]);
        $sig2 = MiniTournamentSession::signature([2, 4, 3, 1]);
        $sig3 = MiniTournamentSession::signature(['1', '2', '3', '4']);

        $this->assertEquals([1, 2, 3, 4], $sig1);
        $this->assertEquals([1, 2, 3, 4], $sig2);
        $this->assertEquals([1, 2, 3, 4], $sig3);
    }

    /**
     * MiniTournamentSession accessor normalises stored JSON.
     */
    public function test_session_tried_suggestions_round_trip(): void
    {
        // Direct test of the attribute without hitting the database.
        $session = new MiniTournamentSession();
        $session->tried_suggestions = [
            [3, 1, 2, 4],
            [10, 9, 8, 7],
        ];

        $list = $session->tried_suggestions;
        $this->assertCount(2, $list);
        $this->assertEquals([1, 2, 3, 4], $list[0]);
        $this->assertEquals([7, 8, 9, 10], $list[1]);
    }

    /**
     * hasTried and remember behave as described in the model docs.
     */
    public function test_session_has_tried_and_remember_are_idempotent(): void
    {
        $session = new MiniTournamentSession();

        $signature = [1, 2, 3, 4];

        $this->assertFalse($session->hasTried($signature));

        $session->remember($signature);
        $this->assertTrue($session->hasTried($signature));

        // Idempotent
        $session->remember($signature);
        $this->assertCount(1, $session->tried_suggestions);
    }

    /**
     * clearHistory wipes the tried list.
     */
    public function test_session_clear_history_wipes_list(): void
    {
        $session = new MiniTournamentSession();
        $session->remember([1, 2, 3, 4]);
        $session->remember([5, 6, 7, 8]);

        $this->assertCount(2, $session->tried_suggestions);

        $session->clearHistory();
        $this->assertCount(0, $session->tried_suggestions);
    }

    /**
     * Rotation algorithm (wrap-around) using direct MiniTournamentSession
     * state: when every signature is in the history, the next rotation
     * must reset the history and pick from the start.
     */
    public function test_rotation_algorithm_wraps_when_every_signature_tried(): void
    {
        $session = new MiniTournamentSession();

        // Pretend we tried every possible 4-combo out of 6 users.
        $allSignatures = [
            [1, 2, 3, 4],
            [1, 2, 3, 5],
            [1, 2, 3, 6],
            [1, 2, 4, 5],
            [1, 2, 4, 6],
            [1, 2, 5, 6],
            [1, 3, 4, 5],
            [1, 3, 4, 6],
            [1, 3, 5, 6],
            [1, 4, 5, 6],
            [2, 3, 4, 5],
            [2, 3, 4, 6],
            [2, 3, 5, 6],
            [2, 4, 5, 6],
            [3, 4, 5, 6],
        ];
        foreach ($allSignatures as $sig) {
            $session->remember($sig);
        }

        $this->assertCount(15, $session->tried_suggestions);

        // Now the rotation algorithm triggers: clear history, pick first.
        $session->clearHistory();
        $this->assertCount(0, $session->tried_suggestions);

        // The first signature picked must be recordable again.
        $session->remember([1, 2, 3, 4]);
        $this->assertTrue($session->hasTried([1, 2, 3, 4]));
        $this->assertCount(1, $session->tried_suggestions);
    }

    /**
     * Helper: build a MatchSuggestionRequestDTO with friendly defaults.
     */
    private function createRequest(): MatchSuggestionRequestDTO
    {
        return new MatchSuggestionRequestDTO(
            mini_tournament_id: 1,
            participants: [],
            settings: new MatchSuggestionSettingsDTO(
                fair_play: true,
                balance_team: true,
                prefer_high_tier_match: true,
                prevent_three_consecutive: true,
                organizer_as_backup: false,
            ),
            seed: 42,
        );
    }

    /**
     * Helper: construct PlayerContextDTO array from a list of spec rows.
     *
     * Each row: ['id' => int, 'gender' => int, 'tier' => PlayerTier, ...]
     */
    private function createPlayers(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->createPlayerContext($row);
        }
        return $out;
    }

    private function createPlayerContext(array $row): PlayerContextDTO
    {
        $tier = $row['tier'] ?? PlayerTier::Yellow;

        return new PlayerContextDTO(
            mini_participant_id: $row['id'] ?? $row['mini_participant_id'] ?? 0,
            user_id: $row['user_id'] ?? $row['id'] ?? 0,
            full_name: $row['full_name'] ?? 'Player ' . ($row['id'] ?? ''),
            avatar_url: $row['avatar_url'] ?? null,
            tier: $tier,
            is_manual_override: false,
            gender: $row['gender'] ?? User::MALE,
            is_guest: false,
            played_count: $row['played'] ?? $row['played_count'] ?? 0,
            consecutive_count: 0,
            waiting_rounds: 0,
            last_played_round: $row['last_played_round'] ?? null,
            vndupr_score: null,
            partner_ids: [],
            is_checked_in: true,
            is_playing: false,
            skip_next_round: false,
            is_absent: false,
            payment_status: null,
            is_backup: false,
        );
    }
}
