<?php

namespace App\Notifications;

use App\Models\MiniMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniMatchCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $match;

    public function __construct(MiniMatch $match)
    {
        $this->match = $match;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // realtime
    }

    public function toDatabase($notifiable)
    {
        return [
            'match_id' => $this->match->id,
            'mini_tournament_id' => $this->match->mini_tournament_id,
            'title' => 'Bạn có trận đấu mới',
            'message' => "Trận đấu '{$this->match->name}' đã được tạo.",
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'match_id' => $this->match->id,
            'mini_tournament_id' => $this->match->mini_tournament_id,
            'title' => 'Bạn có trận đấu mới',
            'message' => "Trận đấu '{$this->match->name}' đã được tạo.",
        ]);
    }
    public function toArray($notifiable)
    {
        return [
            'match_id' => $this->match->id,
            'message' => "Bạn có trận đấu mới: {$this->match->name}",
        ];
    }
}
