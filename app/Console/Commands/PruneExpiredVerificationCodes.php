<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredVerificationCodes extends Command
{
    protected $signature = 'verification-codes:prune-expired';

    protected $description = 'Purge expired OTP verification codes from database';

    public function handle(): int
    {
        $now = now();
        $this->info("Purging expired verification codes (expires_at < {$now})...");

        $deleted = DB::table('verification_codes')
            ->where('expires_at', '<', $now)
            ->delete();

        $this->info("Successfully pruned {$deleted} expired verification code(s).");
        return Command::SUCCESS;
    }
}
