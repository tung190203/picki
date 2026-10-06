<?php

namespace App\Services\Club;

use App\Enums\ClubMemberRole;
use App\Enums\ClubMembershipStatus;
use App\Enums\ClubMemberStatus;
use App\Exceptions\BusinessException;
use App\Models\Club\Club;
use App\Models\Club\ClubGuest;
use App\Models\Club\ClubMember;
use App\Models\MiniParticipant;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Collection;

class ClubGuestService
{
    /**
     * Trả guests chia 2 nhóm:
     * - normal: chưa chơi lại trong 1 tháng gần nhất
     * - potential: đã tham gia lại trong 1 tháng gần nhất
     *
     * Mỗi guest có user model kèm play_count + days_since_last_play.
     */
    public function getGuestsWithSegments(Club $club, \DateTimeInterface $oneMonthAgo): array
    {
        $memberUserIds = $club->activeMembers()->pluck('user_id')->toArray();
        $tournamentIds = $club->tournaments()->pluck('id');
        $miniIds = $club->miniTournaments()->pluck('id');

        $guestUserIds = ClubGuest::where('club_id', $club->id)
            ->whereNotIn('user_id', $memberUserIds)
            ->pluck('user_id');

        if ($guestUserIds->isEmpty()) {
            return ['normal' => collect(), 'potential' => collect()];
        }

        $guests = ClubGuest::where('club_id', $club->id)
            ->whereNotIn('user_id', $memberUserIds)
            ->with(['user' => function ($q) {
                $q->select(['id', 'full_name', 'avatar_url', 'email', 'gender'])
                    ->with('sports.sport');
            }])
            ->get()
            ->keyBy('user_id');

        // Lấy last participated + event count từ participants
        $tournamentStats = $this->participantStats(
            Participant::whereIn('tournament_id', $tournamentIds)
                ->whereIn('user_id', $guestUserIds)
                ->whereNotIn('user_id', $memberUserIds),
            'tournament_id'
        );
        $miniStats = $this->participantStats(
            MiniParticipant::whereIn('mini_tournament_id', $miniIds)
                ->whereIn('user_id', $guestUserIds)
                ->whereNotIn('user_id', $memberUserIds),
            'mini_tournament_id'
        );

        $enriched = collect();
        foreach ($guestUserIds as $userId) {
            $t = $tournamentStats->get($userId);
            $m = $miniStats->get($userId);

            $lastAt = $this->maxDate($t['last_at'] ?? null, $m['last_at'] ?? null);
            $eventCount = ($t['count'] ?? 0) + ($m['count'] ?? 0);

            $guest = $guests->get($userId);
            if (!$guest) continue;

            $guest->setAttribute('computed_last_participated_at', $lastAt);
            $guest->setAttribute('computed_event_count', $eventCount);
            $guest->setAttribute('days_since_last_play', $lastAt ? (int) now()->diffInDays($lastAt) : null);
            $enriched->push($guest);
        }

        // Chia 2 nhóm theo last participated
        $normal = $enriched->filter(function ($g) use ($oneMonthAgo) {
            $last = $g->computed_last_participated_at;
            return !$last || $last->lt($oneMonthAgo);
        })->values();
        $potential = $enriched->filter(function ($g) use ($oneMonthAgo) {
            $last = $g->computed_last_participated_at;
            return $last && $last->gte($oneMonthAgo);
        })->values();

        return ['normal' => $normal, 'potential' => $potential];
    }

    /**
     * Upsert club_guests khi tournament/mini-tournament kết thúc.
     * Cộng play_count cho user đã tồn tại, tạo mới cho user chưa có.
     */
    public function upsertFromEvent(int $clubId, Collection $participantUserIds): void
    {
        if ($participantUserIds->isEmpty() || !$clubId) {
            return;
        }

        $now = now();
        $existingUserIds = ClubGuest::where('club_id', $clubId)
            ->whereIn('user_id', $participantUserIds)
            ->pluck('user_id')
            ->all();

        // Increment cho user đã tồn tại
        if (!empty($existingUserIds)) {
            ClubGuest::where('club_id', $clubId)
                ->whereIn('user_id', $existingUserIds)
                ->increment('play_count');
            ClubGuest::where('club_id', $clubId)
                ->whereIn('user_id', $existingUserIds)
                ->update(['last_played_at' => $now, 'updated_at' => $now]);
        }

        // Tạo mới cho user chưa có
        $newUserIds = array_diff($participantUserIds->all(), $existingUserIds);
        $rows = [];
        foreach ($newUserIds as $userId) {
            $rows[] = [
                'club_id' => $clubId,
                'user_id' => $userId,
                'play_count' => 1,
                'first_played_at' => $now,
                'last_played_at' => $now,
                'is_invited' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (!empty($rows)) {
            ClubGuest::insert($rows);
        }
    }

    public function deleteGuest(Club $club, int $userId): void
    {
        ClubGuest::where('club_id', $club->id)->where('user_id', $userId)->delete();
    }

    /**
     * Mời guest vào CLB — đánh dấu is_invited và gọi lại inviteMember.
     */
    public function inviteGuestToClub(Club $club, int $userId, int $inviterId): ClubMember
    {
        if ($club->hasMember($userId)) {
            throw new BusinessException('Người dùng đã là thành viên của CLB này');
        }

        // Đánh dấu đã mời (idempotent — nếu chưa có bản ghi thì bỏ qua)
        ClubGuest::where('club_id', $club->id)->where('user_id', $userId)
            ->update(['is_invited' => true]);

        return app(ClubMemberManagementService::class)->inviteMember(
            $club,
            ['user_id' => $userId, 'role' => ClubMemberRole::Member->value],
            $inviterId
        );
    }

    /**
     * Query stats: MAX(created_at) as last_at, COUNT(*) as count, groupBy user_id.
     * @return \Illuminate\Support\Collection<string, array{last_at: ?\Illuminate\Support\Carbon, count: int}>
     */
    protected function participantStats($query, string $eventColumn): Collection
    {
        return $query
            ->selectRaw("user_id, MAX(created_at) as last_at, COUNT(*) as cnt")
            ->groupBy('user_id')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->user_id => ['last_at' => $row->last_at, 'count' => (int) $row->cnt]];
            });
    }

    protected function maxDate($a, $b)
    {
        $aC = $a ? \Illuminate\Support\Carbon::parse($a) : null;
        $bC = $b ? \Illuminate\Support\Carbon::parse($b) : null;
        if (!$aC) return $bC;
        if (!$bC) return $aC;
        return $aC->gt($bC) ? $aC : $bC;
    }
}
