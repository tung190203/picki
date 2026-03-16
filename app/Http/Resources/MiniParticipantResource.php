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
        ];
    }
}
