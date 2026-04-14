<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\Matches;
use App\Models\TeamRanking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserTournamentResource extends JsonResource
{
    /**
     * @var int|null
     */
    protected ?int $targetUserId = null;

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
     */
    protected function isCompleted(): bool
    {
        return $this->status === 3; // CLOSED = 3
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
     * Vòng đấu hiện tại đang diễn ra.
     * Lấy max round có match chưa hoàn tất (status != 'completed').
     */
    protected function getCurrentRound(): ?string
    {
        $maxRound = $this->tournamentTypes()
            ->whereHas('groups.matches', fn($q) => $q->where('status', '!=', 'completed'))
            ->get()
            ->flatMap(fn($type) => $type->groups)
            ->flatMap(fn($group) => $group->matches)
            ->where('status', '!=', 'completed')
            ->max('round');

        return $this->roundName($maxRound ?: null);
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
            ->where('winner_id', $teamId)
            ->where(function ($q) use ($teamId) {
                $q->where('home_team_id', $teamId)
                  ->orWhere('away_team_id', $teamId);
            })
            ->count();
    }

    /**
     * Số trận thua của user trong giải.
     * Lose = tổng trận completed mà team tham gia - số trận thắng.
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
     */
    protected function getTournamentRank(int $teamId): ?int
    {
        $typeId = $this->tournamentTypes()->value('id');

        if (!$typeId) {
            return null;
        }

        $rank = TeamRanking::where('tournament_type_id', $typeId)
            ->where('team_id', $teamId)
            ->value('rank');

        return $rank ?: null;
    }

    /**
     * Vòng cuối cùng mà team tham gia trong giải (final round user participated).
     */
    protected function getFinalRound(int $teamId): ?string
    {
        $maxRound = $this->tournamentTypes()
            ->with('groups.matches')
            ->get()
            ->flatMap(fn($type) => $type->groups)
            ->flatMap(fn($group) => $group->matches)
            ->filter(fn($match) =>
                ($match->home_team_id === $teamId || $match->away_team_id === $teamId)
                && $match->status === 'completed'
            )
            ->max('round');

        return $this->roundName($maxRound ?: null);
    }

    /**
     * Lấy VN DUPR rating hiện tại của user cho sport_id.
     */
    protected function getUserRating(?int $sportId): ?float
    {
        if (!$this->targetUserId || !$sportId) {
            return null;
        }

        $user = User::find($this->targetUserId);
        if (!$user) {
            return null;
        }

        $score = $user->vnduprScoresBySport($sportId)->max('score_value');

        return $score ? (float) $score : null;
    }

    /**
     * Lấy rank VN hiện tại của user cho sport_id.
     */
    protected function getUserRank(?int $sportId): ?int
    {
        if (!$this->targetUserId || !$sportId) {
            return null;
        }

        $user = User::find($this->targetUserId);
        if (!$user) {
            return null;
        }

        $rank = $user->getVNRank($sportId);

        return $rank ? (int) $rank : null;
    }
}
