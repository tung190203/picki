<?php

namespace App\Http\Resources;

use App\DTO\MatchSuggestionResponseDTO;
use App\DTO\SuggestionMatchDTO;
use App\DTO\PlayerContextDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchSuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource instanceof MatchSuggestionResponseDTO) {
            return $this->transformDto($this->resource);
        }

        return $this->transformArray($this->resource);
    }

    private function transformDto(MatchSuggestionResponseDTO $dto): array
    {
        return [
            'match' => $dto->match?->toArray(),
            'waiting_players' => array_map(fn($p) => $this->formatWaitingPlayer($p), $dto->waiting_players),
            'backup_used' => $dto->backup_used,
            'backup_player' => $dto->backup_player ? $this->formatWaitingPlayer($dto->backup_player) : null,
            'statistics' => $dto->statistics,
            'seed' => $dto->seed,
            'rules_applied' => $dto->rules_applied,
            'messages' => $dto->messages,
            'total_candidates' => $dto->total_candidates,
            'selected_offset' => $dto->selected_offset,
            'wrapped' => $dto->wrapped,
            'error_message' => $dto->error_message,
        ];
    }

    private function transformArray(array $data): array
    {
        return [
            'match' => $data['match'] ?? null,
            'waiting_players' => array_map(fn($p) => $this->formatWaitingPlayer($p), $data['waiting_players'] ?? []),
            'backup_used' => $data['backup_used'] ?? false,
            'backup_player' => isset($data['backup_player']) ? $this->formatWaitingPlayer($data['backup_player']) : null,
            'statistics' => $data['statistics'] ?? [],
            'seed' => $data['seed'] ?? 0,
            'rules_applied' => $data['rules_applied'] ?? [],
            'messages' => $data['messages'] ?? [],
        ];
    }

    private function formatWaitingPlayer(PlayerContextDTO|array $player): array
    {
        if ($player instanceof PlayerContextDTO) {
            return [
                'id' => $player->mini_participant_id,
                'user_id' => $player->user_id,
                'full_name' => $player->full_name,
                'avatar_url' => $player->avatar_url,
                'tier' => $player->tier->value,
            ];
        }

        return [
            'id' => $player['mini_participant_id'] ?? $player['id'] ?? null,
            'user_id' => $player['user_id'] ?? null,
            'full_name' => $player['full_name'] ?? null,
            'avatar_url' => $player['avatar_url'] ?? null,
            'tier' => $player['tier'] ?? null,
        ];
    }
}
