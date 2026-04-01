<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class FollowNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $follower;
    protected $followable;

    public function __construct($follower, $followable)
    {
        $this->follower = $follower;       // User vừa follow
        $this->followable = $followable;   // Model được follow
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // lưu DB + realtime
    }

    public function toDatabase($notifiable): array
    {
        return [
            'follower_id' => $this->follower->id,
            'follower_name' => $this->follower->full_name,
            'followable_type' => get_class($this->followable),
            'followable_id' => $this->followable->id,
            'title' => 'Bạn có một người theo dõi mới!',
            'message' => "{$this->follower->full_name} đã theo dõi bạn.",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'data' => $this->toDatabase($notifiable),
        ]);
    }

    public function toArray($notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
