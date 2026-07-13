<?php

namespace App\Http\Requests\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMemberStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', Rule::enum(ClubMemberRole::class)],
            'position' => ['nullable', 'string', Rule::enum(ClubMemberRole::class)],
            'status' => ['sometimes', Rule::enum(ClubMemberStatus::class)],
            'notes' => 'nullable|string',
            'rejection_reason' => 'nullable|string',
        ];
    }
}
