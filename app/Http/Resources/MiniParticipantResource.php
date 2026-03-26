<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniParticipantResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'is_confirmed'          => (bool) $this->is_confirmed,
            'is_invited'            => (bool) $this->is_invited,
            'payment_status'        => $this->payment_status?->value,
            'payment_status_label'  => $this->payment_status?->label(),
            'joined_at'             => $this->created_at->format('d-m-Y'),
            'user'                  => new UserListResource($this->whenLoaded('user')),
            // Guest fields
            'is_guest'              => (bool) $this->is_guest,
            'guest_name'            => $this->when($this->is_guest, $this->guest_name),
            'guest_phone'           => $this->when($this->is_guest, $this->guest_phone),
            'guarantor'             => new UserListResource($this->whenLoaded('guarantor')),
            'guarantor_user_id'     => $this->when($this->is_guest, $this->guarantor_user_id),
            'guarantor_name'       => $this->when($this->is_guest, fn() => $this->guarantor?->full_name),
            'guarantor_participant_id' => $this->when($this->is_guest, function () {
                return $this->guarantor
                    ? $this->miniTournament
                        ->participants()
                        ->where('user_id', $this->guarantor_user_id)
                        ->value('id')
                    : null;
            }),
            'estimated_level_range' => $this->when(
                $this->is_guest,
                fn() => $this->estimated_level_min && $this->estimated_level_max
                    ? ['min' => (float) $this->estimated_level_min, 'max' => (float) $this->estimated_level_max]
                    : null
            ),
            'is_absent' => (bool) $this->is_absent,
            'checked_in_at' => $this->checked_in_at?->format('d-m-Y H:i'),
        ];
    }
}
