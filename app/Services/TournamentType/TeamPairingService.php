<?php

namespace App\Services\TournamentType;

use Illuminate\Support\Collection;

/**
 * Service xử lý logic pairing teams cho knockout stage
 */
class TeamPairingService
{
    // Pairing modes
    const PAIRING_MODE_SEQUENTIAL = 'sequential';  // Tuần tự: A-B, C-D, E-F, G-H
    const PAIRING_MODE_SYMMETRIC = 'symmetric';    // Đối xứng: A-H, B-G, C-F, D-E
    const PAIRING_MODE_MANUAL = 'manual';

    /**
     * Sắp xếp đội advancing theo mode đã chọn
     */
    public function arrangeAdvancingTeams(
        $advancingByRank,
        ?string $pairingMode = null,
        ?array $manualPairings = null
    ): Collection {
        // Normalize: Trim và lowercase
        $pairingMode = $pairingMode ? strtolower(trim($pairingMode)) : null;

        return match ($pairingMode) {
            self::PAIRING_MODE_SYMMETRIC, 'symmetric' => $this->arrangeSymmetric($advancingByRank),
            self::PAIRING_MODE_MANUAL, 'manual' => $this->arrangeManual($advancingByRank, $manualPairings),
            default => $this->arrangeSequential($advancingByRank),
        };
    }

    /**
     * Pattern: Nhất A vs Nhì B, Nhất B vs Nhì A, Nhất C vs Nhì D, Nhất D vs Nhì C
     * Ví dụ 8 bảng: A-B, B-A, C-D, D-C, E-F, F-E, G-H, H-G
     */
    private function arrangeSequential($advancingByRank): Collection
    {
        $advancing = collect();

        $firstPlaceTeams = $advancingByRank->get(0, collect());
        $secondPlaceTeams = $advancingByRank->get(1, collect());

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        // Pattern tuần tự
        for ($i = 0; $i < max($numFirstPlace, $numSecondPlace); $i += 2) {
            // Cặp thứ i: Nhất[i] vs Nhì[i+1]
            if ($i < $numFirstPlace) {
                $advancing->push($firstPlaceTeams->get($i));
            }
            if (($i + 1) < $numSecondPlace) {
                $advancing->push($secondPlaceTeams->get($i + 1));
            }

            // Cặp thứ i+1: Nhất[i+1] vs Nhì[i]
            if (($i + 1) < $numFirstPlace) {
                $advancing->push($firstPlaceTeams->get($i + 1));
            }
            if ($i < $numSecondPlace) {
                $advancing->push($secondPlaceTeams->get($i));
            }
        }

        // Xử lý các hạng còn lại (hạng 3, 4...)
        foreach ($advancingByRank as $rank => $teamsAtRank) {
            if ($rank < 2) continue;
            foreach ($teamsAtRank as $team) {
                $advancing->push($team);
            }
        }

        return $advancing;
    }

    /**
     * Pattern: Nhất A vs Nhì H, Nhất B vs Nhì G, Nhất C vs Nhì F, Nhất D vs Nhì E
     * Ví dụ 8 bảng: A-H, B-G, C-F, D-E, E-D, F-C, G-B, H-A
     * Khi chỉ có 1 đội/bảng (numAdvancing=1): symmetric pair đầu vs cuối → A-D, B-C (4 bảng)
     */
    private function arrangeSymmetric($advancingByRank): Collection
    {
        $firstPlaceTeams = $advancingByRank->get(0, collect());
        $secondPlaceTeams = $advancingByRank->get(1, collect());

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        // === KHI CHỈ CÓ 1 ĐỘI/BẢNG (numSecondPlace === 0) ===
        // Symmetric pair đầu vs cuối: [A1, B1, C1, D1] → [A1, D1, B1, C1] → (A-D), (B-C)
        if ($numSecondPlace === 0 && $numFirstPlace > 0) {
            $reordered = collect();
            $numPairs = intdiv($numFirstPlace, 2);
            for ($i = 0; $i < $numPairs; $i++) {
                $reordered->push($firstPlaceTeams->get($i));
                $reordered->push($firstPlaceTeams->get($numFirstPlace - 1 - $i));
            }
            if ($numFirstPlace % 2 === 1) {
                $reordered->push($firstPlaceTeams->get($numPairs));
            }
            return $reordered;
        }

        // === EXISTING LOGIC: Nhất vs Nhì theo pattern đối xứng ===
        $advancing = collect();
        for ($i = 0; $i < max($numFirstPlace, $numSecondPlace); $i++) {
            if ($i < $numFirstPlace) {
                $advancing->push($firstPlaceTeams->get($i));
            }
            $oppositeIndex = $numSecondPlace - 1 - $i;
            if ($oppositeIndex >= 0 && $oppositeIndex < $numSecondPlace) {
                $advancing->push($secondPlaceTeams->get($oppositeIndex));
            }
        }

        foreach ($advancingByRank as $rank => $teamsAtRank) {
            if ($rank < 2) continue;
            foreach ($teamsAtRank as $team) {
                $advancing->push($team);
            }
        }

        return $advancing;
    }

    /**
     * Sắp xếp theo danh sách thủ công
     * $manualPairings format:
     * [
     *   ['group_id' => 587, 'rank' => 1, 'position' => 0],  // group_id = database ID (_from_group)
     *   ['group_id' => 588, 'rank' => 2, 'position' => 1],
     *   ...
     * ]
     */
    private function arrangeManual($advancingByRank, ?array $manualPairings): Collection
    {
        if (empty($manualPairings)) {
            return $this->arrangeSequential($advancingByRank);
        }

        $advancing = collect();

        // Map theo database ID (_from_group)
        $teamMap = [];
        foreach ($advancingByRank as $rank => $teamsAtRank) {
            foreach ($teamsAtRank as $team) {
                if ($team->_from_group !== null) {
                    $key = "{$team->_from_group}_{$rank}";
                    $teamMap[$key] = $team;
                }
            }
        }

        usort($manualPairings, fn($a, $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        foreach ($manualPairings as $pairing) {
            $groupId = $pairing['group_id'] ?? null;
            $rank = (int)($pairing['rank'] ?? 1) - 1;  // Convert 1-based (frontend) sang 0-based
            $key = "{$groupId}_{$rank}";

            if (isset($teamMap[$key])) {
                $advancing->push($teamMap[$key]);
            }
        }

        return $advancing;
    }
}
