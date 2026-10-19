<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\ClubMember;
use App\Models\MiniParticipant;
use App\Models\MiniTournament;
use App\Models\Tournament;
use App\Models\Tournament as TournamentModel;
use App\Models\UserAchievement;
use App\Services\RoundRobinSchedulerService;
use App\Services\TournamentType\TournamentRankService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ghi thành tích top 3 (star + cup) vào bảng user_achievements khi event đã CLOSED.
 *
 * Idempotent: updateOrCreate theo (user_id, club_id, event_type, event_id).
 * - 1 row / user / event / club
 * - Cup ngoài CLB: 1 row / CLB user đã từng thuộc (giữ rule "cộng dồn toàn bộ
 *   thời gian đã thuộc CLB" như code cũ)
 */
class UserAchievementRecorder
{
    public function __construct(
        private TournamentRankService $rankService,
        private RoundRobinSchedulerService $roundRobinService,
    ) {}

    /**
     * Ghi star cho 1 mini-tournament (1 CLB duy nhất).
     * Bỏ qua participant là guest hoặc thiếu user_id (giống rule cũ).
     */
    public function recordForMiniTournament(MiniTournament $mini): int
    {
        $clubId = $mini->club_id;

        $result = $this->roundRobinService->calculateLeaderboard($mini->id);
        $leaderboard = $result['leaderboard'] ?? [];
        $top3 = array_slice($leaderboard, 0, 3);

        $count = 0;
        foreach ($top3 as $entry) {
            $rank = (int) ($entry['rank'] ?? 0);
            if ($rank < 1 || $rank > 3) {
                continue;
            }

            $participantId = $entry['participant_id'] ?? null;
            $userId = (int) ($entry['user_id'] ?? 0);
            if (!$participantId || !$userId) {
                continue;
            }

            $participant = MiniParticipant::find($participantId);
            if (!$participant || $participant->is_guest || !$participant->user_id) {
                continue;
            }

            $this->upsert([
                'user_id' => $userId,
                'club_id' => $clubId,
                'event_type' => UserAchievement::TYPE_MINI_TOURNAMENT,
                'event_id' => $mini->id,
                'event_name' => $mini->name,
                'event_date' => $mini->start_time,
                'event_status' => 'closed',
                'is_club_hosted' => true,
                'rank' => $rank,
                'medal' => $this->medal($rank),
                'points' => $this->starPoints($rank),
                'is_star' => true,
                'is_cup' => false,
                'is_guest_snapshot' => false,
                'partner_names' => $this->resolveStarPartnerNames($mini, $userId),
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Ghi cup cho 1 tournament.
     * Mỗi giải có thể tạo nhiều row cho cùng 1 user (nếu user thuộc nhiều CLB):
     * - 1 row với club_id = tournament.club_id (nếu có) cho CLB chủ nhà
     * - 1 row cho MỖI CLB user đã từng thuộc (tính từ trước tournamentDate)
     *   → match đúng rule "cup được đếm cho tất cả CLB user đã từng là member"
     */
    public function recordForTournament(Tournament $tournament): int
    {
        $tournamentDate = $tournament->start_date
            ?? $tournament->end_date
            ?? $tournament->created_at;

        $hostClubId = $tournament->club_id ? (int) $tournament->club_id : null;

        $count = 0;
        foreach ($tournament->tournamentTypes as $type) {
            $rankLabels = $this->rankService->rankLabelsByTeam($type->id);

            foreach ($rankLabels as $teamId => $rankInfo) {
                $rank = (int) ($rankInfo['overall_rank'] ?? 0);
                if ($rank < 1 || $rank > 3) {
                    continue;
                }

                $team = \App\Models\Team::with('members')->find($teamId);
                if (!$team) {
                    continue;
                }

                $partnerNames = $team->members
                    ->map(fn ($m) => $m->full_name)
                    ->all();

                foreach ($team->members as $member) {
                    $userId = (int) $member->id;
                    if (!$userId) {
                        continue;
                    }

                    // CLB áp dụng: chủ nhà + tất cả CLB user đã từng là member
                    // trước ngày đạt cup.
                    $clubIds = $this->resolveEligibleClubIds($userId, $tournamentDate, $hostClubId);

                    foreach ($clubIds as $clubId) {
                        $this->upsert([
                            'user_id' => $userId,
                            'club_id' => $clubId,
                            'event_type' => UserAchievement::TYPE_TOURNAMENT,
                            'event_id' => $tournament->id,
                            'event_name' => $tournament->name,
                            'event_date' => $tournamentDate,
                            'event_status' => 'closed',
                            'is_club_hosted' => $hostClubId !== null && (int) $clubId === $hostClubId,
                            'rank' => $rank,
                            'medal' => $this->medal($rank),
                            'points' => 1,
                            'is_star' => false,
                            'is_cup' => true,
                            'is_guest_snapshot' => (bool) ($member->is_guest ?? false),
                            'partner_names' => array_values(array_filter(
                                $partnerNames,
                                fn ($n) => $n !== $member->full_name
                            )),
                        ]);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * @return int[] Danh sách club_id user đã từng là member
     *                (kể cả left/rejected) trước $tournamentDate.
     *                Nếu có host club và user là member của host → cũng bao gồm.
     */
    private function resolveEligibleClubIds(int $userId, mixed $tournamentDate, ?int $hostClubId): array
    {
        $cutoff = $tournamentDate ? Carbon::parse($tournamentDate) : null;

        // ponytail: chỉ lấy CLB còn tồn tại — club_members có thể giữ row
        // tham chiếu CLB đã xoá, FK user_achievements.club_id sẽ fail.
        // Bỏ qua tạm thời, nếu cần restore lịch sử thì add cột deleted_club_id
        // hoặc làm sạch club_members trước.
        $query = DB::table('club_members')
            ->where('user_id', $userId)
            ->whereNotNull('joined_at')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('clubs')
                    ->whereColumn('clubs.id', 'club_members.club_id');
            });

        if ($cutoff) {
            $query->where('joined_at', '<=', $cutoff);
        }

        $clubIds = $query->pluck('club_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        return $clubIds;
    }

    private function resolveStarPartnerNames(MiniTournament $mini, int $userId): array
    {
        $participant = MiniParticipant::with(['team.members', 'user'])
            ->where('mini_tournament_id', $mini->id)
            ->where('user_id', $userId)
            ->first();

        if (!$participant || !$participant->team) {
            return [];
        }

        return $participant->team->members
            ->filter(fn ($m) => (int) $m->id !== $userId)
            ->pluck('full_name')
            ->all();
    }

    private function medal(int $rank): string
    {
        return match ($rank) {
            1 => 'gold',
            2 => 'silver',
            3 => 'bronze',
            default => '',
        };
    }

    private function starPoints(int $rank): int
    {
        return match ($rank) {
            1 => 3,
            2 => 2,
            3 => 1,
            default => 0,
        };
    }

    private function upsert(array $attrs): void
    {
        UserAchievement::updateOrCreate(
            [
                'user_id' => $attrs['user_id'],
                'club_id' => $attrs['club_id'],
                'event_type' => $attrs['event_type'],
                'event_id' => $attrs['event_id'],
            ],
            $attrs
        );
    }
}
