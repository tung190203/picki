<?php

namespace App\DTO;

class MatchSuggestionResponseDTO
{
    public function __construct(
        // Match được gợi ý
        public readonly ?SuggestionMatchDTO $match,
        
        // Danh sách chờ
        public readonly array $waiting_players,
        
        // Backup
        public readonly bool $backup_used,
        public readonly ?PlayerContextDTO $backup_player,
        
        // Statistics
        public readonly array $statistics,
        
        // Metadata
        public readonly int $seed,
        public readonly array $rules_applied,
        public readonly array $messages,
    ) {}

    public static function fromArray(array $data): self
    {
        $match = null;
        if ($data['match'] !== null) {
            $match = SuggestionMatchDTO::fromArray($data['match']);
        }

        $waitingPlayers = array_map(
            fn($p) => PlayerContextDTO::fromArray($p),
            $data['waiting_players'] ?? []
        );

        $backupPlayer = null;
        if ($data['backup_player'] !== null) {
            $backupPlayer = PlayerContextDTO::fromArray($data['backup_player']);
        }

        return new self(
            match: $match,
            waiting_players: $waitingPlayers,
            backup_used: $data['backup_used'] ?? false,
            backup_player: $backupPlayer,
            statistics: $data['statistics'] ?? [],
            seed: $data['seed'] ?? 0,
            rules_applied: $data['rules_applied'] ?? [],
            messages: $data['messages'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'match' => $this->match?->toArray(),
            'waiting_players' => array_map(fn($p) => $p->toArray(), $this->waiting_players),
            'backup_used' => $this->backup_used,
            'backup_player' => $this->backup_player?->toArray(),
            'statistics' => $this->statistics,
            'seed' => $this->seed,
            'rules_applied' => $this->rules_applied,
            'messages' => $this->messages,
        ];
    }
}
