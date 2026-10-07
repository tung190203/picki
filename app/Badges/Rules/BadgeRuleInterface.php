<?php

namespace App\Badges\Rules;

/**
 * Interface BadgeRuleInterface
 *
 * Defines the structure for a badge rule that evaluates whether a user
 * should be awarded a badge based on an event.
 */
interface BadgeRuleInterface
{
    /**
     * The unique code of the badge this rule manages.
     *
     * @return string
     */
    public function badgeCode(): string;

    /**
     * Check if the given event meets the condition to award the badge.
     *
     * @param mixed $event The event object.
     * @return bool
     */
    public function condition($event): bool;

    /**
     * Handle the badge awarding process.
     *
     * @param mixed $event The event object.
     * @return void
     */
    public function handle($event): void;
}
