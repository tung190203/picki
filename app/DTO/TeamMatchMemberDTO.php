<?php

namespace App\DTO;

class TeamMatchMemberDTO
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $user_id,
        public readonly ?int $team_id,
        public readonly string $full_name,
        public readonly ?string $avatar_url,
        public readonly bool $is_guest,
        public readonly string $visibility,
        public readonly array $sports,
        public readonly string $tier,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            user_id: $data['user_id'] ?? null,
            team_id: $data['team_id'] ?? null,
            full_name: $data['full_name'],
            avatar_url: $data['avatar_url'] ?? null,
            is_guest: $data['is_guest'] ?? false,
            visibility: $data['visibility'] ?? 'open',
            sports: $data['sports'] ?? [],
            tier: $data['tier'] ?? 'B',
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'team_id' => $this->team_id,
            'full_name' => $this->full_name,
            'avatar_url' => $this->avatar_url,
            'is_guest' => $this->is_guest,
            'visibility' => $this->visibility,
            'sports' => $this->sports,
            'tier' => $this->tier,
        ];
    }
}
