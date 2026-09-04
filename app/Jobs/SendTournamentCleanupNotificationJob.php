<?php

namespace App\Jobs;

use App\Enums\TournamentCleanupType;
use App\Models\User;
use App\Notifications\TournamentCleanupNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job riêng cho notification cleanup tournament, dispatch từ
 * CleanupEmptyTournaments command để giải phóng process ngay
 * (không block transaction xóa tournament).
 */
class SendTournamentCleanupNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 60;

    public function __construct(
        public ?int $creatorId,
        public TournamentCleanupType $type,
        public string $tournamentName,
        public ?int $clubId,
        public int $tournamentId,
        public string $reason = 'Không có người tham gia hợp lệ khi đã quá thời gian bắt đầu.',
    ) {}

    public function handle(): void
    {
        if (!$this->creatorId) {
            return;
        }

        $creator = User::find($this->creatorId);
        if (!$creator) {
            Log::warning('Cleanup notification skipped: creator not found', [
                'creator_id' => $this->creatorId,
                'tournament_id' => $this->tournamentId,
            ]);
            return;
        }

        $creator->notify(new TournamentCleanupNotification(
            tournamentType: $this->type,
            tournamentName: $this->tournamentName,
            reason: $this->reason,
            clubId: $this->clubId,
            tournamentId: $this->tournamentId,
        ));
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendTournamentCleanupNotificationJob failed', [
            'creator_id' => $this->creatorId,
            'tournament_id' => $this->tournamentId,
            'type' => $this->type->value,
            'error' => $e->getMessage(),
        ]);
    }
}
