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
        return ResponseHelper::single($settings, 'Lay settings thanh cong');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'k_factor' => 'nullable|numeric|min:1|max:100',
            'service_fee_percent' => 'nullable|numeric|min:0|max:100',
            'auto_confirm_hours' => 'nullable|integer|min:1',
            'ranking_matches' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
            'features.ai_assistant' => 'nullable|boolean',
            'features.online_payment' => 'nullable|boolean',
            'features.maintenance_mode' => 'nullable|boolean',
            // Map provider keys
            'goong_api_key' => 'nullable|string|max:500',
            'goong_map_key' => 'nullable|string|max:500',
        ]);

        $admin = auth()->user();
        $settings = $this->settingsService->update($validated, $admin);

        return ResponseHelper::success($settings, 'Update settings thanh cong');
    }

    /**
     * Get map provider settings (keys masked).
     */
    public function mapProvider()
    {
        $data = $this->settingsService->getMapProvider();
        return ResponseHelper::success($data, 'Lay map provider settings thanh cong');
    }
}
