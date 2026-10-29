<?php

namespace App\Services\Club;

use App\Enums\ClubMembershipStatus;
use App\Models\Club\Club;
use App\Models\Club\ClubMember;
use App\Models\User;
use App\Models\UserAchievement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * BXH + lịch sử thành tích của CLB, đọc thẳng từ bảng user_achievements.
 *
 * Bảng user_achievements được fill bởi UserAchievementRecorder khi event
 * (mini_tournament hoặc tournament) chuyển sang CLOSED — idempotent qua
 * unique key (user_id, club_id, event_type, event_id).
 *
 * Do đã áp dụng các rule (filter status, joined_at, multi-CLB split) lúc
 * ghi → read path chỉ cần WHERE + GROUP BY, không cần join bảng nặng.
 */
class ClubAchievementLeaderboardService
{
    /**
     * BXH sao/cúp của CLB.
     *
     * @return Collection<int, array{
     *     user_id: int, name: string|null, avatar_url: string|null,
     *     is_guest: bool,
     *     gold: int, silver: int, bronze: int, total_points: int,
     *     rank: int
     * }>
     */
    public function getLeaderboard(Club $club, string $subType = 'star', string $timeFrame = 'month'): Collection
    {
        $startDate = $this->getStartDateForTimeFrame($timeFrame);
        $isCup = $subType === 'cup';

        $query = UserAchievement::query()
            ->where('club_id', $club->id)
            ->where('is_cup', $isCup)
            ->whereNotNull('user_id');

        if ($startDate) {
            $query->where('event_date', '>=', $startDate);
        }

        $rows = $query
            ->selectRaw('user_id,
                SUM(CASE WHEN medal = ? THEN 1 ELSE 0 END) as gold,
                SUM(CASE WHEN medal = ? THEN 1 ELSE 0 END) as silver,
                SUM(CASE WHEN medal = ? THEN 1 ELSE 0 END) as bronze,
                SUM(points) as total_points,
                MAX(is_guest_snapshot) as is_guest', ['gold', 'silver', 'bronze'])
            ->groupBy('user_id')
            ->get();

        // BXH chỉ dành cho thành viên CLB — loại khách/vãng lai có thành tích
        // ở giải/kèo của CLB nhưng không thuộc club_members (joined).
        $memberSet = ClubMember::where('club_id', $club->id)
            ->where('membership_status', ClubMembershipStatus::Joined->value)
            ->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->flip()
            ->all();
        $rows = $rows->filter(fn ($row) => isset($memberSet[(int) $row->user_id]))->values();

        if ($rows->isEmpty()) {
            return collect();
        }

        $userIds = $rows->pluck('user_id')->all();
        $users = $userIds
            ? User::whereIn('id', $userIds)->get()->keyBy('id')
            : collect();
        $primaryBadges = $userIds
            ? app(\App\Services\BadgeService::class)->getBatchPrimaryBadges($userIds)
            : [];

        $scored = $rows->map(function ($row) use ($users, $primaryBadges) {
            $user = $users->get($row->user_id);
            return [
                'user_id' => (int) $row->user_id,
                'is_guest' => (bool) $row->is_guest,
                'name' => $user?->full_name ?? 'Khách',
                'avatar_url' => $user?->avatar_url,
                'primary_badge' => $primaryBadges[$row->user_id] ?? null,
                'gold' => (int) $row->gold,
                'silver' => (int) $row->silver,
                'bronze' => (int) $row->bronze,
                'total_points' => (int) $row->total_points,
            ];
        });

        // Sort: cup → gold DESC > silver DESC > bronze DESC (giữ rule cũ).
        //      star → total_points DESC > gold DESC > silver DESC > bronze DESC.
        $sorted = $scored->sort(function ($a, $b) use ($isCup) {
            if ($isCup) {
                if ($a['gold'] !== $b['gold']) return $b['gold'] <=> $a['gold'];
                if ($a['silver'] !== $b['silver']) return $b['silver'] <=> $a['silver'];
                return $b['bronze'] <=> $a['bronze'];
            }
            if ($a['total_points'] !== $b['total_points']) return $b['total_points'] <=> $a['total_points'];
            if ($a['gold'] !== $b['gold']) return $b['gold'] <=> $a['gold'];
            if ($a['silver'] !== $b['silver']) return $b['silver'] <=> $a['silver'];
            return $b['bronze'] <=> $a['bronze'];
        })->values();

        return $sorted->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
    }

    /**
     * Lịch sử thành tích của 1 user trong CLB.
     *
     * @return array{user: array, club: array, summary: array, events: Collection}
     */
    public function getMemberAchievements(Club $club, int $userId): array
    {
        $events = UserAchievement::query()
            ->where('user_id', $userId)
            ->where('club_id', $club->id)
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (UserAchievement $a) => [
                'event_id' => (int) $a->event_id,
                'event_type' => $a->event_type,
                'event_name' => $a->event_name,
                'event_date' => optional($a->event_date)->toIso8601String(),
                'event_status' => $a->event_status,
                'is_club_hosted' => (bool) $a->is_club_hosted,
                'rank' => (int) $a->rank,
                'medal' => $a->medal,
                'points' => (int) $a->points,
                'is_star' => (bool) $a->is_star,
                'is_cup' => (bool) $a->is_cup,
                'partner_names' => $a->partner_names ?? [],
            ])
            ->values();

        return [
            'user' => $this->summarizeUser($userId),
            'club' => $this->summarizeClub($club),
            'summary' => $this->summarizeEvents($events),
            'events' => $events,
        ];
    }

    private function getStartDateForTimeFrame(string $timeFrame): ?Carbon
    {
        $now = Carbon::now();
        return match ($timeFrame) {
            'month' => $now->copy()->startOfMonth(),
            'quarter' => $now->copy()->startOfQuarter(),
            'year' => $now->copy()->startOfYear(),
            default => null,
        };
    }

    private function summarizeUser(int $userId): array
    {
        $user = User::find($userId);
        return [
            'id' => $userId,
            'full_name' => $user?->full_name,
            'avatar_url' => $user?->avatar_url,
            'primary_badge' => $user ? app(\App\Services\BadgeService::class)->getPrimaryBadge($user->id) : null,
        ];
    }

    private function summarizeClub(Club $club): array
    {
        return [
            'id' => $club->id,
            'name' => $club->name,
        ];
    }

    private function summarizeEvents(Collection $events): array
    {
        $star = ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0];
        $cup = ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0];

        foreach ($events as $e) {
            $bucket = $e['is_cup'] ? 'cup' : 'star';
            ${$bucket}[$e['medal']]++;
            ${$bucket}['total_points'] += $e['points'];
        }

        return [
            'star' => $star,
            'cup' => $cup,
            'total_events' => $events->count(),
        ];
    }
}
