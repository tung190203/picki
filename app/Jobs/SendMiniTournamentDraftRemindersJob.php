<?php

namespace App\Jobs;

use App\Models\MiniTournament;
use App\Models\MiniTournamentDraftReminder;
use App\Models\MiniTournamentStaff;
use App\Notifications\MiniTournamentDraftReminderNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMiniTournamentDraftRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = Carbon::now();
        $cutoffTime = $now->copy()->subHours(24);

        // Find tournaments that are DRAFT and were created more than 24 hours ago
        $tournaments = MiniTournament::where('status', MiniTournament::STATUS_DRAFT)
            ->where('created_at', '<=', $cutoffTime)
            ->get();

        foreach ($tournaments as $tournament) {
            // Get organizers who haven't been reminded in the last 24 hours
            $staffMembers = MiniTournamentStaff::where('mini_tournament_id', $tournament->id)
                ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
                ->with('user')
                ->get();

            foreach ($staffMembers as $staff) {
                // Check if this organizer was already reminded in the last 24 hours
                $recentReminder = MiniTournamentDraftReminder::where('mini_tournament_id', $tournament->id)
                    ->where('user_id', $staff->user_id)
                    ->where('sent_at', '>=', $cutoffTime)
                    ->exists();

                if ($recentReminder) {
                    continue;
                }

                // Send notification to organizer
                $staff->user->notify(new MiniTournamentDraftReminderNotification($tournament));

                // Record the reminder
                MiniTournamentDraftReminder::create([
                    'mini_tournament_id' => $tournament->id,
                    'user_id' => $staff->user_id,
                    'sent_at' => now(),
                ]);
            }
        }
    }
}
