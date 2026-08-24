<?php

namespace App\Http\Requests\Admin\NotificationTemplate;

use App\Enums\AdminPushNotification\ActionType;
use App\Enums\AdminPushNotification\RecipientType;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:150'],
            'action_type' => ['nullable', 'string', 'in:' . ActionType::pattern()],
            'action_id' => ['nullable', 'integer'],
            'recipient_type' => ['required', 'string', 'in:' . RecipientType::pattern()],
            'recipient_config' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên mẫu',
            'name.max' => 'Tên mẫu không được vượt quá 255 ký tự',
            'title.required' => 'Vui lòng nhập tiêu đề',
            'title.max' => 'Tiêu đề không được vượt quá 50 ký tự',
            'content.required' => 'Vui lòng nhập nội dung',
            'content.max' => 'Nội dung không được vượt quá 150 ký tự',
            'action_type.in' => 'Loại hành động không hợp lệ',
            'recipient_type.required' => 'Vui lòng chọn loại người nhận',
            'recipient_type.in' => 'Loại người nhận không hợp lệ',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            if (($data['action_type'] ?? null) !== ActionType::NONE->value && !empty($data['action_id'])) {
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

            $this->validateRecipientConfig($validator, $data);
        });
    }

    private function validateRecipientConfig($validator, array $data): void
    {
        $config = $data['recipient_config'] ?? [];
        $type = $data['recipient_type'] ?? null;

        switch ($type) {
            case RecipientType::ALL->value:
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
