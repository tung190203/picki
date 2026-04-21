<?php

namespace App\Services\Admin;

use App\Models\Matches;
use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getStats(): array
    {
        $totalUsers = User::where('is_guest', false)->count();
        $newUsersThisWeek = User::where('is_guest', false)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();
        $activeMatches = Matches::where('status', 'active')->count();
        $activeTournaments = Tournament::where('status', 'active')->count();
        $tournamentsThisMonth = Tournament::whereMonth('created_at', Carbon::now()->month)->count();

        $thisWeekMatches = Matches::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $lastWeekMatches = Matches::whereBetween('created_at', [
            Carbon::now()->startOfWeek()->subWeek(),
            Carbon::now()->startOfWeek(),
        ])->count();

        $matchesGrowthPercent = $lastWeekMatches > 0
            ? round((($thisWeekMatches - $lastWeekMatches) / $lastWeekMatches) * 100, 1)
            : ($thisWeekMatches > 0 ? 100 : 0);

        $totalDisputes = DB::table('disputes')->count();
        $totalMatchesAllTime = Matches::count();
        $disputeRate = $totalMatchesAllTime > 0
            ? round(($totalDisputes / $totalMatchesAllTime) * 100, 1)
            : 0;

        $lastMonthDisputes = DB::table('disputes')
            ->where('created_at', '>=', Carbon::now()->startOfMonth()->subMonth())
            ->count();
        $lastMonthMatches = Matches::whereBetween('created_at', [
            Carbon::now()->startOfMonth()->subMonth(),
            Carbon::now()->startOfMonth(),
        ])->count();
        $lastMonthDisputeRate = $lastMonthMatches > 0
            ? round(($lastMonthDisputes / $lastMonthMatches) * 100, 1)
            : 0;
        $disputeRateChange = $lastMonthDisputeRate > 0
            ? round($disputeRate - $lastMonthDisputeRate, 1)
            : 0;

        $recentNewUsers = User::where('is_guest', false)
            ->select(['id', 'full_name', 'avatar_url', 'trust_score', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $openMatches = Matches::whereNotIn('status', [Matches::STATUS_COMPLETED])
            ->select([
                'id',
                'name_of_match as title',
                'status',
                DB::raw('(SELECT COUNT(*) FROM participants WHERE participants.match_id = matches.id) as players_count'),
                'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $openTournaments = Tournament::whereIn('status', [Tournament::DRAFT, Tournament::OPEN])
            ->select([
                'id',
                'name',
                'status',
                'is_featured',
                'start_date',
                'registration_open_at',
                'registration_closed_at',
                'fee',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return [
            'total_users' => $totalUsers,
            'new_users_this_week' => $newUsersThisWeek,
            'active_matches' => $activeMatches,
            'matches_growth_percent' => $matchesGrowthPercent,
            'active_tournaments' => $activeTournaments,
            'tournaments_this_month' => $tournamentsThisMonth,
            'dispute_rate' => $disputeRate,
            'dispute_rate_change' => $disputeRateChange,
            'recent_new_users' => $recentNewUsers,
            'open_matches' => $openMatches,
            'open_tournaments' => $openTournaments,
        ];
    }

    public function getList(string $type, int $page, int $limit, ?string $keyword): LengthAwarePaginator
    {
        return match ($type) {
            'users' => $this->getUsersList($page, $limit, $keyword),
            'matches' => $this->getMatchesList($page, $limit, $keyword),
            'tournaments' => $this->getTournamentsList($page, $limit, $keyword),
            'recent_new_users' => $this->getRecentNewUsersList($page, $limit, $keyword),
            'open_matches' => $this->getOpenMatchesList($page, $limit, $keyword),
            'open_tournaments' => $this->getOpenTournamentsList($page, $limit, $keyword),
            default => $this->getUsersList($page, $limit, $keyword),
        };
    }

    private function getRecentNewUsersList(int $page, int $limit, ?string $keyword)
    {
        $query = User::query()
            ->where('is_guest', false)
            ->select([
                'id',
                'full_name',
                'avatar_url',
                'location_id',
                'trust_score',
                'total_matches',
                'is_banned',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    private function getOpenMatchesList(int $page, int $limit, ?string $keyword)
    {
        $query = Matches::query()
            ->whereNotIn('status', [Matches::STATUS_COMPLETED])
            ->select([
                'id',
                'name_of_match as title',
                'status',
                DB::raw('(SELECT COUNT(*) FROM participants WHERE participants.match_id = matches.id) as players_count'),
                'created_at',
                DB::raw('(SELECT COUNT(*) FROM disputes WHERE disputes.match_id = matches.id AND disputes.status = "open") as has_dispute'),
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('name_of_match', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    private function getOpenTournamentsList(int $page, int $limit, ?string $keyword)
    {
        $query = Tournament::query()
            ->whereIn('status', [Tournament::DRAFT, Tournament::OPEN])
            ->select([
                'id',
                'name',
                'status',
                'is_featured',
                'start_date',
                'registration_open_at',
                'registration_closed_at',
                'fee',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    private function getUsersList(int $page, int $limit, ?string $keyword)
    {
        $query = User::query()
            ->select([
                'id',
                'full_name',
                'avatar_url',
                'location_id',
                'trust_score',
                'total_matches',
                'is_banned',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('full_name', 'like', "%{$keyword}%")
                  ->orWhere('email', 'like', "%{$keyword}%")
                  ->orWhere('phone', 'like', "%{$keyword}%");
            });
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    private function getMatchesList(int $page, int $limit, ?string $keyword)
    {
        $query = Matches::query()
            ->select([
                'id',
                'name_of_match as title',
                'status',
                DB::raw('(SELECT COUNT(*) FROM participants WHERE participants.match_id = matches.id) as players_count'),
                'created_at',
                DB::raw('(SELECT COUNT(*) FROM disputes WHERE disputes.match_id = matches.id AND disputes.status = "open") as has_dispute'),
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('name_of_match', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    private function getTournamentsList(int $page, int $limit, ?string $keyword)
    {
        $query = Tournament::query()
            ->select([
                'id',
                'name',
                'status',
                'is_featured',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }
}
