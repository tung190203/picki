<?php

namespace App\DTO;

class SuggestionMatchDTO
{
    public function __construct(
        public readonly TeamMatchDTO $team1,
        public readonly TeamMatchDTO $team2,
        public readonly bool $is_high_tier_match,
    ) {}

    public static function fromArray(array $data): self
    {
        $team1 = new TeamMatchDTO(
            id: $data['team1']['id'] ?? null,
            name: $data['team1']['name'] ?? 'Team 1',
            members: array_map(fn($m) => $m instanceof TeamMatchMemberDTO ? $m : TeamMatchMemberDTO::fromArray($m), $data['team1']['members'] ?? []),
        );

        $team2 = new TeamMatchDTO(
            id: $data['team2']['id'] ?? null,
            name: $data['team2']['name'] ?? 'Team 2',
            members: array_map(fn($m) => $m instanceof TeamMatchMemberDTO ? $m : TeamMatchMemberDTO::fromArray($m), $data['team2']['members'] ?? []),
        );

        return new self(
            team1: $team1,
            team2: $team2,
            is_high_tier_match: $data['is_high_tier_match'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'team1' => $this->team1->toArray(),
            'team2' => $this->team2->toArray(),
            'is_high_tier_match' => $this->is_high_tier_match,
        ];
    }
}
