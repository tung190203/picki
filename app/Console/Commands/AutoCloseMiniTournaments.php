<?php

namespace App\Console\Commands;

use App\Models\MiniTournament;
use App\Models\MiniParticipant;
use App\Models\User;
use Illuminate\Console\Command;

class AutoCloseMiniTournaments extends Command
{
    protected $signature = 'mini-tournaments:auto-close';

    protected $description = 'Tự động đóng mini-tournament khi end_time < now() và tất cả matches đã completed, cập nhật stats cho participants';

    public function handle(): int
    {
        // Lấy tất cả mini-tournaments có status != CLOSED và đã hết giờ
        $miniTournaments = MiniTournament::where('status', '!=', MiniTournament::STATUS_CLOSED)
            ->whereNotNull('end_time')
            ->where('end_time', '<', now())
            ->with(['matches', 'participants'])
            ->get();

        if ($miniTournaments->isEmpty()) {
            $this->info('Khong co mini-tournament nao can dong.');
            return 0;
        }

        $closedCount = 0;

        foreach ($miniTournaments as $miniTournament) {
            $totalMatches = $miniTournament->matches->count();
            $completedMatches = $miniTournament->matches->where('status', 'completed')->count();

            // Chỉ đóng nếu có match và tất cả matches đều completed
            if ($totalMatches > 0 && $totalMatches === $completedMatches) {
                $this->closeMiniTournament($miniTournament);
                $closedCount++;
                $this->info("Da dong mini-tournament #{$miniTournament->id} '{$miniTournament->name}'.");
            }
        }

        $this->info("Da dong {$closedCount} mini-tournament.");
        return 0;
    }

    protected function closeMiniTournament(MiniTournament $miniTournament): void
    {
        $miniTournament->status = MiniTournament::STATUS_CLOSED;
        $miniTournament->save();

        // Cập nhật stats cho từng participant
        foreach ($miniTournament->participants as $participant) {
            if (!$participant->user_id || $participant->is_guest) {
                continue;
            }

            $sportId = $miniTournament->sport_id;
            $userId = $participant->user_id;

            $user = User::find($userId);
            if (!$user) {
                continue;
            }

            // Lấy rating hiện tại trước khi cập nhật
            $currentRating = $user->vnduprScoresBySport($sportId)->max('score_value');
            $currentRank = $user->getVNRank($sportId);

            $participant->rating_before = $currentRating;
            $participant->rating_after = $currentRating;
            $participant->rank_before = $currentRank;
            $participant->rank_after = $currentRank;
            $participant->rank_change = null;
            $participant->save();
        }
    }
}
