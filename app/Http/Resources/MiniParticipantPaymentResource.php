<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniParticipantPaymentResource extends JsonResource
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
            'mini_tournament_id' => $this->mini_tournament_id,
            'participant_id' => $this->participant_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'amount' => $this->amount,
            'status' => $this->status,
            'status_text' => $this->status_text,
            'receipt_image' => $this->receipt_image,
            'note' => $this->note,
            'admin_note' => $this->admin_note,
            'paid_at' => $this->paid_at,
            'confirmed_at' => $this->confirmed_at,
            'confirmed_by' => $this->confirmed_by,
            'confirmer' => $this->whenLoaded('confirmer', fn() => new UserResource($this->confirmer)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Guarantor info khi payment thuộc về guest
            'guarantor_id' => $this->whenLoaded('participant', fn() => $this->participant?->guarantor_user_id),
            'guarantor' => $this->whenLoaded('participant', fn() => $this->participant?->guarantor ? new UserResource($this->participant->guarantor) : null),
            // Participant info để FE tách từng item (hiện tại là guest)
            'participant' => $this->whenLoaded('participant', fn() => new MiniParticipantResource($this->participant)),
        ];
    }
}
