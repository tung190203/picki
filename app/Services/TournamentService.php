<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TournamentService
{
    /**
     * Tính bảng xếp hạng cho một group
     */
    public static function calculateGroupStandings($groupMatches): Collection
    {
        $standings = [];
        
        foreach ($groupMatches as $match) {
            if ($match->status !== 'completed') continue;

            $homeId = $match->home_team_id;
            $awayId = $match->away_team_id;

            // Khởi tạo standings cho home team
            if (!isset($standings[$homeId])) {
                $standings[$homeId] = [
                    'team' => [
                        'id' => $match->homeTeam->id,
                        'name' => $match->homeTeam->name,
                        'team_avatar' => $match->homeTeam->avatar,
                        'members' => $match->homeTeam->members->map(fn($u) => [
                            'id' => $u->id,
                            'full_name' => $u->full_name,
                            'avatar_url' => $u->avatar_url,
                            'name' => $u->full_name,
                            'avatar' => $u->avatar_url,
                        ])->values(),
                    ],
                    'played' => 0,
                    'won' => 0,
                    'draw' => 0,
                    'lost' => 0,
                    'sets_won' => 0,
                    'sets_lost' => 0,
                    'points_for' => 0,
                    'points_against' => 0,
                    'set_difference' => 0,
                    'points' => 0,
                ];
            }

            // Khởi tạo standings cho away team
            if (!isset($standings[$awayId])) {
                $standings[$awayId] = [
                    'team' => [
                        'id' => $match->awayTeam->id,
                        'name' => $match->awayTeam->name,
                        'team_avatar' => $match->awayTeam->avatar,
                        'members' => $match->awayTeam->members->map(fn($u) => [
                            'id' => $u->id,
                            'full_name' => $u->full_name,
                            'avatar_url' => $u->avatar_url,
                            'name' => $u->full_name,
                            'avatar' => $u->avatar_url,
                        ])->values(),
                    ],
                    'played' => 0,
                    'won' => 0,
                    'draw' => 0,
                    'lost' => 0,
                    'sets_won' => 0,
                    'sets_lost' => 0,
                    'points_for' => 0,
                    'points_against' => 0,
                    'set_difference' => 0,
                    'points' => 0,
                ];
            }

            // Cập nhật số trận đã đấu
            $standings[$homeId]['played']++;
            $standings[$awayId]['played']++;

            // Tính số set thắng cho mỗi đội
            $homeSetsWon = $match->results->where('team_id', $homeId)->where('won_match', true)->count();
            $awaySetsWon = $match->results->where('team_id', $awayId)->where('won_match', true)->count();

            $standings[$homeId]['sets_won'] += $homeSetsWon;
            $standings[$homeId]['sets_lost'] += $awaySetsWon;
            $standings[$awayId]['sets_won'] += $awaySetsWon;
            $standings[$awayId]['sets_lost'] += $homeSetsWon;

            // Tính tổng điểm số (score)
            $homePoints = $match->results->where('team_id', $homeId)->sum('score');
            $awayPoints = $match->results->where('team_id', $awayId)->sum('score');

            $standings[$homeId]['points_for'] += $homePoints;
            $standings[$homeId]['points_against'] += $awayPoints;
            $standings[$awayId]['points_for'] += $awayPoints;
            $standings[$awayId]['points_against'] += $homePoints;

            // Tính điểm xếp hạng dựa trên winner_id
            if ($match->winner_id == $homeId) {
                $standings[$homeId]['won']++;
                $standings[$homeId]['points'] += 3;
                $standings[$awayId]['lost']++;
            } elseif ($match->winner_id == $awayId) {
                $standings[$awayId]['won']++;
                $standings[$awayId]['points'] += 3;
                $standings[$homeId]['lost']++;
            } else {
                // Trường hợp hòa (nếu có)
                $standings[$homeId]['draw']++;
                $standings[$awayId]['draw']++;
                $standings[$homeId]['points']++;
                $standings[$awayId]['points']++;
            }
        }

        // Tính set difference và sắp xếp
        $standings = collect($standings)->map(function ($team) {
            $team['set_difference'] = $team['sets_won'] - $team['sets_lost'];
            return $team;
        })->sortByDesc('points')
          ->sortByDesc('set_difference')
          ->sortByDesc('sets_won')
          ->values();

        // Thêm rank
        $rank = 1;
        return $standings->map(function ($team) use (&$rank) {
            $team['rank'] = $rank++;
            return $team;
        });
    }
    
    /**
     * Format team data
     */
    /**
     * Format team data cho response
     * @param mixed $team
     * @param string|null $placeholderText
     * @param int|null $tournamentId Nếu truyền vào, hydrate tournamentParticipant + trả đầy đủ fields (id, full_name, avatar, sports, tournament_participant)
     */
    public static function formatTeam($team, ?string $placeholderText = null, ?int $tournamentId = null): ?array
    {
        if (!$team) {
            return [
                'id' => null,
                'name' => $placeholderText ?? 'TBD',
                'team_avatar' => null,
                'members' => [],
            ];
        }

        // Nếu có tournamentId: hydrate members để TeamMemberResource trả đầy đủ contract
        if ($tournamentId) {
            $team->load(['members.sports.scores', 'members.sports.sport']);
            \App\Support\TournamentTeamMemberHydrator::hydrateTeam($team, $tournamentId);
            $members = $team->members->map(function ($m) {
                return (new \App\Http\Resources\TeamMemberResource($m))->resolve(request());
            })->values();
        } else {
            // Không có tournamentId: trả đơn giản (backward compat)
            $members = $team->members->map(function ($user) {
                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'avatar_url' => $user->avatar_url,
                    'name' => $user->full_name,
                    'avatar' => $user->avatar_url,
                ];
            })->values();
        }

        return [
            'id' => $team->id,
            'name' => $team->name,
            'team_avatar' => $team->avatar,
            'members' => $members,
        ];
    }

    /**
     * Cập nhật rating, rank cho tất cả participants khi giải đấu kết thúc.
     * OPTIMIZED: Sử dụng ROW_NUMBER() window function thay vì correlated subquery
     */
    public function updateParticipantsRatingStats(Tournament $tournament): void
    {
        $sportId = $tournament->sport_id;

        $participants = $tournament->participants()
            ->whereNotNull('user_id')
            ->with('user')
            ->get();

        if ($participants->isEmpty()) {
            return;
        }

        $userIds = $participants->pluck('user_id');

        // Batch load all user_sport records for the tournament sport
        $userSportRecords = DB::table('user_sport')
            ->whereIn('user_id', $userIds)
            ->where('sport_id', $sportId)
            ->get()
            ->keyBy('user_id');

        $userSportIds = $userSportRecords->pluck('id')->values();

        // Batch load all vndupr scores
        $scoreMap = DB::table('user_sport_scores')
            ->whereIn('user_sport_id', $userSportIds)
            ->where('score_type', 'vndupr_score')
            ->get()
            ->keyBy('user_sport_id');

        // OPTIMIZED: Get all scores for ranking calculation in single query
        // Use ROW_NUMBER() window function to calculate rank instead of correlated subquery
        $allScores = DB::select("
            SELECT 
                user_id,
                score_value,
                ROW_NUMBER() OVER (ORDER BY score_value DESC) as rank
            FROM (
                SELECT 
                    us.user_id,
                    MAX(uss.score_value) as score_value
                FROM user_sport us
                JOIN user_sport_scores uss ON uss.user_sport_id = us.id
                WHERE us.sport_id = ?
                  AND uss.score_type = 'vndupr_score'
                  AND us.is_anchor = false
                GROUP BY us.user_id
            ) as ranked_users
            ORDER BY rank
        ", [$sportId]);

        // Build rank map
        $rankMap = [];
        foreach ($allScores as $row) {
            $rankMap[$row->user_id] = (int) $row->rank;
        }

        foreach ($participants as $participant) {
            $user = $participant->user;
            if (!$user) {
                continue;
            }

            $userSport = $userSportRecords->get($user->id);
            $currentScore = null;
            if ($userSport) {
                $scoreRecord = $scoreMap->get($userSport->id);
                $currentScore = $scoreRecord ? (float) $scoreRecord->score_value : null;
            }

            $currentRank = $rankMap[$user->id] ?? null;

            $updateData = [
                'rating_after' => $currentScore,
                'rank_after' => $currentRank,
            ];

            if ($participant->rank_before && $currentRank) {
                $updateData['rank_change'] = $participant->rank_before - $currentRank;
            }

            $participant->update($updateData);
        }
    }

    /**
     * Đóng giải đấu: đổi status = CLOSED và cập nhật stats cho participants.
     */
    public function closeTournament(Tournament $tournament): void
    {
        if ($tournament->status === Tournament::CLOSED) {
            return;
        }

        $tournament->update(['status' => Tournament::CLOSED]);
        $this->updateParticipantsRatingStats($tournament);
    }

    /**
     * Tính và lưu end_date = start_date + duration (phút).
     * Gọi khi tạo hoặc cập nhật giải đấu có start_date hoặc duration thay đổi.
     */
    public function calculateEndDate(Tournament $tournament): void
    {
        if (!$tournament->start_date || !$tournament->duration) {
            return;
        }

        $startDate = $tournament->start_date instanceof \Carbon\Carbon
            ? $tournament->start_date
            : \Carbon\Carbon::parse($tournament->start_date);

        $endDate = $startDate->copy()->addMinutes($tournament->duration);
        $tournament->end_date = $endDate;
        $tournament->save();
    }
}
