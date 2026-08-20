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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendMiniTournamentDraftRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum reminders per (tournament, organizer) pair across the lifetime
     * of the draft. Prevents indefinite notification loops for forgotten
     * drafts that organizers have not published or deleted.
     */
    private const MAX_REMINDERS_PER_DRAFT = 3;

    public function handle(): void
    {
        $now = Carbon::now();
        $cutoffTime = $now->copy()->subHours(24);

        // SoftDeletes global scope already filters deleted rows.
        $tournaments = MiniTournament::where('status', MiniTournament::STATUS_DRAFT)
            ->where('created_at', '<=', $cutoffTime)
            ->get();

        foreach ($tournaments as $tournament) {
            // Re-check status inside the loop: tournament may have been
            // published (status -> OPEN) or deleted between the query above
            // and processing this row.
            $tournament->refresh();
            if ($tournament->trashed()
                || $tournament->status !== MiniTournament::STATUS_DRAFT
                || $tournament->created_at->gt($cutoffTime)) {
                continue;
            }

            $staffMembers = MiniTournamentStaff::where('mini_tournament_id', $tournament->id)
                ->where('role', MiniTournamentStaff::ROLE_ORGANIZER)
                ->with('user')
                ->get();

            foreach ($staffMembers as $staff) {
                $user = $staff->user;
                if (!$user) {
                    // Organizer account was deleted; nothing to notify.
                    continue;
                }

                $recentReminder = MiniTournamentDraftReminder::where('mini_tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->where('sent_at', '>=', $cutoffTime)
                    ->exists();

                if ($recentReminder) {
                    continue;
                }

                $totalSent = MiniTournamentDraftReminder::where('mini_tournament_id', $tournament->id)
                    ->where('user_id', $user->id)
                    ->count();

                if ($totalSent >= self::MAX_REMINDERS_PER_DRAFT) {
                    continue;
                }

                // Record FIRST, then notify. If notify fails we have already
                // persisted the attempt; subsequent runs will see the record
                // and skip instead of re-firing the notification.
                try {
                    DB::transaction(function () use ($tournament, $user) {
                        MiniTournamentDraftReminder::create([
                            'mini_tournament_id' => $tournament->id,
                            'user_id' => $user->id,
                            'sent_at' => now(),
                        ]);
                    });
                } catch (\Throwable $e) {
                    // Unique constraint or other DB error: another worker
                    // already recorded the reminder. Safe to skip.
                    Log::warning('MiniTournamentDraftReminder record failed', [
                        'mini_tournament_id' => $tournament->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                $user->notify(new MiniTournamentDraftReminderNotification($tournament));
            }
        }
    }
}
