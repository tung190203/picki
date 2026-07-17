<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ActivityLog
{
    public static function log(int $userId, string $action, array $metadata = []): void
    {
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
        ];

        // Try to log to database if table exists
        try {
            if (DB::getSchemaBuilder()->hasTable('activity_logs')) {
                DB::table('activity_logs')->insert($data);
                return;
            }
        } catch (\Exception $e) {
            // Table doesn't exist, fall back to Laravel log
        }

        // Fallback to Laravel log
        Log::info("ActivityLog [{$action}]", array_merge(['user_id' => $userId], $metadata));
    }
}
