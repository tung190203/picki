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
        return [
            'id'                       => $this->id,
            'is_confirmed'             => (bool) $this->is_confirmed,
            'self_registered'          => (bool) ($this->self_registered ?? false),
            'is_guest'                 => (bool) $this->is_guest,
            'user'                     => $this->user ? new UserListResource($this->user) : null,
            'guest_name'               => $this->when($this->is_guest, $this->guest_name),
            'guest_phone'              => $this->when($this->is_guest, $this->guest_phone),
            'guest_avatar'             => $this->when($this->is_guest, $this->guest_avatar),
            'guarantor'                => $this->guarantor ? new UserListResource($this->guarantor) : null,
            'guarantor_user_id'        => $this->when($this->is_guest, (int) $this->guarantor_user_id),
            'guarantor_name'           => $this->when($this->is_guest, fn() => $this->guarantor?->full_name),
            'estimated_level'          => $this->when($this->is_guest, (float) $this->estimated_level),
            'is_pending_confirmation'  => $this->when($this->is_guest, (bool) $this->is_pending_confirmation),
            'checked_in_at'            => $this->checked_in_at,
            'is_absent'                => (bool) $this->is_absent,
            'is_virtual'               => (bool) ($this->is_virtual ?? false),
            'virtual_member_id'        => $this->virtual_member_id ?? null,
        ];
    }
}
