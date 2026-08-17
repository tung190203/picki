<?php

namespace Tests\Unit;

use App\DTO\MatchSuggestionRequestDTO;
use App\DTO\MatchSuggestionSettingsDTO;
use App\DTO\ParticipantTierDTO;
use App\DTO\PlayerContextDTO;
use App\Enums\PlayerTier;
use App\Models\User;
use App\Services\SchedulerService;
use PHPUnit\Framework\TestCase;

class NextMatchFairnessTest extends TestCase
{
    private SchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new SchedulerService();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function createPlayer(array $config): PlayerContextDTO
    {
        return new PlayerContextDTO(
            mini_participant_id: $config['id'],
            user_id: $config['user_id'] ?? $config['id'],
            full_name: $config['name'] ?? 'Player ' . ($config['id'] ?? 0),
            avatar_url: null,
            tier: $config['tier'] ?? PlayerTier::Yellow,
            is_manual_override: true,
            gender: $config['gender'] ?? User::MALE,
            is_guest: $config['is_guest'] ?? false,
            played_count: $config['played'] ?? 0,
            consecutive_count: $config['consecutive'] ?? 0,
            waiting_rounds: $config['waiting'] ?? 0,
            last_played_round: $config['last_round'] ?? null,
            vndupr_score: $config['vndupr'] ?? null,
            partner_ids: [],
            is_checked_in: true,
            is_playing: false,
            skip_next_round: false,
            is_absent: false,
            payment_status: null,
            is_backup: $config['backup'] ?? false,
        );
    }

    private function createRequest(?int $seed = null, ?int $anchorUserId = null): MatchSuggestionRequestDTO
    {
        return new MatchSuggestionRequestDTO(
            mini_tournament_id: 1,
            participants: [],
            settings: new MatchSuggestionSettingsDTO(
                fair_play: true,
                balance_team: true,
                prefer_high_tier_match: false,
                prevent_three_consecutive: true,
                organizer_as_backup: false,
            ),
            seed: $seed,
            exclude_player_ids: null,
            anchor_participant_id: null,
            anchor_user_id: $anchorUserId,
        );
    }

    private function createRequestWithSettings(MatchSuggestionSettingsDTO $settings, ?int $seed = null): MatchSuggestionRequestDTO
    {
        return new MatchSuggestionRequestDTO(
            mini_tournament_id: 1,
            participants: [],
            settings: $settings,
            seed: $seed,
            exclude_player_ids: null,
            anchor_participant_id: null,
            anchor_user_id: null,
        );
    }

    // -------------------------------------------------------------------------
    // Test: Người màu lẻ không bị bỏ đói (BUG v1)
    //
    // Setup: 4M đỏ + 4F vàng + 1M vàng (lẻ) + 1F đỏ (lẻ)
    //
    // v1 buggy behavior: cứ luân phiên chọn 4M đỏ và 4F vàng → 2 người lẻ
    //                    bị bỏ đói mãi mãi.
    // v2 fixed behavior: người 0 trận (F đỏ) đứng đầu queue, cửa sổ mở rộng
    //                     tới khi ghép được → F đỏ + 1F vàng + 1M đỏ + 1M vàng.
    // -------------------------------------------------------------------------

