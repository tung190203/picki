<?php

namespace App\Notifications;

use App\Models\QuickMatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class QuickMatchInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected QuickMatch $quickMatch;
    protected int $invitedBy;

    public function __construct(QuickMatch $quickMatch, ?int $invitedBy = null)
    {
        $this->quickMatch = $quickMatch;
        $this->invitedBy = $invitedBy ?? auth()->id();
    }

    public function via($notifiable)
    {
        return ['database', 'broadcast'];
    }

    private function buildMessage(): string
    {
        $creatorName = $this->quickMatch->creator?->full_name ?? 'Một người chơi';
        $matchName = $this->quickMatch->name;
        $matchType = $this->quickMatch->match_type === QuickMatch::MATCH_TYPE_RANK ? 'Rank' : 'Casual';

        return "{$creatorName} đã mời bạn tham gia kèo đấu nhanh \"{$matchName}\" ({$matchType}).";
    }

    public function toDatabase($notifiable)
    {
        return [
            'quick_match_id' => $this->quickMatch->id,
            'qr_code' => $this->quickMatch->qr_code,
            'title' => 'Bạn được mời tham gia kèo đấu nhanh',
            'message' => $this->buildMessage(),
            'invited_by' => $this->invitedBy,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'quick_match_id' => $this->quickMatch->id,
            'qr_code' => $this->quickMatch->qr_code,
            'title' => 'Bạn được mời tham gia kèo đấu nhanh',
            'message' => $this->buildMessage(),
            'invited_by' => $this->invitedBy,
        ]);
    }

    public function toArray($notifiable)
    {
        return [
            'quick_match_id' => $this->quickMatch->id,
            'title' => 'Bạn được mời tham gia kèo đấu nhanh',
            'message' => $this->buildMessage(),
            'invited_by' => $this->invitedBy,
        ];
    }
}
