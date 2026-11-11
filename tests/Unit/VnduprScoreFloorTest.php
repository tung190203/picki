<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class VnduprScoreFloorTest extends TestCase
{
    /**
     * Test logic chuyển thành giao hữu khi đội thua có người điểm sàn <= 1.0
     */
    public function test_losing_team_with_floor_player_disables_scoring(): void
    {
        // Giả lập trận đấu: Đội 1 (Home) vs Đội 2 (Away)
        // Home gồm 2 người: Player A (rating 1.000), Player B (rating 2.500)
        // Away gồm 2 người: Player C (rating 2.500), Player D (rating 3.000)
        $homeRatings = [1.000, 2.500];
        $awayRatings = [2.500, 3.000];

        // Kết quả: Away thắng (Home thua)
        $winnerTeam = 'away';
        $homeLost = ($winnerTeam === 'away');
        $awayLost = ($winnerTeam === 'home');

        $disableScoring = false;
        if ($homeLost) {
            foreach ($homeRatings as $r) {
                if ($r <= 1.0) {
                    $disableScoring = true;
                    break;
                }
            }
        }
        if ($awayLost && !$disableScoring) {
            foreach ($awayRatings as $r) {
                if ($r <= 1.0) {
                    $disableScoring = true;
                    break;
                }
            }
        }

        $this->assertTrue($disableScoring, 'Trận đấu có người 1.0 thua phải được chuyển thành giao hữu (disableScoring = true)');

        // Khi disableScoring = true, rating giữ nguyên
        $R_old = 1.000;
        $R_new = $disableScoring ? $R_old : ($R_old - 0.1);
        $this->assertEquals(1.000, $R_new, 'Người 1.0 thua cuộc giữ nguyên điểm 1.0');
    }

    /**
     * Test trận đấu có người 1.0 THẮNG thì vẫn tính điểm (không disable scoring)
     */
    public function test_winning_team_with_floor_player_does_not_disable_scoring(): void
    {
        // Home gồm Player A (1.000) và Player B (2.500)
        // Away gồm Player C (2.000) và Player D (2.200)
        // Home THẮNG (Away THUA)
        $homeRatings = [1.000, 2.500];
        $awayRatings = [2.000, 2.200];

        $winnerTeam = 'home';
        $homeLost = ($winnerTeam === 'away');
        $awayLost = ($winnerTeam === 'home');

        $disableScoring = false;
        if ($homeLost) {
            foreach ($homeRatings as $r) {
                if ($r <= 1.0) {
                    $disableScoring = true;
                    break;
                }
            }
        }
        if ($awayLost && !$disableScoring) {
            foreach ($awayRatings as $r) {
                if ($r <= 1.0) {
                    $disableScoring = true;
                    break;
                }
            }
        }

        $this->assertFalse($disableScoring, 'Người 1.0 nằm ở đội thắng thì trận đấu vẫn được tính điểm');
    }

    /**
     * Test công thức ELO luôn bị chặn sàn tối thiểu 1.0
     */
    public function test_rating_new_never_drops_below_one(): void
    {
        // Giả sử user có rating 1.050, thua trận bị trừ điểm ELO lớn
        $R_old = 1.050;
        $W = 0.6;
        $K = 1.0;
        $S = 0.0;
        $E = 0.9; // Kỳ vọng thắng cao nhưng lại thua -> trừ 0.6 * 1.0 * (-0.9) = -0.54

        $R_calc = $R_old + ($W * $K * ($S - $E)); // 1.050 - 0.54 = 0.510
        $this->assertLessThan(1.0, $R_calc, 'Tính thô sẽ ra < 1.0');

        $R_new = max(1.0, round($R_calc, 3));
        $this->assertEquals(1.000, $R_new, 'R_new luôn được chặn sàn tối thiểu 1.000');
    }
}

