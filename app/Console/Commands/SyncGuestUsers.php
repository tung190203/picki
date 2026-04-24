<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncGuestUsers extends Command
{
    protected $signature = 'guest:sync-users';
    protected $description = 'Sync is_guest=true in users table from participants/mini_participants tables';

    public function handle(): int
    {
        $guestUserIds = DB::table('participants')
            ->where('is_guest', true)
            ->pluck('user_id')
            ->merge(
                DB::table('mini_participants')
                    ->where('is_guest', true)
                    ->pluck('user_id')
            )
            ->filter()
            ->unique()
            ->values();

        $total = $guestUserIds->count();
        $this->info("Found {$total} guest user IDs from participants tables.");

        if ($total === 0) {
            $this->info('No guests to sync.');
            return self::SUCCESS;
        }

        DB::table('users')
            ->whereIn('id', $guestUserIds)
            ->update(['is_guest' => true]);

        $this->info("Updated is_guest = true for {$total} users.");

        $nowGuests = DB::table('users')->where('is_guest', true)->count();
        $this->info("Total users with is_guest = true: {$nowGuests}");

        return self::SUCCESS;
    }
}
