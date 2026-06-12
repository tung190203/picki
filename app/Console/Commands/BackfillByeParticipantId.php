<?php

namespace App\Console\Commands;

use App\Models\MiniMatch;
use Illuminate\Console\Command;

class BackfillByeParticipantId extends Command
{
    protected $signature = 'mini-match:backfill-bye-participant';

    protected $description = 'Backfill bye_participant_id for existing bye matches from participant_win_id or participant1_id/participant2_id';

    public function handle(): int
    {
        $matches = MiniMatch::where('is_bye', true)
            ->whereNull('bye_participant_id')
            ->get();

        if ($matches->isEmpty()) {
            $this->info('No bye matches need backfilling.');
            return Command::SUCCESS;
        }

        $this->info("Found {$matches->count()} bye matches to backfill.");

        foreach ($matches as $match) {
            // Prefer participant_win_id (who automatically won the bye)
            // Fall back to whichever participant field is set
            $byeParticipantId = $match->participant_win_id
                ?? $match->participant1_id
                ?? $match->participant2_id;

            if ($byeParticipantId) {
                $match->update(['bye_participant_id' => $byeParticipantId]);
                $this->line("Match #{$match->id}: bye_participant_id = {$byeParticipantId}");
            } else {
                $this->warn("Match #{$match->id}: no participant found, skipping.");
            }
        }

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
