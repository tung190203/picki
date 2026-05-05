<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poster' => 'nullable|image|max:350',
            'sport_id' => 'required|exists:sports,id',
            'name' => 'required|string',
            'competition_location_id' => 'nullable|exists:competition_locations,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'registration_open_at' => 'nullable|date',
            'registration_closed_at' => 'nullable|date',
            'early_registration_deadline' => 'nullable|date',
            'duration' => 'nullable|integer',
            'enable_dupr' => 'nullable|boolean',
            'enable_vndupr' => 'nullable|boolean',
            'min_level' => 'nullable',
            'max_level' => 'nullable',
            'age_group' => 'nullable|string',
            'age_group_text' => 'nullable|string',
            'gender_policy' => 'nullable|string',
            'gender_policy_text' => 'nullable|string',
            'participant' => 'nullable|in:team,user',
            'max_team' => 'nullable|integer|required_if:participant,team',
            'player_per_team' => 'nullable|integer|required_if:participant,team',
            'max_player' => 'nullable|integer|required_if:participant,user',
            'is_private' => 'nullable|boolean',
            'auto_approve' => 'nullable|boolean',
            'description' => 'nullable|string',
            'club_id' => 'nullable|exists:clubs,id',
            'creator_join' => 'nullable|boolean',

            // Financial fields
            'has_financial_management' => 'nullable|boolean',
            'has_fee' => 'nullable|boolean',
            'fee_amount' => 'nullable|integer|min:0',
            'auto_split_fee' => 'nullable|boolean',
            'fee_description' => 'nullable|string|max:500',
            'qr_code_url' => 'nullable',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $hasFinancialMgmt = $this->boolean('has_financial_management');
            $hasFee = $this->boolean('has_fee');

            // QR code required khi có phí + quản lý tài chính
            if ($hasFee && $hasFinancialMgmt && !$this->hasFile('qr_code_url') && !$this->input('qr_code_url')) {
                $validator->errors()->add(
                    'qr_code_url',
                    'Mã QR thanh toán là bắt buộc khi bật thu phí và quản lý tài chính.'
                );
            }

            // fee_amount required khi has_fee = true
            if ($hasFee && !$this->input('fee_amount')) {
                $validator->errors()->add(
                    'fee_amount',
                    'Số tiền phí là bắt buộc khi bật thu phí.'
                );
            }
        });
    }

    public function prepareForValidation(): void
    {
        $boolKeys = [
            'enable_dupr', 'enable_vndupr', 'is_private', 'auto_approve',
            'has_financial_management', 'has_fee', 'auto_split_fee', 'creator_join',
        ];

        $prepared = [];
        foreach ($boolKeys as $key) {
            if ($this->has($key)) {
                $prepared[$key] = filter_var($this->input($key), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            }
        }

        $this->merge($prepared);
    }
}
