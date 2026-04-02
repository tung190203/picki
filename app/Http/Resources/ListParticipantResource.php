<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_id' => $this->tournament_id,
            'full_name' => $this->user?->full_name,
            'avatar_url' => $this->user?->avatar_url,
            'is_confirmed' => $this->is_confirmed,
            'is_invite_by_organizer' => $this->is_invite_by_organizer,
            'is_guest' => (bool) $this->is_guest,
            'user' => new UserListResource($this->whenLoaded('user')),
            'guest_name' => $this->when($this->is_guest, $this->guest_name),
            'guest_phone' => $this->when($this->is_guest, $this->guest_phone),
            'guest_avatar' => $this->when($this->is_guest, $this->guest_avatar),
            'is_pending_confirmation' => $this->when($this->is_guest, (bool) $this->is_pending_confirmation),
        ];
    }
}
