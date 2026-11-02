<?php

namespace App\Notifications;

use App\Models\MiniTournamentMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class MiniTournamentMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $message;

    public function __construct(MiniTournamentMessage $message)
    {
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        // Lưu DB và broadcast realtime
        return ['database', 'broadcast'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'mini_tournament_id' => $this->message->mini_tournament_id,
            'message_id' => $this->message->id,
            'sender_id' => $this->message->user_id,
            'sender_name' => $this->message->user->full_name,
            'type' => $this->message->type,
            'content' => $this->message->content,
        ];
    }

    public function toBroadcast($notifiable)
    {
        return [
            'data' => $this->toDatabase($notifiable)
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $this->message->loadMissing(['user', 'tournament']);
        $senderName = $this->message->user?->full_name ?? 'Một thành viên';
        $tourName = $this->message->tournament?->name ?? 'Mini Tournament';

        $body = match ($this->message->type) {
            'image' => 'Đã gửi một hình ảnh',
            'voice' => 'Đã gửi một đoạn ghi âm',
            'file'  => 'Đã gửi một tệp đính kèm',
            default => $this->message->content ?? 'Đã gửi một tin nhắn',
        };

        return [
            'title' => "[{$tourName}] {$senderName}",
            'body'  => $body,
            'data'  => [
                'type'               => 'MINI_TOURNAMENT_CHAT',
                'mini_tournament_id' => (string) $this->message->mini_tournament_id,
                'message_id'         => (string) $this->message->id,
                'sender_id'          => (string) $this->message->user_id,
                'action'             => 'open_chat',
            ],
        ];
    }

    public function toArray($notifiable)
    {
        return [
            'mini_tournament_id' => $this->message->mini_tournament_id,
            'message_id' => $this->message->id,
            'sender_id' => $this->message->user_id,
            'sender_name' => $this->message->user?->full_name,
            'type' => $this->message->type,
            'content' => $this->message->content,
        ];
    }
}
