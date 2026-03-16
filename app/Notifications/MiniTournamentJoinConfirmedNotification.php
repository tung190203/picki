<?php

namespace App\Notifications;

use App\Models\MiniParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class MiniTournamentJoinConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $participant;
    protected ?int $confirmedBy;

    public function __construct(MiniParticipant $participant, ?int $confirmedBy = null)
    {
        $this->participant = $participant;
        $this->confirmedBy = $confirmedBy ?? auth()->id();
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast']; // hoặc thêm mail nếu muốn
    }

    public function toDatabase($notifiable)
    {
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'participant_id' => $this->participant->id,
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'title' => 'Yêu cầu tham gia đã được duyệt',
            'message' => "Bạn đã được duyệt tham gia kèo đấu \"{$tournamentName}\".",
            'confirmed_by' => $this->confirmedBy,
        ];
    }

    public function toBroadcast($notifiable)
    {
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return new BroadcastMessage([
            'participant_id' => $this->participant->id,
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'title' => 'Yêu cầu tham gia đã được duyệt',
            'message' => "Bạn đã được duyệt tham gia kèo đấu \"{$tournamentName}\".",
            'confirmed_by' => $this->confirmedBy,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function toArray($notifiable)
    {
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';

        return [
            'participant_id' => $this->participant->id,
            'title' => 'Yêu cầu tham gia đã được duyệt',
            'message' => "Bạn đã được duyệt tham gia kèo đấu \"{$tournamentName}\".",
            'confirmed_by' => $this->confirmedBy,
        ];
    }
}
