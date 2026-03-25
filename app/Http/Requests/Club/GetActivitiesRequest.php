<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

class GetActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'category' => 'sometimes|in:all,activity,mini_tournament,tournament',
            'type' => 'sometimes|in:meeting,practice,match,tournament,event,other',
            'statuses' => 'sometimes|array',
            'statuses.*' => 'sometimes|in:all,registered,available,scheduled,ongoing,completed,cancelled',
            'date_from' => 'sometimes|date',
            'date_to' => 'sometimes|date|after_or_equal:date_from',
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date|after_or_equal:from_date',
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = $this->all();
        if (!empty($data['from_date']) && empty($data['date_from'])) {
            $data['date_from'] = $data['from_date'];
        }
        if (!empty($data['to_date']) && empty($data['date_to'])) {
            $data['date_to'] = $data['to_date'];
        }
        $this->merge($data);
    }
}
