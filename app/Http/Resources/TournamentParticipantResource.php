<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isGuest = (bool) $this->is_guest;

        return [
            'id'                       => $this->id,
            'is_confirmed'             => (bool) $this->is_confirmed,
            'self_registered'          => (bool) ($this->self_registered ?? false),
            'is_guest'                 => $isGuest,
            'user'                     => $this->user ? new UserListResource($this->user) : null,
            'guest_name'               => $isGuest ? $this->guest_name : null,
            'guest_phone'              => $isGuest ? $this->guest_phone : null,
            'guest_avatar'             => $isGuest ? $this->guest_avatar : null,
            'guarantor'                => $this->guarantor ? new UserListResource($this->guarantor) : null,
            'guarantor_user_id'        => $isGuest ? (int) $this->guarantor_user_id : null,
            'guarantor_name'           => $isGuest ? $this->guarantor?->full_name : null,
            'estimated_level'          => $isGuest ? (float) $this->estimated_level : null,
            'is_pending_confirmation'  => $isGuest ? (bool) $this->is_pending_confirmation : false,
            'checked_in_at'            => $this->checked_in_at,
            'is_absent'                => (bool) $this->is_absent,
            'is_guest' => $this->user ? (bool) $this->user->is_guest : false,
        ];
    }
}
