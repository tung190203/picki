<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\BadgeGrantedNotification;
use App\Notifications\BadgeRevokedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BadgeService
{
    /**
     * Ngữ cảnh tạm thời khi chạy qua BadgeAchievementSubscriber hoặc command
     * để ghi log truy vết tự động (event, rule, match_id, mini_match_id, tournament_id...).
     */
    protected static array $activeTriggerContext = [];

    public static function setTriggerContext(array $context): void
    {
        self::$activeTriggerContext = $context;
    }

    public static function clearTriggerContext(): void
    {
        self::$activeTriggerContext = [];
    }
    /**
     * 4 loại huy hiệu được phép hiển thị bên ngoài BXH / primary_badge.
     * Thứ tự ưu tiên mặc định khi không ghim: PICKI > CHAMPION > ANCHOR > VERIFIED.
     */
    public const LEADERBOARD_BADGE_CODES = ['PICKI', 'CHAMPION', 'ANCHOR', 'VERIFIED'];

    public const LEADERBOARD_BADGE_PRIORITY = [
        'PICKI' => 4,
        'CHAMPION' => 3,
        'ANCHOR' => 2,
        'VERIFIED' => 1,
    ];

    /**
     * Sắp xếp danh sách huy hiệu đã ghim (is_featured = true) theo đúng thứ tự vị trí người dùng đã chọn.
     */
    public function sortFeaturedUserBadges(iterable $userBadges): \Illuminate\Support\Collection
    {
        return collect($userBadges)
            ->filter(fn ($ub) => $ub?->badge && (bool) $ub->is_featured)
            ->sort(function ($a, $b) {
                $tA = $a->updated_at ? strtotime((string) $a->updated_at) : 0;
                $tB = $b->updated_at ? strtotime((string) $b->updated_at) : 0;
                if ($tA !== $tB) {
                    return $tA <=> $tB;
                }
                return ((int) ($a->badge_id ?? $a->id ?? 0)) <=> ((int) ($b->badge_id ?? $b->id ?? 0));
            })
            ->values();
    }

    /**
     * Xác định primary_badge từ danh sách UserBadge của người chơi:
     * - Chỉ xét trong 4 loại huy hiệu được phép hiển thị ra ngoài (PICKI, CHAMPION, ANCHOR, VERIFIED).
     * - Nếu người chơi có ghim (is_featured = true) huy hiệu thuộc 1 trong 4 loại này -> lấy theo đúng thứ tự vị trí ghim từ đầu đến cuối.
     * - Nếu không ghim (hoặc chỉ ghim huy hiệu thành tựu khác trong profile) -> tự động lấy 1 trong 4 huy hiệu này nếu người chơi sở hữu.
     * - Nếu không sở hữu bất kỳ huy hiệu nào trong 4 loại trên -> trả về null.
     */
    public function resolvePrimaryBadge(iterable $userBadges): ?array
    {
        $collection = collect($userBadges)->filter(fn ($ub) => (bool) $ub?->badge);
        if ($collection->isEmpty()) {
            return null;
        }

        // 1. Ưu tiên theo đúng thứ tự ghim của người dùng (ô 1 -> ô 2 -> ô 3)
        $featuredAllowed = $this->sortFeaturedUserBadges($collection)
            ->first(function ($ub) {
                $code = strtoupper((string) ($ub->badge->code ?? ''));
                return in_array($code, self::LEADERBOARD_BADGE_CODES, true);
            });

        if ($featuredAllowed) {
            return [
                'name' => $featuredAllowed->badge->name,
                'type' => $featuredAllowed->badge->code,
                'icon_url' => $featuredAllowed->badge->icon_url,
            ];
        }

        // 2. Nếu không ghim huy hiệu nào thuộc 4 loại trên -> tự động lấy 1 trong 4 huy hiệu đang sở hữu
        $ownedAllowed = $collection
            ->filter(function ($ub) {
                $code = strtoupper((string) ($ub->badge->code ?? ''));
                return in_array($code, self::LEADERBOARD_BADGE_CODES, true);
            })
            ->sort(function ($a, $b) {
                $pA = (int) ($a->badge->priority ?? 0);
                $pB = (int) ($b->badge->priority ?? 0);
                if ($pA !== $pB) {
                    return $pB <=> $pA;
                }
                $orderA = self::LEADERBOARD_BADGE_PRIORITY[strtoupper((string) $a->badge->code)] ?? 0;
                $orderB = self::LEADERBOARD_BADGE_PRIORITY[strtoupper((string) $b->badge->code)] ?? 0;
                return $orderB <=> $orderA;
            })
            ->first();

        if (!$ownedAllowed) {
            return null;
        }

        return [
            'name' => $ownedAllowed->badge->name,
            'type' => $ownedAllowed->badge->code,
            'icon_url' => $ownedAllowed->badge->icon_url,
        ];
    }

    public function getUserBadges(int $userId): array
    {
        $userBadges = UserBadge::with('badge')
            ->where('user_id', $userId)
            ->get()
            ->sortByDesc(fn($ub) => $ub->badge->priority ?? 0);

        $badges = $userBadges->map(function ($ub) {
            if (!$ub->badge) return null;
            return [
                'name' => $ub->badge->name,
                'type' => $ub->badge->code,
                'icon_url' => $ub->badge->icon_url,
            ];
        })->filter()->values()->toArray();

        $featuredBadges = $this->sortFeaturedUserBadges($userBadges)->map(function ($ub) {
            return [
                'name' => $ub->badge->name,
                'type' => $ub->badge->code,
                'icon_url' => $ub->badge->icon_url,
            ];
        })->values()->toArray();

        $primaryBadge = $this->resolvePrimaryBadge($userBadges);

        return [
            'badges' => $badges,
            'featured_badges' => $featuredBadges,
            'primary_badge' => $primaryBadge,
        ];
    }

    public function get_badges(int $userId): array
    {
        return $this->getUserBadges($userId);
    }

    public function getPrimaryBadge(int $userId): ?array
    {
        $userBadges = UserBadge::with('badge')
            ->where('user_id', $userId)
            ->get();

        return $this->resolvePrimaryBadge($userBadges);
    }

    public function get_primary_badge(int $userId): ?array
    {
        return $this->getPrimaryBadge($userId);
    }

    public function getBatchPrimaryBadges(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userBadges = UserBadge::with('badge')
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $result = [];
        foreach ($userIds as $userId) {
            $badges = $userBadges->get($userId, collect());
            $result[$userId] = $this->resolvePrimaryBadge($badges);
        }

        return $result;
    }

    public function getBatchUserBadges(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $userBadges = UserBadge::with('badge')
            ->whereIn('user_id', $userIds)
            ->get()
            ->groupBy('user_id');

        $result = [];
        foreach ($userIds as $userId) {
            $badges = $userBadges->get($userId, collect())->sortByDesc(fn($ub) => $ub->badge->priority ?? 0);
            $badgesArray = $badges->map(function ($ub) {
                if (!$ub->badge) return null;
                return [
                    'name' => $ub->badge->name,
                    'type' => $ub->badge->code,
                    'icon_url' => $ub->badge->icon_url,
                ];
            })->filter()->values()->toArray();

            $featuredBadges = $this->sortFeaturedUserBadges($badges)->map(function ($ub) {
                return [
                    'name' => $ub->badge->name,
                    'type' => $ub->badge->code,
                    'icon_url' => $ub->badge->icon_url,
                ];
            })->values()->toArray();

            $result[$userId] = [
                'badges' => $badgesArray,
                'featured_badges' => $featuredBadges,
                'primary_badge' => $this->resolvePrimaryBadge($badges),
            ];
        }

        return $result;
    }

    public function has_any_badge(int $userId): bool
    {
        return UserBadge::where('user_id', $userId)->exists();
    }

    public function has_badge(int $userId, string $code): bool
    {
        return UserBadge::where('user_id', $userId)
            ->whereHas('badge', fn($q) => $q->where('code', $code))
            ->exists();
    }

    public function hasBadge(int $userId, string $code): bool
    {
        return $this->has_badge($userId, $code);
    }

    private function _create_badge(int $userId, string $code, ?int $createdBy = null, array $context = []): ?UserBadge
    {
        $badge = Badge::where('code', $code)->first();
        if (!$badge) {
            return null;
        }

        $existingBadge = UserBadge::where('user_id', $userId)
            ->where('badge_id', $badge->id)
            ->first();

        if ($existingBadge) {
            return null;
        }

        $userBadge = UserBadge::create([
            'user_id' => $userId,
            'badge_id' => $badge->id,
            'created_by' => $createdBy,
            'acquired_at' => now(),
        ]);

        if ($code === 'ANCHOR') {
            User::where('id', $userId)->update(['is_anchor' => true]);
        }

        $user = User::find($userId);
        if ($user) {
            $user->notify(new BadgeGrantedNotification($code, $createdBy));
        }

        $this->writeBadgeAuditLog(
            action: 'AWARDED',
            userId: $userId,
            userName: $user?->full_name,
            badge: $badge,
            userBadgeId: $userBadge->id,
            createdBy: $createdBy,
            context: $context
        );

        return $userBadge;
    }

    public function awardBadge(int $userId, string $code, ?int $createdBy = null, array $context = []): ?UserBadge
    {
        return $this->_create_badge($userId, $code, $createdBy, $context);
    }

    public function revokeBadge(int $userId, string $code, array $context = []): bool
    {
        $badge = Badge::where('code', $code)->first();
        if (!$badge) {
            return false;
        }

        $user = User::find($userId);

        $deleted = UserBadge::where('user_id', $userId)
            ->where('badge_id', $badge->id)
            ->delete() > 0;

        if ($deleted) {
            if ($code === 'ANCHOR') {
                User::where('id', $userId)->update(['is_anchor' => false]);
            }
            if ($user) {
                $user->notify(new BadgeRevokedNotification($code));
            }

            $this->writeBadgeAuditLog(
                action: 'REVOKED',
                userId: $userId,
                userName: $user?->full_name,
                badge: $badge,
                userBadgeId: null,
                createdBy: auth()->id(),
                context: $context
            );
        }

        return $deleted;
    }

    /**
     * Ghi log truy vết cấp/thu hồi huy hiệu vào file riêng: storage/logs/badges.log
     */
    public function writeBadgeAuditLog(
        string $action,
        int $userId,
        ?string $userName,
        Badge $badge,
        ?int $userBadgeId = null,
        ?int $createdBy = null,
        array $context = []
    ): void {
        try {
            $caller = $this->detectCallerSource();
            $mergedContext = array_merge(self::$activeTriggerContext, $context);

            $logPayload = array_filter([
                'action' => $action,
                'user_badge_id' => $userBadgeId,
                'user_id' => $userId,
                'user_name' => $userName ?? 'N/A',
                'badge_id' => $badge->id,
                'badge_code' => $badge->code,
                'badge_name' => $badge->name,
                'created_by' => $createdBy,
                'trigger_source' => $mergedContext['rule'] ?? $caller,
                'caller' => $caller,
            ], fn ($v) => $v !== null) + $mergedContext;

            $summary = sprintf(
                '[BADGE_%s] badge=%s user_id=%d (%s) source=%s',
                $action,
                $badge->code,
                $userId,
                $userName ?? 'N/A',
                $logPayload['trigger_source'] ?? 'unknown'
            );

            Log::channel('badges')->info($summary, $logPayload);
        } catch (\Throwable $e) {
            // Không làm gián đoạn luồng chính nếu ghi file log gặp lỗi quyền ghi
        }
    }

    private function detectCallerSource(): string
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        $skipClasses = [self::class, AwardBadgeService::class];

        foreach ($frames as $frame) {
            $class = $frame['class'] ?? null;
            if ($class && in_array($class, $skipClasses, true)) {
                continue;
            }
            if ($class) {
                return $class . '::' . ($frame['function'] ?? 'unknown');
            }
        }

        return 'unknown';
    }

    public function syncFromLegacyFields(User $user): void
    {
        DB::transaction(function () use ($user) {
            if ($user->getRawOriginal('is_verified')) {
                $this->awardBadge($user->id, 'VERIFIED', $user->id);
            }
            if ($user->getRawOriginal('is_anchor')) {
                $this->awardBadge($user->id, 'ANCHOR', $user->id);
            }
        });
    }

    public function syncAllFromLegacyFields(): int
    {
        $count = 0;

        User::where('is_verified', true)
            ->orWhere('is_anchor', true)
            ->chunk(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $this->syncFromLegacyFields($user);
                    $count++;
                }
            });

        return $count;
    }
}
