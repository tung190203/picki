<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\Club\ClubVirtualMember;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\Participant;
use App\Models\Tournament;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClubAchievementLeaderboardService
{
    /**
     * Get Achievement Leaderboard (Sao or Cúp) for a Club.
     *
     * @param Club $club
     * @param string $subType 'star' (Kèo đấu) or 'cup' (Giải đấu)
     * @param string $timeFrame 'month', 'quarter', 'year', 'all'
     * @return Collection
     */
    public function getLeaderboard(Club $club, string $subType = 'star', string $timeFrame = 'month'): Collection
    {
        $startDate = $this->getStartDateForTimeFrame($timeFrame);

        if ($subType === 'cup') {
            return $this->calculateCupLeaderboard($club, $startDate);
        }

        return $this->calculateStarLeaderboard($club, $startDate);
    }

    private function getStartDateForTimeFrame(string $timeFrame): ?Carbon
    {
        $now = Carbon::now();

        return match ($timeFrame) {
            'month' => $now->copy()->startOfMonth(),
            'quarter' => $now->copy()->startOfQuarter(),
            'year' => $now->copy()->startOfYear(),
            default => null,
        };
    }

    /**
     * Tính BXH Cúp (Giải đấu CLB)
     */
    private function calculateCupLeaderboard(Club $club, ?Carbon $startDate): Collection
    {
        $scores = [];

        // Lấy tất cả giải đấu của CLB đã kết thúc (CLOSED = 3)
        $tournamentsQuery = Tournament::where('club_id', $club->id)
            ->whereIn('status', [Tournament::CLOSED, 3]);

        if ($startDate) {
            $tournamentsQuery->where('updated_at', '>=', $startDate);
        }

        $tournaments = $tournamentsQuery->get();

        foreach ($tournaments as $tournament) {
            // Lấy participants có rank_after hoặc position top 3
            $participants = Participant::where('tournament_id', $tournament->id)
                ->whereIn('rank_after', [1, 2, 3])
                ->get();

            foreach ($participants as $p) {
                $isGuest = (bool)($p->is_guest || !$p->user_id || ($p->user && $p->user->is_guest));
                if ($isGuest) {
                    $guestName = $p->guest_name ?: ($p->user ? $p->user->full_name : 'Khách');
                    $key = 'guest_' . md5(mb_strtolower(trim($guestName)));
                } else {
                    $key = 'user_' . $p->user_id;
                }

                if (!isset($scores[$key])) {
                    $scores[$key] = [
                        'user_id' => $isGuest ? null : $p->user_id,
                        'virtual_member_id' => null,
                        'is_virtual' => $isGuest,
                        'name' => $isGuest ? ($p->guest_name ?: ($p->user ? $p->user->full_name : 'Khách')) : ($p->user ? $p->user->full_name : 'Khách'),
                        'avatar_url' => $isGuest ? $p->guest_avatar : ($p->user ? $p->user->avatar_url : null),
                        'gold' => 0,
                        'silver' => 0,
                        'bronze' => 0,
                        'total_points' => 0,
                    ];
                }

                if ($p->rank_after == 1) {
                    $scores[$key]['gold'] += 1;
                    $scores[$key]['total_points'] += 3;
                } elseif ($p->rank_after == 2) {
                    $scores[$key]['silver'] += 1;
                    $scores[$key]['total_points'] += 2;
                } elseif ($p->rank_after == 3) {
                    $scores[$key]['bronze'] += 1;
                    $scores[$key]['total_points'] += 1;
                }
            }
        }

        return $this->sortAndRankLeaderboard($scores, $club);
    }

    /**
     * Tính BXH Sao (Kèo đấu CLB)
     */
    private function calculateStarLeaderboard(Club $club, ?Carbon $startDate): Collection
    {
        $scores = [];

        // Lấy tất cả kèo đấu của CLB đã kết thúc (STATUS_CLOSED = 3)
        $miniTournamentsQuery = MiniTournament::where('club_id', $club->id)
            ->whereIn('status', [MiniTournament::STATUS_CLOSED, 3]);

        if ($startDate) {
            $miniTournamentsQuery->where('end_time', '>=', $startDate);
        }

        $miniTournaments = $miniTournamentsQuery->get();

        foreach ($miniTournaments as $mini) {
            $participants = MiniParticipant::where('mini_tournament_id', $mini->id)
                ->whereIn('rank_after', [1, 2, 3])
                ->get();

            foreach ($participants as $p) {
                $isGuest = (bool)($p->is_guest || !$p->user_id || ($p->user && $p->user->is_guest));
                if ($isGuest) {
                    $guestName = $p->guest_name ?: ($p->user ? $p->user->full_name : 'Khách');
                    $key = 'guest_' . md5(mb_strtolower(trim($guestName)));
                } else {
                    $key = 'user_' . $p->user_id;
                }

                if (!isset($scores[$key])) {
                    $scores[$key] = [
                        'user_id' => $isGuest ? null : $p->user_id,
                        'virtual_member_id' => null,
                        'is_virtual' => $isGuest,
                        'name' => $isGuest ? ($p->guest_name ?: ($p->user ? $p->user->full_name : 'Khách')) : ($p->user ? $p->user->full_name : 'Khách'),
                        'avatar_url' => $isGuest ? $p->guest_avatar : ($p->user ? $p->user->avatar_url : null),
                        'gold' => 0,
                        'silver' => 0,
                        'bronze' => 0,
                        'total_points' => 0,
                    ];
                }

                if ($p->rank_after == 1) {
                    $scores[$key]['gold'] += 1;
                    $scores[$key]['total_points'] += 3;
                } elseif ($p->rank_after == 2) {
                    $scores[$key]['silver'] += 1;
                    $scores[$key]['total_points'] += 2;
                } elseif ($p->rank_after == 3) {
                    $scores[$key]['bronze'] += 1;
                    $scores[$key]['total_points'] += 1;
                }
            }
        }

        return $this->sortAndRankLeaderboard($scores, $club);
    }

    /**
     * Sắp xếp tie-break: Tổng điểm desc -> Vàng desc -> Bạc desc -> Đồng desc
     */
    private function sortAndRankLeaderboard(array $scores, Club $club): Collection
    {
        $collection = collect(array_values($scores));

        // Nếu thành viên ảo thuộc CLB có tên khớp với guest, map thông tin thành viên ảo vào
        $virtualMembers = ClubVirtualMember::where('club_id', $club->id)->get()->keyBy('name');

        $collection = $collection->map(function ($item) use ($virtualMembers) {
            if ($item['is_virtual'] && isset($virtualMembers[$item['name']])) {
                $vm = $virtualMembers[$item['name']];
                $item['virtual_member_id'] = $vm->id;
                if ($vm->avatar_url) {
                    $item['avatar_url'] = $vm->avatar_url;
                }
            }
            return $item;
        });

        // Sắp xếp
        $sorted = $collection->sort(function ($a, $b) {
            if ($a['total_points'] !== $b['total_points']) {
                return $b['total_points'] <=> $a['total_points'];
            }
            if ($a['gold'] !== $b['gold']) {
                return $b['gold'] <=> $a['gold'];
            }
            if ($a['silver'] !== $b['silver']) {
                return $b['silver'] <=> $a['silver'];
            }
            return $b['bronze'] <=> $a['bronze'];
        })->values();

        // Gán rank 1..N
        return $sorted->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
    }
}
