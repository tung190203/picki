<?php

namespace App\Http\Resources;

use App\Models\MiniTournamentStaff;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniTournamentStaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->pivot->id,
            'mini_tournament_id' => $this->pivot->mini_tournament_id,
            'user' => $this->pivot->user_id ? new UserListResource(User::with(['sports.scores', 'sports.sport'])->find($this->pivot->user_id)) : null,
            'role' => $this->pivot->role,
            'role_text' => MiniTournamentStaff::getRoleText($this->pivot->role),
            'checked_in_at' => isset($this->pivot->checked_in_at) && $this->pivot->checked_in_at
                ? \Carbon\Carbon::parse($this->pivot->checked_in_at)->format('d-m-Y H:i')
                : null,
            'is_absent' => (bool) ($this->pivot->is_absent ?? false),
        ];
    }
}
