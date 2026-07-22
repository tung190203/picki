<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class UserMiniTournamentResource extends JsonResource
{
    protected ?int $targetUserId = null;
    protected static array $preloadedRanks = [];
    protected static array $preloadedRatings = [];

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
            'id' => $this->id,
            'name' => $this->name,
            'poster' => $posterUrl,
            'start_time' => $this->start_time ? $this->start_time->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d\TH:i:s') : null,
            'end_time' => $this->end_time ? $this->end_time->timezone('Asia/Ho_Chi_Minh')->format('Y-m-d\TH:i:s') : null,
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
                    'is_guest' => (bool) $p->is_guest,
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
            'match_format' => $this->match_format ?: null,
            'session_status' => $this->session_status,
            'is_session_started' => $this->is_session_started,
            'zalo_link' => $this->zalo_link,
        ];
    }

    protected function isCompleted(): bool
    {
        if ($this->status !== 3) {
            return false;
        }

        $matches = $this->matches ?? collect();

        if ($matches->isEmpty()) {
            return false;
        }

        return $matches->contains(fn($m) => $m->relationLoaded('results') && $m->results->isNotEmpty());
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

        return self::$preloadedRatings[$userId] ?? null;
    }

    protected function getUserRank(?int $sportId, ?int $userId): ?int
    {
        if (!$userId || !$sportId) {
            return null;
        }

        return self::$preloadedRanks[$userId] ?? null;
    }
}
