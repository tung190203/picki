<?php

namespace App\Services\Tournament;

use App\Jobs\SendPushJob;
use App\Models\Matches;
use App\Models\Tournament;
use App\Models\TournamentCourt;
use App\Models\User;

class TournamentCourtNotificationService
{
    public function notifyCourtAssigned(TournamentCourt $court, Tournament $tournament, int $newRefereeId, int $actorId): void
    {
        if ($newRefereeId === $actorId) {
            return;
        }

        $this->dispatchPush(
            $newRefereeId,
            'Phân công sân',
            "Bạn phụ trách {$court->name} – {$tournament->name}",
            [
                'type' => 'referee_queue_updated',
                'tournament_id' => (string) $tournament->id,
                'court_id' => (string) $court->id,
            ]
        );
    }

    public function notifyCourtUnassigned(TournamentCourt $court, Tournament $tournament, int $oldRefereeId, int $actorId): void
    {
        if ($oldRefereeId === $actorId) {
            return;
        }

        $this->dispatchPush(
            $oldRefereeId,
            'Thay đổi phân công sân',
            "Bạn không còn phụ trách {$court->name} – {$tournament->name}",
            [
                'type' => 'referee_queue_updated',
                'tournament_id' => (string) $tournament->id,
                'court_id' => (string) $court->id,
            ]
        );
    }

    public function notifyMatchMoved(Matches $match, ?TournamentCourt $oldCourt, ?TournamentCourt $newCourt, int $actorId): void
    {
        $teamA = $match->homeTeam?->name ?? 'Đội A';
        $teamB = $match->awayTeam?->name ?? 'Đội B';
        $oldName = $oldCourt?->name ?? 'Chưa xếp sân';
        $newName = $newCourt?->name ?? 'Chưa xếp sân';

        $recipients = collect([
            $oldCourt?->referee_user_id,
            $newCourt?->referee_user_id,
        ])
            ->filter()
            ->unique()
            ->reject(fn (int $id) => $id === $actorId);

        foreach ($recipients as $userId) {
            $this->dispatchPush(
                (int) $userId,
                'Chuyển sân thi đấu',
                "{$teamA} vs {$teamB} chuyển từ {$oldName} sang {$newName}",
                [
                    'type' => 'referee_queue_updated',
                    'match_id' => (string) $match->id,
                    'court_id' => (string) ($newCourt?->id ?? ''),
                ]
            );
        }
    }

    public function notifyMatchMovedToTop(Matches $match, TournamentCourt $court, int $actorId): void
    {
        $refereeId = $court->referee_user_id;
        if (!$refereeId || (int) $refereeId === $actorId) {
            return;
        }

        $teamA = $match->homeTeam?->name ?? 'Đội A';
        $teamB = $match->awayTeam?->name ?? 'Đội B';

        $this->dispatchPush(
            (int) $refereeId,
            'Ưu tiên trận đấu',
            "Trận {$teamA} vs {$teamB} được đưa lên đánh tiếp theo",
            [
                'type' => 'referee_queue_updated',
                'match_id' => (string) $match->id,
                'court_id' => (string) $court->id,
            ]
        );
    }

    public function notifyMatchReassigned(Matches $match, ?int $oldRefereeId, int $newRefereeId, int $actorId): void
    {
        $teamA = $match->homeTeam?->name ?? 'Đội A';
        $teamB = $match->awayTeam?->name ?? 'Đội B';
        $newRefereeName = User::find($newRefereeId)?->full_name ?? 'Trọng tài mới';

        $recipients = collect([$oldRefereeId, $newRefereeId])
            ->filter()
            ->unique()
            ->reject(fn (int $id) => (int) $id === $actorId);

        foreach ($recipients as $userId) {
            $this->dispatchPush(
                (int) $userId,
                'Đổi trọng tài trận đấu',
                "Trận {$teamA} vs {$teamB} đã giao cho {$newRefereeName}",
                [
                    'type' => 'referee_match_reassigned',
                    'match_id' => (string) $match->id,
                    'new_referee_user_id' => (string) $newRefereeId,
                ]
            );
        }
    }

    public function notifyQueueChanged(array $refereeUserIds, int $tournamentId, int $actorId): void
    {
        $recipients = collect($refereeUserIds)
            ->filter()
            ->unique()
            ->reject(fn (int $id) => (int) $id === $actorId);

        foreach ($recipients as $userId) {
            $this->dispatchPush(
                (int) $userId,
                'Cập nhật lịch sân',
                'Hàng chờ trận đấu trên sân của bạn vừa được cập nhật',
                [
                    'type' => 'referee_queue_updated',
                    'tournament_id' => (string) $tournamentId,
                ]
            );
        }
    }

    private function dispatchPush(int $userId, string $title, string $body, array $data): void
    {
        try {
            SendPushJob::dispatch($userId, $title, $body, $data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('TournamentCourtNotificationService push failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
