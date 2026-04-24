<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserOnlineStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $fullName;
    public ?string $avatarUrl;
    public bool $isOnline;

    public function __construct(int $userId, string $fullName, ?string $avatarUrl, bool $isOnline)
    {
        $this->userId = $userId;
        $this->fullName = $fullName;
        $this->avatarUrl = $avatarUrl;
        $this->isOnline = $isOnline;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.presence')];
    }

    public function broadcastAs(): string
    {
        return 'user.presence.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'full_name' => $this->fullName,
            'avatar_url' => $this->avatarUrl,
            'is_online' => $this->isOnline,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