    public function test_odd_color_player_not_starved(): void
    {
        $players = [
            // Nhóm 4M đỏ — đã chơi nhiều trận
            $this->createPlayer(['id' => 1, 'gender' => User::MALE,   'tier' => PlayerTier::Red,    'played' => 3, 'name' => 'M-Red-A']),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE,   'tier' => PlayerTier::Red,    'played' => 3, 'name' => 'M-Red-B']),
            $this->createPlayer(['id' => 3, 'gender' => User::MALE,   'tier' => PlayerTier::Red,    'played' => 3, 'name' => 'M-Red-C']),
            $this->createPlayer(['id' => 4, 'gender' => User::MALE,   'tier' => PlayerTier::Red,    'played' => 3, 'name' => 'M-Red-D']),
            // Nhóm 4F vàng — đã chơi nhiều trận
            $this->createPlayer(['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3, 'name' => 'F-Yellow-A']),
            $this->createPlayer(['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3, 'name' => 'F-Yellow-B']),
            $this->createPlayer(['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3, 'name' => 'F-Yellow-C']),
            $this->createPlayer(['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3, 'name' => 'F-Yellow-D']),
            // Người lẻ: M vàng (ít trận) + F đỏ (ít trận nhất)
            $this->createPlayer(['id' => 9,  'gender' => User::MALE,   'tier' => PlayerTier::Yellow, 'played' => 0, 'name' => 'M-Yellow-Lone']),
            $this->createPlayer(['id' => 10, 'gender' => User::FEMALE, 'tier' => PlayerTier::Red,    'played' => 0, 'name' => 'F-Red-Lone']),
        ];

        $request = $this->createRequest();
        $response = $this->scheduler->generate($players, $request, []);

        $this->assertNotNull($response->match, 'Must return a match');

        $selectedIds = [];
        foreach ($response->match->team1->members as $m) $selectedIds[] = $m->mini_participant_id;
        foreach ($response->match->team2->members as $m) $selectedIds[] = $m->mini_participant_id;

        // ID 10 (F-Red-Lone, 0 trận) phải được chọn — công bằng số trận
        $this->assertContains(10, $selectedIds,
            'F-Red-Lone (0 matches) must be selected — fair play');
    }

    // -------------------------------------------------------------------------
    // Test: Anchor phải có mặt trong trận được chọn (BUG v3)
    //
    // Setup: Nữ đứng đầu queue (anchor), cửa sổ 4 người đầu có đủ 4 nam.
    //
    // v3 buggy behavior (trước fix): chọn trận 4M → bỏ sót nữ anchor.
    // v3 fixed behavior: thuật toán tìm trận trong cửa sổ phải CHỨA anchor,
    //                     không chọn trận toàn giới khác.
    // -------------------------------------------------------------------------

    public function test_anchor_must_be_in_match(): void
    {
        // ID order after fairness sort: 1F (0 matches), 2M (0), 3M (0), 4M (0), 5F (1), 6M (1), 7F (1), 8M (1)
        $players = [
            $this->createPlayer(['id' => 1, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green,  'played' => 0, 'name' => 'F-Green-A']),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE,   'tier' => PlayerTier::Green,  'played' => 0, 'name' => 'M-Green-A']),
            $this->createPlayer(['id' => 3, 'gender' => User::MALE,   'tier' => PlayerTier::Green,  'played' => 0, 'name' => 'M-Green-B']),
            $this->createPlayer(['id' => 4, 'gender' => User::MALE,   'tier' => PlayerTier::Green,  'played' => 0, 'name' => 'M-Green-C']),
            $this->createPlayer(['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green,  'played' => 1, 'name' => 'F-Green-B']),
            $this->createPlayer(['id' => 6, 'gender' => User::MALE,   'tier' => PlayerTier::Green,  'played' => 1, 'name' => 'M-Green-D']),
            $this->createPlayer(['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green,  'played' => 1, 'name' => 'F-Green-C']),
            $this->createPlayer(['id' => 8, 'gender' => User::MALE,   'tier' => PlayerTier::Green,  'played' => 1, 'name' => 'M-Green-E']),
        ];

        $request = $this->createRequest(anchorUserId: 1);
        $response = $this->scheduler->generate($players, $request, []);

        $this->assertNotNull($response->match, 'Must return a match');

        $selectedIds = [];
        foreach ($response->match->team1->members as $m) $selectedIds[] = $m->mini_participant_id;
        foreach ($response->match->team2->members as $m) $selectedIds[] = $m->mini_participant_id;

        // Anchor (ID=1, F-Green-A, 0 matches) phải trong trận
        $this->assertContains(1, $selectedIds,
            'Anchor player (ID=1, F with 0 matches) must be in the match');
    }

    // -------------------------------------------------------------------------
    // Test: played_count là tiêu chí ưu tiên SỐ 1
    //
    // Setup: 1 người Purple 5 trận + 1 người Green 0 trận cùng giới.
    // Expected: người Green 0 trận phải được ưu tiên, bất kể tier.
    // -------------------------------------------------------------------------

    public function test_played_count_is_primary_sort(): void
    {
        $players = [
            $this->createPlayer(['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Purple, 'played' => 5, 'name' => 'M-Purple']),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Green,  'played' => 0, 'name' => 'M-Green']),
            $this->createPlayer(['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red,    'played' => 5, 'name' => 'M-Red']),
            $this->createPlayer(['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 5, 'name' => 'M-Yellow']),
            $this->createPlayer(['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0, 'name' => 'F-Green-A']),
            $this->createPlayer(['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 0, 'name' => 'F-Yellow-A']),
            $this->createPlayer(['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 3, 'name' => 'F-Green-B']),
            $this->createPlayer(['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3, 'name' => 'F-Yellow-B']),
        ];

        $request = $this->createRequest();
        $response = $this->scheduler->generate($players, $request, []);

        $this->assertNotNull($response->match);

        $selectedIds = [];
        foreach ($response->match->team1->members as $m) $selectedIds[] = $m->mini_participant_id;
        foreach ($response->match->team2->members as $m) $selectedIds[] = $m->mini_participant_id;

        // Người 0 trận (ID 2, 5, 6) phải được chọn trước người 5 trận
        $zeroMatchSelected = array_intersect([2, 5, 6], $selectedIds);
        $this->assertNotEmpty($zeroMatchSelected,
            'Players with 0 matches (Green-M, Green-F, Yellow-F) must be prioritized over Purple with 5 matches');
    }

    // -------------------------------------------------------------------------
    // Test: prevent_three_consecutive vẫn hoạt động
    // -------------------------------------------------------------------------

    public function test_prevent_three_consecutive_still_applies(): void
    {
        $players = [
            // consecutive=2 → không thể chơi thêm (sắp thành 3 liên tiếp)
            $this->createPlayer(['id' => 1, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'consecutive' => 2, 'played' => 5]),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'consecutive' => 2, 'played' => 5]),
            $this->createPlayer(['id' => 3, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'consecutive' => 2, 'played' => 5]),
            $this->createPlayer(['id' => 4, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'consecutive' => 2, 'played' => 5]),
            // consecutive=1 → có thể chơi (sẽ thành 2)
            $this->createPlayer(['id' => 5, 'gender' => User::MALE,   'tier' => PlayerTier::Yellow, 'consecutive' => 1, 'played' => 0]),
            $this->createPlayer(['id' => 6, 'gender' => User::MALE,   'tier' => PlayerTier::Yellow, 'consecutive' => 1, 'played' => 0]),
            $this->createPlayer(['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green,  'consecutive' => 1, 'played' => 0]),
            $this->createPlayer(['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green,  'consecutive' => 1, 'played' => 0]),
        ];

        $request = $this->createRequest();
        $response = $this->scheduler->generate($players, $request, []);

        $this->assertNotNull($response->match);

        $selectedIds = [];
        foreach ($response->match->team1->members as $m) $selectedIds[] = $m->mini_participant_id;
        foreach ($response->match->team2->members as $m) $selectedIds[] = $m->mini_participant_id;

        // IDs 1-4 có consecutive=2 phải bị loại
        $blockedSelected = array_intersect([1, 2, 3, 4], $selectedIds);
        $this->assertEmpty($blockedSelected,
            'Players with consecutive_count=2 must be excluded from pool');
    }

    // -------------------------------------------------------------------------
    // Test: Shuffle seed không phá fairness sort khi fair_play=true
    // -------------------------------------------------------------------------

    public function test_same_seed_produces_deterministic_results(): void
    {
        $players = [
            $this->createPlayer(['id' => 1, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 3, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 4, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 5, 'gender' => User::MALE,   'tier' => PlayerTier::Yellow, 'played' => 1]),
            $this->createPlayer(['id' => 6, 'gender' => User::MALE,   'tier' => PlayerTier::Yellow, 'played' => 1]),
            $this->createPlayer(['id' => 7, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 1]),
            $this->createPlayer(['id' => 8, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 1]),
        ];

        $response1 = $this->scheduler->generate($players, $this->createRequest(12345), []);
        $response2 = $this->scheduler->generate($players, $this->createRequest(12345), []);

        $ids1 = [];
        foreach ($response1->match->team1->members as $m) $ids1[] = $m->id;
        foreach ($response1->match->team2->members as $m) $ids1[] = $m->id;
        sort($ids1);

        $ids2 = [];
        foreach ($response2->match->team1->members as $m) $ids2[] = $m->id;
        foreach ($response2->match->team2->members as $m) $ids2[] = $m->id;
        sort($ids2);

        $this->assertEquals($ids1, $ids2,
            'Same seed must produce deterministic match selection');
    }

    // -------------------------------------------------------------------------
    // Test: enumerateCandidates trả về đúng anchor index priority cho regenerate
    // -------------------------------------------------------------------------

    public function test_enumerate_candidates_anchor_priority(): void
    {
        $players = [
            // Anchor: ID=1, F, 0 trận
            $this->createPlayer(['id' => 1, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0]),
            // ID=2, M, 0 trận
            $this->createPlayer(['id' => 2, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 3, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 4, 'gender' => User::MALE,   'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 5, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3]),
            $this->createPlayer(['id' => 6, 'gender' => User::FEMALE, 'tier' => PlayerTier::Yellow, 'played' => 3]),
            $this->createPlayer(['id' => 7, 'gender' => User::MALE,   'tier' => PlayerTier::Yellow, 'played' => 3]),
            $this->createPlayer(['id' => 8, 'gender' => User::MALE,   'tier' => PlayerTier::Yellow, 'played' => 3]),
        ];

        $request = $this->createRequest();
        $evaluation = $this->scheduler->enumerateCandidates($players, $request, []);

        $this->assertNotEmpty($evaluation['candidates'], 'Must have candidates');

        $firstCandidate = $evaluation['candidates'][0];

        $candidateIds = [];
        foreach ($firstCandidate['team_a'] as $p) $candidateIds[] = $p->user_id;
        foreach ($firstCandidate['team_b'] as $p) $candidateIds[] = $p->user_id;

        // Anchor (ID=1) phải trong candidate đầu tiên
        $this->assertContains(1, $candidateIds,
            'First candidate must include anchor (ID=1)');
    }

    // -------------------------------------------------------------------------
    // Test: played_count là tiêu chí công bằng TUYỆT ĐỐI
    //
    // Mọi người đều được chơi bằng nhau → candidate chứa người ít trận nhất thắng
    // -------------------------------------------------------------------------
    public function test_fairness_played_count_is_absolute_priority(): void
    {
        $players = [
            // Purple đã đấu 5 trận, Green 0 trận
            $this->createPlayer(['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Purple, 'played' => 5]),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Purple, 'played' => 5]),
            $this->createPlayer(['id' => 3, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0]),
            $this->createPlayer(['id' => 4, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0]),
        ];
        $request = $this->createRequest();
        $response = $this->scheduler->generate($players, $request, []);
        $this->assertNotNull($response->match);
        $selectedIds = [];
        foreach ($response->match->team1->members as $m) $selectedIds[] = $m->mini_participant_id;
        foreach ($response->match->team2->members as $m) $selectedIds[] = $m->mini_participant_id;
        $this->assertContains(3, $selectedIds);
        $this->assertContains(4, $selectedIds);
    }

    // -------------------------------------------------------------------------
    // Test: Last-resort bắt buộc ghép trận khi còn đủ 4 người nhưng không thỏa
    // luật giới tính hoặc còng màu
    // -------------------------------------------------------------------------
    public function test_mixed_gender_last_resort_always_matches_when_pool_ge_4(): void
    {
        // 3M + 1F: không thỏa luật 2+2, nhưng vẫn phải ghép được trận
        $players = [
            $this->createPlayer(['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0]),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0]),
            $this->createPlayer(['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Yellow, 'played' => 0]),
            $this->createPlayer(['id' => 4, 'gender' => User::FEMALE, 'tier' => PlayerTier::Green, 'played' => 0]),
        ];
        $request = $this->createRequest();
        $response = $this->scheduler->generate($players, $request, []);
        $this->assertNotNull($response->match, 'Must match 3M+1F as last resort');
    }

    // -------------------------------------------------------------------------
    // Test: Organizer bị loại khi organizer_as_backup = false
    // -------------------------------------------------------------------------
    public function test_organizer_excluded_when_organizer_as_backup_false(): void
    {
        $players = [
            // Organizers (should be excluded when organizer_as_backup=false)
            $this->createPlayer(['id' => 1, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0, 'backup' => true]),
            $this->createPlayer(['id' => 2, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0, 'backup' => true]),
            // Non-organizers (enough for a full match after organizers are filtered)
            $this->createPlayer(['id' => 3, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0, 'backup' => false]),
            $this->createPlayer(['id' => 4, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0, 'backup' => false]),
            $this->createPlayer(['id' => 5, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0, 'backup' => false]),
            $this->createPlayer(['id' => 6, 'gender' => User::MALE, 'tier' => PlayerTier::Red, 'played' => 0, 'backup' => false]),
        ];
        $settings = new \App\DTO\MatchSuggestionSettingsDTO(
            fair_play: true,
            balance_team: true,
            prefer_high_tier_match: false,
            prevent_three_consecutive: true,
            organizer_as_backup: false,
        );
        $request = $this->createRequestWithSettings($settings);
        $response = $this->scheduler->generate($players, $request, []);
        $this->assertNotNull($response->match, 'Must return match from non-organizers');
        $selectedIds = [];
        foreach ($response->match->team1->members as $m) $selectedIds[] = $m->mini_participant_id;
        foreach ($response->match->team2->members as $m) $selectedIds[] = $m->mini_participant_id;
        $this->assertNotContains(1, $selectedIds, 'Organizer #1 should not be selected');
        $this->assertNotContains(2, $selectedIds, 'Organizer #2 should not be selected');
    }
}
