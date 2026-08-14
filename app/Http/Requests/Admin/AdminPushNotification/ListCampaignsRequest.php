<?php

namespace App\Http\Requests\Admin\AdminPushNotification;

use App\Enums\AdminPushNotification\CampaignStatus;
use Illuminate\Foundation\Http\FormRequest;

class ListCampaignsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'limit' => ['integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:' . CampaignStatus::pattern()],
            'recipient_type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }
}