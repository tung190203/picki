<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for tournament scoring rules.
 *
 * - `inferScoringType(int $playerPerTeam)` — pure mapping from team size to
 *   the scoring system the engine should use. Server is authoritative; clients
 *   never pick `scoring_type` themselves.
 * - `defaultStagePoints(?int $count)` — ordered integer milestones for Rally
 *   scoring, default 3 stages @ 10/20/30.
 * - `validateStagePoints(array $points)` — enforces the business contract
 *   (count 2-5, strictly increasing, consecutive gap >= 5). Used by the
 *   TournamentType request and controllers; called from anywhere that needs
 *   the same rules.
 */
class TournamentScoringTypeInferrer
{
    public const SIDE_OUT = 'side_out';
    public const RALLY = 'rally';

    /** Allowed values for `tournaments.player_per_team`. */
    public const ALLOWED_PLAYER_PER_TEAM = [2, 3, 4];

    /** `player_per_team >= RALLY_PLAYER_THRESHOLD` ⇒ Rally scoring. */
    public const RALLY_PLAYER_THRESHOLD = 3;

    public const MIN_STAGES = 2;
    public const MAX_STAGES = 5;
    public const MIN_STAGE_GAP = 5;

    public const DEFAULT_STAGES = 3;
    public const DEFAULT_STAGE_VALUES = [10, 20, 30];

    /**
     * Map a `player_per_team` value to the canonical scoring type.
     */
    public function inferScoringType(int $playerPerTeam): string
    {
        if ($playerPerTeam < self::RALLY_PLAYER_THRESHOLD) {
            return self::SIDE_OUT;
        }
        return self::RALLY;
    }

    /**
     * Default ordered milestones for Rally scoring.
     *
     * Returns the first `$count` items of the full default sequence
     * [10, 20, 30, 40, 50] (or a clamped count if out of bounds). Defaults
     * are spaced 10 apart — well above `MIN_STAGE_GAP` (5), so they always
     * pass validation.
     *
     * @return array<int, int>
     */
    public function defaultStagePoints(?int $count = null): array
    {
        $count = $count ?? self::DEFAULT_STAGES;
        $count = max(self::MIN_STAGES, min(self::MAX_STAGES, $count));

        $full = [];
        for ($i = 0; $i < self::MAX_STAGES; $i++) {
            $full[] = self::DEFAULT_STAGE_VALUES[0] + ($i * 10);
        }
        return array_values(array_slice($full, 0, $count));
    }

    /**
     * Validate the stage-point contract for Rally scoring.
     *
     * Rules:
     *  - count in [MIN_STAGES, MAX_STAGES]
     *  - every value is a positive integer
     *  - strictly increasing
     *  - consecutive diff >= MIN_STAGE_GAP
     *
     * @param  array<int, mixed>  $stagePoints
     * @return array<int, int>   The validated, normalized array.
     *
     * @throws ValidationException
     */
    public function validateStagePoints(array $stagePoints): array
    {
        $count = count($stagePoints);

        if ($count < self::MIN_STAGES) {
            throw ValidationException::withMessages([
                'match_rules.stage_points' => [
                    "Số chặng tối thiểu là " . self::MIN_STAGES . " (hiện tại: {$count}).",
                ],
            ]);
        }
        if ($count > self::MAX_STAGES) {
            throw ValidationException::withMessages([
                'match_rules.stage_points' => [
                    "Số chặng tối đa là " . self::MAX_STAGES . " (hiện tại: {$count}).",
                ],
            ]);
        }

        $normalized = [];
        foreach ($stagePoints as $idx => $value) {
            if (!is_numeric($value) || (int) $value != $value) {
                throw ValidationException::withMessages([
                    "match_rules.stage_points.{$idx}" => [
                        "Mốc điểm chặng " . ($idx + 1) . " phải là số nguyên.",
                    ],
                ]);
            }
            $intVal = (int) $value;
            if ($intVal < 1) {
                throw ValidationException::withMessages([
                    "match_rules.stage_points.{$idx}" => [
                        "Mốc điểm chặng " . ($idx + 1) . " phải ≥ 1.",
                    ],
                ]);
            }
            $normalized[] = $intVal;
        }

        for ($i = 1; $i < $count; $i++) {
            $prev = $normalized[$i - 1];
            $curr = $normalized[$i];
            if ($curr <= $prev) {
                throw ValidationException::withMessages([
                    'match_rules.stage_points' => [
                        "Các mốc điểm phải tăng nghiêm ngặt (chặng " . ($i + 1) . " ≤ chặng {$i}).",
                    ],
                ]);
            }
            if (($curr - $prev) < self::MIN_STAGE_GAP) {
                throw ValidationException::withMessages([
                    'match_rules.stage_points' => [
                        "Khoảng cách tối thiểu giữa hai mốc liền kề là " . self::MIN_STAGE_GAP
                            . " điểm (chặng " . ($i + 1) . " - chặng {$i} = " . ($curr - $prev) . ").",
                    ],
                ]);
            }
        }

        return $normalized;
    }
}
