<?php

namespace App\Http\Resources\Club;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ClubActivityListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'name' => $this->title,
            'status' => $this->status,
            'start_time' => $this->start_time?->format('c'),
            'duration' => $this->duration,
            'end_time' => $this->end_time?->format('c'),
            'location' => $this->address,
            'address' => $this->address,
            'max_participants' => $this->max_participants !== null ? (int) $this->max_participants : null,
            'is_public' => (bool) ($this->is_public ?? true),
            'is_recurring' => $this->recurring_schedule !== null,
            'recurring_schedule' => $this->recurring_schedule,
            'recurrence_series_id' => $this->recurrence_series_id,
            'created_by' => $this->created_by,
            'creator_always_join' => (bool) ($this->creator_always_join ?? true),
            'creator' => new \App\Http\Resources\UserResource($this->whenLoaded('creator')),
            'has_transaction' => (bool) ($this->has_transaction ?? false),
            'fund_collection_id' => $this->fundCollection && isset($this->fundCollection->id)
                ? (int) $this->fundCollection->id
                : null,
            'fee_split_type' => $this->fee_split_type,
            'fee_amount' => $this->fee_amount ? (float) $this->fee_amount : null,
            'guest_fee' => $this->guest_fee ? (float) $this->guest_fee : null,
            'currency' => $this->currency ?? 'VND',
            'participants_count' => $this->whenLoaded('participants', fn() => $this->participants->count()),
            'participants' => $this->whenLoaded('participants', fn() => ClubActivityParticipantResource::collection($this->participants)),
        ];

        return $data;
    }
}
