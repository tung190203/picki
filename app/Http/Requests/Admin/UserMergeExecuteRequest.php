<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UserMergeExecuteRequest extends FormRequest
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
            'survivor_user_id' => ['required', 'integer', 'exists:users,id'],
            'duplicate_override' => ['required', 'boolean'],
            'confirmation_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $survivorId = $this->input('survivor_user_id');
            $userAId = $this->input('user_a_id');
            $userBId = $this->input('user_b_id');

            if ($survivorId && ($userAId || $userBId)) {
                $validIds = array_filter([$userAId, $userBId]);
                if (!in_array((int) $survivorId, array_map('intval', $validIds))) {
                    $validator->errors()->add(
                        'survivor_user_id',
                        'Survivor phải là một trong hai user được chọn'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_a_id.required' => 'Vui lòng chọn user A',
            'user_a_id.exists' => 'User A không tồn tại',
            'user_b_id.required' => 'Vui lòng chọn user B',
            'user_b_id.exists' => 'User B không tồn tại',
            'user_b_id.different' => 'User A và User B phải khác nhau',
            'survivor_user_id.required' => 'Vui lòng chọn user được giữ lại',
            'survivor_user_id.exists' => 'Survivor không tồn tại',
            'duplicate_override.required' => 'Vui lòng xác nhận override duplicate',
            'duplicate_override.boolean' => 'Duplicate override phải là true hoặc false',
            'confirmation_name.required' => 'Vui lòng nhập tên xác nhận',
            'confirmation_name.max' => 'Tên xác nhận không được quá 255 ký tự',
        ];
    }
}
