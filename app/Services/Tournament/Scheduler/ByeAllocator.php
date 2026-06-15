<?php

namespace App\Services\Tournament\Scheduler;

/**
 * Tracks BYE counts per partnership and per player.
 * Used to distribute BYE matches as fairly as possible:
 * - Prioritize partnerships with fewer BYE counts.
 * - Tie-break randomly.
 */
class ByeAllocator
{
    /** @var array<string, int> partnership key => bye count */
    private array $partnershipByeCount = [];

    /** @var array<int, int> player id => bye count */
    private array $playerByeCount = [];

    /**
     * Record a BYE for the given partnership and its players.
     *
     * @param string $key Partnership key (e.g. "1-101")
     * @param array<int> $players List of player IDs in the partnership
     */
    public function recordBye(string $key, array $players): void
    {
        $this->partnershipByeCount[$key] = ($this->partnershipByeCount[$key] ?? 0) + 1;
        foreach ($players as $pid) {
            $this->playerByeCount[$pid] = ($this->playerByeCount[$pid] ?? 0) + 1;
        }
    }

    /**
     * Get the bye count for a partnership.
     */
    public function getPartnershipByeCount(string $key): int
    {
        return $this->partnershipByeCount[$key] ?? 0;
    }

    /**
     * Get the bye count for a player.
     */
    public function getPlayerByeCount(int $playerId): int
    {
        return $this->playerByeCount[$playerId] ?? 0;
    }

    /**
     * Sort indexed partnerships in-place by fewest BYE count, random tie-break.
     * Returns the sorted array (array is modified by reference as well).
     *
     * @param array<array{p: mixed, key: string}> $indexed
     * @return array<array{p: mixed, key: string}>
     */
    public function sortByFewestBye(array &$indexed): array
    {
        usort($indexed, function ($a, $b) {
            $scoreA = $this->partnershipByeCount[$a['key']] ?? 0;
            $scoreB = $this->partnershipByeCount[$b['key']] ?? 0;
            if ($scoreA !== $scoreB) {
                return $scoreA - $scoreB;
            }
            return mt_rand(-1, 1);
        });
        return $indexed;
    }

    /**
     * Get the range of bye counts across all tracked partnerships.
     *
     * @return array{min: int, max: int}
     */
    public function byeRange(): array
    {
        $counts = array_values($this->partnershipByeCount);
        if (empty($counts)) {
            return ['min' => 0, 'max' => 0];
        }
        return [
            'min' => min($counts),
            'max' => max($counts),
        ];
    }

    /**
     * Get all tracked partnership bye counts.
     *
     * @return array<string, int>
     */
    public function allPartnershipByeCounts(): array
    {
        return $this->partnershipByeCount;
    }

    /**
     * Get all tracked player bye counts.
     *
     * @return array<int, int>
     */
    public function allPlayerByeCounts(): array
    {
        return $this->playerByeCount;
    }
}
