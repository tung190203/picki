<?php

namespace App\Http\Requests\Club;

use App\Enums\ClubMemberRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'role' => ['sometimes', Rule::enum(ClubMemberRole::class)],
            'position' => ['nullable', 'string', Rule::enum(ClubMemberRole::class)],
            'message' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng truyền user_id (id người được mời tham gia CLB).',
            'user_id.exists' => 'Người dùng không tồn tại.',
        ];
    }
}
