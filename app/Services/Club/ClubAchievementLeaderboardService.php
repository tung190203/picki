<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\Tournament;
use App\Models\TournamentType;
use App\Services\RoundRobinSchedulerService;
use App\Services\TournamentType\TournamentRankService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClubAchievementLeaderboardService
{
    public function __construct(
        private TournamentRankService $rankService,
        private RoundRobinSchedulerService $roundRobinService,
    ) {}

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
     * Tính BXH Cúp (Giải đấu CLB).
     *
     * Lấy champion/runner-up/third từ TournamentRankService (cùng logic với
     * GET /api/tournaments/{id}/leaderboard), phân bổ điểm cho từng thành viên
     * trong team.
     */
    private function calculateCupLeaderboard(Club $club, ?Carbon $startDate): Collection
    {
        $scores = [];

        $tournamentsQuery = Tournament::where('club_id', $club->id);

        if ($startDate) {
            // Filter theo start_date (bao gồm cả giải đang diễn ra)
            $tournamentsQuery->where('start_date', '>=', $startDate);
        }

        $tournaments = $tournamentsQuery->with('tournamentTypes')->get();

        // Bỏ filter status=CLOSED: tournament được tính khi final match completed
        // (TournamentRankService tự skip khi chưa có rank hợp lệ).
        foreach ($tournaments as $tournament) {
            foreach ($tournament->tournamentTypes as $type) {
                $rankLabels = $this->rankService->rankLabelsByTeam($type->id);

                foreach ($rankLabels as $teamId => $rankInfo) {
                    $rank = (int) ($rankInfo['overall_rank'] ?? 0);
                    if ($rank < 1 || $rank > 3) {
                        continue;
                    }

                    $team = \App\Models\Team::with('members')->find($teamId);
                    if (!$team) {
                        continue;
                    }

                    $this->awardTeamPoints(
                        $scores,
                        $team,
                        $rank,
                        $club
                    );
                }
            }
        }

        return $this->sortAndRankLeaderboard($scores, $club);
    }

    /**
     * Phân bổ điểm cup cho từng thành viên của team.
     */
    private function awardTeamPoints(array &$scores, \App\Models\Team $team, int $rank, Club $club): void
    {
        $points = match ($rank) {
            1 => ['gold' => 1, 'silver' => 0, 'bronze' => 0, 'total' => 3],
            2 => ['gold' => 0, 'silver' => 1, 'bronze' => 0, 'total' => 2],
            3 => ['gold' => 0, 'silver' => 0, 'bronze' => 1, 'total' => 1],
            default => null,
        };
        if ($points === null) {
            return;
        }

        $memberIds = $team->members->pluck('id')->all();

        foreach ($memberIds as $userId) {
            $key = 'user_' . $userId;
            if (!isset($scores[$key])) {
                $user = \App\Models\User::find($userId);
                $scores[$key] = [
                    'user_id' => $userId,
                    'virtual_member_id' => null,
                    'is_virtual' => false,
                    'name' => $user?->full_name ?? 'Khách',
                    'avatar_url' => $user?->avatar_url,
                    'gold' => 0,
                    'silver' => 0,
                    'bronze' => 0,
                    'total_points' => 0,
                ];
            }

            $scores[$key]['gold'] += $points['gold'];
            $scores[$key]['silver'] += $points['silver'];
            $scores[$key]['bronze'] += $points['bronze'];
            $scores[$key]['total_points'] += $points['total'];
        }
    }

    /**
     * Tính BXH Sao (Kèo đấu CLB).
     *
     * Lấy top 3 từ RoundRobinSchedulerService::calculateLeaderboard() (cùng logic
     * với GET /api/mini-tournaments/{id}/leaderboard).
     */
    private function calculateStarLeaderboard(Club $club, ?Carbon $startDate): Collection
    {
        $scores = [];

        $miniTournamentsQuery = \App\Models\MiniTournament::where('club_id', $club->id)
            ->whereIn('status', [\App\Models\MiniTournament::STATUS_CLOSED, 3]);

        if ($startDate) {
            // Filter theo start_time (bao gồm cả kèo đang diễn ra)
            $miniTournamentsQuery->where('start_time', '>=', $startDate);
        }

        $miniTournaments = $miniTournamentsQuery->get();

        foreach ($miniTournaments as $mini) {
            $result = $this->roundRobinService->calculateLeaderboard($mini->id);
            $leaderboard = $result['leaderboard'] ?? [];

            $top3 = array_slice($leaderboard, 0, 3);

            foreach ($top3 as $entry) {
                $rank = (int) ($entry['rank'] ?? 0);
                if ($rank < 1 || $rank > 3) {
                    continue;
                }

                $userId = $entry['user_id'] ?? null;
                $participantId = $entry['participant_id'] ?? null;

                if (!$userId || !$participantId) {
                    continue;
                }

                $participant = \App\Models\MiniParticipant::with('user')->find($participantId);
                if (!$participant) {
                    continue;
                }

                $isGuest = (bool) ($participant->is_guest || !$participant->user_id || ($participant->user && $participant->user->is_guest));
                $guestName = $participant->guest_name ?: ($participant->user ? $participant->user->full_name : 'Khách');

                if ($isGuest) {
                    $key = 'guest_' . md5(mb_strtolower(trim($guestName)));
                } else {
                    $key = 'user_' . $userId;
                }

                if (!isset($scores[$key])) {
                    $scores[$key] = [
                        'user_id' => $isGuest ? null : $userId,
                        'virtual_member_id' => null,
                        'is_virtual' => $isGuest,
                        'name' => $guestName,
                        'avatar_url' => $isGuest ? $participant->guest_avatar : ($participant->user ? $participant->user->avatar_url : null),
                        'gold' => 0,
                        'silver' => 0,
                        'bronze' => 0,
                        'total_points' => 0,
                    ];
                }

                if ($rank === 1) {
                    $scores[$key]['gold'] += 1;
                    $scores[$key]['total_points'] += 3;
                } elseif ($rank === 2) {
                    $scores[$key]['silver'] += 1;
                    $scores[$key]['total_points'] += 2;
                } elseif ($rank === 3) {
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

        // CLB guest giờ là user thật (User.is_guest = true), không cần map VM riêng —
        // avatar/định danh đã lấy từ User row lúc build $scores.

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