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

        return [
            // Tournament info - chỉ những field cơ bản
            'id' => $this->id,
            'name' => $this->name,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'is_completed' => $isCompleted,
            'player_per_team' => $this->player_per_team,
            'gender' => $this->gender_policy,
            'max_team' => $this->max_team,
            'status' => $this->status,

            // Stats - khác nhau tùy trạng thái
            'stats' => $this->buildStats($isCompleted, $participant, $team),
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
     */
    protected function getTargetTeam(): ?\App\Models\Team
    {
        if (!$this->relationLoaded('participants')) {
            return null;
        }

        $participant = $this->getTargetParticipant();
        if (!$participant || !$participant->team_id) {
            return null;
        }

        return $this->teams
            ? $this->teams->firstWhere('id', $participant->team_id)
            : null;
    }

    /**
     * Xây dựng stats theo trạng thái giải đấu.
     */
    protected function buildStats(bool $isCompleted, ?\App\Models\Participant $participant, ?\App\Models\Team $team): array
    {
        $sportId = $this->sport_id;

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
            // current_round: vòng đấu đang diễn ra (max round có pending match)
            'current_round' => $this->getCurrentRound(),

            // total_matches_left: tổng số trận còn lại (pending + bye chưa xử lý)
            'total_matches_left' => $this->getTotalMatchesLeft(),

            // Win/Lose tính từ match results
            'total_win' => $teamId ? $this->getTeamWinCount($teamId) : 0,
            'total_lose' => $teamId ? $this->getTeamLoseCount($teamId) : 0,

            // tournament_rank: xếp hạng trong giải
            'tournament_rank' => $teamId ? $this->getTournamentRank($teamId) : null,

            // current_rating: điểm VN DUPR hiện tại của user
            'current_rating' => $this->getUserRating($sportId),

            // current_rank: hạng trong hệ thống VN
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
            // tournament_rank: xếp hạng trong giải (lấy từ rank_change field)
            'tournament_rank' => $teamId ? $this->getTournamentRank($teamId) : null,

            // total_win/lose
            'total_win' => $teamId ? $this->getTeamWinCount($teamId) : 0,
            'total_lose' => $teamId ? $this->getTeamLoseCount($teamId) : 0,

            // final_round: vòng cuối cùng user tham gia
            'final_round' => $teamId ? $this->getFinalRound($teamId) : null,

            // current_rating: điểm VN DUPR hiện tại
            'current_rating' => $this->getUserRating($sportId),

            // current_rank: hạng trong hệ thống VN
            'current_rank' => $this->getUserRank($sportId),

            // rank_change: thay đổi hạng (participant đã được update khi giải kết thúc)
            'rank_change' => $participant?->rank_change,
        ];
    }

    /**
     * Vòng đấu hiện tại đang diễn ra.
     * Lấy max round có match chưa hoàn tất (status != 'completed').
     */
    protected function getCurrentRound(): ?int
    {
        $maxRound = $this->tournamentTypes()
            ->whereHas('groups.matches', fn($q) => $q->where('status', '!=', 'completed'))
            ->get()
            ->flatMap(fn($type) => $type->groups)
            ->flatMap(fn($group) => $group->matches)
            ->where('status', '!=', 'completed')
            ->max('round');

        return $maxRound ?: null;
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
     * Số trận thắng của team trong giải.
     */
    protected function getTeamWinCount(int $teamId): int
    {
        return $this->tournamentTypes()
            ->with('groups.matches.results')
            ->get()
            ->flatMap(fn($type) => $type->groups)
            ->flatMap(fn($group) => $group->matches)
            ->where('winner_id', $teamId)
            ->count();
    }

    /**
     * Số trận thua của team trong giải.
     */
    protected function getTeamLoseCount(int $teamId): int
    {
        $totalMatches = $this->tournamentTypes()
            ->with('groups.matches.results')
            ->get()
            ->flatMap(fn($type) => $type->groups)
            ->flatMap(fn($group) => $group->matches)
            ->filter(fn($match) =>
                $match->home_team_id === $teamId || $match->away_team_id === $teamId
            )
            ->count();

        $wins = $this->getTeamWinCount($teamId);

        return max(0, $totalMatches - $wins);
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
    protected function getFinalRound(int $teamId): ?int
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

        return $maxRound ?: null;
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
