<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\User;
use Illuminate\Console\Command;

class AutoCloseMiniTournaments extends Command
{
    protected $signature = 'mini-tournaments:auto-close';

    protected $description = 'Tự động đóng mini-tournament khi end_time < now(), cập nhật stats cho participants';

    public function handle(): int
    {
        // Eager-load participant + user để tránh N+1 trong vòng foreach
        $miniTournaments = MiniTournament::query()
            ->where('status', '!=', MiniTournament::STATUS_CLOSED)
            ->whereNotNull('end_time')
            ->where('end_time', '<', now())
            ->with(['participants' => function ($q) {
                $q->whereNotNull('user_id')
                  ->where('is_guest', false);
            }])
            ->get();

        if ($miniTournaments->isEmpty()) {
            $this->info('Khong co mini-tournament nao can dong.');
            return 0;
        }

        $closedCount = 0;

        foreach ($miniTournaments as $miniTournament) {
            $this->closeMiniTournament($miniTournament);
            $closedCount++;
            $this->info("Da dong mini-tournament #{$miniTournament->id} '{$miniTournament->name}'.");
        }

        $this->info("Da dong {$closedCount} mini-tournament.");
        return 0;
    }

    protected function closeMiniTournament(MiniTournament $miniTournament): void
    {
        $sportId = $miniTournament->sport_id;

        // Collect user IDs để batch query ratings/rankings 1 lần thay vì N lần User::find()
        $userIds = $miniTournament->participants
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($userIds)) {
            $miniTournament->status = MiniTournament::STATUS_CLOSED;
            $miniTournament->saveQuietly();
            return;
        }

        // Batch load users để tránh N+1
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        foreach ($miniTournament->participants as $participant) {
            if (!$participant->user_id || $participant->is_guest) {
                continue;
            }

            $user = $users->get($participant->user_id);
            if (!$user) {
                continue;
            }

            $currentRating = $user->vnduprScoresBySport($sportId)->max('score_value');
            $currentRank = $user->getVNRank($sportId);

            $participant->rating_before = $currentRating;
            $participant->rating_after = $currentRating;
            $participant->rank_before = $currentRank;
            $participant->rank_after = $currentRank;
            $participant->rank_change = null;
            $participant->saveQuietly();
        }

        $miniTournament->status = MiniTournament::STATUS_CLOSED;
        $miniTournament->saveQuietly();
    }
}
