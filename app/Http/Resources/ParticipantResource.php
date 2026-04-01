<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
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
            'name' => $this->user?->full_name,
            'avatar' => $this->user?->avatar_url ?: User::GUEST_AVATAR_DEFAULT,
            'is_confirmed' => (bool) $this->is_confirmed,
            'is_invite_by_organizer' => $this->is_invite_by_organizer,
            'is_guest' => (bool) $this->is_guest,
            'user' => new UserListResource($this->whenLoaded('user')),
            'guest_name' => $this->when($this->is_guest, $this->guest_name),
            'guest_phone' => $this->when($this->is_guest, $this->guest_phone),
            'guest_avatar' => $this->when($this->is_guest, $this->guest_avatar ?: User::GUEST_AVATAR_DEFAULT),
            'guarantor' => new UserListResource($this->whenLoaded('guarantor')),
            'guarantor_user_id' => $this->when($this->is_guest, $this->guarantor_user_id),
            'guarantor_name' => $this->when($this->is_guest, fn() => $this->guarantor?->full_name),
            'estimated_level' => $this->when($this->is_guest, (float) $this->estimated_level),
            'is_pending_confirmation' => $this->when($this->is_guest, (bool) $this->is_pending_confirmation),
            'checked_in_at' => $this->checked_in_at,
            'is_absent' => (bool) $this->is_absent,
        ];
    }
}
