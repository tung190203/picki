<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatchSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // mini_tournament_id is in URL, not body
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.mini_participant_id' => ['required', 'integer'],
            'participants.*.tier' => ['sometimes', 'nullable', 'string', 'in:purple,red,yellow,green'],

            // Settings
            'settings' => ['sometimes', 'array'],
            'settings.fair_play' => ['sometimes', 'boolean'],
            'settings.balance_team' => ['sometimes', 'boolean'],
            'settings.prefer_high_tier_match' => ['sometimes', 'boolean'],
            'settings.prevent_three_consecutive' => ['sometimes', 'boolean'],
            'settings.organizer_as_backup' => ['sometimes', 'boolean'],

            // Fixed pairs for pairing constraint
            'fixed_pairs' => ['sometimes', 'array'],
            'fixed_pairs.*.player1_id' => ['required', 'integer'],
            'fixed_pairs.*.player2_id' => ['required', 'integer'],
            'fixed_pairs.*.player1_is_guest' => ['sometimes', 'boolean'],
            'fixed_pairs.*.player2_is_guest' => ['sometimes', 'boolean'],

            // Optional
            'seed' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999'],
            'exclude_player_ids' => ['sometimes', 'nullable', 'array'],
            'exclude_player_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'participants.required' => 'Danh sách participants là bắt buộc.',
            'participants.array' => 'Danh sách participants phải là mảng.',
            'participants.min' => 'Cần ít nhất 1 participant.',
            'participants.*.mini_participant_id.required' => 'mini_participant_id là bắt buộc.',
            'participants.*.tier.in' => 'tier phải là purple, red, yellow hoặc green.',
            'fixed_pairs.*.player1_id.required' => 'player1_id là bắt buộc.',
            'fixed_pairs.*.player2_id.required' => 'player2_id là bắt buộc.',
            'seed.integer' => 'Seed phải là số nguyên.',
            'seed.min' => 'Seed phải lớn hơn 0.',
            'seed.max' => 'Seed không được lớn hơn 999999.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // mini_tournament_id from URL is available in route
        // Add it to validated data for DTO
        if ($this->route('miniTournamentId')) {
            $this->merge([
                'mini_tournament_id' => (int) $this->route('miniTournamentId'),
            ]);
        }
    }
}
