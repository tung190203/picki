<?php

namespace App\Badges\Rules;

use App\Events\MatchResultConfirmed;
use App\Models\Matches;
use App\Models\MiniMatch;
use App\Models\QuickMatch;
use App\Services\BadgeService;
use Illuminate\Support\Facades\DB;

class GiantSlayerBadgeRule implements BadgeRuleInterface
{
    /**
     * Độ chênh lệch điểm trình tối thiểu (đội thua cao hơn đội thắng >= 1 điểm trình).
     */
    public const MIN_RATING_DIFF = 1;

    public function badgeCode(): string
    {
        return 'GIANT_SLAYER';
    }

    public function condition($event): bool
    {
        return $event instanceof MatchResultConfirmed;
    }

    public function handle($event): void
    {
        /** @var MatchResultConfirmed $event */
        $evaluation = $this->evaluateMatch($event->match);
        if (empty($evaluation['winner_user_ids'])) {
            return;
        }

        /** @var BadgeService $badgeService */
        $badgeService = app(BadgeService::class);

        foreach ($evaluation['winner_user_ids'] as $uid) {
            if ($uid > 0) {
                $badgeService->awardBadge((int) $uid, $this->badgeCode(), null, $evaluation['context'] ?? []);
            }
        }
    }

    /**
     * Trả về danh sách user_id của đội thắng nếu trận đấu thỏa điều kiện Giant Slayer.
     *
     * @param  mixed  $match
     * @return array<int>
     */
    public function getQualifiedWinnerUserIds($match): array
    {
        return $this->evaluateMatch($match)['winner_user_ids'];
    }

