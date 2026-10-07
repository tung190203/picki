<?php

namespace App\Http\Requests\Club;

use App\Enums\ClubStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('zalo_link_enabled')) {
            $this->merge([
                'zalo_link_enabled' => filter_var($this->zalo_link_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
        if ($this->has('qr_zalo_enabled')) {
            $this->merge([
                'qr_zalo_enabled' => filter_var($this->qr_zalo_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
        if ($this->has('remove_qr_zalo')) {
            $this->merge([
                'remove_qr_zalo' => filter_var($this->remove_qr_zalo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
        if ($this->has('qr_code_enabled')) {
            $this->merge([
                'qr_code_enabled' => filter_var($this->qr_code_enabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
        if ($this->has('is_public')) {
            $this->merge([
                'is_public' => filter_var($this->is_public, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ]);
        }
        // Chuỗi rỗng cho latitude/longitude gây lỗi decimal - chuyển thành null
        $merge = [];
        if ($this->has('latitude') && (trim((string) ($this->latitude ?? '')) === '')) {
            $merge['latitude'] = null;
        }
        if ($this->has('longitude') && (trim((string) ($this->longitude ?? '')) === '')) {
            $merge['longitude'] = null;
        }
        if ($this->has('address') && (trim((string) ($this->address ?? '')) === '')) {
            $merge['address'] = null;
        }
        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $clubId = $this->route('clubId');

        return [
            'name' => "nullable|string|max:255|unique:clubs,name,{$clubId},id,deleted_at,NULL",
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'logo_url' => 'nullable|image|max:5120',
            'cover_image_url' => 'nullable|image|max:5120',
            'status' => ['nullable', Rule::enum(ClubStatus::class)],
            'is_public' => 'nullable|boolean',
            'description' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|url|max:255',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'footer' => 'nullable|string|max:1000',
            'zalo_link' => 'required_if:zalo_link_enabled,true|nullable|string|max:500',
            'zalo_link_enabled' => 'nullable|boolean',
            'qr_zalo_enabled' => 'nullable|boolean',
            'qr_zalo' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:5120',
            'remove_qr_zalo' => 'nullable|boolean',
            'qr_code_image_url' => 'nullable|image|mimes:png,jpg,jpeg,gif|max:5120',
            'qr_code_enabled' => 'nullable|boolean',
            'rules' => 'nullable|string|max:5000',
            'recurring_schedule_text' => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'logo_url.max' => 'Đã xảy ra lỗi vui lòng thử lại sau',
            'cover_image_url.max' => 'Đã xảy ra lỗi vui lòng thử lại sau',
        ];
    }
}
