<?php

namespace App\Http\Resources;

use App\Models\MiniParticipant;
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
        // Load guest participants if guest_ids exists
        $guestParticipants = null;
        if (!empty($this->guest_ids)) {
            $guestParticipants = MiniParticipantResource::collection(
                MiniParticipant::with(['user', 'guarantor'])
                    ->whereIn('id', $this->guest_ids)
                    ->get()
            );
        }

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
            // Guest fields
            'guest_ids' => $this->guest_ids ?? [],
            'guest_participants' => $guestParticipants,
        ];
    }
}
