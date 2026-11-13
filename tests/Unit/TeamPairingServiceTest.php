<?php

namespace Tests\Unit;

use App\Services\TournamentType\TeamPairingService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TeamPairingServiceTest extends TestCase
{
    private TeamPairingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TeamPairingService();
    }

    private function makeTeams(array $groupNames): Collection
    {
        $first = collect();
        $second = collect();
        foreach ($groupNames as $idx => $name) {
            $groupId = $idx + 1;
            $first->push((object) [
                'team_id' => $groupId * 10 + 1,
                '_from_group' => $groupId,
                '_rank' => 1,
                'label' => "Nhất {$name}",
            ]);
            $second->push((object) [
                'team_id' => $groupId * 10 + 2,
                '_from_group' => $groupId,
                '_rank' => 2,
                'label' => "Nhì {$name}",
            ]);
        }

        return collect([
            0 => $first,
            1 => $second,
        ]);
    }

    private function makeVirtualTeams(array $groupNames, int $virtualCount): Collection
    {
        $first = collect();
        foreach ($groupNames as $idx => $name) {
            $groupId = $idx + 1;
            $first->push((object) [
                'team_id' => $groupId * 10 + 1,
                '_from_group' => $groupId,
                '_rank' => 1,
                'label' => "Nhất {$name}",
            ]);
        }

        $second = collect();
        for ($v = 1; $v <= $virtualCount; $v++) {
            $second->push((object) [
                'team_id' => null,
                '_from_group' => null,
                '_virtual' => true,
                '_virtual_index' => $v,
                '_rank' => 2,
                'label' => "Nhì tốt nhất #{$v}",
            ]);
        }

        return collect([
            0 => $first,
            1 => $second,
        ]);
    }

    public function test_group_separation_4_groups_matches_expected_bracket(): void
    {
        $advancingByRank = $this->makeTeams(['A', 'B', 'C', 'D']);

        $result = $this->service->arrangeAdvancingTeams(
            $advancingByRank,
            TeamPairingService::PAIRING_MODE_GROUP_SEPARATION
        );

        $labels = $result->pluck('label')->all();

        // T1: Nhất A vs Nhì B, T2: Nhất C vs Nhì D (Nhánh trên)
        // T3: Nhất B vs Nhì A, T4: Nhất D vs Nhì C (Nhánh dưới)
        $this->assertEquals([
            'Nhất A', 'Nhì B',
            'Nhất C', 'Nhì D',
            'Nhất B', 'Nhì A',
            'Nhất D', 'Nhì C',
        ], $labels);

        // Verify same-group teams are in opposite halves (cannot meet before Final)
        $topGroups = $result->slice(0, 4)->pluck('_from_group')->all();
        $bottomGroups = $result->slice(4, 4)->pluck('_from_group')->all();

        $this->assertCount(4, array_unique($topGroups));
        $this->assertCount(4, array_unique($bottomGroups));
    }

    public function test_group_separation_2_groups_separates_branches(): void
    {
        $advancingByRank = $this->makeTeams(['A', 'B']);

        $result = $this->service->arrangeAdvancingTeams(
            $advancingByRank,
            TeamPairingService::PAIRING_MODE_GROUP_SEPARATION
        );

        $this->assertEquals([
            'Nhất A', 'Nhì B',
            'Nhất B', 'Nhì A',
        ], $result->pluck('label')->all());
    }

    public function test_group_separation_8_groups_separates_all_groups_into_two_halves(): void
    {
        $advancingByRank = $this->makeTeams(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);

        $result = $this->service->arrangeAdvancingTeams(
            $advancingByRank,
            TeamPairingService::PAIRING_MODE_GROUP_SEPARATION
        );

        $this->assertCount(16, $result);

        $topGroups = $result->slice(0, 8)->pluck('_from_group')->all();
        $bottomGroups = $result->slice(8, 8)->pluck('_from_group')->all();

        // Every group appears exactly once in Top Half and once in Bottom Half
        $this->assertCount(8, array_unique($topGroups));
        $this->assertCount(8, array_unique($bottomGroups));

        // Check no match pits same group against each other
        for ($i = 0; $i < 16; $i += 2) {
            $this->assertNotEquals(
                $result[$i]->_from_group,
                $result[$i + 1]->_from_group,
                "Match at index {$i} should not have same group"
            );
        }
    }

    public function test_group_separation_odd_groups_preserves_all_teams(): void
    {
        $advancingByRank = $this->makeTeams(['A', 'B', 'C']);

        $result = $this->service->arrangeAdvancingTeams(
            $advancingByRank,
            TeamPairingService::PAIRING_MODE_GROUP_SEPARATION
        );

        $this->assertCount(6, $result);
        $this->assertEquals([
            'Nhất A', 'Nhì B',
            'Nhất B', 'Nhì A',
            'Nhất C', 'Nhì C',
        ], $result->pluck('label')->all());
    }

    public function test_group_separation_with_virtual_second_place_distributes_across_halves(): void
    {
        // 6 groups + 2 Nhì tốt nhất = 8 teams (4 matches: 2 top, 2 bottom)
        $advancingByRank = $this->makeVirtualTeams(['A', 'B', 'C', 'D', 'E', 'F'], 2);

        $result = $this->service->arrangeAdvancingTeams(
            $advancingByRank,
            TeamPairingService::PAIRING_MODE_GROUP_SEPARATION
        );

        $this->assertCount(8, $result);

        $topVirtual = $result->slice(0, 4)->filter(fn ($t) => !empty($t->_virtual))->count();
        $bottomVirtual = $result->slice(4, 4)->filter(fn ($t) => !empty($t->_virtual))->count();

        $this->assertEquals(1, $topVirtual);
        $this->assertEquals(1, $bottomVirtual);
    }

    public function test_group_separation_single_team_per_group(): void
    {
        $advancingByRank = collect([
            0 => collect([
                (object) ['team_id' => 11, '_from_group' => 1, 'label' => 'Nhất A'],
                (object) ['team_id' => 21, '_from_group' => 2, 'label' => 'Nhất B'],
                (object) ['team_id' => 31, '_from_group' => 3, 'label' => 'Nhất C'],
                (object) ['team_id' => 41, '_from_group' => 4, 'label' => 'Nhất D'],
            ]),
        ]);

        $result = $this->service->arrangeAdvancingTeams(
            $advancingByRank,
            TeamPairingService::PAIRING_MODE_GROUP_SEPARATION
        );

        $this->assertCount(4, $result);
        $this->assertCount(4, $result->pluck('_from_group')->unique());
    }
}

