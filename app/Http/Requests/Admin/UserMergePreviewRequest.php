<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserMergePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_a_id' => ['required', 'integer', 'exists:users,id'],
            'user_b_id' => ['required', 'integer', 'exists:users,id', 'different:user_a_id'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_a_id.required' => 'Vui lòng chọn user A',
            'user_a_id.exists' => 'User A không tồn tại',
            'user_b_id.required' => 'Vui lòng chọn user B',
            'user_b_id.exists' => 'User B không tồn tại',
            'user_b_id.different' => 'User A và User B phải khác nhau',
        ];
    }
}
