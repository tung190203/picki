<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

class GetLeaderboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'per_page' => 'sometimes|integer|min:1|max:100',
        ];
    }
}
