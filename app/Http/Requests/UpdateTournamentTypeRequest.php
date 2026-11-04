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
            'format_specific_config' => 'array|nullable',
            'rules' => 'string|nullable',
            'rules_file_path' => 'string|nullable',
            'num_legs' => 'integer|nullable|in:' . implode(',', \App\Models\TournamentType::NUM_LEGS_OPTIONS),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rawRules = $this->input('match_rules');
            $ruleItem = (is_array($rawRules) && isset($rawRules[0]) && is_array($rawRules[0]))
                ? $rawRules[0]
                : (is_array($rawRules) ? $rawRules : []);
            $stagePoints = $ruleItem['stage_points'] ?? $this->input('match_rules.stage_points');
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
