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
     * Hỗ trợ 2 nguồn dữ liệu:
     *  - `User` qua pivot `staff` (belongsToMany) — bản ghi user thật
     *  - `MiniTournamentStaff` trực tiếp — bản ghi user (kể cả is_guest=true) với user_id thật
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->resource instanceof MiniTournamentStaff
            ? $this->resource
            : $this->resource->pivot;

        $userId = $record->user_id ?? null;
        $user = $userId ? User::with(['sports.scores', 'sports.sport'])->find($userId) : null;

        return [
            'id' => $record->id,
            'mini_tournament_id' => $record->mini_tournament_id,
            'user_id' => $userId !== null ? (int) $userId : null,
            'is_guest' => $user ? (bool) $user->is_guest : false,
            'user' => $user ? new UserListResource($user) : null,
            'guest_name' => $user?->full_name ?? $record->guest_name ?? null,
            'guest_avatar' => $user?->avatar_url ?? $record->guest_avatar ?? null,
            'role' => $record->role,
            'role_text' => MiniTournamentStaff::getRoleText($record->role),
            'checked_in_at' => isset($record->checked_in_at) && $record->checked_in_at
                ? \Carbon\Carbon::parse($record->checked_in_at)->format('d-m-Y H:i')
                : null,
            'is_absent' => (bool) ($record->is_absent ?? false),
        ];
    }
}
