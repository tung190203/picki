<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Models\MiniMatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserMiniTournamentResource extends JsonResource
{
    protected ?int $targetUserId = null;

    public function toArray(Request $request): array
    {
        $this->targetUserId = (int) ($request->input('user_id') ?? auth()->id() ?? 0);

        $isCompleted = $this->isCompleted();
        $participant = $this->getTargetParticipant();

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

            // Stats
            'stats' => $this->buildStats($isCompleted, $participant),

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
        return $this->status === 3;
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

    protected function buildStats(bool $isCompleted, ?\App\Models\MiniParticipant $participant): array
    {
        $sportId = $this->sport_id;
        $userId = $this->targetUserId;

        [$totalMatches, $totalWin, $totalLose] = $this->getMatchStats($userId);

        return [
            // Match stats
            'total_matches' => $totalMatches,
            'total_win' => $totalWin,
            'total_lose' => $totalLose,

            // Rating & Rank from participant (sau khi kèo kết thúc)
            'rating_before' => $participant?->rating_before,
            'rating_after' => $participant?->rating_after,
            'rank_before' => $participant?->rank_before,
            'rank_after' => $participant?->rank_after,
            'rank_change' => $participant?->rank_change,

            // Current rating & rank của user
            'current_rating' => $this->getUserRating($sportId, $userId),
            'current_rank' => $this->getUserRank($sportId, $userId),

            // Role của user trong mini tournament
            'role' => $this->getUserRole($userId),
        ];
    }

    protected function getMatchStats(int $userId): array
    {
        $matches = $this->matches ?? collect();

        $totalMatches = 0;
        $totalWin = 0;
        $totalLose = 0;

        foreach ($matches as $match) {
            $isUserParticipant = $match->participant1_id === $userId
                || $match->participant2_id === $userId;

            if (!$isUserParticipant) {
                continue;
            }

            $totalMatches++;

            if ($match->participant_win_id === $userId) {
                $totalWin++;
            } elseif ($match->status === 'completed' && $match->participant_win_id !== null) {
                $totalLose++;
            }
        }

        return [$totalMatches, $totalWin, $totalLose];
    }

    protected function getUserRole(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }

        // Kiểm tra participant
        if ($this->getTargetParticipant()) {
            return 'participant';
        }

        // Kiểm tra staff
        if ($this->relationLoaded('miniTournamentStaffs')) {
            $isStaff = $this->miniTournamentStaffs
                ? $this->miniTournamentStaffs->contains('user_id', $userId)
                : false;

            if ($isStaff) {
                $staff = $this->miniTournamentStaffs->firstWhere('user_id', $userId);
                return $staff?->role === 'organizer' ? 'organizer' : 'staff';
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
