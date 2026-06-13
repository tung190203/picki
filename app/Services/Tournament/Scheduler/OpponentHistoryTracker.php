<?php

namespace App\Services\Tournament\Scheduler;

/**
 * Tracks opponent encounters between partnerships.
 * Used to minimize repeated matchups when pairing partnerships into doubles matches.
 */
class OpponentHistoryTracker
{
    /** @var array<string, array<string, int>> */
    private array $history = [];

    /**
     * Record that two partnerships faced each other.
     */
    public function increment(string $key1, string $key2): void
    {
        if (!isset($this->history[$key1])) {
            $this->history[$key1] = [];
        }
        if (!isset($this->history[$key2])) {
            $this->history[$key2] = [];
        }

        $this->history[$key1][$key2] = ($this->history[$key1][$key2] ?? 0) + 1;
        $this->history[$key2][$key1] = ($this->history[$key2][$key1] ?? 0) + 1;
    }

    /**
     * Get the encounter score between two partnerships.
     * Lower = less frequently faced = preferred opponent.
     *
     * @return int Number of times the two partnerships have faced each other
     */
    public function getEncounterCount(string $key1, string $key2): int
    {
        return ($this->history[$key1][$key2] ?? 0) + ($this->history[$key2][$key1] ?? 0);
    }

    /**
     * Clear all history (useful for testing or reset).
     */
    public function reset(): void
    {
        $this->history = [];
    }

    /**
     * Get all recorded encounter counts for debugging.
     *
     * @return array<string, array<string, int>>
     */
    public function all(): array
    {
        return $this->history;
    }
}
