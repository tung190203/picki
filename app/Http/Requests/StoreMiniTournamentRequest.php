<?php

namespace App\Http\Requests;

use App\Models\MiniTournament;
use App\Rules\ValidRecurringSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

class StoreMiniTournamentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'sport_id' => 'required|exists:sports,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',

            // Play mode and format
            'play_mode' => 'required|in:casual,competition,practice,' . implode(',', [MiniTournament::PLAY_MODE_CASUAL, MiniTournament::PLAY_MODE_COMPETITION, MiniTournament::PLAY_MODE_PRACTICE]),
            'format' => 'required|in:single,double,mens_doubles,womens_doubles,mixed,' . implode(',', MiniTournament::FORMAT),

            // Time fields (new naming)
            'start_time' => 'required|date|after_or_equal:now',
            'end_time' => 'nullable|date|after:start_time',
            'duration' => 'required|integer|min:1',
            'competition_location_id' => 'required|exists:competition_locations,id',

            'is_private' => 'boolean',

            // Fee fields (updated naming)
            'has_fee' => 'nullable|boolean',
            'auto_split_fee' => 'boolean',
            'fee_description' => 'nullable|string|max:500',
            'qr_code_url' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
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
                    if (!is_string($value)) {
                        $fail('Mã QR phải là file ảnh hoặc URL hợp lệ.');
                    }
                },
            ],
            'payment_account_id' => 'nullable|exists:club_wallets,id',
            'fee_amount' => 'nullable|integer|min:0',
            'max_players' => 'required|integer|min:2|max:100',

            // Club fund integration
            'use_club_fund' => 'boolean',
            'included_in_club_fund' => 'boolean',

            // Rating
            'min_rating' => 'nullable|numeric|min:1|max:8',
            'max_rating' => 'nullable|numeric|min:1|max:8',

            // Game rules (updated naming) - conditional based on apply_rule
            'set_number' => 'nullable|integer|min:1',
            'base_points' => 'nullable|integer|min:11',
            'points_difference' => 'nullable|integer|min:1',
            'max_points' => 'nullable|integer|min:11',

            // Gender (replaced gender_policy)
            'gender' => 'required|integer|in:' . implode(',', MiniTournament::GENDER),

            // New fields
            'apply_rule' => 'boolean',
            'allow_cancellation' => 'boolean',
            'cancellation_duration' => 'nullable|integer|min:0',
            'auto_approve' => 'boolean',
            'allow_participant_add_friends' => 'boolean',

            // Recurring schedule (same format as clubs)
            'recurring_schedule' => ['nullable', 'array', new ValidRecurringSchedule()],

            'status' => 'required|integer|in:' . implode(',', MiniTournament::STATUS),

            'invite_user' => 'nullable|array',
            'invite_user.*' => 'distinct|exists:users,id',
        ];

            // Custom validation: if has_fee is true, require fee_amount
        if ($this->has('has_fee') && $this->has_fee) {
            $rules['fee_amount'] = 'required|integer|min:1';
            // qr_code_url check handled by the custom closure above — accepts file OR string URL
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $minRating = $this->input('min_rating');
            $maxRating = $this->input('max_rating');

            if ($minRating !== null && $maxRating !== null && (float) $minRating > (float) $maxRating) {
                $validator->errors()->add('min_rating', 'Trình độ tối thiểu không được lớn hơn trình độ tối đa.');
            }

            // Only validate QR when user explicitly chose has_fee=true AND use_club_fund=false.
            // If use_club_fund was not sent in the request at all, skip this check — the club
            // may have a shared QR wallet that will be used by the controller/service.
            // If use_club_fund=true, QR is not needed (club fund handles it).
            if ($this->boolean('has_fee') && $this->has('use_club_fund') && !$this->boolean('use_club_fund') && !$this->getClubHasQrWallet()) {
                $qrValue = $this->input('qr_code_url');
                $qrFile = $this->file('qr_code_url');
                $hasQrInput = $qrFile !== null || ($qrValue !== null && $qrValue !== '');
                if (!$hasQrInput && !$this->filled('payment_account_id')) {
                    $validator->errors()->add('qr_code_url', 'Kèo thu phí cần tải ảnh QR thanh toán. Nếu dùng quỹ CLB, vui lòng chọn CLB có ví với mã QR chung.');
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

    /**
     * Calculate duration from start_time and end_time, OR calculate end_time from start_time and duration
     */
    protected function prepareForValidation(): void
    {
        // Normalize empty strings to null for nullable fields
        $nullableKeys = [
            'min_rating', 'max_rating', 'fee_description', 'description',
            'payment_account_id', 'end_time',
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

        // Handle recurring_schedule from FormData (convert array to proper structure)
        $recurringSchedule = $this->input('recurring_schedule');
        if ($recurringSchedule) {
            if (is_array($recurringSchedule)) {
                // From FormData
                $schedule = [
                    'period' => $recurringSchedule['period'] ?? null,
                ];

                if ($schedule['period'] === 'weekly' && isset($recurringSchedule['week_days'])) {
                    $schedule['week_days'] = array_values(array_filter(
                        (array) $recurringSchedule['week_days'],
                        fn($v) => $v !== null && $v !== ''
                    ));
                } elseif (in_array($schedule['period'], ['monthly', 'quarterly', 'yearly']) && isset($recurringSchedule['recurring_date'])) {
                    $schedule['recurring_date'] = $recurringSchedule['recurring_date'];
                }

                $this->merge(['recurring_schedule' => $schedule]);
            }
            // If it's already an object/array from JSON, leave it as is
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

    public function getClubHasQrWallet(): bool
    {
        $clubId = $this->route('clubId') ?? $this->input('club_id');
        if (!$clubId) {
            return false;
        }

        return \App\Models\Club\Club::find($clubId)?->activeQrWallet() !== null;
    }

    public function messages(): array
    {
        return [
            'sport_id.required' => 'Vui lòng chọn môn thể thao',
            'sport_id.exists' => 'Môn thể thao không hợp lệ',
            'name.required' => 'Vui lòng nhập tên kèo đấu',
            'name.max' => 'Tên kèo đấu không được vượt quá 255 ký tự',
            'play_mode.required' => 'Vui lòng chọn chế độ thi đấu',
            'play_mode.in' => 'Chế độ thi đấu không hợp lệ (casual, competition, practice)',
            'format.required' => 'Vui lòng chọn thể thức thi đấu',
            'format.in' => 'Thể thức thi đấu không hợp lệ',
            'start_time.required' => 'Vui lòng chọn thời gian bắt đầu',
            'start_time.date' => 'Thời gian bắt đầu không hợp lệ',
            'start_time.after_or_equal' => 'Thời gian bắt đầu phải từ thời điểm hiện tại trở đi',
            'end_time.after' => 'Thời gian kết thúc phải sau thời gian bắt đầu',
            'duration.required' => 'Vui lòng chọn thời lượng kèo đấu',
            'duration.min' => 'Thời lượng kèo đấu phải lớn hơn 0 phút',
            'competition_location_id.required' => 'Vui lòng chọn địa điểm thi đấu',
            'competition_location_id.exists' => 'Địa điểm thi đấu không hợp lệ',
            'fee_amount.required' => 'Vui lòng nhập phí tham gia',
            'fee_amount.min' => 'Phí tham gia phải lớn hơn 0',
            'max_players.required' => 'Vui lòng nhập số lượng người chơi',
            'max_players.min' => 'Số người chơi tối thiểu là 2',
            'max_players.max' => 'Số người chơi tối đa là 100',
            'min_rating.min' => 'Trình độ tối thiểu phải từ 1.0',
            'max_rating.max' => 'Trình độ tối đa không được vượt quá 8.0',
            'set_number.required' => 'Vui lòng nhập số set thi đấu',
            'set_number.min' => 'Số set thi đấu phải lớn hơn 0',
            'base_points.required' => 'Vui lòng nhập điểm cơ bản',
            'base_points.min' => 'Điểm cơ bản phải lớn hơn hoặc bằng 11',
            'points_difference.required' => 'Vui lòng nhập cách biệt điểm',
            'points_difference.min' => 'Cách biệt điểm phải lớn hơn 0',
            'max_points.required' => 'Vui lòng nhập điểm tối đa',
            'max_points.min' => 'Điểm tối đa phải lớn hơn hoặc bằng 11',
            'gender.required' => 'Vui lòng chọn giới tính',
            'gender.in' => 'Giới tính không hợp lệ',
            'status.required' => 'Vui lòng chọn trạng thái',
            'status.in' => 'Trạng thái không hợp lệ',
            'cancellation_duration.required' => 'Vui lòng nhập thời gian hủy kèo (phút)',
            'cancellation_duration.min' => 'Thời gian hủy kèo phải lớn hơn 0',
            'invite_user.*.distinct' => 'Danh sách người được mời không được trùng lặp',
            'invite_user.*.exists' => 'Người dùng được mời không tồn tại',
        ];
    }
}
