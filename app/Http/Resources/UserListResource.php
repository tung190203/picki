<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserListResource extends JsonResource
{
    private ?int $tournamentId = null;
    private ?int $miniTournamentId = null;

    private bool $includeSports = true;

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
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Resolve guest fields from participant record
        $guestName = null;
        $guestAvatar = null;

        if ($this->tournamentId) {
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
