<?php

namespace App\Notifications;

use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class MiniTournamentReminder extends Notification
{
    use Queueable;

    public $miniTournament;

    public function __construct($miniTournament)
    {
        $this->miniTournament = $miniTournament;
    }

    public function via($notifiable)
    {
        $channels = ['database'];

        // Push FCM rõ ràng qua FirebaseService, không phụ thuộc notification channel mặc định
        if ($this->firebaseEnabled()) {
            $this->sendPushNotification($notifiable);
        }

        return $channels;
    }

    protected function firebaseEnabled(): bool
    {
        return app(FirebaseService::class)->isConfigured();
    }

    protected function sendPushNotification($notifiable): void
    {
        $data = [
            'type' => 'MINI_TOURNAMENT_REMINDER',
            'mini_tournament_id' => (string) $this->miniTournament->id,
        ];

        app(FirebaseService::class)->sendToUser(
            $notifiable->id,
            $this->miniTournament->name,
            "Kèo đấu '{$this->miniTournament->name}' sẽ bắt đầu lúc {$this->miniTournament->start_time}",
            $data
        );
    }

    public function toDatabase($notifiable)
    {
        return [
            'mini_tournament_id' => $this->miniTournament->id,
            'title' => $this->miniTournament->name,
            'start_time' => $this->miniTournament->start_time,
            'message' => "Kèo đấu '{$this->miniTournament->name}' sắp bắt đầu lúc {$this->miniTournament->start_time}",
        ];
    }
}
