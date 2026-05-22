<?php

namespace App\Console\Commands;

use App\Jobs\SendSystemPushNotification;
use App\Models\SystemNotification;
use Illuminate\Console\Command;

class SendVersionUpdateNotification extends Command
{
    protected $signature = 'system:send-version-notification {version}';
    protected $description = 'Gửi thông báo cập nhật phiên bản cho app mobile (chạy 1 lần)';

    public function handle(): int
    {
        $version = $this->argument('version');

        $notification = SystemNotification::updateOrCreate(
            [
                'title' => "Bản cập nhật Picki v{$version}",
                'body' => "Hệ thống đã được cập nhật lên phiên bản {$version}. Cảm ơn bạn đã đồng hành cùng Picki!",
            ],
            [
                'data' => [
                    'type' => 'SYSTEM_NOTIFICATION',
                    'version' => $version,
                ],
                'scheduled_at' => now(),
                'sent_at' => null,
            ]
        );

        if ($notification->wasRecentlyCreated) {
            $this->info("Đã tạo thông báo version {$version} (ID: {$notification->id})");
        } else {
            $this->warn("Thông báo version {$version} đã tồn tại (ID: {$notification->id})");
        }

        if ($notification->sent_at !== null) {
            $this->warn("Thông báo đã được gửi trước đó, bỏ qua.");
            return Command::SUCCESS;
        }

        $this->info('Đang gửi push notification...');
        SendSystemPushNotification::dispatchSync($notification);

        $notification->refresh();
        if ($notification->sent_at) {
            $this->info("Đã gửi thông báo version {$version} thành công!");
        } else {
            $this->error("Gửi thông báo thất bại.");
        }

        return Command::SUCCESS;
    }
}
