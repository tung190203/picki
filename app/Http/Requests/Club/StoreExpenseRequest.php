<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:65535',
            'description' => 'required_without:title|string|max:65535',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,qr_code,other',
            'activity_id' => ['nullable', 'exists:club_activities,id'],
            'spent_at' => 'nullable|date',
            'note' => 'nullable|string',
            'reference_code' => 'nullable|string|max:255',
        ];
    }
}
