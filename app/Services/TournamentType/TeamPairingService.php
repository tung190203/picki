<?php

namespace App\Services\TournamentType;

use Illuminate\Support\Collection;

/**
 * Service xử lý logic pairing teams cho knockout stage
 */
class TeamPairingService
{
    // Pairing modes
    const PAIRING_MODE_SEQUENTIAL = 'sequential';              // Tuần tự: A-B, C-D, E-F, G-H
    const PAIRING_MODE_SYMMETRIC = 'symmetric';                // Đối xứng: A-H, B-G, C-F, D-E
    const PAIRING_MODE_GROUP_SEPARATION = 'group_separation';  // Tách nhánh: cùng bảng khác nhánh
    const PAIRING_MODE_MANUAL = 'manual';

    /**
     * Sắp xếp đội advancing theo mode đã chọn.
     *
     * Logic phân biệt 2 trường hợp dựa vào việc có _virtual entries trong secondPlace:
     *
     * 1. BÌNH THƯỜNG (numAdvancing × number_of_groups = 2^n, không có Nhì/Ba tốt nhất):
     *    - Mỗi bảng đóng góp 1 Nhất + 1 Nhì vào knockout
     *    - Tuần tự:  Nhất A - Nhì B, Nhất B - Nhì A, Nhất C - Nhì D, ...
     *    - Đối xứng: Nhất A - Nhì D, Nhất B - Nhì C, ...
     *
     * 2. CÓ PICK NHÌ/BA TỐT NHẤT (cross_group_ranking=true, không đủ 2^n):
     *    - Nhì tốt nhất là ảo (không thuộc bảng nào), nằm ở CUỐI mảng
     *    - Tuần tự:  Nhất A - Nhất B, Nhất C - Nhất D, ..., Nhì 1 - Nhì 2
     *    - Đối xứng: Nhất A - Nhì 2, Nhất B - Nhì 1, Nhất C - Nhất F, ...
     */
    public function arrangeAdvancingTeams(
        $advancingByRank,
        ?string $pairingMode = null,
        ?array $manualPairings = null
    ): Collection {
        // Normalize: Trim và lowercase
        $pairingMode = $pairingMode ? strtolower(trim($pairingMode)) : null;

        if ($pairingMode === self::PAIRING_MODE_MANUAL) {
            return $this->arrangeManual($advancingByRank, $manualPairings);
        }

        // Phát hiện có Nhì/Ba tốt nhất (ảo) hay không
        $hasVirtualSecondPlace = $this->hasVirtualEntries($advancingByRank->get(1, collect()));

        if ($hasVirtualSecondPlace) {
            // === CÓ PICK NHÌ/BA TỐT NHẤT: dùng logic tách riêng ===
            if ($pairingMode === self::PAIRING_MODE_SYMMETRIC) {
                return $this->arrangeSymmetricWithVirtual($advancingByRank);
            }
            if ($pairingMode === self::PAIRING_MODE_GROUP_SEPARATION) {
                return $this->arrangeGroupSeparationWithVirtual($advancingByRank);
            }
            return $this->arrangeSequentialWithVirtual($advancingByRank);
        }

        // === BÌNH THƯỜNG: mỗi bảng có Nhất + Nhì ===
        if ($pairingMode === self::PAIRING_MODE_SYMMETRIC) {
            return $this->arrangeSymmetric($advancingByRank);
        }
        if ($pairingMode === self::PAIRING_MODE_GROUP_SEPARATION) {
            return $this->arrangeGroupSeparation($advancingByRank);
        }
        return $this->arrangeSequential($advancingByRank);
    }

    /**
     * Kiểm tra xem có _virtual entries (Nhì/Ba tốt nhất) trong rank không
     */
    private function hasVirtualEntries(Collection $rankTeams): bool
    {
        if ($rankTeams->isEmpty()) {
            return false;
        }
        foreach ($rankTeams as $team) {
            if (isset($team->_virtual) && $team->_virtual === true) {
                return true;
            }
        }
        return false;
    }

