<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Chỉ convert qr_code_url thành qr_image nếu nó là file upload
        if ($this->hasFile('qr_code_url')) {
            $this->merge(['qr_image' => $this->file('qr_code_url')]);
            $this->request->remove('qr_code_url');
        }
        // Nếu qr_code_url là string URL, giữ nguyên để kế thừa từ activity

        if ($this->has('included_in_club_fund')) {
            $val = $this->input('included_in_club_fund');
            $this->merge(['included_in_club_fund' => filter_var($val, FILTER_VALIDATE_BOOLEAN)]);
        }

        if ($this->has('member_ids')) {
            $ids = $this->member_ids;
            if (is_string($ids)) {
                if (str_starts_with(trim($ids), '[')) {
                    $ids = @json_decode($ids, true) ?: [];
                } else {
                    $ids = array_filter(array_map('intval', explode(',', $ids)));
                }
                $this->merge(['member_ids' => array_values(array_map('intval', (array) $ids))]);
            } elseif (is_array($ids)) {
                $this->merge(['member_ids' => array_values(array_map('intval', $ids))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => 'required_without:description|nullable|string|max:255',
            'description' => 'required_without:title|nullable|string',
            'target_amount' => 'required_without:amount_per_member|nullable|numeric|min:0.01',
            'amount_per_member' => 'required_without:target_amount|nullable|numeric|min:0.01',
            'activity_id' => ['nullable', 'exists:club_activities,id'],
            'included_in_club_fund' => ['sometimes', 'boolean'],
            'member_ids' => 'required_without:activity_id|array|min:1',
            'member_ids.*' => 'integer|exists:users,id',
            'currency' => 'sometimes|string|max:3',
            'start_date' => 'required|date',
            'deadline' => 'nullable|date|after_or_equal:start_date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'qr_code_url' => 'nullable|string',
            'qr_image' => [
                // Chỉ yêu cầu qr_image nếu không bật thu vào quỹ chung VÀ không có qr_code_url (URL cũ)
                \Illuminate\Validation\Rule::requiredIf(fn () => 
                    $this->input('included_in_club_fund') === false && 
                    empty($this->input('qr_code_url'))
                ),
                'nullable', 'image', 'mimes:png,jpg,jpeg,gif', 'max:5120',
            ],
            'qr_code_id' => [
                'nullable',
                'integer',
                Rule::exists('club_fund_collections', 'id')
                    ->where('club_id', (int) $this->route('clubId'))
                    ->whereNotNull('qr_code_url'),
            ],
        ];
    }
}
