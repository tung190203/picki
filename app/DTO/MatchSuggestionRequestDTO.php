<?php

namespace App\DTO;

use App\Enums\MatchTier;

class ParticipantTierDTO
{
    public function __construct(
        public readonly int $mini_participant_id,
        public readonly MatchTier $tier,
    ) {}

    public static function fromArray(array $data): self
    {
        $tier = $data['tier'];
        if (is_string($tier)) {
            $tier = MatchTier::from($tier);
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

class MatchSuggestionRequestDTO
{
    /**
     * @param ParticipantTierDTO[] $participants
     */
    public function __construct(
        public readonly int $mini_tournament_id,
        public readonly array $participants,
        public readonly MatchSuggestionSettingsDTO $settings,
        public readonly ?int $seed = null,
        public readonly ?array $exclude_player_ids = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $participants = [];
        foreach ($data['participants'] ?? [] as $p) {
            $participants[] = ParticipantTierDTO::fromArray($p);
        }

        return new self(
            mini_tournament_id: $data['mini_tournament_id'],
            participants: $participants,
            settings: MatchSuggestionSettingsDTO::fromArray($data['settings'] ?? []),
            seed: $data['seed'] ?? null,
            exclude_player_ids: $data['exclude_player_ids'] ?? null,
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
        ];
    }
}