    /**
     * Đánh giá trận đấu cho huy hiệu Giant Slayer và trả về kèm thông tin truy vết (context).
     *
     * @param  mixed  $match
     * @return array{winner_user_ids: array<int>, context: array<string, mixed>}
     */
    public function evaluateMatch($match): array
    {
        $winnerUserIds = [];
        $loserUserIds = [];
        $sportId = 1;
        $historyMatchColumn = null;
        $matchContext = [];

        // Phân tách thành viên thắng và thua dựa theo loại match
        if ($match instanceof Matches) {
            $tournament = $match->tournamentType?->tournament ?? $match->group?->tournamentType?->tournament;
            $sportId = (int) ($tournament?->sport_id ?? 1);
            $winnerTeamId = $match->winner_id;

            if (!$winnerTeamId) {
                return ['winner_user_ids' => [], 'context' => []];
            }

            if ($winnerTeamId == $match->home_team_id) {
                $loserTeamId = $match->away_team_id;
            } elseif ($winnerTeamId == $match->away_team_id) {
                $loserTeamId = $match->home_team_id;
            } else {
                return ['winner_user_ids' => [], 'context' => []];
            }

            $winnerUserIds = DB::table('team_members')->where('team_id', $winnerTeamId)->pluck('user_id')->toArray();
            $loserUserIds = DB::table('team_members')->where('team_id', $loserTeamId)->pluck('user_id')->toArray();
            $historyMatchColumn = 'match_id';
            $matchContext = [
                'source_type' => 'tournament_match',
                'match_id' => $match->id,
                'tournament_type_id' => $match->tournament_type_id,
                'tournament_id' => $tournament?->id,
                'tournament_name' => $tournament?->name,
                'winner_team_id' => $winnerTeamId,
                'loser_team_id' => $loserTeamId,
            ];
        } elseif ($match instanceof MiniMatch) {
            $miniTournament = $match->miniTournament;
            $sportId = (int) ($miniTournament?->sport_id ?? 1);
            $historyMatchColumn = 'mini_match_id';
            $matchContext = [
                'source_type' => 'mini_match',
                'mini_match_id' => $match->id,
                'mini_tournament_id' => $match->mini_tournament_id,
                'mini_tournament_name' => $miniTournament?->name,
            ];

            if ($match->team_win_id) {
                // Đấu đội
                $winnerTeamId = $match->team_win_id;
                if ($winnerTeamId == $match->team1_id) {
                    $loserTeamId = $match->team2_id;
                } elseif ($winnerTeamId == $match->team2_id) {
                    $loserTeamId = $match->team1_id;
                } else {
                    return ['winner_user_ids' => [], 'context' => []];
                }

                $winnerUserIds = DB::table('mini_team_members')->where('mini_team_id', $winnerTeamId)->pluck('user_id')->toArray();
                $loserUserIds = DB::table('mini_team_members')->where('mini_team_id', $loserTeamId)->pluck('user_id')->toArray();
                $matchContext['team_win_id'] = $winnerTeamId;
                $matchContext['team_lose_id'] = $loserTeamId;
            } elseif ($match->participant_win_id) {
                // Đấu đơn: participant1_id / participant2_id / participant_win_id là mini_participants.id
                $winnerParticipantId = $match->participant_win_id;
                if ($winnerParticipantId == $match->participant1_id) {
                    $loserParticipantId = $match->participant2_id;
                } elseif ($winnerParticipantId == $match->participant2_id) {
                    $loserParticipantId = $match->participant1_id;
                } else {
                    return ['winner_user_ids' => [], 'context' => []];
                }

                $participantUsers = DB::table('mini_participants')
                    ->whereIn('id', array_filter([$winnerParticipantId, $loserParticipantId]))
                    ->pluck('user_id', 'id');

                $winnerUid = $participantUsers[$winnerParticipantId] ?? null;
                $loserUid = $participantUsers[$loserParticipantId] ?? null;

                $winnerUserIds = $winnerUid ? [(int) $winnerUid] : [];
                $loserUserIds = $loserUid ? [(int) $loserUid] : [];
                $matchContext['participant_win_id'] = $winnerParticipantId;
                $matchContext['participant_lose_id'] = $loserParticipantId;
            } else {
                return ['winner_user_ids' => [], 'context' => []];
            }
        } elseif ($match instanceof QuickMatch) {
            $sportId = (int) ($match->sport_id ?? 1);
            $winnerSide = $match->winner; // 'team_a' or 'team_b'
            $historyMatchColumn = 'quick_match_id';

            if (!in_array($winnerSide, ['team_a', 'team_b'], true)) {
                return ['winner_user_ids' => [], 'context' => []];
            }

            $loserSide = ($winnerSide === 'team_a') ? 'team_b' : 'team_a';

            // Ưu tiên lấy trực tiếp từ mảng team_a / team_b trên QuickMatch
            // (vì QuickMatch::update chạy trước saveMatchHistories trong QuickMatchController)
            $winnerUserIds = is_array($match->{$winnerSide}) ? $match->{$winnerSide} : [];
            $loserUserIds = is_array($match->{$loserSide}) ? $match->{$loserSide} : [];

            if (empty($winnerUserIds) || empty($loserUserIds)) {
                $winnerUserIds = DB::table('match_histories')
                    ->where('quick_match_id', $match->id)
                    ->where('team_side', $winnerSide)
                    ->pluck('user_id')->toArray();

                $loserUserIds = DB::table('match_histories')
                    ->where('quick_match_id', $match->id)
                    ->where('team_side', $loserSide)
                    ->pluck('user_id')->toArray();
            }

            $matchContext = [
                'source_type' => 'quick_match',
                'quick_match_id' => $match->id,
                'winner_side' => $winnerSide,
            ];
        }

        $winnerUserIds = array_values(array_unique(array_filter(array_map('intval', $winnerUserIds))));
        $loserUserIds = array_values(array_unique(array_filter(array_map('intval', $loserUserIds))));

        if (empty($winnerUserIds) || empty($loserUserIds)) {
            return ['winner_user_ids' => [], 'context' => []];
        }

        // Lấy điểm trình độ (vndupr_score) của các thành viên:
        // Ưu tiên lấy score_before trong vndupr_history của trận đấu này (nếu điểm trình đã được cộng/trừ trước khi bắn event),
        // fallback sang điểm vndupr_score hiện tại trong user_sport_scores.
        $allUserIds = array_values(array_unique(array_merge($winnerUserIds, $loserUserIds)));
        $ratings = $this->getPlayerRatingsBeforeMatch($allUserIds, $sportId, $historyMatchColumn, (int) $match->id);

        // Hàm tính trung bình điểm trình của 1 team
        $calculateAvgRating = function (array $userIds) use ($ratings): float {
            $sum = 0.0;
            $count = 0;
            foreach ($userIds as $uid) {
                if (isset($ratings[$uid]) && $ratings[$uid] > 0) {
                    $sum += (float) $ratings[$uid];
                    $count++;
                }
            }
            return $count > 0 ? ($sum / $count) : 0.0;
        };

        $winnerAvgRating = $calculateAvgRating($winnerUserIds);
        $loserAvgRating = $calculateAvgRating($loserUserIds);

        // Nếu bất kỳ đội nào chưa có điểm trình (> 0) thì bỏ qua.
        if ($winnerAvgRating <= 0 || $loserAvgRating <= 0) {
            return ['winner_user_ids' => [], 'context' => []];
        }

        $diff = round($loserAvgRating - $winnerAvgRating, 3);

        // Điều kiện Giant Slayer: Đội thắng có điểm trình thấp hơn đội thua >= MIN_RATING_DIFF (1)
        // Tức là đội thắng đã đánh bại đối thủ có điểm trình cao hơn mình >= 1.
        if ($diff >= self::MIN_RATING_DIFF) {
            return [
                'winner_user_ids' => $winnerUserIds,
                'context' => array_filter($matchContext + [
                    'reason' => sprintf(
                        'Thắng đối thủ có điểm trình TB cao hơn (Đội thắng: %.3f vs Đội thua: %.3f, chênh +%.3f >= %s)',
                        $winnerAvgRating,
                        $loserAvgRating,
                        $diff,
                        self::MIN_RATING_DIFF
                    ),
                    'winner_user_ids' => $winnerUserIds,
                    'loser_user_ids' => $loserUserIds,
                    'winner_avg_rating' => round($winnerAvgRating, 3),
                    'loser_avg_rating' => round($loserAvgRating, 3),
                    'rating_diff' => $diff,
                    'player_ratings' => $ratings,
                ], fn ($v) => $v !== null),
            ];
        }

        return ['winner_user_ids' => [], 'context' => []];
    }

