<?php

namespace App\Events\SuperAdmin;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public string $action = 'created';
    public string $eventType = 'user';

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('DashboardAdminChannel')];
    }

    public function broadcastAs(): string
    {
        return 'super_admin.user';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'event_type' => $this->eventType,
            'data' => [
                'id' => $this->user->id,
                'full_name' => $this->user->full_name,
                'email' => $this->user->email,
                'avatar_url' => $this->user->avatar_url,
                'phone' => $this->user->phone,
                'gender' => $this->user->gender,
                'gender_text' => $this->user->gender_text,
                'role' => $this->user->role,
                'is_verified' => $this->user->is_verified,
                'is_anchor' => $this->user->is_anchor,
                'trust_score' => $this->user->trust_score,
                'total_tournaments' => $this->user->total_tournaments ?? 0,
                'total_mini_tournaments' => $this->user->total_mini_tournaments ?? 0,
                'created_at' => $this->user->created_at?->toIso8601String(),
            ],
        ];
    }
}
