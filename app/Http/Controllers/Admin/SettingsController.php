<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseHelper;
use App\Services\Admin\SettingsService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settingsService
    ) {}

    public function index()
    {
        $settings = $this->settingsService->get();
        return ResponseHelper::single($settings, 'Lấy settings thành công');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'k_factor' => 'nullable|numeric|min:1|max:100',
            'service_fee_percent' => 'nullable|numeric|min:0|max:100',
            'auto_confirm_hours' => 'nullable|integer|min:1',
            'features' => 'nullable|array',
            'features.ai_assistant' => 'nullable|boolean',
            'features.online_payment' => 'nullable|boolean',
            'features.maintenance_mode' => 'nullable|boolean',
        ]);

        $admin = auth()->user();
        $settings = $this->settingsService->update($validated, $admin);

        return ResponseHelper::success($settings, 'Update settings thành công');
    }
}
