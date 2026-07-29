<?php

namespace App\DTO;

class TeamMatchDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly array $members,
    ) {}

    public static function fromArray(array $data): self
    {
        $members = array_map(function ($m) {
            return $m instanceof TeamMatchMemberDTO ? $m : TeamMatchMemberDTO::fromArray($m);
        }, $data['members'] ?? []);

        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? 'Team',
            members: $members,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'members' => array_map(fn($m) => $m instanceof TeamMatchMemberDTO ? $m->toArray() : $m, $this->members),
        ];
    }
}
