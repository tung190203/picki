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
    protected bool $hasAutoSplitFee;
    protected ?float $estimatedFeePerPerson;
    private const TITLE = 'Bạn được mời tham gia kèo đấu';

    public function __construct(
        MiniParticipant $participant,
        ?int $invitedBy = null,
        bool $hasAutoSplitFee = false,
        ?float $estimatedFeePerPerson = null
    ) {
        $this->participant = $participant;
        $this->invitedBy = $invitedBy ?? auth()->id();
        $this->hasAutoSplitFee = $hasAutoSplitFee;
        $this->estimatedFeePerPerson = $estimatedFeePerPerson;
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    private function buildMessage(): string
    {
        $tournamentName = $this->participant->miniTournament?->name ?? 'kèo đấu';
        $message = self::TITLE . " \"{$tournamentName}\".";

        if ($this->hasAutoSplitFee && $this->estimatedFeePerPerson !== null) {
            $feeFormatted = number_format($this->estimatedFeePerPerson, 0, ',', '.');
            $message .= " Phí dự kiến {$feeFormatted} VND/người, sẽ được chia khi kèo bắt đầu.";
        }

        return $message;
    }

    public function toDatabase($notifiable)
    {
        return [
            'participant_id' => $this->participant->id,
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'title' => self::TITLE,
            'message' => $this->buildMessage(),
            'invited_by' => $this->invitedBy,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'participant_id' => $this->participant->id,
            'mini_tournament_id' => $this->participant->mini_tournament_id,
            'title' => self::TITLE,
            'message' => $this->buildMessage(),
            'invited_by' => $this->invitedBy,
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'participant_id' => $this->participant->id,
            'title' => self::TITLE,
            'message' => $this->buildMessage(),
            'invited_by' => $this->invitedBy,
        ];
    }
}
