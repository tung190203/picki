<?php

namespace App\Http\Resources;

use App\Models\Club\ClubVirtualMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MiniParticipantResource extends JsonResource
{
    /**
     * Resolve virtual member (nếu có) từ guest_name + club_id của miniTournament.
     * Lazy lookup, không thêm schema. Match theo name + club_id để tránh nhầm giữa các CLB.
     * Fallback: nếu không thấy trong CLB của miniTournament, thử lookup theo name đơn lẻ (best-effort,
     * trường hợp VM được tạo ở CLB khác nhưng data participant bị lệch CLB — vẫn trả avatar/name đúng).
     */
    private function resolveVirtualMember(): ?ClubVirtualMember
    {
        if (!$this->is_guest) {
            return null;
        }
        if (empty($this->guest_name)) {
            return null;
        }
        $clubId = $this->miniTournament?->club_id;
        if ($clubId) {
            $vm = ClubVirtualMember::where('club_id', $clubId)
                ->where('name', $this->guest_name)
                ->first();
            if ($vm) {
                return $vm;
            }
        }
        // Fallback: match name only (cùng tên giữa 2 CLB rất hiếm, ưu tiên hiển thị đúng)
        return ClubVirtualMember::where('name', $this->guest_name)->first();
    }

    /**
     * Build user object cho guest/virtual participant — bắt buộc đủ 3 field id/name/avatar_url.
     * id và name không được null; avatar_url có thể null.
     */
    private function buildGuestUserObject(): array
    {
        $vm = $this->resolveVirtualMember();
        if ($vm) {
            return [
                'id'         => (int) $vm->id,
                'name'       => (string) ($this->guest_name ?? $vm->name),
                'avatar_url' => $this->guest_avatar ?: ($vm->avatar_url ?? null),
                'is_virtual' => true,
                'virtual_member_id' => (int) $vm->id,
            ];
        }

        // Guest nhập tay — không match VM
        return [
            'id'         => null,
            'name'       => (string) ($this->guest_name ?? ''),
            'avatar_url' => $this->guest_avatar,
            'is_virtual' => false,
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $vm = $this->is_guest ? $this->resolveVirtualMember() : null;

        return [
            'id'                    => $this->id,
            'is_confirmed'          => (bool) $this->is_confirmed,
            'self_confirmed'        => (bool) ($this->self_confirmed ?? true),
            'is_invited'            => (bool) $this->is_invited,
            'invited_by'            => $this->invited_by,
            'invited_by_user'       => new UserListResource($this->whenLoaded('invitedBy')),
            'payment_status'        => $this->payment_status?->value,
            'payment_status_label'  => $this->payment_status?->label(),
            'joined_at'             => $this->created_at->format('d-m-Y'),
            'user'                  => $this->is_guest
                ? $this->buildGuestUserObject()
                : new UserListResource($this->whenLoaded('user')),
            // Guest fields
            'is_guest'              => (bool) $this->is_guest,
            'guest_name'            => $this->when($this->is_guest, $this->guest_name),
            'guest_phone'           => $this->when($this->is_guest, $this->guest_phone),
            'avatar_url'            => $this->user?->avatar_url,
            'guest_avatar'          => $this->when($this->is_guest, $this->guest_avatar),
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
            'is_pending_confirmation' => $this->when($this->is_guest, (bool) $this->is_pending_confirmation),
            'is_absent' => (bool) $this->is_absent,
            'checked_in_at' => $this->checked_in_at?->format('d-m-Y H:i'),
            'is_declined' => $this->declined_at !== null,
            'player_group' => $this->player_group,
            'modified_score' => $this->modified_score,
            'modify_gender' => $this->modify_gender,
            'modified_avatar' => $this->modified_avatar,
            'effective_score' => $this->effective_score,
            'played_matches' => $this->played_matches,
            'is_virtual'       => $vm !== null,
            'virtual_member_id' => $vm?->id,
        ];
    }
}
