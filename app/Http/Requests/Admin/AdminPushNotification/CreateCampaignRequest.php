<?php

namespace App\Http\Requests\Admin\AdminPushNotification;

use App\Enums\AdminPushNotification\ActionType;
use App\Enums\AdminPushNotification\RecipientType;
use App\Enums\AdminPushNotification\SendType;
use Illuminate\Foundation\Http\FormRequest;

class CreateCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Decode recipient_config JSON trước khi validate nếu client gửi multipart form-data.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('recipient_config') && is_string($this->input('recipient_config'))) {
            $decoded = json_decode($this->input('recipient_config'), true);
            if (is_array($decoded)) {
                $this->merge(['recipient_config' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],

            'action_type' => ['required', 'string', 'in:' . ActionType::pattern()],
            'action_id' => ['nullable', 'integer'],

            'recipient_type' => ['required', 'string', 'in:' . RecipientType::pattern()],
            'recipient_config' => ['nullable', 'array'],

            'send_type' => ['required', 'string', 'in:' . SendType::pattern()],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề',
            'title.max' => 'Tiêu đề không được vượt quá 50 ký tự',
            'content.required' => 'Vui lòng nhập nội dung',
            'content.max' => 'Nội dung không được vượt quá 150 ký tự',
            'image.image' => 'File phải là hình ảnh',
            'image.max' => 'Kích thước ảnh không được vượt quá 5MB',
            'action_type.required' => 'Vui lòng chọn loại hành động',
            'action_type.in' => 'Loại hành động không hợp lệ',
            'recipient_type.required' => 'Vui lòng chọn loại người nhận',
            'recipient_type.in' => 'Loại người nhận không hợp lệ',
            'recipient_config.required' => 'Vui lòng cấu hình người nhận',
            'send_type.required' => 'Vui lòng chọn kiểu gửi',
            'scheduled_at.required_if' => 'Vui lòng chọn thời gian gửi',
            'scheduled_at.after' => 'Thời gian gửi phải sau thời điểm hiện tại',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            // action_id required khi action_type != NONE
            if (($data['action_type'] ?? null) !== ActionType::NONE->value) {
                if (empty($data['action_id'])) {
                    $validator->errors()->add('action_id', 'Vui lòng chọn đối tượng hành động');
                    return;
                }

                $table = match ($data['action_type']) {
                    ActionType::MATCH->value => 'quick_matches',
                    ActionType::TOURNAMENT->value => 'tournaments',
                    ActionType::CLUB->value => 'clubs',
                    default => null,
                };

                if ($table && !\DB::table($table)->where('id', $data['action_id'])->exists()) {
                    $validator->errors()->add('action_id', "Đối tượng không tồn tại trong {$table}");
                }
            }

            // scheduled_at required khi send_type=SCHEDULED
            if (($data['send_type'] ?? null) === SendType::SCHEDULED->value && empty($data['scheduled_at'])) {
                $validator->errors()->add('scheduled_at', 'Vui lòng chọn thời gian gửi');
            }

            // Validate recipient_config theo recipient_type
            $this->validateRecipientConfig($validator, $data);
        });
    }

    private function validateRecipientConfig($validator, array $data): void
    {
        $config = $data['recipient_config'] ?? [];
        $type = $data['recipient_type'] ?? null;

        switch ($type) {
            case RecipientType::ALL->value:
                // ALL: recipient_config không cần thiết, bỏ qua validation
                break;

            case RecipientType::CLUB->value:
                if (empty($config) || !is_array($config)) {
                    $validator->errors()->add('recipient_config', 'Cấu hình người nhận không hợp lệ');
                    return;
                }
                if (empty($config['club_id'])) {
                    $validator->errors()->add('recipient_config.club_id', 'Vui lòng chọn câu lạc bộ');
                    return;
                }
                if (!\DB::table('clubs')->where('id', $config['club_id'])->exists()) {
                    $validator->errors()->add('recipient_config.club_id', 'Câu lạc bộ không tồn tại');
                }
                break;

            case RecipientType::ACTIVITY->value:
                if (empty($config) || !is_array($config)) {
                    $validator->errors()->add('recipient_config', 'Cấu hình người nhận không hợp lệ');
                    return;
                }
                if (empty($config['level'])) {
                    $validator->errors()->add('recipient_config.level', 'Vui lòng chọn mức độ hoạt động');
                    return;
                }
                $validLevels = ['HOT', 'WARM', 'COLD'];
                if (!in_array($config['level'], $validLevels, true)) {
                    $validator->errors()->add('recipient_config.level', 'Mức độ hoạt động không hợp lệ');
                }
                break;

            case RecipientType::USERS->value:
                $userIds = $config['user_ids'] ?? [];
                if (empty($userIds) || !is_array($userIds)) {
                    $validator->errors()->add('recipient_config.user_ids', 'Vui lòng chọn ít nhất 1 người dùng');
                    return;
                }
                if (count($userIds) > 1000) {
                    $validator->errors()->add('recipient_config.user_ids', 'Tối đa 1000 người dùng mỗi lần gửi');
                }
                break;
        }
    }
}