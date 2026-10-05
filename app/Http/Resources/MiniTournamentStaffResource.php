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
     *  - `MiniTournamentStaff` trực tiếp — bản ghi thành viên ảo (user_id = null)
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $record = $this->resource instanceof MiniTournamentStaff
            ? $this->resource
            : $this->resource->pivot;

        // Thành viên ảo (ClubVirtualMember) có user_id = null, không có bản ghi trong `users`.
        $isVirtual = (bool) ($record->is_virtual ?? false);
        $userId = $record->user_id ?? null;

        return [
            'id' => $record->id,
            'mini_tournament_id' => $record->mini_tournament_id,
            'user_id' => $userId !== null ? (int) $userId : null,
            'is_virtual' => $isVirtual,
            'virtual_member_id' => isset($record->virtual_member_id) && $record->virtual_member_id !== null
                ? (int) $record->virtual_member_id
                : null,
            'user' => $userId
                ? new UserListResource(User::with(['sports.scores', 'sports.sport'])->find($userId))
                : null,
            // Thành viên ảo không có `user` — FE dùng `guest_name` / `guest_avatar` để render.
            'guest_name' => $record->guest_name ?? null,
            'guest_avatar' => $record->guest_avatar ?? null,
            'role' => $record->role,
            'role_text' => MiniTournamentStaff::getRoleText($record->role),
            'checked_in_at' => isset($record->checked_in_at) && $record->checked_in_at
                ? \Carbon\Carbon::parse($record->checked_in_at)->format('d-m-Y H:i')
                : null,
            'is_absent' => (bool) ($record->is_absent ?? false),
        ];
    }
}
