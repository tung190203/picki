<?php

namespace App\Console\Commands;

use App\Models\MiniParticipantPayment;
use App\Models\TournamentParticipantPayment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixMergedUserPaymentsCommand extends Command
{
    protected $signature = 'payments:fix-merged-users {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Fix payment records that reference merged (deleted) users by updating them to the survivor user';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in dry-run mode - no changes will be made');
        }

        // Find merged users with their survivor
        $mergedUsers = DB::table('users')
            ->where('is_merged', true)
            ->whereNotNull('merged_into_user_id')
            ->select('id as merged_user_id', 'merged_into_user_id')
            ->get();

        if ($mergedUsers->isEmpty()) {
            $this->info('No merged users found');
            return 0;
        }

        $this->info("Found {$mergedUsers->count()} merged users to process");

        $miniPaymentsUpdated = 0;
        $tournamentPaymentsUpdated = 0;

        foreach ($mergedUsers as $user) {
            // Fix MiniParticipantPayment
            $miniPayments = MiniParticipantPayment::where('user_id', $user->merged_user_id)->get();
            foreach ($miniPayments as $payment) {
                if ($dryRun) {
                    $this->line("  Would update MiniParticipantPayment #{$payment->id}: user_id {$payment->user_id} -> {$user->merged_into_user_id}");
                } else {
                    $payment->update(['user_id' => $user->merged_into_user_id]);
                }
                $miniPaymentsUpdated++;
            }

            // Fix TournamentParticipantPayment
            $tournamentPayments = TournamentParticipantPayment::where('user_id', $user->merged_user_id)->get();
            foreach ($tournamentPayments as $payment) {
                if ($dryRun) {
                    $this->line("  Would update TournamentParticipantPayment #{$payment->id}: user_id {$payment->user_id} -> {$user->merged_into_user_id}");
                } else {
                    $payment->update(['user_id' => $user->merged_into_user_id]);
                }
                $tournamentPaymentsUpdated++;
            }
        }

        if ($dryRun) {
            $this->info("Dry-run complete. Would update {$miniPaymentsUpdated} mini-tournament payments and {$tournamentPaymentsUpdated} tournament payments");
        } else {
            $this->info("Fixed {$miniPaymentsUpdated} mini-tournament payments and {$tournamentPaymentsUpdated} tournament payments");
        }

        return 0;
    }
}
