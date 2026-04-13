<?php

namespace App\Services\Club;

use App\Http\Controllers\UserMatchStatsController;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\Sport;
use App\Models\VnduprHistory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ClubLeaderboardService
{
    /**
     * Tính rank của club dựa trên tổng điểm members trong tháng
     * Cache 5 phút để tránh load toàn bộ clubs mỗi request
     */
    public function calculateClubRank(Club $club, ?int $month = null, ?int $year = null): ?int
    {
        $month = $month ?? now()->month;
        $year = $year ?? now()->year;

        $cacheKey = "club_rank:{$club->id}:{$year}:{$month}";
        return Cache::remember($cacheKey, 300, fn () => $this->computeClubRank($club, $month, $year));
    }

    private function computeClubRank(Club $club, int $month, int $year): ?int
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $allClubs = Club::where('status', \App\Enums\ClubStatus::Active)
            ->with(['joinedMembers.user.sports.scores'])
            ->get();

        $clubScores = $allClubs->map(function ($clubItem) use ($startDate, $endDate) {
            $members = $clubItem->joinedMembers;

            if ($members->isEmpty()) {
                return [
                    'club_id' => $clubItem->id,
                    'total_score' => 0,
                ];
            }

            $memberIds = $members->pluck('user_id')->filter()->unique();
            $histories = VnduprHistory::whereIn('user_id', $memberIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'asc')
                ->get()
                ->groupBy('user_id');

            $totalScore = 0;
            foreach ($members as $member) {
                $userId = $member->user_id;
                $userHistories = $histories->get($userId, collect());

                if ($userHistories->isNotEmpty()) {
                    $totalScore += $userHistories->last()->score_after;
                } else {
                    $vnduprScore = $member->user?->sports->flatMap(fn($sport) => $sport->scores()->get())
                        ->where('score_type', 'vndupr_score')
                        ->sortByDesc('created_at')
                        ->first();
                    $totalScore += $vnduprScore ? $vnduprScore->score_value : 0;
                }
            }

            return [
                'club_id' => $clubItem->id,
                'total_score' => $totalScore,
            ];
        });

        $sortedClubs = $clubScores->sortByDesc('total_score')->values();

        $rank = null;
        foreach ($sortedClubs as $index => $item) {
            if ($item['club_id'] === $club->id) {
                $rank = $index + 1;
                break;
            }
        }

        return $rank;
    }

    /**
     * Bảng xếp hạng all-time (không theo tháng).
     * Vẫn nhận month, year từ FE để tương thích nhưng không dùng để tính.
     */
    public function getMonthlyLeaderboard(Club $club, int $month, int $year): Collection
    {
        $members = $club->joinedMembers()->with(['user.sports.scores'])->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $memberIds = $members->pluck('user_id')->filter()->unique();

        $sport = Sport::where('slug', 'pickleball')->first();
        $sportId = $sport?->id ?? 1;

        // Toàn bộ lịch sử (all-time) cho vndupr_score
        $allHistories = VnduprHistory::whereIn('user_id', $memberIds)
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('user_id');

        $leaderboardData = $members->map(function ($member) use ($allHistories, $sportId) {
            return $this->calculateMemberStats($member, $allHistories, $sportId);
        });

        $sorted = $leaderboardData->sortByDesc('vndupr_score')->values();
        $verified = $sorted->filter(fn($item) => ($item['monthly_stats']['matches_played'] ?? 0) >= 10);
        $unverified = $sorted->filter(fn($item) => ($item['monthly_stats']['matches_played'] ?? 0) < 10);

        // Top 3: chỉ user đã verified (>= 10 trận)
        $topThree = $verified->take(3)->values();
        $rest = $verified->skip(3)->concat($unverified)->sortByDesc('vndupr_score')->values();
        $combined = $topThree->concat($rest)->values();

        return $combined->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
    }

    private function calculateMemberStats(
        ClubMember $member,
        Collection $allHistories,
        int $sportId
    ): array {
        $userId = $member->user_id;
        $userHistories = $allHistories->get($userId, collect());

        $finalScore = 0;
        if ($userHistories->isNotEmpty()) {
            $finalScore = $userHistories->last()->score_after;
        } else {
            $vnduprScore = $member->user?->sports->flatMap(fn($sport) => $sport->scores()->get())
                ->where('score_type', 'vndupr_score')
                ->sortByDesc('created_at')
                ->first();
            $finalScore = $vnduprScore ? $vnduprScore->score_value : 0;
        }

        $stats = UserMatchStatsController::getSportStats($userId, $sportId);

        return [
            'member_id' => $member->id,
            'user_id' => $userId,
            'user' => $member->user,
            'vndupr_score' => round($finalScore, 3),
            'monthly_stats' => $stats,
        ];
    }
}