    /**
     * Lấy điểm trình (vndupr_score) của danh sách người chơi trước trận đấu.
     *
     * @param  array<int>  $userIds
     * @return array<int, float>
     */
    private function getPlayerRatingsBeforeMatch(
        array $userIds,
        int $sportId,
        ?string $historyMatchColumn,
        int $matchId
    ): array {
        if (empty($userIds)) {
            return [];
        }

        $ratings = [];

        if ($historyMatchColumn && $matchId > 0) {
            $historyRows = DB::table('vndupr_history')
                ->where($historyMatchColumn, $matchId)
                ->whereIn('user_id', $userIds)
                ->orderBy('id', 'asc')
                ->get(['user_id', 'score_before']);

            foreach ($historyRows as $row) {
                $uid = (int) $row->user_id;
                if (!isset($ratings[$uid]) && (float) $row->score_before > 0) {
                    $ratings[$uid] = (float) $row->score_before;
                }
            }
        }

        $missingUserIds = array_values(array_diff($userIds, array_keys($ratings)));
        if (!empty($missingUserIds)) {
            $scoreRows = DB::table('user_sport')
                ->join('user_sport_scores', 'user_sport.id', '=', 'user_sport_scores.user_sport_id')
                ->whereIn('user_sport.user_id', $missingUserIds)
                ->where('user_sport.sport_id', $sportId)
                ->where('user_sport_scores.score_type', 'vndupr_score')
                ->select('user_sport.user_id', DB::raw('MAX(user_sport_scores.score_value) as rating'))
                ->groupBy('user_sport.user_id')
                ->get();

            foreach ($scoreRows as $row) {
                if ((float) $row->rating > 0) {
                    $ratings[(int) $row->user_id] = (float) $row->rating;
                }
            }
        }

        return $ratings;
    }
}