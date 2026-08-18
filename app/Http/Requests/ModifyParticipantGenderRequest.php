<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ModifyParticipantGenderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gender' => ['required', 'integer', 'in:' . User::MALE . ',' . User::FEMALE],
        ];
    }

    public function messages(): array
    {
        return [
            'gender.required' => 'Giới tính là bắt buộc.',
            'gender.integer' => 'Giới tính phải là số nguyên.',
            'gender.in' => 'Giới tính không hợp lệ. Chỉ chấp nhận 1 (Nam) hoặc 2 (Nữ).',
        ];
    }
}
