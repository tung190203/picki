<?php

namespace App\Notifications;

use App\Models\MiniMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniMatchResultConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    protected $match;

    public function __construct(MiniMatch $match)
    {
        $this->match = $match;
    }

    /**
     * Các kênh thông báo.
     */
    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    /**
     * Lưu thông báo vào DB.
     */
    public function toDatabase($notifiable)
    {
        return [
            'match_id' => $this->match->id,
            'mini_tournament_id' => $this->match->mini_tournament_id,
            'title' => 'Kết quả trận đấu đã được xác nhận',
            'message' => "Một bên đã xác nhận kết quả trận đấu '{$this->match->name}'",
            'confirmed_by' => auth()->id(),
        ];
    }

    /**
     * Dùng broadcast realtime.
     */
    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'match_id' => $this->match->id,
            'mini_tournament_id' => $this->match->mini_tournament_id,
            'title' => 'Kết quả trận đấu đã được xác nhận',
            'message' => "Một bên đã xác nhận kết quả trận đấu '{$this->match->name}'",
            'confirmed_by' => auth()->id(),
        ]);
    }

    /**
     * Tên kênh riêng tư của user để push realtime.
     */
    public function receivesBroadcastNotificationsOn()
    {
        return 'App.Models.User.' . $this->notifiable->getKey();
    }
}
