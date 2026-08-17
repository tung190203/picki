<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tournament_id' => $this->tournament_id,
            'format' => $this->format,
            'format_label' => $this->format_label,
            'has_resurrection_bracket' => (bool) ($this->has_resurrection_bracket ?? ($this->format_specific_config[0]['has_resurrection_bracket'] ?? false)),
            'main_bracket_name' => $this->main_bracket_name ?? ($this->format_specific_config[0]['main_bracket_name'] ?? 'Giải chính'),
            'sub_bracket_name' => $this->sub_bracket_name ?? ($this->format_specific_config[0]['sub_bracket_name'] ?? 'Giải Tái sinh'),
            'num_legs' => $this->num_legs,
            'num_legs_label' => $this->num_legs_label,
            'match_rules' => $this->match_rules,
            'rules' => $this->rules,
            'rules_file_path' => $this->rules_file_path ? asset('storage/' . $this->rules_file_path) : null,
            'format_specific_config' => $this->normalizeFormatSpecificConfig($this->format_specific_config),
            'total_matches' => $this->total_matches,
            'total_teams' => $this->total_teams,
            'total_matches_per_team' => $this->total_matches_per_team,
            'groups' => $this->whenLoaded('groups', function () {
                return $this->groups->map(fn($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                ]);
            }),
        ];
    }

    private function normalizeFormatSpecificConfig($config)
    {
        if (!is_array($config)) {
            return [];
        }
        if (isset($config[0])) {
            return $config;
        }
        return [$config];
    }
}