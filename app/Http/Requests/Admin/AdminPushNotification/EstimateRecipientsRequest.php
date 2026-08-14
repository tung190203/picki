<?php

namespace App\Http\Requests\Admin\AdminPushNotification;

use App\Enums\AdminPushNotification\RecipientType;
use Illuminate\Foundation\Http\FormRequest;

class EstimateRecipientsRequest extends FormRequest
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
            'recipient_type' => ['required', 'string', 'in:' . RecipientType::pattern()],
            'recipient_config' => ['present', 'array'], // present = có thể là array rỗng
        ];
    }
}