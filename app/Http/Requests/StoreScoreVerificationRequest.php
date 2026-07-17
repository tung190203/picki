<?php

namespace App\Http\Requests;

use App\Enums\ScoreType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScoreVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'score_type' => ['required', Rule::enum(ScoreType::class)],
            'score' => ['required', 'numeric', 'between:0,8'],
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'score_type.required' => 'Vui lòng chọn loại điểm.',
            'score_type.enum' => 'Loại điểm không hợp lệ.',
            'score.required' => 'Vui lòng nhập điểm.',
            'score.numeric' => 'Điểm phải là số.',
            'score.between' => 'Điểm phải từ 0 đến 8.',
            'image.required' => 'Vui lòng tải lên ảnh chứng minh.',
            'image.image' => 'Ảnh chứng minh phải là định dạng hình ảnh.',
            'image.mimes' => 'Ảnh chứng minh phải là định dạng jpeg, png, jpg hoặc gif.',
            'image.max' => 'Ảnh chứng minh không được vượt quá 5MB.',
        ];
    }
}
