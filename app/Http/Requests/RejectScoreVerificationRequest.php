<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectScoreVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Vui lòng nhập lý do từ chối.',
            'reason.max' => 'Lý do không được vượt quá 500 ký tự.',
        ];
    }
}
