<?php

namespace App\Console\Commands;

use App\Models\Chat\Conversation;
use App\Models\MiniTournament;
use App\Models\Tournament;
use Illuminate\Console\Command;

class AutoArchiveInactiveChatCommand extends Command
{
    protected $signature = 'chat:auto-archive-inactive {--days=30 : Số ngày không có tin nhắn mới}';
    protected $description = 'Tự động chuyển các nhóm chat Kèo/Giải đã kết thúc và không hoạt động sang trạng thái Lưu trữ (Archive)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        $this->info("Bắt đầu kiểm tra các nhóm chat Kèo/Giải không có tin nhắn mới trong {$days} ngày qua...");

        $conversations = Conversation::whereIn('type', [
            Conversation::TYPE_MINI_TOURNAMENT,
            Conversation::TYPE_TOURNAMENT,
        ])
            ->where('is_archived', false)
            ->where(function ($q) use ($threshold) {
                $q->where('last_message_at', '<', $threshold)
                  ->orWhere(function ($sub) use ($threshold) {
                      $sub->whereNull('last_message_at')
                          ->where('created_at', '<', $threshold);
                  });
            })
            ->get();

        $archivedCount = 0;

        foreach ($conversations as $conversation) {
            $isFinished = false;

            if ($conversation->type === Conversation::TYPE_MINI_TOURNAMENT) {
                $mini = MiniTournament::find($conversation->target_id);
                if ($mini) {
                    $isFinished = in_array((int) $mini->status, [
                        MiniTournament::STATUS_CLOSED,
                        MiniTournament::STATUS_CANCELLED,
                    ], true) || ($mini->end_date && $mini->end_date < now());
                } else {
                    $isFinished = true; // Kèo không tồn tại nữa
                }
            } elseif ($conversation->type === Conversation::TYPE_TOURNAMENT) {
                $tour = Tournament::find($conversation->target_id);
                if ($tour) {
                    $isFinished = in_array((int) $tour->status, [
                        Tournament::CLOSED,
                        Tournament::CANCELLED,
                    ], true) || ($tour->end_date && $tour->end_date < now());
                } else {
                    $isFinished = true; // Giải không tồn tại nữa
                }
            }

            if ($isFinished) {
                $conversation->update([
                    'is_archived' => true,
                    'archived_at' => now(),
                ]);
                $archivedCount++;
            }
        }

        $this->info("Đã tự động lưu trữ {$archivedCount} cuộc trò chuyện Kèo/Giải cũ.");

        return Command::SUCCESS;
    }
}
