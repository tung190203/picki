<?php

namespace App\Services\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Geocoding\GoongKeyResolver;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public function get(): array
    {
        $kFactor = SystemSetting::where('key', 'k_factor')->first();
        $serviceFee = SystemSetting::where('key', 'service_fee_percent')->first();
        $autoConfirmHours = SystemSetting::where('key', 'auto_confirm_hours')->first();
        $rankingMatches = SystemSetting::where('key', 'ranking_matches')->first();
        $features = SystemSetting::where('key', 'features')->first();

        return [
            'k_factor' => $kFactor ? (float) $kFactor->value : 32,
            'service_fee_percent' => $serviceFee ? (float) $serviceFee->value : 5.5,
            'auto_confirm_hours' => $autoConfirmHours ? (int) $autoConfirmHours->value : 24,
            'ranking_matches' => $rankingMatches ? (int) $rankingMatches->value : 10,
            'features' => $features ? json_decode($features->value, true) : [
                'ai_assistant' => true,
                'online_payment' => true,
                'maintenance_mode' => false,
            ],
        ];
    }

    /**
     * Get map provider settings (keys masked for security).
     */
    public function getMapProvider(): array
    {
        $apiKeyRow = SystemSetting::where('key', 'goong.api_key')->first();
        $mapKeyRow = SystemSetting::where('key', 'goong.map_key')->first();

        return [
            'goong_api_key' => $this->maskValue($apiKeyRow?->value),
            'goong_map_key' => $this->maskValue($mapKeyRow?->value),
        ];
    }

    public function update(array $data, User $admin): array
    {
        $oldSettings = $this->get();
        $changes = [];

        if (isset($data['k_factor'])) {
            $this->upsertSetting('k_factor', (string) $data['k_factor'], 'number');
            $changes['k_factor'] = $data['k_factor'];
        }

        if (isset($data['service_fee_percent'])) {
            $this->upsertSetting('service_fee_percent', (string) $data['service_fee_percent'], 'number');
            $changes['service_fee_percent'] = $data['service_fee_percent'];
        }

        if (isset($data['auto_confirm_hours'])) {
            $this->upsertSetting('auto_confirm_hours', (string) $data['auto_confirm_hours'], 'number');
            $changes['auto_confirm_hours'] = $data['auto_confirm_hours'];
        }

        if (isset($data['ranking_matches'])) {
            $this->upsertSetting('ranking_matches', (string) $data['ranking_matches'], 'number');
            $changes['ranking_matches'] = $data['ranking_matches'];
        }

        if (isset($data['features'])) {
            $this->upsertSetting('features', json_encode($data['features']), 'json');
            $changes['features'] = $data['features'];
        }

        // Handle Goong map provider keys
        if (isset($data['goong_api_key']) || isset($data['goong_map_key'])) {
            $this->upsertSetting('goong.api_key', $data['goong_api_key'] ?? '', 'string');
            $this->upsertSetting('goong.map_key', $data['goong_map_key'] ?? '', 'string');

            // Invalidate Goong key cache so new keys take effect immediately
            app(GoongKeyResolver::class)->forgetCache();

            $changes['goong_api_key'] = $this->maskValue($data['goong_api_key'] ?? '');
            $changes['goong_map_key'] = $this->maskValue($data['goong_map_key'] ?? '');
        }

        if (! empty($changes)) {
            $auditLogService = app(AuditLogService::class);
            $auditLogService->log(
                $admin,
                'update_settings',
                SystemSetting::class,
                null,
                $oldSettings,
                $changes
            );

            Cache::forget('system_settings');
        }

        return $this->get();
    }

    private function upsertSetting(string $key, string $value, string $type): void
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    /**
     * Mask a sensitive value for display: show first 4 and last 4 chars.
     * Returns empty string if value is empty/null.
     */
    private function maskValue(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $len = mb_strlen($value);
        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return mb_substr($value, 0, 4) . str_repeat('*', max(0, $len - 8)) . mb_substr($value, $len - 4);
    }
}
