<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\Matches;
use App\Services\TournamentType\RoundNameResolver;
use App\Services\TournamentType\TournamentRankService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserTournamentResource extends JsonResource
{
    /**
     * @var int|null
     */
    protected ?int $targetUserId = null;
    protected static array $preloadedRanks = [];
    protected static array $preloadedRatings = [];

    /**
     * Memo hoá rank của tournament cho instance resource này.
     *
     * @var array<int, array{overall_rank: int, rank_label: string, is_champion: bool}>|null
     */
    protected ?array $resolvedRanks = null;

    public static function preloadRanks(array $userIds, int $sportId = 1): void
    {
        self::$preloadedRanks = User::getBatchVNRanks($userIds, $sportId);
    }

    public static function preloadRatings(array $userIds, int $sportId = 1): void
    {
        if (empty($userIds)) {
            self::$preloadedRatings = [];
            return;
        }
        $rows = \DB::table('user_sport_scores')
            ->join('user_sport', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
            ->where('user_sport.sport_id', $sportId)
            ->where('user_sport_scores.score_type', 'vndupr_score')
            ->whereIn('user_sport.user_id', $userIds)
            ->groupBy('user_sport.user_id')
            ->select('user_sport.user_id', \DB::raw('MAX(user_sport_scores.score_value) as score_value'))
            ->get();
        $map = [];
        foreach ($rows as $row) {
            $map[$row->user_id] = (float) $row->score_value;
        }
        self::$preloadedRatings = $map;
    }

    /**
     * Mapping round number -> tên vòng hiển thị.
     */
    protected const ROUND_NAMES = [
        0 => 'Vòng bảng',
        1 => 'Vòng 1/8',
        2 => 'Tứ kết',
        3 => 'Bán kết',
        4 => 'Chung kết',
        5 => 'Tranh hạng 3',
    ];

    /**
     * Chuyển round number -> tên vòng. Không có thì trả số.
     */
    protected function roundName(?int $round): ?string
    {
        if ($round === null || !array_key_exists($round, self::ROUND_NAMES)) {
            return $round !== null ? "Vòng {$round}" : null;
        }
        return self::ROUND_NAMES[$round];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->targetUserId = (int) ($request->input('user_id') ?? auth()->id() ?? 0);

        $isCompleted = $this->isCompleted();
        $participant = $this->getTargetParticipant();
        $team = $this->getTargetTeam();
        $isParticipant = $participant !== null;
        $role = $this->getTargetRole();

        return [
            // Tournament info - chỉ những field cơ bản
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_completed' => $isCompleted,
            'player_per_team' => $this->player_per_team,
            'gender_policy' => (int) $this->gender_policy,
            'max_team' => $this->max_team,
            'participated_team' => $this->getParticipatedTeamCount(),
            'status' => $this->status,
            'is_creator' => (int) $this->created_by === $this->targetUserId,

            // Participant flag & role
            'is_participant' => $isParticipant,
            'role' => $role,

            // Stats - khác nhau tùy trạng thái
            'stats' => $this->buildStats($isCompleted, $participant, $team, $isParticipant),
        ];
    }

    /**
     * Xác định giải đấu đã kết thúc hay chưa.
     * Giải chỉ completed khi tất cả trận đấu đã có kết quả xác nhận.
     */
    protected function isCompleted(): bool
    {
        if ($this->status !== 3) {
            return false;
        }

        $tournamentTypes = $this->tournamentTypes ?? collect();

        $allMatches = $tournamentTypes
            ->flatMap(fn($type) => $type->groups ?? collect())
            ->flatMap(fn($group) => $group->matches ?? collect());

        if ($allMatches->isEmpty()) {
            return true;
        }

        // Kiểm tra results đã eager loaded chưa
        $firstMatch = $allMatches->first();
        $resultsLoaded = $firstMatch && $firstMatch->relationLoaded('results');

        if ($resultsLoaded) {
            // Dùng dữ liệu đã eager load (tránh N+1)
            $incompleteCount = $allMatches
                ->filter(fn($match) => !$match->relationLoaded('results') || $match->results->isEmpty())
                ->count();
            return $incompleteCount === 0;
        }

        // Fallback: query DB khi results chưa loaded
        $matchIds = $allMatches->pluck('id')->toArray();
        if (empty($matchIds)) {
            return true;
        }

        $matchesWithResults = DB::table('match_results')
            ->whereIn('match_id', $matchIds)
            ->distinct()
            ->pluck('match_id')
            ->count();

        return $matchesWithResults === count($matchIds);
    }

    /**
     * Lấy participant của target user trong tournament này.
     */
    protected function getTargetParticipant(): ?\App\Models\Participant
    {
        if (!$this->targetUserId) {
            return null;
        }

        $participant = $this->participants
            ? $this->participants->firstWhere('user_id', $this->targetUserId)
            : null;

        // Lazy load nếu chưa có
        if (!$participant && $this->relationLoaded('participants')) {
            $participant = $this->participants
                ->filter(fn($p) => $p->user_id === $this->targetUserId)
                ->first();
        }

        return $participant;
    }

    /**
     * Lấy team của target user trong tournament này.
     * User có thể thuộc nhiều team, lấy team đầu tiên.
     */
    protected function getTargetTeam(): ?\App\Models\Team
    {
        if (!$this->targetUserId) {
            return null;
        }

        if (!$this->teams) {
            return null;
        }

        foreach ($this->teams as $team) {
            // Ưu tiên kiểm tra qua relation đã load sẵn
            if ($team->relationLoaded('members') && $team->members->contains('id', $this->targetUserId)) {
                return $team;
            }
        }

        // Nếu relation chưa load, query trực tiếp từ DB cho từng team
        foreach ($this->teams as $team) {
            $hasMember = DB::table('team_members')
                ->where('team_id', $team->id)
                ->where('user_id', $this->targetUserId)
                ->exists();
            if ($hasMember) {
                return $team;
            }
        }

        return null;
    }

    /**
     * Lấy role của target user trong tournament (staff/organizer).
     */
    protected function getTargetRole(): ?string
    {
        if (!$this->targetUserId) {
            return null;
        }

        if ($this->relationLoaded('tournamentStaffs') && $this->tournamentStaffs) {
            $staff = $this->tournamentStaffs->firstWhere('user_id', $this->targetUserId);
            if ($staff) {
                return match ((int) $staff->role) {
                    1 => 'organizer',
                    2 => 'staff',
                    default => null,
                };
            }
        }

        return null;
    }

    /**
     * Xây dựng stats theo trạng thái giải đấu.
     */
    protected function buildStats(bool $isCompleted, ?\App\Models\Participant $participant, ?\App\Models\Team $team, bool $isParticipant): array
    {
        $sportId = $this->sport_id;
        $role = $this->getTargetRole();

        if (!$isParticipant) {
            return [
                'current_round'      => null,
                'total_matches_left'=> null,
                'total_win'         => null,
                'total_lose'        => null,
                'tournament_rank'    => null,
                'final_round'       => null,
                'current_rating'    => null,
                'current_rank'      => null,
                'rank_change'       => null,
                'role'              => $role,
            ];
        }

        if ($isCompleted) {
            return $this->buildFinishedStats($participant, $team, $sportId);
        }

        return $this->buildOngoingStats($participant, $team, $sportId);
    }

    /**
     * Stats cho giải đang diễn ra.
     */
    protected function buildOngoingStats(?\App\Models\Participant $participant, ?\App\Models\Team $team, ?int $sportId): array
    {
        $teamId = $team?->id;

        return [
            'role' => 'participant',
            'current_round' => $this->getCurrentRound(),
            'total_matches_left' => $this->getTotalMatchesLeft(),
            'total_win' => $teamId ? $this->getTeamWinCount($teamId) : 0,
            'total_lose' => $teamId ? $this->getTeamLoseCount($teamId) : 0,
            'tournament_rank' => $teamId ? $this->getTournamentRank($teamId) : null,
            'current_rating' => $this->getUserRating($sportId),
            'current_rank' => $this->getUserRank($sportId),
        ];
    }

    /**
     * Stats cho giải đã kết thúc.
     */
    protected function buildFinishedStats(?\App\Models\Participant $participant, ?\App\Models\Team $team, ?int $sportId): array
    {
        $teamId = $team?->id;

        return [
            'role' => 'participant',
            'tournament_rank' => $teamId ? $this->getTournamentRank($teamId) : null,
            'total_win' => $teamId ? $this->getTeamWinCount($teamId) : 0,
            'total_lose' => $teamId ? $this->getTeamLoseCount($teamId) : 0,
            'final_round' => $teamId ? $this->getFinalRound($teamId) : null,
            'current_rating' => $this->getUserRating($sportId),
            'current_rank' => $this->getUserRank($sportId),
            'rank_change' => $participant?->rank_change,
        ];
    }

    /**
     * Đếm số team thực tế đã tham gia giải.
     * Ưu tiên dùng eager-loaded teams, fallback query DB.
     */
    protected function getParticipatedTeamCount(): int
    {
        if ($this->relationLoaded('teams') && $this->teams) {
            return $this->teams->filter(fn($t) => ($t->members_count ?? 0) >= 2)->count();
        }

        return DB::table('teams')
            ->where('tournament_id', $this->id)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('team_members')
                    ->whereColumn('team_members.team_id', 'teams.id')
                    ->groupBy('team_members.team_id')
                    ->havingRaw('COUNT(*) >= 2');
            })
            ->count();
    }

    /**
     * Vòng đấu hiện tại đang diễn ra.
     * Lấy max round có match chưa hoàn tất (status != 'completed').
     */
    protected function getCurrentRound(): ?string
    {
        $bestRound = null;
        $bestFormat = null;
        $bestMatches = null;

        foreach ($this->tournamentTypes()->with('matches')->get() as $type) {
            $maxRound = $type->matches
                ->where('status', '!=', 'completed')
                ->max('round');

            if ($maxRound !== null && ($bestRound === null || $maxRound > $bestRound)) {
                $bestRound = $maxRound;
                $bestFormat = (int) $type->format;
                $bestMatches = $type->matches;
            }
        }

        if ($bestRound === null || $bestMatches === null || $bestFormat === null) {
            return null;
        }

        $roundNames = app(RoundNameResolver::class)->resolve($bestMatches, $bestFormat);

        return $roundNames[$bestRound] ?? $this->roundName($bestRound);
    }

    /**
     * Tổng số trận còn lại của toàn giải (kể cả tranh hạng 3).
     */
    protected function getTotalMatchesLeft(): int
    {
        $count = $this->tournamentTypes()
            ->with('groups.matches')
            ->get()
            ->flatMap(fn($type) => $type->groups)
            ->flatMap(fn($group) => $group->matches)
            ->where('status', 'pending')
            ->count();

        return $count ?: 0;
    }

    /**
     * Số trận thắng của user trong giải.
     * Win = trận completed mà team chứa user là winner.
     * Bỏ trận tranh hạng 3 để khớp chuẩn LeaderboardController::getTeamStats().
     */
    protected function getTeamWinCount(int $teamId): int
    {
        $typeIds = $this->tournamentTypes()->pluck('id')->toArray();

        if (empty($typeIds)) {
            return 0;
        }

        return DB::table('matches')
            ->where('tournament_type_id', $typeIds)
            ->where('status', 'completed')
            ->where('is_third_place', '!=', true)
            ->whereNotNull('winner_id')
            ->where('winner_id', $teamId)
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                  ->orWhere('away_team_id', $teamId);
            })
            ->count();
    }

    /**
     * Số trận thua của user trong giải.
     * Lose = tổng trận có kết quả (không tính trận hạng 3) - số trận thắng,
     * nên trận hòa không bị tính nhầm là thua.
     */
    protected function getTeamLoseCount(int $teamId): int
    {
        $typeIds = $this->tournamentTypes()->pluck('id')->toArray();

        if (empty($typeIds)) {
            return 0;
        }

        $totalCompleted = DB::table('matches')
            ->where('tournament_type_id', $typeIds)
            ->where('status', 'completed')
            ->where('is_third_place', '!=', true)
            ->whereNotNull('winner_id')
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                  ->orWhere('away_team_id', $teamId);
            })
            ->count();

        return $totalCompleted - $this->getTeamWinCount($teamId);
    }

    /**
     * Xếp hạng của team trong giải đấu.
     * Lấy từ TournamentRankService - cùng nguồn dữ liệu với API leaderboard,
     * nên khớp đúng thứ hạng hiển thị trên bảng xếp hạng của tournament.
     */
    protected function getTournamentRank(int $teamId): ?int
    {
        return $this->getResolvedRanks()[$teamId]['overall_rank'] ?? null;
    }

    /**
     * Rank label của team (Vô địch / Á quân / Hạng 3 / Tứ kết ...).
     */
    protected function getTournamentRankLabel(int $teamId): ?string
    {
        return $this->getResolvedRanks()[$teamId]['rank_label'] ?? null;
    }

    /**
     * Rank của mọi team trong tournament, memo hoá cho từng instance resource
     * để không gọi service lặp lại giữa buildStats() và getFinalRound().
     */
    protected function getResolvedRanks(): array
    {
        if ($this->resolvedRanks === null) {
            $this->resolvedRanks = app(TournamentRankService::class)
                ->bestRanksForTournament((int) $this->id);
        }

        return $this->resolvedRanks;
    }

    /**
     * Vòng cuối cùng mà team tham gia trong giải (final round user participated).
     * Ưu tiên rank_label từ TournamentRankService (đúng theo bracket thật),
     * fallback về tên vòng cao nhất từ matches khi chưa có label.
     */
    protected function getFinalRound(int $teamId): ?string
    {
        $label = $this->getTournamentRankLabel($teamId);
        if ($label !== null) {
            return $label;
        }

        // Fallback: vòng cao nhất mà team đã đá có kết quả.
        // Tên vòng lấy từ RoundNameResolver vì matches.round chỉ là số thứ tự.
        $bestRound = null;
        $bestFormat = null;
        $bestMatches = null;

        foreach ($this->tournamentTypes()->with('matches')->get() as $type) {
            $teamMatches = $type->matches->filter(
                fn($match) => ($match->home_team_id === $teamId || $match->away_team_id === $teamId)
                    && $match->status === 'completed'
            );

            $maxRound = $teamMatches->max('round');
            if ($maxRound !== null && ($bestRound === null || $maxRound > $bestRound)) {
                $bestRound = $maxRound;
                $bestFormat = (int) $type->format;
                $bestMatches = $type->matches;
            }
        }

        if ($bestRound === null || $bestMatches === null || $bestFormat === null) {
            return null;
        }

        $roundNames = app(RoundNameResolver::class)->resolve($bestMatches, $bestFormat);

        return $roundNames[$bestRound] ?? null;
    }

    /**
     * Lấy VN DUPR rating hiện tại của user cho sport_id.
     */
    protected function getUserRating(?int $sportId): ?float
    {
        if (!$this->targetUserId || !$sportId) {
            return null;
        }

        return self::$preloadedRatings[$this->targetUserId] ?? null;
    }

    /**
     * Lấy rank VN hiện tại của user cho sport_id.
     */
    protected function getUserRank(?int $sportId): ?int
    {
        if (!$this->targetUserId || !$sportId) {
            return null;
        }

        return self::$preloadedRanks[$this->targetUserId] ?? null;
    }
}
