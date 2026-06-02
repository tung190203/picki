<?php

namespace App\Services;

use App\Jobs\SendPushJob;
use App\Exceptions\BusinessException;
use App\Models\Club\Club;
use App\Notifications\PromotionNotification;
use App\Models\Club\ClubActivity;
use App\Models\Follow;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\User;
use Illuminate\Support\Collection;

class PromotionService
{
    public const PROMOTABLE_CLUB_ACTIVITY = 'club_activity';
    public const PROMOTABLE_CLUB = 'club';
    public const PROMOTABLE_MINI_TOURNAMENT = 'mini_tournament';

    public const PROMOTABLE_TYPES = [
        self::PROMOTABLE_CLUB_ACTIVITY,
        self::PROMOTABLE_CLUB,
        self::PROMOTABLE_MINI_TOURNAMENT,
    ];

    public const RECIPIENT_LIMIT = 50;

    public const DEFAULT_RADIUS_KM = 50;

    public function getRecipients(int $userId, string $promotableType, int $promotableId, int $limit = self::RECIPIENT_LIMIT): Collection
    {
        $this->validatePromotable($promotableType, $promotableId);

        [$lat, $lng] = $this->getLocationFromPromotable($promotableType, $promotableId);
        $excludeIds = $this->getExcludedUserIds($userId, $promotableType, $promotableId);
        $excludeIds[] = $userId;

        $friendIds = $this->getFriendIds($userId)->diff($excludeIds)->values();

        $hasLocation = ($lat != 0 || $lng != 0);

        if (!$hasLocation) {
            return $this->getRecipientsWithoutLocation($userId, $excludeIds, $friendIds, $limit);
        }

        $haversine = "(6371 * acos(
            cos(radians(?)) * cos(radians(users.latitude))
            * cos(radians(users.longitude) - radians(?))
            + sin(radians(?)) * sin(radians(users.latitude))
        ))";
        $bindings = [$lat, $lng, $lat];

        $baseQuery = User::query()
            ->where('id', '!=', $userId)
            ->whereNotNull('users.latitude')
            ->whereNotNull('users.longitude')
            ->whereNotIn('id', $excludeIds);

        $friendsQuery = (clone $baseQuery)
            ->whereIn('id', $friendIds)
            ->whereRaw("$haversine <= ?", array_merge($bindings, [self::DEFAULT_RADIUS_KM]))
            ->orderByRaw($haversine . ' asc', $bindings)
            ->limit($limit);

        $friendUsers = $friendsQuery->get();
        $friendUserIds = $friendUsers->pluck('id')->toArray();
        $remaining = $limit - count($friendUserIds);

        if ($remaining <= 0) {
            return $friendUsers;
        }

        $publicQuery = (clone $baseQuery)
            ->where('visibility', User::VISIBILITY_PUBLIC)
            ->whereNotIn('id', $friendUserIds)
            ->whereRaw("$haversine <= ?", array_merge($bindings, [self::DEFAULT_RADIUS_KM]))
            ->orderByRaw($haversine . ' asc', $bindings)
            ->limit($remaining);

        $publicUsers = $publicQuery->get();

        return $friendUsers->merge($publicUsers)->values();
    }

    protected function getRecipientsWithoutLocation(int $userId, array $excludeIds, $friendIds, int $limit): Collection
    {
        $publicUserIds = User::query()
            ->where('id', '!=', $userId)
            ->where('visibility', User::VISIBILITY_PUBLIC)
            ->whereNotIn('id', $excludeIds)
            ->whereNotIn('id', $friendIds->toArray())
            ->pluck('id');

        $recipientIds = $friendIds->merge($publicUserIds)->take($limit)->values();

        return User::whereIn('id', $recipientIds)->get();
    }


    protected function getLocationFromPromotable(string $promotableType, int $promotableId): array
    {
        switch ($promotableType) {
            case self::PROMOTABLE_CLUB_ACTIVITY:
                $activity = ClubActivity::with('club')->find($promotableId);
                $lat = $activity->latitude ?? $activity->club?->latitude;
                $lng = $activity->longitude ?? $activity->club?->longitude;
                break;
            case self::PROMOTABLE_CLUB:
                $club = Club::find($promotableId);
                $lat = $club->latitude;
                $lng = $club->longitude;
                break;
            case self::PROMOTABLE_MINI_TOURNAMENT:
                $mini = MiniTournament::with('competitionLocation')->find($promotableId);
                $loc = $mini->competitionLocation;
                $lat = $loc?->latitude;
                $lng = $loc?->longitude;
                break;
            default:
                $lat = 0;
                $lng = 0;
        }

        return [
            $lat !== null ? (float) $lat : 0,
            $lng !== null ? (float) $lng : 0,
        ];
    }

    public function sendPromotion(int $promoterId, string $promotableType, int $promotableId, ?array $recipientIds = null): array
    {
        $this->validatePromotable($promotableType, $promotableId);
        $this->authorizePromotion($promoterId, $promotableType, $promotableId);

        if ($recipientIds === null) {
            $recipients = $this->getRecipients($promoterId, $promotableType, $promotableId);
            $recipientIds = $recipients->pluck('id')->toArray();
        } else {
            $recipients = User::whereIn('id', $recipientIds)->get();
        }

        $promoter = User::find($promoterId);
        $promoterName = $promoter ? ($promoter->full_name ?: $promoter->email) : 'Một người dùng';

        [$title, $message, $data] = $this->buildNotificationPayload($promotableType, $promotableId, $promoterName);

        foreach ($recipients as $user) {
            $user->notify(new PromotionNotification($title, $message, $data));
            SendPushJob::dispatch($user->id, $title, $message, $data);
        }

        return [
            'sent_count' => count($recipientIds),
            'recipients' => $recipients->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->full_name,
                'avatar_url' => $u->avatar_url,
            ])->toArray(),
        ];
    }

    protected function getFriendIds(int $userId): Collection
    {
        $followingIds = Follow::where('user_id', $userId)
            ->where('followable_type', User::class)
            ->pluck('followable_id');

        $followerIds = Follow::where('followable_type', User::class)
            ->where('followable_id', $userId)
            ->pluck('user_id');

        return $followingIds->intersect($followerIds)->values();
    }

    protected function getExcludedUserIds(int $userId, string $promotableType, int $promotableId): array
    {
        $exclude = [$userId];

        switch ($promotableType) {
            case self::PROMOTABLE_CLUB_ACTIVITY:
                $activity = ClubActivity::with('club', 'participants')->find($promotableId);
                if (!$activity) {
                    return $exclude;
                }
                $exclude[] = $activity->created_by;
                $exclude = array_merge(
                    $exclude,
                    $activity->club->members()->pluck('user_id')->toArray()
                );
                $exclude = array_merge(
                    $exclude,
                    $activity->participants()->pluck('user_id')->toArray()
                );
                break;

            case self::PROMOTABLE_CLUB:
                $club = Club::find($promotableId);
                if (!$club) {
                    return $exclude;
                }
                $exclude[] = $club->created_by;
                $exclude = array_merge(
                    $exclude,
                    $club->members()->pluck('user_id')->toArray()
                );
                break;

            case self::PROMOTABLE_MINI_TOURNAMENT:
                $miniTournament = MiniTournament::find($promotableId);
                if (!$miniTournament) {
                    return $exclude;
                }
                $exclude = array_merge(
                    $exclude,
                    MiniParticipant::where('mini_tournament_id', $promotableId)->pluck('user_id')->toArray()
                );
                break;
        }

        return array_unique(array_filter($exclude));
    }

    protected function validatePromotable(string $promotableType, int $promotableId): void
    {
        if (!in_array($promotableType, self::PROMOTABLE_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid promotable_type: {$promotableType}");
        }

        switch ($promotableType) {
            case self::PROMOTABLE_CLUB_ACTIVITY:
                ClubActivity::findOrFail($promotableId);
                break;
            case self::PROMOTABLE_CLUB:
                Club::findOrFail($promotableId);
                break;
            case self::PROMOTABLE_MINI_TOURNAMENT:
                MiniTournament::findOrFail($promotableId);
                break;
        }
    }

    protected function authorizePromotion(int $userId, string $promotableType, int $promotableId): void
    {
        switch ($promotableType) {
            case self::PROMOTABLE_CLUB_ACTIVITY:
                $activity = ClubActivity::with('club')->findOrFail($promotableId);
                if (!$activity->club->isMember($userId) && $activity->created_by !== $userId) {
                    throw new BusinessException('Chỉ thành viên CLB hoặc người tạo sự kiện mới có quyền quảng bá');
                }
                break;

            case self::PROMOTABLE_CLUB:
                $club = Club::findOrFail($promotableId);
                if (!$club->isMember($userId)) {
                    throw new BusinessException('Chỉ thành viên CLB mới có quyền quảng bá');
                }
                break;

            case self::PROMOTABLE_MINI_TOURNAMENT:
                $miniTournament = MiniTournament::with('staff')->findOrFail($promotableId);
                if (!$miniTournament->hasOrganizer($userId)) {
                    throw new BusinessException('Chỉ organizer mới có quyền quảng bá kèo đấu');
                }
                break;
        }
    }

    protected function buildNotificationPayload(string $promotableType, int $promotableId, string $promoterName): array
    {
        switch ($promotableType) {
            case self::PROMOTABLE_CLUB_ACTIVITY:
                $activity = ClubActivity::with('club')->findOrFail($promotableId);
                $title = 'Lời mời tham gia sự kiện';
                $message = "{$promoterName} mời bạn tham gia sự kiện {$activity->title} tại CLB {$activity->club->name}";
                $data = [
                    'type' => 'CLUB_ACTIVITY_PROMOTION',
                    'club_id' => (string) $activity->club_id,
                    'club_activity_id' => (string) $activity->id,
                ];
                return [$title, $message, $data];

            case self::PROMOTABLE_CLUB:
                $club = Club::findOrFail($promotableId);
                $title = 'Lời mời tham gia CLB';
                $message = "{$promoterName} mời bạn tham gia CLB {$club->name}";
                $data = [
                    'type' => 'CLUB_PROMOTION',
                    'club_id' => (string) $club->id,
                ];
                return [$title, $message, $data];

            case self::PROMOTABLE_MINI_TOURNAMENT:
                $miniTournament = MiniTournament::findOrFail($promotableId);
                $title = 'Lời mời tham gia kèo đấu';
                $message = "{$promoterName} mời bạn tham gia kèo đấu {$miniTournament->name}";
                $data = [
                    'type' => 'MINI_TOURNAMENT_PROMOTION',
                    'mini_tournament_id' => (string) $miniTournament->id,
                ];
                return [$title, $message, $data];

            default:
                throw new \InvalidArgumentException("Unknown promotable_type: {$promotableType}");
        }
    }
}
