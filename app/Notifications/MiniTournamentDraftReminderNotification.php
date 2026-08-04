<?php

namespace App\Notifications;

class MiniTournamentDraftReminderNotification extends BaseNotification
{
    public $miniTournament;

    public function __construct($miniTournament)
    {
        $this->miniTournament = $miniTournament;
    }

    public function toDatabase(object $notifiable): array
    {
        $name = $this->miniTournament->name;

        return [
            'type' => 'MiniTournamentDraftReminder',
            'mini_tournament_id' => $this->miniTournament->id,
            'title' => $name,
            'message' => "Kèo đấu '{$name}' của bạn vẫn đang ở trạng thái nháp. Hãy công bố kèo đấu để người chơi có thể tham gia!",
            'action' => 'open_tournament',
        ];
    }
}
