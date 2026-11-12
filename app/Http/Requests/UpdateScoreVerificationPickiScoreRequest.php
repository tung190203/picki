<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScoreVerificationPickiScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'picki_score' => ['required', 'numeric', 'min:1.0', 'max:10.0'],
            'award_anchor_badge' => ['sometimes', 'boolean'],
        ];
    }
}

