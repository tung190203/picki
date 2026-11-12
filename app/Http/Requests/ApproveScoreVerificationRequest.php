<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveScoreVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'award_anchor_badge' => ['sometimes', 'boolean'],
            'picki_score' => ['sometimes', 'nullable', 'numeric', 'min:1.0', 'max:10.0'],
        ];
    }
}
