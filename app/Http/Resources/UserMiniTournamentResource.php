<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\MiniMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserMiniTournamentResource extends JsonResource
{
    protected ?int $targetUserId = null;

    public function toArray(Request $request): array
    {
        $this->targetUserId = (int) ($request->input('user_id') ?? auth()->id() ?? 0);

        $isCompleted = $this->isCompleted();
        $participant = $this->getTargetParticipant();
        $isParticipant = $participant !== null;
        $role = $this->getTargetRole();

        $posterUrl = $this->poster
            ? asset('storage/' . $this->poster)
            : null;

        return [
            // Mini Tournament info
            'id' => $this->id,
            'name' => $this->name,
            'poster' => $posterUrl,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'is_completed' => $isCompleted,
            'is_creator' => (int) $this->created_by === $this->targetUserId,

            // Sport info
            'sport' => new SportResource($this->whenLoaded('sport')),

            // Club info (chỉ public club)
            'club' => ($this->club && $this->club->is_public !== false)
                ? new ClubResource($this->club)
                : null,

            // Competition location
            'competition_location' => new CompetitionLocationResource(
                $this->whenLoaded('competitionLocation')
            ),

            // Participants list (app tự filter: is_confirmed=true && payment_status=confirmed)
            'participants' => $this->relationLoaded('participants')
                ? $this->participants->map(fn($p) => [
                    'id' => $p->id,
                    'user_id' => $p->user_id,
                    'user' => $p->user ? [
                        'id' => $p->user->id,
                        'full_name' => $p->user->full_name,
                        'avatar_url' => $p->user->avatar_url,
                    ] : null,
                    'is_confirmed' => (bool) $p->is_confirmed,
                    'payment_status' => $p->payment_status?->value ?? null,
                ])->toArray()
                : [],

            // Participant flag & role
            'is_participant' => $isParticipant,
            'role' => $role,

            // Stats
            'stats' => $this->buildStats($isCompleted, $participant, $isParticipant),

            // Metadata
            'has_fee' => $this->has_fee,
            'fee_amount' => $this->fee_amount,
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,
            'max_players' => $this->max_players,
            'play_mode' => $this->play_mode,
            'format' => $this->format,
        ];
    }

    protected function isCompleted(): bool
    {
        if ($this->status === 3) {
            return true;
        }

        if ($this->end_time && now()->greaterThan($this->end_time)) {
            $totalMatches = $this->matches?->count() ?? 0;
            $completedMatches = $this->matches?->where('status', 'completed')->count() ?? 0;
            return $totalMatches > 0 && $totalMatches === $completedMatches;
        }

        return false;
    }

    protected function getTargetParticipant(): ?\App\Models\MiniParticipant
    {
        if (!$this->targetUserId) {
            return null;
        }

        $participant = $this->participants
            ? $this->participants->firstWhere('user_id', $this->targetUserId)
            : null;

        if (!$participant && $this->relationLoaded('participants')) {
            $participant = $this->participants
                ->filter(fn($p) => $p->user_id === $this->targetUserId)
                ->first();
        }

        return $participant;
    }

    protected function buildStats(bool $isCompleted, ?\App\Models\MiniParticipant $participant, bool $isParticipant): array
    {
        $sportId = $this->sport_id;

        if (!$isParticipant) {
            return [
                'total_matches' => null,
                'total_win' => null,
                'total_lose' => null,
                'rating_before' => null,
                'rating_after' => null,
                'rank_before' => null,
                'rank_after' => null,
                'rank_change' => null,
                'current_rating' => null,
                'current_rank' => null,
                'role' => null,
            ];
        }

        if ($isCompleted) {
            return $this->buildFinishedStats($participant, $sportId);
        }

        return $this->buildOngoingStats($participant, $sportId);
    }

    protected function buildOngoingStats(?\App\Models\MiniParticipant $participant, ?int $sportId): array
    {
        $userId = $this->targetUserId;
        [$totalMatches, $totalWin, $totalLose] = $this->getMatchStats($userId);

        return [
            'total_matches' => $totalMatches,
            'total_win' => $totalWin,
            'total_lose' => $totalLose,
            'current_rating' => $this->getUserRating($sportId, $userId),
            'current_rank' => $this->getUserRank($sportId, $userId),
            'role' => 'participant',
        ];
    }

    protected function buildFinishedStats(?\App\Models\MiniParticipant $participant, ?int $sportId): array
    {
        $userId = $this->targetUserId;
        [$totalMatches, $totalWin, $totalLose] = $this->getMatchStats($userId);

        return [
            'total_matches' => $totalMatches,
            'total_win' => $totalWin,
            'total_lose' => $totalLose,
            'rating_before' => $participant?->rating_before,
            'rating_after' => $participant?->rating_after,
            'rank_before' => $participant?->rank_before,
            'rank_after' => $participant?->rank_after,
            'rank_change' => $participant?->rank_change,
            'current_rating' => $this->getUserRating($sportId, $userId),
            'current_rank' => $this->getUserRank($sportId, $userId),
            'role' => 'participant',
        ];
    }

    protected function getMatchStats(int $userId): array
    {
        $matches = $this->matches ?? collect();

        // Get user's team IDs from mini_team_members table (đấu đôi)
        $userTeamIds = DB::table('mini_team_members')
            ->join('mini_teams', 'mini_team_members.mini_team_id', '=', 'mini_teams.id')
            ->where('mini_teams.mini_tournament_id', $this->id)
            ->where('mini_team_members.user_id', $userId)
            ->pluck('mini_team_id')
            ->toArray();

        // Get user's participant IDs from mini_participants table (đấu đơn)
        $userParticipantIds = DB::table('mini_participants')
            ->where('mini_tournament_id', $this->id)
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();

        $totalMatches = 0;
        $totalWin = 0;
        $totalLose = 0;

        foreach ($matches as $match) {
            $isUserParticipant = false;
            $isWinner = false;

            // Check double match (đấu đôi) - qua team IDs
            if (!empty($userTeamIds)) {
                if (in_array($match->team1_id, $userTeamIds) || in_array($match->team2_id, $userTeamIds)) {
                    $isUserParticipant = true;
                    // Kiểm tra thắng/thua qua team_win_id
                    if ($match->team_win_id && in_array($match->team_win_id, $userTeamIds)) {
                        $isWinner = true;
                    }
                }
            }

            // Check single match (đấu đơn) - qua participant IDs
            if (!empty($userParticipantIds)) {
                if (in_array($match->participant1_id, $userParticipantIds) || in_array($match->participant2_id, $userParticipantIds)) {
                    $isUserParticipant = true;
                    // Kiểm tra thắng/thua qua participant_win_id
                    if ($match->participant_win_id && in_array($match->participant_win_id, $userParticipantIds)) {
                        $isWinner = true;
                    }
                }
            }

            if (!$isUserParticipant) {
                continue;
            }

            $totalMatches++;

            if ($isWinner) {
                $totalWin++;
            } elseif ($match->status === 'completed' && ($match->team_win_id || $match->participant_win_id)) {
                // Chỉ tính thua khi trận đấu đã hoàn thành VÀ có người thắng
                $totalLose++;
            }
        }

        return [$totalMatches, $totalWin, $totalLose];
    }

    /**
     * Lấy role của target user trong mini tournament.
     */
    protected function getTargetRole(): ?string
    {
        if (!$this->targetUserId) {
            return null;
        }

        // Kiểm tra participant trước
        if ($this->getTargetParticipant()) {
            return 'participant';
        }

        // Kiểm tra staff
        if ($this->relationLoaded('miniTournamentStaffs')) {
            $staff = $this->miniTournamentStaffs
                ? $this->miniTournamentStaffs->firstWhere('user_id', $this->targetUserId)
                : null;

            if ($staff) {
                return match ((int) $staff->role) {
                    \App\Models\MiniTournamentStaff::ROLE_ORGANIZER => 'organizer',
                    \App\Models\MiniTournamentStaff::ROLE_REFEREE => 'staff',
                    default => null,
                };
            }
        }

        return null;
    }

    protected function getUserRating(?int $sportId, ?int $userId): ?float
    {
        if (!$userId || !$sportId) {
            return null;
        }

        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        $score = $user->vnduprScoresBySport($sportId)->max('score_value');

        return $score ? (float) $score : null;
    }

    protected function getUserRank(?int $sportId, ?int $userId): ?int
    {
        if (!$userId || !$sportId) {
            return null;
        }

        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        $rank = $user->getVNRank($sportId);

        return $rank ? (int) $rank : null;
    }
}
