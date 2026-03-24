<?php

namespace Database\Seeders;

use App\Models\SystemNotification;
use Illuminate\Database\Seeder;

class SystemNotificationSeeder extends Seeder
{
    public function run(): void
    {
        SystemNotification::updateOrCreate(
            [
                'title' => 'Bản cập nhật Picki v0.3.3',
                'body' => 'Hệ thống đã được cập nhật lên phiên bản 0.3.3. Cảm ơn bạn đã đồng hành cùng Picki!',
            ],
            [
                'data' => [
                    'type' => 'SYSTEM_NOTIFICATION',
                    'version' => '0.3.3',
                ],
                'scheduled_at' => now(),
            ]
        );
    }
}
