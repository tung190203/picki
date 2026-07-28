<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListResource extends JsonResource
{
    private ?int $tournamentId = null;
    private ?int $miniTournamentId = null;

    private bool $includeSports = true;

    private static ?array $batchGuestStatuses = null;

    /**
     * Bỏ key sports (dùng khi member đã có sports ở cấp ngoài, ví dụ trong tournament_participant lồng trong TeamMember).
     */
    public function withoutSports(): static
    {
        $clone = clone $this;
        $clone->includeSports = false;

        return $clone;
    }

    public function forTournament(?int $tournamentId): static
    {
        $this->tournamentId = $tournamentId;
        return $this;
    }

    public function forMiniTournament(?int $miniTournamentId): static
    {
        $this->miniTournamentId = $miniTournamentId;
        return $this;
    }

    /**
     * Batch preload guest statuses for all users in a tournament.
     * Call this before rendering multiple UserListResource instances.
     */
    public static function preloadTournamentGuestStatuses(int $tournamentId, array $userIds): void
    {
        if (empty($userIds)) {
            self::$batchGuestStatuses = [];
            return;
        }

        $participants = \App\Models\Participant::where('tournament_id', $tournamentId)
            ->whereIn('user_id', $userIds)
            ->where('is_guest', true)
            ->get()
            ->keyBy('user_id');

        self::$batchGuestStatuses = [];
        foreach ($userIds as $userId) {
            $participant = $participants->get($userId);
            self::$batchGuestStatuses[$userId] = $participant ? [
                'guest_name' => $participant->guest_name,
                'guest_avatar' => $participant->guest_avatar,
            ] : null;
        }
    }

    /**
     * Batch preload guest statuses for all users in a mini-tournament.
     */
    public static function preloadMiniTournamentGuestStatuses(int $miniTournamentId, array $userIds): void
    {
        if (empty($userIds)) {
            self::$batchGuestStatuses = [];
            return;
        }

        $participants = \App\Models\MiniParticipant::where('mini_tournament_id', $miniTournamentId)
            ->whereIn('user_id', $userIds)
            ->where('is_guest', true)
            ->get()
            ->keyBy('user_id');

        self::$batchGuestStatuses = [];
        foreach ($userIds as $userId) {
            $participant = $participants->get($userId);
            self::$batchGuestStatuses[$userId] = $participant ? [
                'guest_name' => $participant->guest_name,
                'guest_avatar' => $participant->guest_avatar,
            ] : null;
        }
    }

    /**
     * Clear batch data after use.
     */
    public static function clearBatchGuestStatuses(): void
    {
        self::$batchGuestStatuses = null;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Use batch preloaded guest status if available (much faster than individual queries)
        $guestName = null;
        $guestAvatar = null;

        if (self::$batchGuestStatuses !== null && isset(self::$batchGuestStatuses[$this->id])) {
            $batchData = self::$batchGuestStatuses[$this->id];
            if ($batchData) {
                $guestName = $batchData['guest_name'];
                $guestAvatar = $batchData['guest_avatar'];
            }
        } elseif ($this->tournamentId) {
            // Fallback to individual query only if batch not preloaded
            $participant = \App\Models\Participant::where('tournament_id', $this->tournamentId)
                ->where('user_id', $this->id)
                ->first();
            if ($participant?->is_guest) {
                $guestName = $participant->guest_name;
                $guestAvatar = $participant->guest_avatar;
            }
        } elseif ($this->miniTournamentId) {
            $participant = \App\Models\MiniParticipant::where('mini_tournament_id', $this->miniTournamentId)
                ->where('user_id', $this->id)
                ->first();
            if ($participant?->is_guest) {
                $guestName = $participant->guest_name;
                $guestAvatar = $participant->guest_avatar;
            }
        }

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'visibility' => $this->visibility,
            'avatar_url' => $this->avatar_url,
            'thumbnail' => $this->thumbnail,
            'gender' => $this->gender,
            'gender_text' => $this->gender_text,
            'play_times' => UserPlayTimeResource::collection($this->whenLoaded('playTimes')),
            'sports' => $this->when(
                $this->includeSports,
                fn () => UserSportResource::collection($this->whenLoaded('sports')) ?? []
            ),
            'is_manager' => $this->whenPivotLoaded('club_members', fn() => (bool)$this->pivot->is_manager, false),
            'rank_in_club' => $this->whenPivotLoaded(
                'club_members',
                fn () => $this->pivot->rank_in_club ?? null
            ),
            'is_anchor' => (bool) $this->is_anchor,
            'is_verify' => (bool) ($this->total_matches_has_anchor >= 10),
            'is_guest'  => (bool) $this->is_guest,
            'guest_name' => $guestName,
            'guest_avatar' => $guestAvatar,
        ];
    }
}
