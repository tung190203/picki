<?php

namespace App\DTO;

use App\Enums\PlayerTier;

/**
 * Player context for match suggestion algorithm.
 * Contains all information needed to make fair match decisions.
 */
class PlayerContextDTO
{
    public function __construct(
        // Identity
        public readonly int $mini_participant_id,
        public readonly ?int $user_id,
        public readonly string $full_name,
        public readonly ?string $avatar_url,

        // Tier (from Frontend)
        public readonly PlayerTier $tier,
        public readonly bool $is_manual_override,

        // Gender (from DB, not FE)
        public readonly ?int $gender,

        // Guest flag
        public readonly bool $is_guest,

        // Calculated from MatchHistory
        public readonly int $played_count,
        public readonly int $consecutive_count,
        public readonly int $waiting_rounds,

        // VN DUPR Score (from DB)
        public readonly ?float $vndupr_score,

        // Partner history (from MiniTeamMember)
        public readonly array $partner_ids,

        // Status
        public readonly bool $is_checked_in,
        public readonly bool $is_playing,
        public readonly bool $skip_next_round,

        // Absent status
        public readonly bool $is_absent,

        // Payment status
        public readonly ?string $payment_status,

        // Backup flag
        public readonly bool $is_backup,
    ) {}

    public static function fromArray(array $data): self
    {
        $tier = $data['tier'];
        if (is_string($tier)) {
            $tier = PlayerTier::from($tier);
        }

        return new self(
            mini_participant_id: $data['mini_participant_id'],
            user_id: $data['user_id'] ?? null,
            full_name: $data['full_name'],
            avatar_url: $data['avatar_url'] ?? null,
            tier: $tier,
            is_manual_override: $data['is_manual_override'] ?? false,
            gender: $data['gender'] ?? null,
            is_guest: $data['is_guest'] ?? false,
            played_count: $data['played_count'] ?? 0,
            consecutive_count: $data['consecutive_count'] ?? 0,
            waiting_rounds: $data['waiting_rounds'] ?? 0,
            vndupr_score: $data['vndupr_score'] ?? null,
            partner_ids: $data['partner_ids'] ?? [],
            is_checked_in: $data['is_checked_in'] ?? false,
            is_playing: $data['is_playing'] ?? false,
            skip_next_round: $data['skip_next_round'] ?? false,
            is_absent: $data['is_absent'] ?? false,
            payment_status: $data['payment_status'] ?? null,
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
            'tier' => $this->tier->value,
            'is_manual_override' => $this->is_manual_override,
            'gender' => $this->gender,
            'is_guest' => $this->is_guest,
            'played_count' => $this->played_count,
            'consecutive_count' => $this->consecutive_count,
            'waiting_rounds' => $this->waiting_rounds,
            'vndupr_score' => $this->vndupr_score,
            'partner_ids' => $this->partner_ids,
            'is_checked_in' => $this->is_checked_in,
            'is_playing' => $this->is_playing,
            'skip_next_round' => $this->skip_next_round,
            'is_absent' => $this->is_absent,
            'payment_status' => $this->payment_status,
            'is_backup' => $this->is_backup,
        ];
    }
}
