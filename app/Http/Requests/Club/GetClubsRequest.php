<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

class GetClubsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'radius' => 'nullable|numeric|min:1',
            'minLat' => 'nullable|numeric',
            'maxLat' => 'nullable|numeric',
            'minLng' => 'nullable|numeric',
            'maxLng' => 'nullable|numeric',
            'per_page' => 'nullable|integer|min:1|max:200',
            'is_map' => 'nullable|boolean',
        ];
    }
}