    /**
     * [BÌNH THƯỜNG] Pattern cũ: Nhất A - Nhì B, Nhất B - Nhì A, Nhất C - Nhì D, Nhất D - Nhì C
     * Áp dụng khi numAdvancing × number_of_groups = 2^n (4 bảng×2=8, 2 bảng×2=4, ...)
     * - Mỗi bảng đóng góp đúng 1 Nhất và 1 Nhì
     * - numFirstPlace === numSecondPlace
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
     * [CÓ PICK NHÌ/BA TỐT NHẤT] Pattern: Nhất A - Nhất B, Nhất C - Nhất D, ..., Nhì 1 - Nhì 2
     * Áp dụng khi cross_group_ranking=true và không đủ 2^n đội → pick Nhì tốt nhất
     * - Nhì tốt nhất là ảo, nằm ở CUỐI mảng secondPlaceTeams
     * - Ghép Nhất-Nhất theo cặp tuần tự trước, Nhì-Nhì theo cặp ở cuối
     */
    private function arrangeSequentialWithVirtual($advancingByRank): Collection
    {
        $advancing = collect();

        $firstPlaceTeams = $advancingByRank->get(0, collect());
        $secondPlaceTeams = $advancingByRank->get(1, collect());

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        // Bước 1: Ghép Nhất-Nhất theo cặp tuần tự (A-B, C-D, E-F, ...)
        for ($i = 0; $i + 1 < $numFirstPlace; $i += 2) {
            $advancing->push($firstPlaceTeams->get($i));
            $advancing->push($firstPlaceTeams->get($i + 1));
        }

        // Bước 2: Ghép Nhì-Nhì theo cặp ở cuối (Nhì 1 - Nhì 2)
        for ($i = 0; $i + 1 < $numSecondPlace; $i += 2) {
            $advancing->push($secondPlaceTeams->get($i));
            $advancing->push($secondPlaceTeams->get($i + 1));
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
     * [BÌNH THƯỜNG] Pattern: Nhất A - Nhì D, Nhất B - Nhì C, ...
     * Áp dụng khi numAdvancing × number_of_groups = 2^n
     * - Mỗi bảng đóng góp đúng 1 Nhất và 1 Nhì (numFirstPlace === numSecondPlace)
     * - Loop xen kẽ, lấy Nhì từ cuối lên (đối xứng)
     * - Ví dụ 4 bảng×2=8 đội: A-D, B-C, C-B, D-A (loop i=0..3)
     */
    private function arrangeSymmetric($advancingByRank): Collection
    {
        $firstPlaceTeams = $advancingByRank->get(0, collect());
        $secondPlaceTeams = $advancingByRank->get(1, collect());

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        // === TRƯỜNG HỢP CHỈ CÓ 1 ĐỘI/BẢNG (numSecondPlace === 0) ===
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

        // === TRƯỜNG HỢP CÓ NHÌ (numSecondPlace > 0) ===
        // Loop xen kẽ, lấy Nhì từ cuối lên (đối xứng cũ)
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
     * [CÓ PICK NHÌ/BA TỐT NHẤT] Pattern: Nhất A - Nhì 2, Nhất B - Nhì 1, Nhất C - Nhất F, ...
     * Áp dụng khi cross_group_ranking=true và không đủ 2^n đội → pick Nhì tốt nhất
     * - Nhì tốt nhất là ảo, nằm ở CUỐI mảng secondPlaceTeams
     * - Bước 1: Nhất đầu ghép với Nhì cuối (đảo)
     * - Bước 2: Nhất còn lại ghép đầu-cuối
     * - Nếu không có Nhì: ghép Nhất đầu-cuối (A-H, B-G, ...)
     */
    private function arrangeSymmetricWithVirtual($advancingByRank): Collection
    {
        $firstPlaceTeams = $advancingByRank->get(0, collect());
        $secondPlaceTeams = $advancingByRank->get(1, collect());

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        // === TRƯỜNG HỢP KHÔNG CÓ NHÌ (numSecondPlace === 0) ===
        // Symmetric pair đầu vs cuối: [A, B, C, D, E, F, G, H] → [A, H, B, G, C, F, D, E]
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

        // === TRƯỜNG HỢP CÓ NHÌ (numSecondPlace > 0) ===
        // Bước 1: Ghép Nhất đầu với Nhì cuối (đảo ngược)
        // Ví dụ: Nhất[A,B] - Nhì[2,1] → A-Nhì2, B-Nhì1
        $reversedSecond = $secondPlaceTeams->reverse()->values();

        $advancing = collect();

        $numPairsWithSecond = min($numFirstPlace, $numSecondPlace);
        for ($i = 0; $i < $numPairsWithSecond; $i++) {
            $advancing->push($firstPlaceTeams->get($i));
            $advancing->push($reversedSecond->get($i));
        }

        // Bước 2: Nhất còn lại ghép đối xứng đầu-cuối
        // Ví dụ: Nhất còn lại [C, D, E, F] → C-F, D-E
        $remainingFirst = $firstPlaceTeams->slice($numPairsWithSecond)->values();
        $numRemaining = $remainingFirst->count();

        $numPairsRemaining = intdiv($numRemaining, 2);
        for ($i = 0; $i < $numPairsRemaining; $i++) {
            $advancing->push($remainingFirst->get($i));
            $advancing->push($remainingFirst->get($numRemaining - 1 - $i));
        }
        if ($numRemaining % 2 === 1) {
            $advancing->push($remainingFirst->get($numPairsRemaining));
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
     * [BÌNH THƯỜNG] Tách nhánh (Group Separation):
     * Đảm bảo các đội cùng bảng (Nhất, Nhì) nằm ở 2 nhánh khác nhau, không gặp nhau trước Chung kết.
     *
     * Ví dụ 4 bảng (A, B, C, D):
     *   - Nhánh trên (Top half):    Nhất A - Nhì B, Nhất C - Nhì D
     *   - Nhánh dưới (Bottom half): Nhất B - Nhì A, Nhất D - Nhì C
     * Ví dụ 8 bảng (A..H):
     *   - Nhánh trên:  A1-B2, C1-D2, E1-F2, G1-H2
     *   - Nhánh dưới:  B1-A2, D1-C2, F1-E2, H1-G2
     */
    private function arrangeGroupSeparation($advancingByRank): Collection
    {
        $firstPlaceTeams = $advancingByRank->get(0, collect())->values();
        $secondPlaceTeams = $advancingByRank->get(1, collect())->values();

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        if ($numSecondPlace === 0 && $numFirstPlace > 0) {
            return $this->arrangeSymmetric($advancingByRank);
        }

        $topHalf = collect();
        $bottomHalf = collect();
        $pairCount = min($numFirstPlace, $numSecondPlace);

        for ($i = 0; $i < $pairCount; $i += 2) {
            if ($i + 1 < $pairCount) {
                // Nhánh trên: Nhất[i] vs Nhì[i+1]
                $topHalf->push($firstPlaceTeams->get($i));
                $topHalf->push($secondPlaceTeams->get($i + 1));

                // Nhánh dưới: Nhất[i+1] vs Nhì[i]
                $bottomHalf->push($firstPlaceTeams->get($i + 1));
                $bottomHalf->push($secondPlaceTeams->get($i));
            } else {
                // Trường hợp số bảng lẻ: cặp cuối cùng đưa vào nhánh dưới
                $bottomHalf->push($firstPlaceTeams->get($i));
                $bottomHalf->push($secondPlaceTeams->get($i));
            }
        }

        for ($i = $pairCount; $i < $numFirstPlace; $i++) {
            $bottomHalf->push($firstPlaceTeams->get($i));
        }
        for ($i = $pairCount; $i < $numSecondPlace; $i++) {
            $bottomHalf->push($secondPlaceTeams->get($i));
        }

        $advancing = $topHalf->concat($bottomHalf)->values();

        foreach ($advancingByRank as $rank => $teamsAtRank) {
            if ($rank < 2) continue;
            foreach ($teamsAtRank as $team) {
                $advancing->push($team);
            }
        }

        return $advancing;
    }

    /**
     * [CÓ PICK NHÌ/BA TỐT NHẤT] Tách nhánh (Group Separation):
     * Phân bổ đều các đội Nhất và Nhì tốt nhất vào 2 nhánh trên/dưới.
     */
    private function arrangeGroupSeparationWithVirtual($advancingByRank): Collection
    {
        $firstPlaceTeams = $advancingByRank->get(0, collect())->values();
        $secondPlaceTeams = $advancingByRank->get(1, collect())->values();

        $numFirstPlace = $firstPlaceTeams->count();
        $numSecondPlace = $secondPlaceTeams->count();

        if ($numSecondPlace === 0 && $numFirstPlace > 0) {
            return $this->arrangeSymmetricWithVirtual($advancingByRank);
        }

        $reversedSecond = $secondPlaceTeams->reverse()->values();
        $matches = [];

        $numPairsWithSecond = min($numFirstPlace, $numSecondPlace);
        for ($i = 0; $i < $numPairsWithSecond; $i++) {
            $matches[] = [$firstPlaceTeams->get($i), $reversedSecond->get($i)];
        }

        $remainingFirst = $firstPlaceTeams->slice($numPairsWithSecond)->values();
        for ($i = 0; $i < $remainingFirst->count(); $i += 2) {
            if ($i + 1 < $remainingFirst->count()) {
                $matches[] = [$remainingFirst->get($i), $remainingFirst->get($i + 1)];
            } else {
                $matches[] = [$remainingFirst->get($i)];
            }
        }

        $remainingSecond = $reversedSecond->slice($numPairsWithSecond)->values();
        for ($i = 0; $i < $remainingSecond->count(); $i += 2) {
            if ($i + 1 < $remainingSecond->count()) {
                $matches[] = [$remainingSecond->get($i), $remainingSecond->get($i + 1)];
            } else {
                $matches[] = [$remainingSecond->get($i)];
            }
        }

        // Xen kẽ các trận vào nhánh trên (chẵn) và nhánh dưới (lẻ)
        $topHalf = collect();
        $bottomHalf = collect();
        foreach ($matches as $idx => $pair) {
            $target = ($idx % 2 === 0) ? $topHalf : $bottomHalf;
            foreach ($pair as $team) {
                $target->push($team);
            }
        }

        $advancing = $topHalf->concat($bottomHalf)->values();

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
     * $manualPairings format (FE convention mới):
     * [
     *   ['group_id' => 631, 'rank' => 1, 'position' => 0],  // Nhất bảng 631 ở slot 0 (pair 0, home)
     *   ['group_id' => 635, 'rank' => 2, 'position' => 0],  // Nhì bảng 635 ở slot 0 (pair 0, away)
     *   ['group_id' => 636, 'rank' => 1, 'position' => 1],  // Nhất bảng 636 ở slot 1 (pair 0, home) ...
     *   ...
     *   ['group_id' => 0,   'rank' => 2, 'position' => 6],  // Virtual "Nhì tốt nhất" #1 ở slot 6
     *   ['group_id' => 0,   'rank' => 2, 'position' => 7],  // Virtual "Nhì tốt nhất" #2 ở slot 7
     * ]
     *
     * Convention:
     *   - position = slot index (0-based), mỗi slot = 1 cặp = 2 entries (rank=1 và rank=2)
     *   - group_id = 0 + rank = 2 (1-based) → virtual "Nhì tốt nhất" #N
     *   - group_id = 0 + rank = 3 (1-based) → virtual "Ba tốt nhất" #N
     *     (BE tự resolve team cụ thể sau khi pool stage kết thúc)
     *   - Thứ tự virtual được xác định theo position (1, 2, 3, ... tương ứng #1, #2, #3)
     */
    private function arrangeManual($advancingByRank, ?array $manualPairings): Collection
    {
        if (empty($manualPairings)) {
            return $this->arrangeSequential($advancingByRank);
        }

        // ✅ Detect convention: old vs new
        // Old: position = slotIndex (0,0, 1,1, 2,2, ...) max < numSlots
        // New: position = slotIndex*2 + subIndex (0,1, 2,3, ...) max >= numSlots
        $maxPos = 0;
        foreach ($manualPairings as $p) {
            $maxPos = max($maxPos, (int)($p['position'] ?? 0));
        }
        $numSlots = count($manualPairings) > 0
            ? max(1, (int)ceil(sqrt(2 * count($manualPairings))))
            : 1;
        $isOldConvention = $maxPos < $numSlots && $maxPos > 0;

        // ✅ Normalize to new convention: position = slotIndex*2 + subIndex
        // Old: (position, rank=1) → (position*2, rank=1), (position, rank=2) → (position*2+1, rank=2)
        // New: already in correct format, no change needed
        foreach ($manualPairings as &$p) {
            $pos = (int)($p['position'] ?? 0);
            $rank = (int)($p['rank'] ?? 1);
            if ($isOldConvention) {
                $p['position'] = $pos * 2 + ($rank - 1);
            }
            // rank stays as-is (1 = Nhất, 2 = Nhì, 3 = Ba)
        }
        unset($p); // break reference

        // ✅ Bước 1: Sắp xếp FE pairings theo (position, rank) — Nhất (rank=1) trước Nhì (rank=2) trong cùng slot
        usort($manualPairings, function ($a, $b) {
            $posCmp = ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
            if ($posCmp !== 0) return $posCmp;
            return ($a['rank'] ?? 1) <=> ($b['rank'] ?? 1);
        });

        // ✅ Bước 2: Map real groups theo (group_id, rank) → entry
        $teamMap = [];
        foreach ($advancingByRank as $rank => $teamsAtRank) {
            foreach ($teamsAtRank as $team) {
                if (($team->_from_group ?? null) !== null) {
                    $key = "{$team->_from_group}_{$rank}";
                    $teamMap[$key] = $team;
                }
            }
        }

        // ✅ Bước 3: Map virtual "Nhì/Ba tốt nhất" entries theo _virtual_index, phân theo rank
        $virtualByRank = [1 => [], 2 => []]; // rank=1 (0-based) = Nhì, rank=2 (0-based) = Ba
        foreach ($advancingByRank as $rank => $teamsAtRank) {
            foreach ($teamsAtRank as $team) {
                if (($team->_from_group ?? null) === null) {
                    $vIdx = (int) ($team->_virtual_index ?? 0);
                    $virtualByRank[$rank][$vIdx] = $team;
                }
            }
        }
        $virtualCursor = [1 => 0, 2 => 0]; // Đếm số virtual đã dùng theo rank

        // ✅ Bước 4: Build $advancing từ manual_pairings
        // Mỗi entry có position → knockoutIndex, basePosition = position % 2 (home/away)
        $advancing = collect();
        foreach ($manualPairings as $pairing) {
            $groupId = $pairing['group_id'] ?? null;
            $rankInput = (int)($pairing['rank'] ?? 1);
            $position = (int)($pairing['position'] ?? 0);
            $rankIndex = $rankInput - 1; // 0-based: 0 = Nhất, 1 = Nhì, 2 = Ba

            // Virtual "Nhì/Ba tốt nhất" (group_id = 0, rank = 2 hoặc 3)
            if ((int) $groupId === 0 && $rankIndex >= 1) {
                $virtualCursor[$rankIndex]++;
                if (isset($virtualByRank[$rankIndex][$virtualCursor[$rankIndex]])) {
                    $advancing->push($virtualByRank[$rankIndex][$virtualCursor[$rankIndex]]);
                }
                continue;
            }

            // Real entry
            $key = "{$groupId}_{$rankIndex}";
            if (isset($teamMap[$key])) {
                $advancing->push($teamMap[$key]);
            }
        }

        return $advancing;
    }
}