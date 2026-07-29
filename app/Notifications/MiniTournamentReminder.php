<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

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
        return ['database'];
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
