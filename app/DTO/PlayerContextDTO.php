<?php

namespace App\DTO;

use App\Enums\MatchTier;

class PlayerContextDTO
{
    public function __construct(
        // Identity
        public readonly int $mini_participant_id,
        public readonly int $user_id,
        public readonly string $full_name,
        public readonly ?string $avatar_url,
        public readonly bool $is_guest,

        // Tier
        public readonly MatchTier $tier,
        public readonly bool $is_manual_override,

        // Calculated from MatchHistory
        public readonly int $played_count,
        public readonly int $consecutive_count,
        public readonly int $rest_count,

        // Partner history (from MiniTeamMember)
        public readonly array $partner_ids,

        // Status
        public readonly bool $is_checked_in,
        public readonly bool $is_playing,
        public readonly bool $skip_next_round,

        // Backup flag
        public readonly bool $is_backup,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            mini_participant_id: $data['mini_participant_id'],
            user_id: $data['user_id'],
            full_name: $data['full_name'],
            avatar_url: $data['avatar_url'] ?? null,
            is_guest: $data['is_guest'] ?? false,
            tier: $data['tier'] instanceof MatchTier ? $data['tier'] : MatchTier::from($data['tier']),
            is_manual_override: $data['is_manual_override'] ?? false,
            played_count: $data['played_count'] ?? 0,
            consecutive_count: $data['consecutive_count'] ?? 0,
            rest_count: $data['rest_count'] ?? 0,
            partner_ids: $data['partner_ids'] ?? [],
            is_checked_in: $data['is_checked_in'] ?? false,
            is_playing: $data['is_playing'] ?? false,
            skip_next_round: $data['skip_next_round'] ?? false,
            is_backup: $data['is_backup'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'mini_participant_id' => $this->mini_participant_id,
            'user_id' => $this->user_id,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'is_guest' => $this->is_guest,
            'tier' => $this->tier->value,
            'is_manual_override' => $this->is_manual_override,
            'played_count' => $this->played_count,
            'consecutive_count' => $this->consecutive_count,
            'rest_count' => $this->rest_count,
            'partner_ids' => $this->partner_ids,
            'is_checked_in' => $this->is_checked_in,
            'is_playing' => $this->is_playing,
            'skip_next_round' => $this->skip_next_round,
            'is_backup' => $this->is_backup,
        ];
    }
}
