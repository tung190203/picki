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
            'age_group' => 'nullable|in:1,2,3,4',
            'gender_policy' => 'nullable|in:1,2,3',
            'participant' => 'nullable|in:team,user',
            'max_team' => 'nullable|integer|required_if:participant,team',
            'player_per_team' => 'nullable|integer|required_if:participant,team',
            'max_player' => 'nullable|integer|required_if:participant,user',
            'fee' => 'nullable|in:free,pair',
            'standard_fee_amount' => 'nullable|numeric|required_if:fee,pair',
            'is_private' => 'nullable|boolean',
            'auto_approve' => 'nullable|boolean',
            'description' => 'nullable|string',
            'club_id' => 'nullable|exists:clubs,id',
            'creator_join' => 'nullable|boolean',

            // Financial fields
            'has_financial_management' => 'nullable|boolean',
            'auto_split_fee' => 'nullable|boolean',
            'fee_description' => 'nullable|string|max:500',
            'qr_code_url' => 'nullable',
            'use_club_fund' => 'nullable|boolean',
            'included_in_club_fund' => 'nullable|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $useClubFund = $this->boolean('use_club_fund');
            $includedInClubFund = $this->boolean('included_in_club_fund');
            $hasFinancialMgmt = $this->boolean('has_financial_management');
            $fee = $this->input('fee');
            $clubId = $this->input('club_id');

            // use_club_fund và included_in_club_fund không thể chọn đồng thời
            if ($useClubFund && $includedInClubFund) {
                $validator->errors()->add(
                    'included_in_club_fund',
                    'Không thể chọn đồng thời "Dùng quỹ CLB" và "Thu vào quỹ CLB".'
                );
            }

            // included_in_club_fund yêu cầu club_id
            if ($includedInClubFund && !$clubId) {
                $validator->errors()->add(
                    'club_id',
                    'Thu tiền vào quỹ CLB yêu cầu chọn CLB.'
                );
            }

            // use_club_fund yêu cầu club_id
            if ($useClubFund && !$clubId) {
                $validator->errors()->add(
                    'club_id',
                    'Dùng quỹ CLB yêu cầu chọn CLB.'
                );
            }

            // QR code required khi có phí + quản lý tài chính + không dùng CLB fund
            if ($fee === 'pair' && $hasFinancialMgmt && !$useClubFund && !$this->hasFile('qr_code_url') && !$this->input('qr_code_url')) {
                $validator->errors()->add(
                    'qr_code_url',
                    'Mã QR thanh toán là bắt buộc khi có phí và sử dụng quản lý tài chính.'
                );
            }

            // use_club_fund tự động loại included_in_club_fund
            if ($useClubFund && $includedInClubFund) {
                // already validated above
            }
        });
    }

    public function prepareForValidation(): void
    {
        $boolKeys = [
            'enable_dupr', 'enable_vndupr', 'is_private', 'auto_approve',
            'has_financial_management', 'auto_split_fee', 'use_club_fund',
            'included_in_club_fund', 'creator_join',
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
