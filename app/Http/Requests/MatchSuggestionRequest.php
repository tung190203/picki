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
            // Required
            'mini_tournament_id' => ['required', 'integer'],
            'participants' => ['required', 'array', 'min:1'],
            'participants.*.mini_participant_id' => ['required', 'integer'],
            'participants.*.tier' => ['required', 'string', 'in:A,B'],
            
            // Settings
            'settings' => ['sometimes', 'array'],
            'settings.fair_play' => ['sometimes', 'boolean'],
            'settings.balance_team' => ['sometimes', 'boolean'],
            'settings.prefer_high_tier_match' => ['sometimes', 'boolean'],
            'settings.prevent_three_consecutive' => ['sometimes', 'boolean'],
            'settings.organizer_as_backup' => ['sometimes', 'boolean'],
            
            // Optional
            'seed' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'mini_tournament_id.required' => 'mini_tournament_id là bắt buộc.',
            'mini_tournament_id.integer' => 'mini_tournament_id phải là số nguyên.',
            'participants.required' => 'Danh sách participants là bắt buộc.',
            'participants.array' => 'Danh sách participants phải là mảng.',
            'participants.min' => 'Cần ít nhất 1 participant.',
            'participants.*.mini_participant_id.required' => 'mini_participant_id là bắt buộc.',
            'participants.*.tier.required' => 'tier là bắt buộc.',
            'participants.*.tier.in' => 'tier phải là A hoặc B.',
            'seed.integer' => 'Seed phải là số nguyên.',
            'seed.min' => 'Seed phải lớn hơn 0.',
            'seed.max' => 'Seed không được lớn hơn 999999.',
        ];
    }
}
