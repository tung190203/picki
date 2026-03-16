<?php

namespace App\Http\Requests;

use App\Models\MiniTournament;
use App\Rules\ValidRecurringSchedule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMiniTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'poster' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'sport_id' => 'sometimes|exists:sports,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',

            // Play mode and format
            'play_mode' => 'sometimes|in:casual,competition,practice,' . implode(',', [MiniTournament::PLAY_MODE_CASUAL, MiniTournament::PLAY_MODE_COMPETITION, MiniTournament::PLAY_MODE_PRACTICE]),
            'format' => 'nullable|in:single,double,mens_doubles,womens_doubles,mixed,' . implode(',', MiniTournament::FORMAT),

            // Time fields
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date|after:start_time',
            'duration' => 'nullable|integer|min:1',
            'competition_location_id' => 'nullable|exists:competition_locations,id',

            'is_private' => 'boolean',

            // Fee fields
            'has_fee' => 'boolean',
            'auto_split_fee' => 'boolean',
            'fee_description' => 'nullable|string|max:500',
            'qr_code_url' => 'nullable|string',
            'payment_account_id' => 'nullable|exists:club_wallets,id',
            'fee_amount' => 'nullable|integer|min:0',
            'max_players' => 'nullable|integer|min:1',

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

            'status' => 'sometimes|integer|in:' . implode(',', MiniTournament::STATUS),

            'invite_user' => 'nullable|array',
            'invite_user.*' => 'exists:users,id',
        ];

        // Custom validation: if has_fee is true, require fee_amount
        if ($this->has('has_fee') && $this->has_fee) {
            $rules['fee_amount'] = 'required|integer|min:1';
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

    /**
     * Calculate duration from start_time and end_time, OR calculate end_time from start_time and duration
     */
    protected function prepareForValidation(): void
    {
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
            'end_time.after' => 'Thời gian kết thúc phải sau thời gian bắt đầu',
            'fee_amount.required' => 'Vui lòng nhập phí tham gia',
            'fee_amount.min' => 'Phí tham gia phải lớn hơn 0',
            'play_mode.in' => 'Chế độ thi đấu không hợp lệ',
            'format.in' => 'Thể thức thi đấu không hợp lệ',
            'gender.in' => 'Giới tính không hợp lệ',
            'status.in' => 'Trạng thái không hợp lệ',
        ];
    }
}
