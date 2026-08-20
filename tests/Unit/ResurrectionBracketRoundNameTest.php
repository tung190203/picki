<?php

namespace Tests\Unit;

use App\Models\TournamentType;
use App\Services\TournamentType\BracketService;
use PHPUnit\Framework\TestCase;

class ResurrectionBracketRoundNameTest extends TestCase
{
    private BracketService $bracketService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bracketService = new BracketService();
    }

    /**
     * Test single branch round naming based on pair count.
     */
    public function test_get_round_name_for_single_branch(): void
    {
        $this->assertEquals('Chung kết', $this->bracketService->getRoundName(4, 1, TournamentType::FORMAT_MIXED));
        $this->assertEquals('Bán kết', $this->bracketService->getRoundName(3, 2, TournamentType::FORMAT_MIXED));
        $this->assertEquals('Tứ kết', $this->bracketService->getRoundName(2, 4, TournamentType::FORMAT_MIXED));
        $this->assertEquals('Vòng 1/8', $this->bracketService->getRoundName(1, 8, TournamentType::FORMAT_ELIMINATION));
    }

    /**
     * Test max pair calculation logic when resurrection bracket is present.
     */
    public function test_max_pairs_calculation_with_resurrection_bracket(): void
    {
        // Simulate Round 4 (Finals of Main and Sub brackets)
        // 1 match in main, 1 match in sub -> total 2 matches, but max per branch is 1.
        $sortedMatches = collect([
            (object)['id' => 10, 'bracket_type' => 'main'],
            (object)['id' => 11, 'bracket_type' => 'sub'],
        ]);

        $maxPairsInSingleBranch = $sortedMatches
            ->groupBy(fn($m) => $m->bracket_type ?? 'main')
            ->map(fn($branchMatches) => $branchMatches->chunk(1)->count())
            ->max();

        $this->assertEquals(1, $maxPairsInSingleBranch);
        $this->assertEquals('Chung kết', $this->bracketService->getRoundName(4, $maxPairsInSingleBranch, TournamentType::FORMAT_MIXED));

        // Simulate Round 3 (Semifinals of Main and Sub brackets)
        // 2 matches in main, 2 matches in sub -> total 4 matches, max per branch is 2.
        $sortedMatchesSemi = collect([
            (object)['id' => 6, 'bracket_type' => 'main'],
            (object)['id' => 7, 'bracket_type' => 'main'],
            (object)['id' => 8, 'bracket_type' => 'sub'],
            (object)['id' => 9, 'bracket_type' => 'sub'],
        ]);

        $maxPairsSemi = $sortedMatchesSemi
            ->groupBy(fn($m) => $m->bracket_type ?? 'main')
            ->map(fn($branchMatches) => $branchMatches->chunk(1)->count())
            ->max();

        $this->assertEquals(2, $maxPairsSemi);
        $this->assertEquals('Bán kết', $this->bracketService->getRoundName(3, $maxPairsSemi, TournamentType::FORMAT_MIXED));

        // Simulate Round 2 (Quarterfinals of Main and Sub brackets)
        // 4 matches in main, 4 matches in sub -> total 8 matches, max per branch is 4.
        $sortedMatchesQuarter = collect([
            (object)['id' => 1, 'bracket_type' => 'main'],
            (object)['id' => 2, 'bracket_type' => 'main'],
            (object)['id' => 3, 'bracket_type' => 'main'],
            (object)['id' => 4, 'bracket_type' => 'main'],
            (object)['id' => 5, 'bracket_type' => 'sub'],
            (object)['id' => 6, 'bracket_type' => 'sub'],
            (object)['id' => 7, 'bracket_type' => 'sub'],
            (object)['id' => 8, 'bracket_type' => 'sub'],
        ]);

        $maxPairsQuarter = $sortedMatchesQuarter
            ->groupBy(fn($m) => $m->bracket_type ?? 'main')
            ->map(fn($branchMatches) => $branchMatches->chunk(1)->count())
            ->max();

        $this->assertEquals(4, $maxPairsQuarter);
        $this->assertEquals('Tứ kết', $this->bracketService->getRoundName(2, $maxPairsQuarter, TournamentType::FORMAT_MIXED));
    }
}
