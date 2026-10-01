<?php

namespace App\Http\Resources;

use App\Models\Club\ClubVirtualMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TournamentParticipantPaymentResource;

class ParticipantResource extends JsonResource
{
    private bool $omitNestedUserSports = false;

    /**
     * Không trả sports trong nested user (member đã có sports ở root — TeamMemberResource).
     */
    public function withoutNestedUserSports(): static
    {
        $clone = clone $this;
        $clone->omitNestedUserSports = true;

        return $clone;
    }

    /**
     * Resolve virtual member (nếu có) từ guest_name + club_id của tournament.
     * Lazy lookup, không thêm schema. Match theo name + club_id để tránh nhầm giữa các CLB.
     * Fallback: nếu không thấy trong CLB của tournament, thử lookup theo name đơn lẻ (best-effort).
     */
    private function resolveVirtualMember(): ?ClubVirtualMember
    {
        if (!$this->is_guest) {
            return null;
        }
        if (empty($this->guest_name)) {
            return null;
        }
        $clubId = $this->tournament?->club_id;
        if ($clubId) {
            $vm = ClubVirtualMember::where('club_id', $clubId)
                ->where('name', $this->guest_name)
                ->first();
            if ($vm) {
                return $vm;
            }
        }
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
            'id' => $this->id,
            'name' => $this->user?->full_name,
            'avatar' => $this->user?->avatar_url,
            'is_confirmed' => (bool) $this->is_confirmed,
            'self_confirmed' => (bool) ($this->self_confirmed ?? true),
            'self_registered' => (bool) ($this->self_registered ?? false),
            'is_guest' => (bool) $this->is_guest,
            'user' => $this->is_guest
                ? $this->buildGuestUserObject()
                : ($this->omitNestedUserSports
                    ? (new UserListResource($this->whenLoaded('user')))->withoutSports()
                    : new UserListResource($this->whenLoaded('user'))),
            'guest_name' => $this->when($this->is_guest, $this->guest_name),
            'guest_phone' => $this->when($this->is_guest, $this->guest_phone),
            'guest_avatar' => $this->when($this->is_guest, $this->guest_avatar),
            'guarantor' => new UserListResource($this->whenLoaded('guarantor')),
            'guarantor_user_id' => $this->when($this->is_guest, $this->guarantor_user_id),
            'guarantor_name' => $this->when($this->is_guest, fn() => $this->guarantor?->full_name),
            'estimated_level' => $this->when($this->is_guest, (float) $this->estimated_level),
            'is_pending_confirmation' => $this->when($this->is_guest, (bool) $this->is_pending_confirmation),
            'checked_in_at' => $this->checked_in_at,
            'is_absent' => (bool) $this->is_absent,
            'payment_status' => $this->payment_status,
            'payment_status_text' => $this->payment_status ? $this->payment_status->label() : null,
            'payment' => new TournamentParticipantPaymentResource($this->whenLoaded('payments')),
            'modified_score' => $this->modified_score,
            'effective_score' => $this->effective_score,
            'is_virtual'       => $vm !== null,
            'virtual_member_id' => $vm?->id,
        ];
    }
}
