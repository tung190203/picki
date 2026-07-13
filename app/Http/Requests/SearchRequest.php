<?php

namespace App\Http\Requests;

use App\Enums\SubTabFilter;
use App\Models\Sport;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Tab & sub-tab
            'tab'       => 'nullable|string|in:mini-tournament,tournament,club,user,court',
            'sub_tab'  => 'nullable|string|in:' . implode(',', SubTabFilter::values()),

            // Geo
            'lat'          => 'nullable|numeric|between:-90,90',
            'lng'          => 'nullable|numeric|between:-180,180',
            'radius'       => 'nullable|numeric|min:1',
            'minLat'       => 'nullable|numeric|between:-90,90',
            'maxLat'       => 'nullable|numeric|between:-90,90',
            'minLng'       => 'nullable|numeric|between:-180,180',
            'maxLng'       => 'nullable|numeric|between:-180,180',

            // Output mode
            'map_mode'     => 'nullable|in:true,false,1,0',
            'page'         => 'nullable|integer|min:1',
            'per_page'     => 'nullable|integer|min:1|max:200',

            // Shared filters
            'keyword'      => 'nullable|string|max:255',
            'sport_id'     => 'nullable|integer|exists:sports,id',
            'location_id'  => 'nullable|integer|exists:locations,id',
            'club_id'      => 'nullable|integer|exists:clubs,id',

            // Filters bundle (V2 structured format)
            'filters'      => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'sub_tab.in'      => 'Bộ lọc tab không hợp lệ.',
            'tab.in'              => 'Tab tìm kiếm không hợp lệ.',
            'radius.min'           => 'Bán kính tìm kiếm phải lớn hơn 0.',
            'per_page.max'         => 'Số lượng kết quả tối đa là 200.',
        ];
    }

    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        return array_merge([
            'tab'      => 'mini-tournament',
            'sub_tab'  => SubTabFilter::ALL->value,
            'page'     => 1,
            'map_mode' => false,
        ], $validated, [
            'sport_id' => $this->input('sport_id', Sport::PICKLEBALL_ID),
        ]);
    }
}
