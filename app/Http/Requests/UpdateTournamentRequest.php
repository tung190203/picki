<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poster' => 'nullable|image|max:5120',
            'remove_poster' => 'nullable|boolean',
            'sport_id' => 'nullable|exists:sports,id',
            'name' => 'nullable|string',
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
            'age_group' => 'nullable|integer',
            'age_group_text' => 'nullable|string',
            'gender_policy' => 'nullable|integer',
            'gender_policy_text' => 'nullable|string',
            'participant' => 'nullable|in:team,user',
            'max_team' => 'nullable|integer|required_if:participant,team',
            'player_per_team' => 'nullable|integer|required_if:participant,team',
            'max_player' => 'nullable|integer|required_if:participant,user',
            'is_private' => 'nullable|boolean',
            'auto_approve' => 'nullable|boolean',
            'description' => 'nullable|string',
            'club_id' => 'nullable|exists:clubs,id',
            'is_public_branch' => 'nullable|boolean',
            'is_own_score' => 'nullable|boolean',
            'status' => 'nullable|in:1,2,3,4',
            'creator_join' => 'nullable|boolean',

            // Financial fields
            'has_financial_management' => 'nullable|boolean',
            'has_fee' => 'nullable|boolean',
            'fee_amount' => 'nullable|integer|min:0',
            'auto_split_fee' => 'nullable|boolean',
            'fee_description' => 'nullable|string|max:500',
            'qr_code_url' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $file = $value instanceof \Illuminate\Http\UploadedFile ? $value : $this->file('qr_code_url');
                    if ($file instanceof \Illuminate\Http\UploadedFile) {
                        if (!$file->isValid()) {
                            $fail('Mã QR phải là một file ảnh hợp lệ.');
                            return;
                        }
                        $allowedMimes = ['png', 'jpg', 'jpeg', 'gif'];
                        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedMimes, true)) {
                            $fail('Mã QR phải là định dạng png, jpg, jpeg hoặc gif.');
                            return;
                        }
                        if ($file->getSize() > 5 * 1024 * 1024) {
                            $fail('Mã QR không được vượt quá 5MB.');
                        }
                        return;
                    }
                    if (!is_string($value)) {
                        $fail('Mã QR phải là file ảnh hoặc URL hợp lệ.');
                    }
                },
            ],
            'zalo_link' => 'nullable|url',
            'main_phone' => 'sometimes|string|max:20|regex:/^[0-9\+\-\s\(\)]+$/',
            'sub_phone' => 'nullable|string|max:20|regex:/^[0-9\+\-\s\(\)]+$/',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            if (!$this->has('has_fee') && !$this->has('has_financial_management')) {
                return;
            }

            $hasFinancialMgmt = $this->boolean('has_financial_management');
            $hasFee = $this->boolean('has_fee');

            if ($hasFee && $hasFinancialMgmt) {
                $hasQrFile = $this->hasFile('qr_code_url');
                $qrInput = $this->input('qr_code_url');
                $hasQrString = is_string($qrInput) && trim($qrInput) !== '';

                if (!$hasQrFile && !$hasQrString && !$this->boolean('use_cached_qr')) {
                    $tournamentId = $this->route('id');
                    if ($tournamentId) {
                        $tournament = \App\Models\Tournament::find($tournamentId);
                        $existingQr = $tournament && $tournament->qr_code_url;
                        if (!$existingQr) {
                            $validator->errors()->add(
                                'qr_code_url',
                                'Mã QR thanh toán là bắt buộc khi bật thu phí và quản lý tài chính.'
                            );
                        }
                    }
                }
            }

            if ($hasFee && !$this->input('fee_amount')) {
                $validator->errors()->add(
                    'fee_amount',
                    'Số tiền phí là bắt buộc khi bật thu phí.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            // Poster
            'poster.image' => 'Ảnh bia giải đấu phải là định dạng hình ảnh (jpeg, png, jpg, gif, svg)',
            'poster.max' => 'Ảnh bia giải đấu không được vượt quá 5MB',

            // Thông tin cơ bản
            'sport_id.exists' => 'Môn thể thao không tồn tại hoặc đã bị xóa',
            'name.string' => 'Tên giải đấu phải là chuỗi ký tự',
            'name.max' => 'Tên giải đấu không được vượt quá 255 ký tự',

            // Địa điểm
            'competition_location_id.exists' => 'Địa điểm thi đấu không hợp lệ',

            // Thời gian
            'start_date.date' => 'Ngày bắt đầu không hợp lệ',
            'end_date.date' => 'Ngày kết thúc không hợp lệ',
            'registration_open_at.date' => 'Thời gian mở đăng ký không hợp lệ',
            'registration_closed_at.date' => 'Thời gian đóng đăng ký không hợp lệ',
            'early_registration_deadline.date' => 'Hạn đăng ký sớm không hợp lệ',

            // Thời lượng
            'duration.integer' => 'Thời lượng phải là số nguyên (phút)',

            // Trình độ
            'age_group.integer' => 'Nhóm tuổi phải là số nguyên',
            'age_group_text.string' => 'Ghi chú nhóm tuổi phải là chuỗi ký tự',
            'gender_policy.integer' => 'Chính sách giới tính phải là số nguyên',
            'gender_policy_text.string' => 'Ghi chú chính sách giới tính phải là chuỗi ký tự',

            // Hình thức tham gia
            'participant.in' => 'Hình thức tham gia không hợp lệ (team hoặc user)',
            'max_team.required_if' => 'Vui lòng nhập số đội tối đa khi chọn hình thức thi đấu theo đội',
            'max_team.integer' => 'Số đội tối đa phải là số nguyên',
            'player_per_team.required_if' => 'Vui lòng nhập số người mỗi đội khi chọn hình thức thi đấu theo đội',
            'player_per_team.integer' => 'Số người mỗi đội phải là số nguyên',
            'max_player.required_if' => 'Vui lòng nhập số người chơi tối đa khi chọn hình thức thi đấu cá nhân',
            'max_player.integer' => 'Số người chơi tối đa phải là số nguyên',

            // Mô tả
            'description.string' => 'Mô tả giải đấu phải là chuỗi ký tự',

            // CLB
            'club_id.exists' => 'Câu lạc bộ không tồn tại hoặc đã bị xóa',

            // Trạng thái
            'status.in' => 'Trạng thái giải đấu không hợp lệ',

            // Tài chính
            'fee_amount.integer' => 'Số tiền phí phải là số nguyên (VNĐ)',
            'fee_amount.min' => 'Số tiền phí không được nhỏ hơn 0',
            'fee_description.max' => 'Mô tả phí không được vượt quá 500 ký tự',

            // Số điện thoại
            'main_phone.string' => 'Số điện thoại chính phải là chuỗi ký tự',
            'main_phone.max' => 'Số điện thoại chính không được vượt quá 20 ký tự',
            'main_phone.regex' => 'Số điện thoại chính không hợp lệ',
            'sub_phone.string' => 'Số điện thoại phụ phải là chuỗi ký tự',
            'sub_phone.max' => 'Số điện thoại phụ không được vượt quá 20 ký tự',
            'sub_phone.regex' => 'Số điện thoại phụ không hợp lệ',
        ];
    }

    public function prepareForValidation(): void
    {
        // Normalize empty strings to null for date fields (mobile app sends "" instead of null)
        $dateKeys = [
            'start_date', 'end_date',
            'registration_open_at', 'registration_closed_at',
            'early_registration_deadline',
        ];
        foreach ($dateKeys as $key) {
            if ($this->has($key)) {
                $v = $this->input($key);
                if ($v === '' || $v === null) {
                    $this->merge([$key => null]);
                }
            }
        }

        // Normalize ALL boolean fields: convert string "true"/"false"/"1"/"0" → bool, null/"" → null
        $boolKeys = [
            'enable_dupr', 'enable_vndupr', 'is_private', 'auto_approve',
            'has_financial_management', 'has_fee', 'auto_split_fee', 'creator_join',
            'use_cached_qr', 'is_public_branch', 'is_own_score', 'remove_poster',
        ];

        $boolNormalized = [];
        foreach ($boolKeys as $key) {
            if (!$this->has($key)) {
                continue;
            }
            $v = $this->input($key);
            $boolNormalized[$key] = match (true) {
                $v === true, $v === 1, $v === '1', $v === 'true' => true,
                $v === false, $v === 0, $v === '0', $v === 'false' => false,
                $v === null, $v === '' => null,
                default => $v,
            };
        }

        $nullableKeys = ['main_phone', 'sub_phone'];
        $nullableNormalized = [];
        foreach ($nullableKeys as $key) {
            if (!$this->has($key)) {
                continue;
            }
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $nullableNormalized[$key] = null;
            }
        }

        $this->merge(array_merge($boolNormalized, $nullableNormalized));

        // has_fee = true → has_financial_management luôn mặc định là true
        if ($this->boolean('has_fee')) {
            $this->merge(['has_financial_management' => true]);
        }
    }
}
