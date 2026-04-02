<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentStaffResource extends JsonResource
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
            'user_id'                   => (int) $this->user_id,
            'staff'                     => $this->whenLoaded('user', function () {
                return [
                    'id'       => $this->user->id,
                    'name'     => $this->user->full_name,
                    'avatar'   => $this->user->avatar_url,
                    'sports'   => UserSportResource::collection($this->user?->sports ?? []),
                    'is_confirmed' => true,
                ];
            }),
            'role'                     => (int) $this->role,
            'role_text'                 => $this->role_text,
            'is_invite_by_organizer'    => (bool) $this->is_invite_by_organizer,
            'checked_in_at'            => $this->checked_in_at?->format('d-m-Y H:i'),
            'is_absent'                => (bool) $this->is_absent,
        ];
    }
}
