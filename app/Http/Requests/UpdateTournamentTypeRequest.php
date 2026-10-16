<?php

namespace App\Http\Requests;

use App\Services\TournamentScoringTypeInferrer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class UpdateTournamentTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'match_rules' => 'sometimes|array',
            'match_rules.scoring_type' => 'sometimes|nullable|string|in:side_out,rally',
            'match_rules.sets_per_match' => 'sometimes|nullable|integer|min:1',
            'match_rules.points_to_win_set' => 'sometimes|nullable|integer|min:1',
            'match_rules.winning_rule' => 'sometimes|nullable|integer|min:1',
            'match_rules.max_points' => 'sometimes|nullable|integer|min:1',
            'match_rules.serve_change_interval' => 'sometimes|nullable|integer|min:0',

            'match_rules.stage_points' => 'sometimes|nullable|array',
            'match_rules.stage_points.*' => 'integer|min:1',

            'format_specific_config' => 'array|nullable',
            'rules' => 'string|nullable',
            'rules_file_path' => 'string|nullable',
            'num_legs' => 'integer|nullable|in:' . implode(',', \App\Models\TournamentType::NUM_LEGS_OPTIONS),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $stagePoints = $this->input('match_rules.stage_points');
            if (!is_array($stagePoints) || empty($stagePoints)) {
                return;
            }

            try {
                app(TournamentScoringTypeInferrer::class)->validateStagePoints($stagePoints);
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    foreach ($messages as $msg) {
                        $validator->errors()->add($key, $msg);
                    }
                }
            }
        });
    }
}
