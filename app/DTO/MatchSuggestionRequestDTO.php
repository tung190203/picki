<?php

namespace App\DTO;

use App\Enums\PlayerTier;

class ParticipantTierDTO
{
    public function __construct(
        public readonly int $mini_participant_id,
        public readonly PlayerTier $tier,
    ) {}

    public static function fromArray(array $data): self
    {
        $tier = $data['tier'] ?? null;
        if (is_string($tier)) {
            $tier = PlayerTier::from($tier);
        } elseif ($tier === null) {
            // Default to Green when tier is not provided
            $tier = PlayerTier::Green;
        }

        return new self(
            mini_participant_id: $data['mini_participant_id'],
            tier: $tier,
        );
    }

    public function toArray(): array
    {
        return [
            'mini_participant_id' => $this->mini_participant_id,
            'tier' => $this->tier->value,
        ];
    }
}

class MatchSuggestionSettingsDTO
{
    public function __construct(
        public readonly bool $fair_play = true,
        public readonly bool $balance_team = true,
        public readonly bool $prefer_high_tier_match = true,
        public readonly bool $prevent_three_consecutive = true,
        public readonly bool $organizer_as_backup = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fair_play: $data['fair_play'] ?? true,
            balance_team: $data['balance_team'] ?? true,
            prefer_high_tier_match: $data['prefer_high_tier_match'] ?? true,
            prevent_three_consecutive: $data['prevent_three_consecutive'] ?? true,
            organizer_as_backup: $data['organizer_as_backup'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'fair_play' => $this->fair_play,
            'balance_team' => $this->balance_team,
            'prefer_high_tier_match' => $this->prefer_high_tier_match,
            'prevent_three_consecutive' => $this->prevent_three_consecutive,
            'organizer_as_backup' => $this->organizer_as_backup,
        ];
    }
}

/**
 * DTO for a fixed player pair (for pairing constraint).
 * Always uses user_id - backend resolves guest flag via mini_participants table when needed.
 */
class FixedPairDTO
{
    public function __construct(
        public readonly int $player1_id,
        public readonly int $player2_id,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            player1_id: $data['player1_id'],
            player2_id: $data['player2_id'],
        );
    }

    public function toArray(): array
    {
        return [
            'player1_id' => $this->player1_id,
            'player2_id' => $this->player2_id,
        ];
    }

    /**
     * Check if a user is part of this pair.
     * Always uses user_id.
     */
    public function hasPlayer(int $userId): bool
    {
        return (int) $this->player1_id === $userId || (int) $this->player2_id === $userId;
    }

    /**
     * Get the partner of a user in this pair.
     * Returns partner's user_id.
     */
    public function getPartnerId(int $userId): ?int
    {
        if ((int) $this->player1_id === $userId) {
            return (int) $this->player2_id;
        }
        if ((int) $this->player2_id === $userId) {
            return (int) $this->player1_id;
        }
        return null;
    }
}

class MatchSuggestionRequestDTO
{
    /**
     * @param ParticipantTierDTO[] $participants
     * @param FixedPairDTO[] $fixed_pairs
     */
    public function __construct(
        public readonly int $mini_tournament_id,
        public readonly array $participants,
        public readonly MatchSuggestionSettingsDTO $settings,
        public readonly ?int $seed = null,
        public readonly ?array $exclude_player_ids = null,
        /** @deprecated Use anchor_user_id instead */
        public readonly ?int $anchor_participant_id = null,
        /** ID of the player who must be in the selected match (user_id or mini_participant_id for guests) */
        public readonly ?int $anchor_user_id = null,
        /** Fixed player pairs - these players must be on the same team */
        public readonly array $fixed_pairs = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $participants = [];
        foreach ($data['participants'] ?? [] as $p) {
            $participants[] = ParticipantTierDTO::fromArray($p);
        }

        $fixedPairs = [];
        foreach ($data['fixed_pairs'] ?? [] as $pair) {
            $fixedPairs[] = FixedPairDTO::fromArray($pair);
        }

        if (!isset($data['mini_tournament_id'])) {
            throw new \InvalidArgumentException('mini_tournament_id là bắt buộc.');
        }

        return new self(
            mini_tournament_id: $data['mini_tournament_id'],
            participants: $participants,
            settings: MatchSuggestionSettingsDTO::fromArray($data['settings'] ?? []),
            seed: $data['seed'] ?? null,
            exclude_player_ids: $data['exclude_player_ids'] ?? null,
            anchor_participant_id: $data['anchor_participant_id'] ?? null,
            anchor_user_id: $data['anchor_user_id'] ?? null,
            fixed_pairs: $fixedPairs,
        );
    }

    public function toArray(): array
    {
        return [
            'mini_tournament_id' => $this->mini_tournament_id,
            'participants' => array_map(fn($p) => $p->toArray(), $this->participants),
            'settings' => $this->settings->toArray(),
            'seed' => $this->seed,
            'exclude_player_ids' => $this->exclude_player_ids,
            'anchor_participant_id' => $this->anchor_participant_id,
            'anchor_user_id' => $this->anchor_user_id,
            'fixed_pairs' => array_map(fn($p) => $p->toArray(), $this->fixed_pairs),
        ];
    }
}
