<?php

namespace App\Services\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public function get(): array
    {
        $kFactor = SystemSetting::where('key', 'k_factor')->first();
        $serviceFee = SystemSetting::where('key', 'service_fee_percent')->first();
        $autoConfirmHours = SystemSetting::where('key', 'auto_confirm_hours')->first();
        $features = SystemSetting::where('key', 'features')->first();

        return [
            'k_factor' => $kFactor ? (float) $kFactor->value : 32,
            'service_fee_percent' => $serviceFee ? (float) $serviceFee->value : 5.5,
            'auto_confirm_hours' => $autoConfirmHours ? (int) $autoConfirmHours->value : 24,
            'features' => $features ? json_decode($features->value, true) : [
                'ai_assistant' => true,
                'online_payment' => true,
                'maintenance_mode' => false,
            ],
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

        if (isset($data['features'])) {
            $this->upsertSetting('features', json_encode($data['features']), 'json');
            $changes['features'] = $data['features'];
        }

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

        return $this->get();
    }

    private function upsertSetting(string $key, string $value, string $type): void
    {
        SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }
}
