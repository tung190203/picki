<?php

namespace App\Events;

use App\Http\Resources\QuickMatchResource;
use App\Models\QuickMatch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class QuickMatchConfirmed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public QuickMatch $quickMatch;

    public function __construct(QuickMatch $quickMatch)
    {
        $this->quickMatch = $quickMatch;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('quick-match.' . $this->quickMatch->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'quick_match.confirmed';
    }

    public function broadcastWith(): array
    {
        return [
            'quick_match' => (new QuickMatchResource($this->quickMatch))->resolve(),
        ];
    }
}
