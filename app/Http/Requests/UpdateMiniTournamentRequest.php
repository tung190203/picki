<?php

namespace App\Http\Requests;

use App\Models\MiniTournament;
use App\Rules\ValidRecurringSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UpdateMiniTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            // File upload HOẶC URL string (app gửi lại poster hiện có khi update multipart)
            'poster' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $file = $value instanceof UploadedFile ? $value : $this->file('poster');
                    if ($file instanceof UploadedFile) {
                        if (!$file->isValid()) {
                            $fail('Trường poster phải là một hình ảnh hợp lệ.');
                            return;
                        }
                        $allowedMimes = ['jpeg', 'png', 'jpg', 'gif', 'svg'];
                        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedMimes, true)) {
                            $fail('Poster phải là định dạng jpeg, png, jpg, gif hoặc svg.');
                            return;
                        }
                        if ($file->getSize() > 2048 * 1024) {
                            $fail('Poster không được vượt quá 2MB.');
                        }
                        return;
                    }
                    if (!is_string($value)) {
                        $fail('Trường poster phải là một hình ảnh hoặc URL hợp lệ.');
                        return;
                    }
                    if (strlen($value) > 2048) {
                        $fail('URL poster không được vượt quá 2048 ký tự.');
                        return;
                    }
                    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                        $fail('Poster phải là URL hợp lệ hoặc file ảnh.');
                    }
                },
            ],
            'sport_id' => 'sometimes|exists:sports,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',

            // Play mode and format
            'play_mode' => 'sometimes|in:casual,competition,practice,' . implode(',', [MiniTournament::PLAY_MODE_CASUAL, MiniTournament::PLAY_MODE_COMPETITION, MiniTournament::PLAY_MODE_PRACTICE]),
            'format' => 'nullable|in:single,double,mens_doubles,womens_doubles,mixed,' . implode(',', MiniTournament::FORMAT),

            // Time fields
            'start_time' => 'nullable|date|after_or_equal:now',
            'end_time' => 'nullable|date|after:start_time',
            'duration' => 'nullable|integer|min:1',
            'competition_location_id' => 'nullable|exists:competition_locations,id',

            'is_private' => 'boolean',

            // Fee fields
            'has_fee' => 'boolean',
            'auto_split_fee' => 'boolean',
            'fee_description' => 'nullable|string|max:500',
            'qr_code_url' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Cho phép null/empty: nghĩa là không thay đổi QR (giữ nguyên ảnh cũ)
                    if ($value === null || $value === '') {
                        return;
                    }
                    // File upload mới: kiểm tra định dạng và kích thước
                    $file = $value instanceof UploadedFile ? $value : $this->file('qr_code_url');
                    if ($file instanceof UploadedFile) {
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
                    // String URL: cho phép (giữ nguyên ảnh cũ khi update)
                    if (!is_string($value)) {
                        $fail('Mã QR phải là file ảnh hoặc URL hợp lệ.');
                    }
                },
            ],
            'payment_account_id' => 'nullable|exists:club_wallets,id',
            'fee_amount' => 'nullable|integer|min:0',
            'max_players' => 'nullable|integer|min:2|max:100',

            // Club fund integration
            'use_club_fund' => 'boolean',
            'included_in_club_fund' => 'boolean',

            // Rating
            'min_rating' => 'nullable|numeric|min:0',
            'max_rating' => 'nullable|numeric|min:0',

            // Game rules
            'set_number' => 'sometimes|nullable|integer|min:1',
            'base_points' => 'sometimes|nullable|integer|min:11',
            'points_difference' => 'sometimes|nullable|integer|min:1',
            'max_points' => 'sometimes|nullable|integer|min:11',

            // Gender
            'gender' => 'sometimes|integer|in:' . implode(',', MiniTournament::GENDER),

            // Additional fields
            'apply_rule' => 'boolean',
            'allow_cancellation' => 'boolean',
            'cancellation_duration' => 'nullable|integer|min:0',
            'auto_approve' => 'boolean',
            'allow_participant_add_friends' => 'boolean',

            // Recurring schedule (same format as clubs)
            'recurring_schedule' => ['nullable', 'array', new ValidRecurringSchedule()],
            'edit_scope' => 'sometimes|string|in:this_occurrence,entire_series',

            'status' => 'sometimes|integer|in:' . implode(',', MiniTournament::STATUS),

            'invite_user' => 'nullable|array',
            'invite_user.*' => 'distinct|exists:users,id',
        ];

        // Custom validation: if has_fee is true, require fee_amount
        if ($this->has('has_fee') && $this->has_fee) {
            $rules['fee_amount'] = 'required|integer|min:1';
            // qr_code_url bắt buộc trừ khi:
            // - use_club_fund = true (admin quản lý quỹ trực tiếp)
            // - hoặc CLB đã có ví với qr_code_url chung
            // Note: qr_code_url check done in withValidator to access DB state
        }

        // Custom validation: if allow_cancellation is true, require cancellation_duration
        if ($this->has('allow_cancellation') && $this->allow_cancellation) {
            $rules['cancellation_duration'] = 'required|integer|min:1';
        }

        // Custom validation: if apply_rule is true, require game rule fields
        if ($this->has('apply_rule') && $this->apply_rule) {
            $rules['set_number'] = 'required|integer|min:1';
            $rules['base_points'] = 'required|integer|min:11';
            $rules['points_difference'] = 'required|integer|min:1';
            $rules['max_points'] = 'required|integer|min:11';
        }

        return $rules;
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $minRating = $this->input('min_rating');
            $maxRating = $this->input('max_rating');

            if ($minRating !== null && $maxRating !== null && (float) $minRating > (float) $maxRating) {
                $validator->errors()->add('min_rating', 'Trình độ tối thiểu không được lớn hơn trình độ tối đa.');
            }

            // qr_code_url validation for paid tournaments:
            // - pass if: file uploaded OR string URL sent OR club already has QR wallet
            // - pass if: tournament already has qr_code_url in DB AND nothing new sent (keep old)
            // - fail if: no QR at all and no payment account and no club wallet
            // Only validate QR when user explicitly chose has_fee=true AND use_club_fund=false.
            // If use_club_fund was not sent, skip — the club may have a shared QR wallet.
            // If use_club_fund=true, QR is not needed (club fund handles it).
            if ($this->boolean('has_fee') && $this->has('use_club_fund') && !$this->boolean('use_club_fund') && !$this->getClubHasQrWallet()) {
                $qrValue = $this->input('qr_code_url');
                $qrFile = $this->file('qr_code_url');
                $hasQrInput = $qrFile !== null || ($qrValue !== null && $qrValue !== '');
                if (!$hasQrInput && !$this->filled('payment_account_id')) {
                    $miniTournamentId = $this->route('miniTournamentId') ?? $this->route('mini_tournament');
                    $miniTournament = $miniTournamentId ? \App\Models\MiniTournament::find($miniTournamentId) : null;
                    $existingQr = $miniTournament?->qr_code_url;
                    if (!$existingQr) {
                        $validator->errors()->add('qr_code_url', 'Kèo thu phí cần tải ảnh QR thanh toán. Nếu dùng quỹ CLB, vui lòng chọn CLB có ví với mã QR chung.');
                    }
                }
            }

            // use_club_fund = true và included_in_club_fund = true loại trừ nhau
            // use_club_fund = true: CLB chi tiền → không thu từ member → KHÔNG tạo collection
            if ($this->boolean('use_club_fund') && $this->boolean('included_in_club_fund')) {
                $validator->errors()->add('included_in_club_fund', 'Không thể chọn đồng thời "Quỹ chi" và "Thu vào quỹ chung CLB". Vui lòng chỉ chọn một trong hai.');
            }

            if ($this->boolean('apply_rule')) {
                $basePoints = $this->input('base_points');
                $maxPoints = $this->input('max_points');
                if ($basePoints !== null && $maxPoints !== null && (int) $maxPoints < (int) $basePoints) {
                    $validator->errors()->add('max_points', 'Điểm tối đa phải lớn hơn hoặc bằng điểm cơ bản.');
                }
            }
        });
    }

    public function getClubHasQrWallet(): bool
    {
        $miniTournamentId = $this->route('miniTournamentId') ?? $this->route('mini_tournament');
        $miniTournament = $miniTournamentId
            ? \App\Models\MiniTournament::find($miniTournamentId)
            : null;
        $clubId = $this->input('club_id') ?? $miniTournament?->club_id;
        if (!$clubId) {
            return false;
        }

        return \App\Models\Club\Club::find($clubId)?->activeQrWallet() !== null;
    }

    /**
     * Calculate duration from start_time and end_time, OR calculate end_time from start_time and duration
     */
    protected function prepareForValidation(): void
    {
        // Normalize empty strings to null for nullable fields
        $nullableKeys = [
            'min_rating', 'max_rating', 'fee_description', 'description',
            'payment_account_id', 'competition_location_id', 'end_time',
            'set_number', 'base_points', 'points_difference', 'max_points',
            'cancellation_duration',
        ];
        $normalized = [];
        foreach ($nullableKeys as $key) {
            if (!$this->has($key)) {
                continue;
            }
            $v = $this->input($key);
            if ($v === '' || $v === null) {
                $normalized[$key] = null;
            }
        }
        if ($normalized !== []) {
            $this->merge($normalized);
        }

        // Normalize string boolean values to actual booleans
        $boolKeys = [
            'is_private', 'has_fee', 'auto_split_fee',
            'apply_rule', 'allow_cancellation', 'auto_approve',
            'allow_participant_add_friends',
            'use_club_fund', 'included_in_club_fund',
        ];
        $boolNormalized = [];
        foreach ($boolKeys as $key) {
            if ($this->has($key)) {
                $val = $this->input($key);
                if (is_string($val)) {
                    $normalizedBool = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    if ($normalizedBool !== null) {
                        $boolNormalized[$key] = $normalizedBool;
                    }
                }
            }
        }
        if ($boolNormalized !== []) {
            $this->merge($boolNormalized);
        }

        // Convert play_mode string to integer
        $playModeMap = [
            'casual' => MiniTournament::PLAY_MODE_CASUAL,
            'competition' => MiniTournament::PLAY_MODE_COMPETITION,
            'practice' => MiniTournament::PLAY_MODE_PRACTICE,
        ];

        $playMode = $this->input('play_mode');
        if ($playMode && isset($playModeMap[$playMode])) {
            $this->merge(['play_mode' => $playModeMap[$playMode]]);
        }

        // Convert format string to integer
        $formatMap = [
            'single' => MiniTournament::FORMAT_SINGLE,
            'double' => MiniTournament::FORMAT_DOUBLE,
            'mens_doubles' => MiniTournament::FORMAT_MENS_DOUBLES,
            'womens_doubles' => MiniTournament::FORMAT_WOMENS_DOUBLES,
            'mixed' => MiniTournament::FORMAT_MIXED,
        ];

        $format = $this->input('format');
        if ($format && isset($formatMap[$format])) {
            $this->merge(['format' => $formatMap[$format]]);
        }

        // Normalize fee fields to satisfy DB constraints.
        // DB hiện tại không cho fee_amount = null, nên khi tắt thu phí phải ép về 0.
        $hasFee = $this->input('has_fee');
        if ($hasFee === false || $hasFee === '0' || $hasFee === 0) {
            $this->merge([
                'fee_amount' => 0,
                'auto_split_fee' => false,
                'fee_description' => null,
                'payment_account_id' => null,
            ]);
        }

        // Handle conditional game rule fields based on apply_rule
        $applyRule = $this->input('apply_rule');
        if ($applyRule === false || $applyRule === '0' || $applyRule === 0) {
            // Set game rule fields to NULL when apply_rule is false
            $this->merge([
                'set_number' => null,
                'base_points' => null,
                'points_difference' => null,
                'max_points' => null,
            ]);
        }

        // use_club_fund = true: kèo miễn phí cho member, CLB chi tiền. Không thu phí từ member.
        // has_fee và fee_amount vẫn giữ nguyên (số tiền CLB chi cho kèo đấu).
        // use_club_fund = true thì included_in_club_fund phải = false (loại trừ nhau)
        if ($this->boolean('use_club_fund')) {
            $this->merge(['included_in_club_fund' => false]);
        }

        $startTime = $this->input('start_time');
        $endTime = $this->input('end_time');
        $duration = $this->input('duration');

        if ($startTime) {
            $start = \Carbon\Carbon::parse($startTime);

            // Case 1: Have start_time AND end_time, calculate duration
            if ($endTime && !$duration) {
                $end = \Carbon\Carbon::parse($endTime);
                $calculatedDuration = $start->diffInMinutes($end);
                $this->merge(['duration' => $calculatedDuration]);
            }
            // Case 2: Have start_time AND duration, calculate end_time
            elseif ($duration && !$endTime) {
                $calculatedEndTime = $start->addMinutes($duration);
                $this->merge(['end_time' => $calculatedEndTime->toDateTimeString()]);
            }
            // Case 3: Have all three - use provided duration to recalculate end_time for consistency
            elseif ($duration && $endTime) {
                $calculatedEndTime = $start->addMinutes($duration);
                // Only update if difference is more than 1 minute (to avoid validation issues)
                $end = \Carbon\Carbon::parse($endTime);
                if (abs($calculatedEndTime->diffInMinutes($end)) > 1) {
                    $this->merge(['end_time' => $calculatedEndTime->toDateTimeString()]);
                }
            }
        }
    }

    public function messages(): array
    {
        return [
            'start_time.date' => 'Thời gian bắt đầu không hợp lệ',
            'start_time.after_or_equal' => 'Thời gian bắt đầu phải từ thời điểm hiện tại trở đi',
            'end_time.after' => 'Thời gian kết thúc phải sau thời gian bắt đầu',
            'fee_amount.required' => 'Vui lòng nhập phí tham gia',
            'fee_amount.min' => 'Phí tham gia phải lớn hơn 0',
            'play_mode.in' => 'Chế độ thi đấu không hợp lệ',
            'format.in' => 'Thể thức thi đấu không hợp lệ',
            'gender.in' => 'Giới tính không hợp lệ',
            'status.in' => 'Trạng thái không hợp lệ',
            'edit_scope.in' => 'Giá trị edit_scope không hợp lệ',
        ];
    }
}
