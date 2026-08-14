<?php

namespace App\Http\Requests\Admin\AdminPushNotification;

use Illuminate\Foundation\Http\FormRequest;

class SendTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:50'],
            'content' => ['required', 'string', 'max:150'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'action_type' => ['nullable', 'string', 'in:NONE,MATCH,TOURNAMENT,CLUB'],
            'action_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tiêu đề',
            'title.max' => 'Tiêu đề không được vượt quá 50 ký tự',
            'content.required' => 'Vui lòng nhập nội dung',
            'content.max' => 'Nội dung không được vượt quá 150 ký tự',
        ];
    }
}