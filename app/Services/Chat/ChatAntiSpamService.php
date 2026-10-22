<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Cache;

class ChatAntiSpamService
{
    const MAX_STRANGER_MESSAGES_PER_DAY = 10;

    protected function getCacheKey(int $userId): string
    {
        return "chat_stranger_daily_count:{$userId}:" . now()->format('Y-m-d');
    }

    public function getTodaySentCount(int $userId): int
    {
        return (int) Cache::get($this->getCacheKey($userId), 0);
    }

    public function getRemainingStrangerMessages(int $userId): int
    {
        $sent = $this->getTodaySentCount($userId);
        return max(0, self::MAX_STRANGER_MESSAGES_PER_DAY - $sent);
    }

    public function canSendToStranger(int $userId): bool
    {
        return $this->getRemainingStrangerMessages($userId) > 0;
    }

    public function recordStrangerMessageSent(int $userId): int
    {
        $key = $this->getCacheKey($userId);
        $secondsUntilMidnight = now()->diffInSeconds(now()->endOfDay());

        if (!Cache::has($key)) {
            Cache::put($key, 1, $secondsUntilMidnight);
            return 1;
        }

        return (int) Cache::increment($key);
    }
}
