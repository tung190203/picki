<?php

namespace Database\Seeders;

use App\Enums\ClubNotificationType as EnumType;
use App\Models\Club\ClubNotificationType;
use Illuminate\Database\Seeder;

class ClubNotificationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['enum' => EnumType::General, 'name' => 'Thông báo chung', 'slug' => 'general', 'description' => 'Thông báo chung của CLB', 'icon' => 'bell', 'is_active' => true],
            ['enum' => EnumType::Event, 'name' => 'Sự kiện', 'slug' => 'event', 'description' => 'Thông báo sự kiện, lịch tập', 'icon' => 'calendar', 'is_active' => true],
            ['enum' => EnumType::Finance, 'name' => 'Tài chính', 'slug' => 'finance', 'description' => 'Thu chi, đóng phí', 'icon' => 'currency', 'is_active' => true],
            ['enum' => EnumType::Member, 'name' => 'Thành viên', 'slug' => 'member', 'description' => 'Thành viên mới, thay đổi vai trò', 'icon' => 'users', 'is_active' => true],
            ['enum' => EnumType::Urgent, 'name' => 'Khẩn cấp', 'slug' => 'urgent', 'description' => 'Thông báo khẩn cấp', 'icon' => 'alert', 'is_active' => true],
        ];

        foreach ($types as $type) {
            ClubNotificationType::updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'id' => $type['enum']->value,
                    'name' => $type['name'],
                    'description' => $type['description'],
                    'icon' => $type['icon'],
                    'is_active' => $type['is_active'],
                ]
            );
        }
    }
}
