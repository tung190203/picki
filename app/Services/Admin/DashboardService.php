<?php

namespace App\Services\Admin;

use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\Club\ClubReport;
use App\Models\Tournament;
use App\Models\User;
use App\Models\CompetitionLocation;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const PLAYERS_COUNT_SQL = '(SELECT COUNT(*) FROM mini_participants WHERE mini_participants.mini_tournament_id = mini_tournaments.id) as players_count';
    private const HAS_DISPUTE_SQL = "(SELECT COUNT(*) FROM disputes WHERE disputes.match_id IN (SELECT id FROM mini_matches WHERE mini_matches.mini_tournament_id = mini_tournaments.id) AND disputes.status = 'open') as has_dispute";

    public function getStats(): array
    {
        // ---------- User Stats ----------
        $totalUsers = User::where('is_guest', false)->count();
        $newUsersThisWeek = User::where('is_guest', false)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->count();

        // ---------- Match Stats (MiniTournament / MiniMatch — kèo nhanh) ----------
        $activeMatches = MiniTournament::whereIn('status', [
            MiniTournament::STATUS_DRAFT,
            MiniTournament::STATUS_OPEN,
        ])->count();

        $thisWeekMatches = MiniTournament::where('created_at', '>=', Carbon::now()->startOfWeek())->count();
        $lastWeekMatches = MiniTournament::whereBetween('created_at', [
            Carbon::now()->startOfWeek()->subWeek(),
            Carbon::now()->startOfWeek(),
        ])->count();
        $matchesGrowthPercent = $lastWeekMatches > 0
            ? round((($thisWeekMatches - $lastWeekMatches) / $lastWeekMatches) * 100, 1)
            : ($thisWeekMatches > 0 ? 100 : 0);

        // ---------- Tournament Stats ----------
        $activeTournaments = Tournament::whereIn('status', [Tournament::DRAFT, Tournament::OPEN])->count();
        $tournamentsThisMonth = Tournament::whereMonth('created_at', Carbon::now()->month)->count();

        // ---------- Dispute Stats ----------
        $openDisputesCount = DB::table('disputes')->where('status', 'open')->count();
        $totalDisputes = DB::table('disputes')->count();
        $totalMatchesAllTime = MiniTournament::count();
        $disputeRate = $totalMatchesAllTime > 0
            ? round(($totalDisputes / $totalMatchesAllTime) * 100, 1)
            : 0;

        $lastMonthDisputes = DB::table('disputes')
            ->where('created_at', '>=', Carbon::now()->startOfMonth()->subMonth())
            ->count();
        $lastMonthMatches = MiniTournament::whereBetween('created_at', [
            Carbon::now()->startOfMonth()->subMonth(),
            Carbon::now()->startOfMonth(),
        ])->count();
        $lastMonthDisputeRate = $lastMonthMatches > 0
            ? round(($lastMonthDisputes / $lastMonthMatches) * 100, 1)
            : 0;
        $disputeRateChange = $lastMonthDisputeRate > 0
            ? round($disputeRate - $lastMonthDisputeRate, 1)
            : 0;

        // ---------- Report Stats ----------
        $pendingReportsCount = ClubReport::where('status', 'pending')->count();

        // ---------- Revenue Stats ----------
        $monthlyRevenue = DB::table('mini_participant_payments')
            ->whereIn('status', ['paid', 'confirmed'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->sum('amount') ?? 0;

        $totalRevenue = DB::table('mini_participant_payments')
            ->whereIn('status', ['paid', 'confirmed'])
            ->sum('amount') ?? 0;

        // ---------- Recent New Users (top 5) ----------
        $recentNewUsers = User::where('is_guest', false)
            ->select(['id', 'full_name', 'avatar_url', 'trust_score', 'created_at'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // ---------- Open Mini Tournaments (chưa hoàn thành hoặc huỷ) ----------
        $openMiniTournaments = MiniTournament::with(['competitionLocation'])
            ->whereIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN, MiniTournament::STATUS_CANCELLED])
            ->select([
                'id',
                'name',
                'status',
                'start_time',
                'competition_location_id',
                'created_at',
                DB::raw(self::PLAYERS_COUNT_SQL),
                DB::raw(self::HAS_DISPUTE_SQL),
            ])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // ---------- Open Tournaments ----------
        $openTournaments = Tournament::with(['competitionLocation'])
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
                'poster',
                'competition_location_id',
                'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // ---------- User Growth ----------
        $userGrowth = [
            'total' => $totalUsers,
            'new_this_week' => $newUsersThisWeek,
        ];

        // ---------- Mini Match Growth ----------
        $todayActive = MiniTournament::whereIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN])
            ->whereDate('created_at', Carbon::today())
            ->count();
        $yesterdayActive = MiniTournament::whereIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN])
            ->whereDate('created_at', Carbon::yesterday())
            ->count();
        $growthPercent = $yesterdayActive > 0
            ? round((($todayActive - $yesterdayActive) / $yesterdayActive) * 100, 2)
            : ($todayActive > 0 ? 100 : 0);

        $miniMatchGrowth = [
            'active_today' => $todayActive,
            'active_yesterday' => $yesterdayActive,
            'growth_percent' => $growthPercent,
        ];

        // ---------- Top Active Locations ----------
        $topLocations = MiniTournament::join('competition_locations', 'competition_locations.id', '=', 'mini_tournaments.competition_location_id')
            ->whereIn('mini_tournaments.status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN])
            ->select('competition_locations.id', 'competition_locations.name', DB::raw('COUNT(*) as match_count'))
            ->groupBy('competition_locations.id', 'competition_locations.name')
            ->orderByDesc('match_count')
            ->limit(5)
            ->get();

        return [
            // Stats cards
            'active_tournaments' => $activeTournaments,
            'tournaments_this_month' => $tournamentsThisMonth,
            'dispute_rate' => $disputeRate,
            'dispute_rate_change' => $disputeRateChange,
            'open_disputes_count' => $openDisputesCount,
            'pending_reports_count' => $pendingReportsCount,
            'monthly_revenue' => $monthlyRevenue,
            'total_revenue' => $totalRevenue,

            // Data lists
            'recent_new_users' => $recentNewUsers,
            'open_mini_tournaments' => $openMiniTournaments,
            'open_tournaments' => $openTournaments,

            // Charts
            'user_growth' => $userGrowth,
            'mini_match_growth' => $miniMatchGrowth,
            'top_locations' => $topLocations,
        ];
    }

    public function getList(string $type, int $page, int $limit, ?string $keyword): LengthAwarePaginator
    {
        return match ($type) {
            'users' => $this->getUsersList($page, $limit, $keyword),
            'matches' => $this->getMatchesList($page, $limit, $keyword),
            'tournaments' => $this->getTournamentsList($page, $limit, $keyword),
            'recent_new_users' => $this->getRecentNewUsersList($page, $limit, $keyword),
            'open_mini_tournaments' => $this->getOpenMiniTournamentsList($page, $limit, $keyword),
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

    private function getOpenMiniTournamentsList(int $page, int $limit, ?string $keyword)
    {
        $query = MiniTournament::with(['competitionLocation'])
            ->whereIn('status', [MiniTournament::STATUS_DRAFT, MiniTournament::STATUS_OPEN, MiniTournament::STATUS_CANCELLED])
            ->select([
                'mini_tournaments.id',
                'mini_tournaments.name',
                'mini_tournaments.status',
                'mini_tournaments.start_time',
                'mini_tournaments.competition_location_id',
                'mini_tournaments.created_at',
                DB::raw(self::PLAYERS_COUNT_SQL),
                DB::raw(self::HAS_DISPUTE_SQL),
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('mini_tournaments.name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    private function getOpenTournamentsList(int $page, int $limit, ?string $keyword)
    {
        $query = Tournament::with(['competitionLocation'])
            ->whereIn('status', [Tournament::DRAFT, Tournament::OPEN])
            ->select([
                'tournaments.id',
                'tournaments.name',
                'tournaments.status',
                'tournaments.is_featured',
                'tournaments.start_date',
                'tournaments.registration_open_at',
                'tournaments.registration_closed_at',
                'tournaments.fee',
                'tournaments.poster',
                'tournaments.competition_location_id',
                'tournaments.created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('tournaments.name', 'like', "%{$keyword}%");
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
        $query = MiniTournament::with(['competitionLocation'])
            ->select([
                'mini_tournaments.id',
                'mini_tournaments.name',
                'mini_tournaments.status',
                'mini_tournaments.start_time',
                'mini_tournaments.competition_location_id',
                'mini_tournaments.created_at',
                DB::raw(self::PLAYERS_COUNT_SQL),
                DB::raw(self::HAS_DISPUTE_SQL),
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('mini_tournaments.name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }

    private function getTournamentsList(int $page, int $limit, ?string $keyword)
    {
        $query = Tournament::with(['competitionLocation'])
            ->select([
                'tournaments.id',
                'tournaments.name',
                'tournaments.status',
                'tournaments.is_featured',
                'tournaments.created_at',
            ])
            ->orderBy('created_at', 'desc');

        if ($keyword) {
            $query->where('tournaments.name', 'like', "%{$keyword}%");
        }

        return $query->paginate($limit, ['*'], 'page', $page);
    }
}
