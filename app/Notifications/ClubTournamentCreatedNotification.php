<?php

namespace App\Notifications;

use App\Models\Club\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ClubTournamentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPE_MINI_TOURNAMENT = 'mini_tournament';
    public const TYPE_TOURNAMENT = 'tournament';

    public function __construct(
        public Club $club,
        public string $tournamentName,
        public string $tournamentType,
        public int $tournamentId,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $isMini = $this->tournamentType === self::TYPE_MINI_TOURNAMENT;
        $label = $isMini ? 'kèo' : 'giải đấu';
        $type = $isMini ? 'CLUB_MINI_TOURNAMENT_CREATED' : 'CLUB_TOURNAMENT_CREATED';

        return [
            'club_id' => $this->club->id,
            'club_name' => $this->club->name,
            'tournament_id' => $this->tournamentId,
            'tournament_type' => $this->tournamentType,
            'title' => "CLB {$this->club->name} vừa tạo {$label} mới",
            'message' => $this->tournamentName,
            'type' => $type,
        ];
    }
}
