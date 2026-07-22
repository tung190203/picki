<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ModifyParticipantScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score' => ['nullable', 'numeric', 'min:0', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'score.numeric' => 'Score phải là số.',
            'score.min' => 'Score không được nhỏ hơn 0.',
            'score.max' => 'Score không được lớn hơn 10.',
        ];
    }
}
