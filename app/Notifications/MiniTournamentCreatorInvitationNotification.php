<?php

namespace App\Notifications;

use App\Models\MiniParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniTournamentCreatorInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $participant;
    protected ?int $invitedBy;

    public function __construct(MiniParticipant $participant, ?int $invitedBy = null)
    {
        $this->participant = $participant;
        $this->invitedBy = $invitedBy ?? auth()->id();
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'participant_id' => $this->participant->id,
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'title' => 'Bạn được mời tham gia kèo đấu',
            'message' => "Bạn được mời tham gia kèo đấu \"{$tournamentName}\".",
            'invited_by' => $this->invitedBy,
        ];
    }

    public function toBroadcast($notifiable)
    {
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return new BroadcastMessage([
            'participant_id' => $this->participant->id,
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'title' => 'Bạn được mời tham gia kèo đấu',
            'message' => "Bạn được mời tham gia kèo đấu \"{$tournamentName}\".",
            'invited_by' => $this->invitedBy,
        ]);
    }

    public function toArray($notifiable)
    {
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'participant_id' => $this->participant->id,
            'title' => 'Bạn được mời tham gia kèo đấu',
            'message' => "Bạn được mời tham gia kèo đấu \"{$tournamentName}\".",
            'invited_by' => $this->invitedBy,
        ];
    }
}
