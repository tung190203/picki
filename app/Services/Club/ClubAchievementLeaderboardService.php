<?php

namespace App\Services\Club;

use App\Models\Club\Club;
use App\Models\MiniTournament;
use App\Models\Participant as TournamentParticipant;
use App\Models\Tournament;
use App\Models\Team as TournamentTeam;
use App\Models\User;
use App\Services\RoundRobinSchedulerService;
use App\Services\TournamentType\TournamentRankService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClubAchievementLeaderboardService
{
    public function __construct(
        private TournamentRankService $rankService,
        private RoundRobinSchedulerService $roundRobinService,
    ) {}

    /**
     * Get Achievement Leaderboard (Sao or Cúp) for a Club.
     *
     * @param Club $club
     * @param string $subType 'star' (Kèo đấu) or 'cup' (Giải đấu)
     * @param string $timeFrame 'month', 'quarter', 'year', 'all'
     * @return Collection
     */
    public function getLeaderboard(Club $club, string $subType = 'star', string $timeFrame = 'month'): Collection
    {
        $startDate = $this->getStartDateForTimeFrame($timeFrame);

        if ($subType === 'cup') {
            return $this->calculateCupLeaderboard($club, $startDate);
        }

        return $this->calculateStarLeaderboard($club, $startDate);
    }

    /**
     * Lấy lịch sử sao/cúp của 1 member trong CLB.
     *
     * Trả về flat list các event (mini_tournament + tournament) mà user đó
     * đạt top 3, CỘNG với summary gold/silver/bronze theo loại.
     *
     * Scope:
     *  - Mini-tournament: chỉ của CLB này, status CLOSED, start_time >= joined_at.
     *  - Tournament: TẤT CẢ tournament (kể cả CLB khác), nhưng chỉ tính các cup
     *    đạt được từ ngày user vào CLB này sớm nhất trở đi.
     *  - Nếu user chưa từng là member của CLB → trả rỗng.
     *
     * @return array{user: array, club: array, summary: array, events: Collection}
     */
    public function getMemberAchievements(Club $club, int $userId): array
    {
        $firstJoinedAt = $this->getFirstJoinedAt($club, $userId);

        if ($firstJoinedAt === null) {
            return [
                'user' => $this->summarizeUser($userId),
                'club' => $this->summarizeClub($club),
                'summary' => $this->emptySummary(),
                'events' => collect(),
            ];
        }

        $events = collect()
            ->merge($this->collectStarEvents($club, $userId, $firstJoinedAt))
            ->merge($this->collectCupEvents($club, $userId, $firstJoinedAt))
            ->sortByDesc('event_date')
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

    /**
     * Tính BXH Cúp (Giải đấu).
     *
     * Lấy champion/runner-up/third từ TournamentRankService (cùng logic với
     * GET /api/tournaments/{id}/leaderboard), phân bổ điểm cho từng thành viên
     * trong team VỚI CÁC RULE:
     *
     *  1. Tính TẤT CẢ tournament (không chỉ của CLB này).
     *  2. Chỉ cộng điểm cho user đang/từng là member của CLB này.
     *  3. Chỉ cộng các cup đạt được kể từ lần joined_at SỚM NHẤT của user
     *     trên CLB này (cộng dồn toàn bộ thời gian đã thuộc CLB, không reset).
     *  4. Nếu user thuộc nhiều CLB cùng lúc → cup của user đó sẽ được đếm
     *     cho tất cả CLB user đã từng là member.
     *
     * Sort: gold DESC → silver DESC → bronze DESC (KHÔNG dùng total_points).
     */
    private function calculateCupLeaderboard(Club $club, ?Carbon $startDate): Collection
    {
        $scores = [];

        $memberJoinedAt = $this->loadClubMembersJoinedAt($club);

        // Không filter club_id (tính tất cả giải), không filter status
        // (TournamentRankService tự skip khi chưa có rank hợp lệ).
        $tournamentsQuery = Tournament::query();

        if ($startDate) {
            $tournamentsQuery->where('start_date', '>=', $startDate);
        }

        $tournaments = $tournamentsQuery->with('tournamentTypes')->get();

        foreach ($tournaments as $tournament) {
            $tournamentDate = $tournament->start_date
                ?? $tournament->end_date
                ?? $tournament->created_at;

            foreach ($tournament->tournamentTypes as $type) {
                $rankLabels = $this->rankService->rankLabelsByTeam($type->id);

                foreach ($rankLabels as $teamId => $rankInfo) {
                    $rank = (int) ($rankInfo['overall_rank'] ?? 0);
                    if ($rank < 1 || $rank > 3) {
                        continue;
                    }

                    $team = TournamentTeam::with('members')->find($teamId);
                    if (!$team) {
                        continue;
                    }

                    $this->awardTeamPoints(
                        $scores,
                        $team,
                        $rank,
                        $memberJoinedAt,
                        $tournamentDate
                    );
                }
            }
        }

        return $this->sortAndRankLeaderboard($scores, 'cup');
    }

    /**
     * Map user_id => earliest joined_at cho mọi user đã từng là member của CLB.
     * Tra mọi trạng thái membership (joined + left + rejected + cancelled)
     * để "cộng dồn toàn bộ thời gian đã thuộc CLB, không reset".
     *
     * @return array<int, Carbon> user_id => earliest joined_at
     */
    private function loadClubMembersJoinedAt(Club $club): array
    {
        return DB::table('club_members')
            ->select('user_id', DB::raw('MIN(joined_at) as first_joined_at'))
            ->where('club_id', $club->id)
            ->whereNotNull('joined_at')
            ->groupBy('user_id')
            ->pluck('first_joined_at', 'user_id')
            ->map(fn ($v) => Carbon::parse($v))
            ->all();
    }

    /**
     * Lấy joined_at SỚM NHẤT của 1 user trong CLB (kể cả đã left).
     * Trả null nếu user chưa từng là member.
     */
    private function getFirstJoinedAt(Club $club, int $userId): ?Carbon
    {
        $value = DB::table('club_members')
            ->where('club_id', $club->id)
            ->where('user_id', $userId)
            ->whereNotNull('joined_at')
            ->min('joined_at');

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Phân bổ điểm cup cho từng thành viên của team.
     * Filter theo rule "từ ngày vào CLB trở đi" + "chỉ members của CLB hiện tại".
     */
    private function awardTeamPoints(array &$scores, TournamentTeam $team, int $rank, array $memberJoinedAt, mixed $tournamentDate): void
    {
        $points = match ($rank) {
            1 => ['gold' => 1, 'silver' => 0, 'bronze' => 0, 'total' => 1],
            2 => ['gold' => 0, 'silver' => 1, 'bronze' => 0, 'total' => 1],
            3 => ['gold' => 0, 'silver' => 0, 'bronze' => 1, 'total' => 1],
            default => null,
        };
        if ($points === null) {
            return;
        }

        foreach ($team->members as $member) {
            $userId = (int) $member->id;

            // Rule 2: chỉ members của CLB này
            if (!isset($memberJoinedAt[$userId])) {
                continue;
            }

            // Rule 3: chỉ tính cup đạt được từ earliest joined_at trở đi
            $firstJoined = $memberJoinedAt[$userId];
            if ($tournamentDate && Carbon::parse($tournamentDate)->lt($firstJoined)) {
                continue;
            }

            if (!isset($scores[$userId])) {
                $scores[$userId] = [
                    'user_id' => $userId,
                    'is_guest' => (bool) ($member->is_guest ?? false),
                    'name' => $member->full_name ?? 'Khách',
                    'avatar_url' => $member->avatar_url,
                    'gold' => 0,
                    'silver' => 0,
                    'bronze' => 0,
                    'total_points' => 0,
                ];
            }

            $scores[$userId]['gold'] += $points['gold'];
            $scores[$userId]['silver'] += $points['silver'];
            $scores[$userId]['bronze'] += $points['bronze'];
            $scores[$userId]['total_points'] += $points['total'];
        }
    }

    /**
     * Tính BXH Sao (Kèo đấu).
     *
     * Lấy top 3 từ RoundRobinSchedulerService::calculateLeaderboard() (cùng logic
     * với GET /api/mini-tournaments/{id}/leaderboard).
     *
     * Scope: chỉ tính kèo của CLB này (mini_tournaments.club_id = club.id).
     * Guest participant (không có user_id thật) → KHÔNG cộng vào BXH Sao CLB.
     * User không phải member của CLB → KHÔNG cộng.
     */
    private function calculateStarLeaderboard(Club $club, ?Carbon $startDate): Collection
    {
        $scores = [];

        $memberJoinedAt = $this->loadClubMembersJoinedAt($club);

        $miniTournamentsQuery = MiniTournament::where('club_id', $club->id)
            ->whereIn('status', [MiniTournament::STATUS_CLOSED, 3]);

        if ($startDate) {
            $miniTournamentsQuery->where('start_time', '>=', $startDate);
        }

        $miniTournaments = $miniTournamentsQuery->get();

        foreach ($miniTournaments as $mini) {
            $result = $this->roundRobinService->calculateLeaderboard($mini->id);
            $leaderboard = $result['leaderboard'] ?? [];

            $top3 = array_slice($leaderboard, 0, 3);

            foreach ($top3 as $entry) {
                $rank = (int) ($entry['rank'] ?? 0);
                if ($rank < 1 || $rank > 3) {
                    continue;
                }

                $userId = $entry['user_id'] ?? null;
                $participantId = $entry['participant_id'] ?? null;

                if (!$participantId) {
                    continue;
                }

                $participant = \App\Models\MiniParticipant::with('user')->find($participantId);
                if (!$participant) {
                    continue;
                }

                $isGuest = (bool) ($participant->is_guest
                    || !$participant->user_id
                    || ($participant->user && $participant->user->is_guest));
                if ($isGuest || !$userId) {
                    continue;
                }

                if (!isset($memberJoinedAt[$userId])) {
                    continue;
                }

                $firstJoined = $memberJoinedAt[$userId];
                $miniStart = $mini->start_time ?? $mini->created_at;
                if ($miniStart && Carbon::parse($miniStart)->lt($firstJoined)) {
                    continue;
                }

                if (!isset($scores[$userId])) {
                    $user = $participant->user ?? User::find($userId);
                    $scores[$userId] = [
                        'user_id' => $userId,
                        'is_guest' => (bool) ($user?->is_guest ?? false),
                        'name' => $user?->full_name ?? 'Khách',
                        'avatar_url' => $user?->avatar_url,
                        'gold' => 0,
                        'silver' => 0,
                        'bronze' => 0,
                        'total_points' => 0,
                    ];
                }

                if ($rank === 1) {
                    $scores[$userId]['gold'] += 1;
                    $scores[$userId]['total_points'] += 3;
                } elseif ($rank === 2) {
                    $scores[$userId]['silver'] += 1;
                    $scores[$userId]['total_points'] += 2;
                } elseif ($rank === 3) {
                    $scores[$userId]['bronze'] += 1;
                    $scores[$userId]['total_points'] += 1;
                }
            }
        }

        return $this->sortAndRankLeaderboard($scores, 'star');
    }

    /**
     * Sắp xếp & gán rank cho leaderboard.
     *
     * Sort rule:
     *  - 'star': total_points DESC → gold DESC → silver DESC → bronze DESC
     *    (giữ tương thích ngược với mobile hiển thị "⭐").
     *  - 'cup': gold DESC → silver DESC → bronze DESC
     *    (BỎ total_points — sort trái → phải theo yêu cầu).
     */
    private function sortAndRankLeaderboard(array $scores, string $subType = 'star'): Collection
    {
        $collection = collect(array_values($scores));

        $sorted = $collection->sort(function ($a, $b) use ($subType) {
            if ($subType === 'cup') {
                if ($a['gold'] !== $b['gold']) {
                    return $b['gold'] <=> $a['gold'];
                }
                if ($a['silver'] !== $b['silver']) {
                    return $b['silver'] <=> $a['silver'];
                }
                return $b['bronze'] <=> $a['bronze'];
            }

            // Star
            if ($a['total_points'] !== $b['total_points']) {
                return $b['total_points'] <=> $a['total_points'];
            }
            if ($a['gold'] !== $b['gold']) {
                return $b['gold'] <=> $a['gold'];
            }
            if ($a['silver'] !== $b['silver']) {
                return $b['silver'] <=> $a['silver'];
            }
            return $b['bronze'] <=> $a['bronze'];
        })->values();

        return $sorted->map(function ($item, $index) {
            $item['rank'] = $index + 1;
            return $item;
        });
    }

    /**
     * Thu thập các event mini_tournament mà user đạt top 3.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function collectStarEvents(Club $club, int $userId, Carbon $firstJoinedAt): Collection
    {
        $events = collect();

        $minis = MiniTournament::where('club_id', $club->id)
            ->whereIn('status', [MiniTournament::STATUS_CLOSED, 3])
            ->where('start_time', '>=', $firstJoinedAt)
            ->orderByDesc('start_time')
            ->get();

        foreach ($minis as $mini) {
            $result = $this->roundRobinService->calculateLeaderboard($mini->id);
            $leaderboard = $result['leaderboard'] ?? [];

            foreach ($leaderboard as $entry) {
                $rank = (int) ($entry['rank'] ?? 0);
                if ($rank < 1 || $rank > 3) {
                    continue;
                }
                if ((int) ($entry['user_id'] ?? 0) !== $userId) {
                    continue;
                }

                $events->push([
                    'event_id' => $mini->id,
                    'event_type' => 'mini_tournament',
                    'event_name' => $mini->name,
                    'event_date' => optional($mini->start_time)->toIso8601String(),
                    'event_status' => 'closed',
                    'is_club_hosted' => true,
                    'rank' => $rank,
                    'medal' => $this->medal($rank),
                    'points' => $this->starPoints($rank),
                    'is_star' => true,
                    'is_cup' => false,
                    'partner_names' => $this->resolveStarPartnerNames($mini, $userId, $rank),
                ]);
            }
        }

        return $events;
    }

    /**
     * Thu thập các event tournament mà user đạt top 3.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function collectCupEvents(Club $club, int $userId, Carbon $firstJoinedAt): Collection
    {
        $events = collect();

        $tournaments = Tournament::query()
            ->with('tournamentTypes')
            ->orderByDesc('start_date')
            ->get();

        foreach ($tournaments as $tournament) {
            $tournamentDate = $tournament->start_date
                ?? $tournament->end_date
                ?? $tournament->created_at;

            if ($tournamentDate && Carbon::parse($tournamentDate)->lt($firstJoinedAt)) {
                continue;
            }

            foreach ($tournament->tournamentTypes as $type) {
                $rankLabels = $this->rankService->rankLabelsByTeam($type->id);

                foreach ($rankLabels as $teamId => $rankInfo) {
                    $rank = (int) ($rankInfo['overall_rank'] ?? 0);
                    if ($rank < 1 || $rank > 3) {
                        continue;
                    }

                    $team = TournamentTeam::with('members')->find($teamId);
                    if (!$team) {
                        continue;
                    }

                    if (!$team->members->contains(fn ($m) => (int) $m->id === $userId)) {
                        continue;
                    }

                    $events->push([
                        'event_id' => $tournament->id,
                        'event_type' => 'tournament',
                        'event_name' => $tournament->name,
                        'event_date' => $tournamentDate ? Carbon::parse($tournamentDate)->toIso8601String() : null,
                        'event_status' => $this->tournamentStatusLabel($tournament->status),
                        'is_club_hosted' => (int) $tournament->club_id === (int) $club->id,
                        'rank' => $rank,
                        'medal' => $this->medal($rank),
                        'points' => 1, // cup = 1 cup / giải
                        'is_star' => false,
                        'is_cup' => true,
                        'partner_names' => $team->members
                            ->filter(fn ($m) => (int) $m->id !== $userId)
                            ->pluck('full_name')
                            ->all(),
                    ]);
                }
            }
        }

        return $events;
    }

    private function resolveStarPartnerNames(MiniTournament $mini, int $userId, int $rank): array
    {
        $participant = \App\Models\MiniParticipant::with(['team.members', 'user'])
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

    private function tournamentStatusLabel(int|string $status): string
    {
        return match ((int) $status) {
            Tournament::DRAFT => 'draft',
            Tournament::OPEN => 'open',
            Tournament::CLOSED => 'closed',
            Tournament::CANCELLED => 'cancelled',
            default => (string) $status,
        };
    }

    private function summarizeUser(int $userId): array
    {
        $user = User::find($userId);
        return [
            'id' => $userId,
            'full_name' => $user?->full_name,
            'avatar_url' => $user?->avatar_url,
        ];
    }

    private function summarizeClub(Club $club): array
    {
        return [
            'id' => $club->id,
            'name' => $club->name,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'star' => ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0],
            'cup' => ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0],
            'total_events' => 0,
        ];
    }

    private function summarizeEvents(Collection $events): array
    {
        $star = ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0];
        $cup = ['gold' => 0, 'silver' => 0, 'bronze' => 0, 'total_points' => 0];

        foreach ($events as $e) {
            $bucket = $e['is_cup'] ? 'cup' : 'star';
            $$bucket[$e['medal']]++;
            $$bucket['total_points'] += $e['points'];
        }

        return [
            'star' => $star,
            'cup' => $cup,
            'total_events' => $events->count(),
        ];
    }
}