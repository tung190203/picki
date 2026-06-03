<?php

namespace App\Console\Commands;

use App\Events\UserOnlineStatusChanged;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncUserOnlineStatus extends Command
{
    protected $signature = 'users:sync-online-status';

    protected $description = 'Sync user online status: dispatch offline events for users inactive > 15 min';

    public function handle(): int
    {
        $offlineUsers = User::whereNotNull('last_active_at')
            ->where('last_active_at', '<', now()->subMinutes(15))
            ->where(function ($q) {
                $q->whereNull('last_login')
                    ->orWhere('last_login', '>=', now()->subMinutes(15));
            })
            ->get();

        foreach ($offlineUsers as $user) {
            $user->updateQuietly(['last_login' => now()->subMinutes(15)]);
            event(new UserOnlineStatusChanged(
                $user->id,
                $user->full_name,
                $user->avatar_url,
                false
            ));
        }

        if ($offlineUsers->isNotEmpty()) {
            $this->info("Dispatched offline events for {$offlineUsers->count()} users.");
            Log::channel('daily')->info("SyncUserOnlineStatus: dispatched offline for {$offlineUsers->count()} users.");
        }

        return 0;
    }
}
